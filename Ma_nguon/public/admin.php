<?php

declare(strict_types=1);

// Quản trị hệ thống (chỉ users.is_admin; mẫu: admin@pinpoint.test).
// Bản 2.6: tách thành bảng điều khiển có thanh bên, mỗi mục là một trang riêng (giao diện ở templates/admin/):
//   ?tab=overview            Tổng quan: số liệu, biểu đồ 14 ngày, dự án nhiều góp ý chờ, nhật ký gần đây
//   ?tab=users&filter=…      Danh sách người dùng theo nhóm (đang hoạt động / bị khoá / quản trị / hỗ trợ / không hoạt động),
//                            tìm, sắp xếp, phân trang, chọn nhiều để khoá – mở khoá, xuất CSV, thêm tài khoản
//   ?tab=user&id=…           Trang chi tiết một người dùng: hồ sơ, dự án tham gia, góp ý gần đây, lịch sử quản trị, thao tác
//   ?tab=projects&state=…    Mọi dự án (đang hoạt động / lưu trữ); mở để can thiệp, xoá (gõ lại tên)
//   ?tab=log&action=…        Nhật ký quản trị, lọc theo loại thao tác
// File này chỉ xử lý quyền, thao tác POST và chuẩn bị dữ liệu; phần HTML nằm ở templates/admin/*.php.
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
AdminRepository::require($c, $user['id']);
$admin = new AdminRepository($c);

$tabs = ['overview', 'users', 'user', 'projects', 'log'];
$tab = in_array(field($_GET, 'tab'), $tabs, true) ? field($_GET, 'tab') : 'overview';
$q = field($_GET, 'q');
$filter = in_array(field($_GET, 'filter'), ['active', 'locked', 'admin', 'support', 'inactive'], true) ? field($_GET, 'filter') : '';
$sort = field($_GET, 'sort');
$state = in_array(field($_GET, 'state'), ['active', 'archived'], true) ? field($_GET, 'state') : '';
$action = field($_GET, 'action');
$detailId = intField($_GET, 'id');
// Giữ nguyên trang, bộ lọc, sắp xếp và số trang sau mỗi thao tác.
$back = '/admin.php?' . http_build_query(array_filter(['tab' => $tab, 'id' => $detailId, 'q' => $q, 'filter' => $filter, 'sort' => $sort,
    'state' => $state, 'page' => intField($_GET, 'page'), 'per' => intField($_GET, 'per')], fn ($v) => $v !== '' && $v !== 0));

if (isPost()) {
    checkCsrf($_POST);
    $target = intField($_POST, 'target');
    try {
        switch (field($_POST, 'action')) {
            case 'lock':
                $admin->setLocked($user['id'], $target, true, field($_POST, 'reason'));
                flash('Đã khoá tài khoản. Người này bị đăng xuất ngay ở lần thao tác tiếp theo.');
                break;
            case 'unlock':
                $admin->setLocked($user['id'], $target, false);
                flash('Đã mở khoá tài khoản.');
                break;
            case 'bulk_lock':
            case 'bulk_unlock':
                $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
                if ($ids === []) {
                    flash('Lỗi: Chưa chọn người dùng nào.');
                    break;
                }
                $n = $admin->bulkLock($user['id'], $ids, field($_POST, 'action') === 'bulk_lock', field($_POST, 'reason'));
                flash((field($_POST, 'action') === 'bulk_lock' ? 'Đã khoá ' : 'Đã mở khoá ') . $n . ' tài khoản'
                    . ($n < count($ids) ? ' (bỏ qua ' . (count($ids) - $n) . ' tài khoản của bạn hoặc đã ở trạng thái này).' : '.'));
                break;
            case 'grant_admin':
            case 'revoke_admin':
            case 'grant_support':
            case 'revoke_support':
                [$verb, $role] = explode('_', field($_POST, 'action'));
                $admin->setRole($user['id'], $target, $role, $verb === 'grant');
                flash(($verb === 'grant' ? 'Đã cấp' : 'Đã bỏ') . ' quyền ' . ($role === 'admin' ? 'quản trị hệ thống.' : 'bộ phận hỗ trợ.'));
                break;
            case 'reset_link':
                $_SESSION['admin_link'] = ['user' => $target, 'link' => $admin->resetLink($user['id'], $target), 'title' => 'Link đặt lại mật khẩu'];
                break;
            case 'create_user':
                $made = $admin->createUser($user['id'], field($_POST, 'name'), field($_POST, 'email'), field($_POST, 'title'), field($_POST, 'support') === '1');
                $_SESSION['admin_link'] = ['user' => $made['id'], 'link' => $made['link'], 'title' => 'Đã tạo tài khoản — gửi link này để người dùng tự đặt mật khẩu'];
                redirect('/admin.php?tab=user&id=' . $made['id']);
            case 'join':
                $admin->joinProject($user['id'], $target);
                flash('Bạn đã tham gia dự án với quyền Quản lý (đã ghi nhật ký quản trị).');
                redirect('/project.php?id=' . $target);
            case 'delete_project':
                $admin->deleteProject($user['id'], $target, field($_POST, 'confirm_name'));
                flash('Đã xoá dự án cùng toàn bộ ảnh và góp ý.');
                break;
            default:
                throw new AppError('BAD_REQUEST', 400);
        }
    } catch (AppError $ex) {
        flash('Lỗi: ' . errorMessage($ex->errorCode));
    }
    redirect($back);
}

// Xuất danh sách người dùng đang lọc ra CSV (mở được bằng Excel, có BOM UTF-8 để giữ dấu tiếng Việt).
if ($tab === 'users' && field($_GET, 'export') === 'csv') {
    $rows = $admin->users($q, $filter, 100000, 0, $sort);
    $admin->log($user['id'], 'EXPORT_USERS', $filter !== '' ? 'nhóm ' . $filter : 'tất cả', count($rows) . ' dòng');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pinpoint-nguoi-dung-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Họ tên', 'Email', 'Chức danh', 'Quản trị', 'Hỗ trợ', 'Trạng thái', 'Dự án', 'Góp ý', 'Nhận xét', 'Đăng nhập gần nhất', 'Ngày tạo']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['name'], $r['email'], $r['title'], $r['is_admin'] === 't' ? 'có' : '', $r['is_support'] === 't' ? 'có' : '',
            $r['locked_at'] !== null ? 'Bị khoá' : 'Hoạt động', $r['projects'], $r['annotations'], $r['comments'],
            $r['last_login_at'] ? date('d/m/Y H:i', (int) strtotime($r['last_login_at'])) : '', date('d/m/Y', (int) strtotime($r['created_at']))]);
    }
    exit;
}

$issued = $_SESSION['admin_link'] ?? null;
unset($_SESSION['admin_link']);
$stats = $admin->stats();
$userCounts = $admin->userCounts();
$projectCounts = $admin->projectCounts();
$bytes = fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';

$userFilters = ['' => ['Tất cả người dùng', 'users', 'all'], 'active' => ['Đang hoạt động', 'check-circle', 'active'], 'locked' => ['Bị khoá', 'lock', 'locked'],
    'admin' => ['Quản trị viên', 'shield', 'admin'], 'support' => ['Bộ phận hỗ trợ', 'inbox', 'support'], 'inactive' => ['Không đăng nhập 30 ngày', 'clock', 'inactive']];
$pageTitles = ['overview' => 'Tổng quan', 'users' => $userFilters[$filter][0], 'user' => 'Chi tiết người dùng',
    'projects' => $state === 'archived' ? 'Dự án đã lưu trữ' : ($state === 'active' ? 'Dự án đang hoạt động' : 'Tất cả dự án'), 'log' => 'Nhật ký quản trị'];

// Dữ liệu riêng của từng trang
$data = [];
switch ($tab) {
    case 'overview':
        $data['daily'] = $admin->daily(14);
        $data['topProjects'] = $admin->projects('', 5, 0, 'open');
        $data['log'] = $admin->recentLog(8);
        $data['newUsers'] = $admin->users('', '', 6, 0, 'new');
        break;
    case 'users':
        $pg = Paginator::fromRequest($admin->countUsers($q, $filter), $_GET, 20);
        $data['rows'] = $admin->users($q, $filter, $pg->perPage, $pg->offset(), $sort);
        break;
    case 'user':
        try {
            $data['u'] = $admin->userDetail($detailId);
        } catch (AppError $ex) {
            flash('Lỗi: Không tìm thấy người dùng này.');
            redirect('/admin.php?tab=users');
        }
        $pageTitles['user'] = $data['u']['name'];
        break;
    case 'projects':
        $pg = Paginator::fromRequest($admin->countProjects($q, $state), $_GET, 20);
        $data['rows'] = $admin->projects($q, $pg->perPage, $pg->offset(), $sort, $state);
        break;
    case 'log':
        $pg = Paginator::fromRequest($admin->countLog($action, $q), $_GET, 20);
        $data['rows'] = $admin->recentLog($pg->perPage, $pg->offset(), $action, $q);
        $data['actions'] = $admin->logActions();
        break;
}

$title = 'Quản trị · ' . $pageTitles[$tab];
$active = 'admin';
$wide = true;
require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/admin/layout.php';
require __DIR__ . '/../templates/admin/' . $tab . '.php';
require __DIR__ . '/../templates/admin/layout_end.php';
require __DIR__ . '/../templates/footer.php';
