<?php

declare(strict_types=1);

// Bản 2.3 — Trang quản trị hệ thống (chỉ users.is_admin; mẫu: admin@pinpoint.test).
//   ?tab=overview  thống kê toàn hệ thống, biểu đồ 14 ngày, dự án nhiều góp ý chưa xử lý nhất, nhật ký quản trị
//   ?tab=users     tìm / lọc người dùng; khoá – mở khoá; cấp – bỏ quyền quản trị, bộ phận hỗ trợ; cấp link đặt lại mật khẩu
//   ?tab=projects  mọi dự án của hệ thống; tham gia với quyền Quản lý để xem/can thiệp; xoá dự án (gõ lại tên)
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
AdminRepository::require($c, $user['id']);
$admin = new AdminRepository($c);
$tab = in_array(field($_GET, 'tab'), ['overview', 'users', 'projects'], true) ? field($_GET, 'tab') : 'overview';
$q = field($_GET, 'q');
$filter = field($_GET, 'filter');
$back = '/admin.php?tab=' . $tab . ($q !== '' ? '&q=' . rawurlencode($q) : '') . ($filter !== '' ? '&filter=' . rawurlencode($filter) : '');
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
            case 'grant_admin':
            case 'revoke_admin':
            case 'grant_support':
            case 'revoke_support':
                [$verb, $role] = explode('_', field($_POST, 'action'));
                $admin->setRole($user['id'], $target, $role, $verb === 'grant');
                flash(($verb === 'grant' ? 'Đã cấp' : 'Đã bỏ') . ' quyền ' . ($role === 'admin' ? 'quản trị hệ thống.' : 'bộ phận hỗ trợ.'));
                break;
            case 'reset_link':
                $_SESSION['admin_link'] = ['user' => $target, 'link' => $admin->resetLink($user['id'], $target)];
                break;
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
$issued = $_SESSION['admin_link'] ?? null;
unset($_SESSION['admin_link']);
$stats = $admin->stats();
$bytes = fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';
$title = 'Quản trị hệ thống';
$active = 'admin';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div>
<h1><?= icon('shield') ?>Quản trị hệ thống</h1>
<p><?= $stats['users']['total'] ?> người dùng · <?= $stats['projects']['projects'] ?> dự án · <?= $stats['annotations']['total'] ?> góp ý · dung lượng ảnh <?= e($bytes($stats['storage'])) ?></p>
</div>
<nav class="segmented" aria-label="Mục quản trị">
<a href="/admin.php?tab=overview"<?= $tab === 'overview' ? ' aria-current="page"' : '' ?>><?= icon('chart', 'sm') ?>Tổng quan</a>
<a href="/admin.php?tab=users"<?= $tab === 'users' ? ' aria-current="page"' : '' ?>><?= icon('users', 'sm') ?>Người dùng <span class="n"><?= $stats['users']['total'] ?></span></a>
<a href="/admin.php?tab=projects"<?= $tab === 'projects' ? ' aria-current="page"' : '' ?>><?= icon('folder', 'sm') ?>Dự án <span class="n"><?= $stats['projects']['projects'] ?></span></a>
<a href="/support_admin.php"><?= icon('inbox', 'sm') ?>Yêu cầu hỗ trợ <span class="n"><?= $stats['tickets']['open'] ?></span></a>
</nav>
</div>

<?php if ($tab === 'overview'): ?>
<?php $daily = $admin->daily(14); $max = max(1, ...array_map(fn ($d) => max($d['annotations'], $d['comments']), $daily)); ?>
<div class="grid grid-4 admin-stats">
<a class="stat" href="/admin.php?tab=users"><span class="stat-icon"><?= icon('users') ?></span><span><strong><?= $stats['users']['total'] ?></strong><span>Người dùng · <?= $stats['users']['new7'] ?> mới trong 7 ngày</span></span></a>
<div class="stat"><span class="stat-icon ok"><?= icon('activity') ?></span><span><strong><?= $stats['users']['active7'] ?></strong><span>Đăng nhập trong 7 ngày</span></span></div>
<a class="stat" href="/admin.php?tab=users&amp;filter=locked"><span class="stat-icon danger"><?= icon('lock') ?></span><span><strong><?= $stats['users']['locked'] ?></strong><span>Tài khoản bị khoá</span></span></a>
<a class="stat" href="/admin.php?tab=projects"><span class="stat-icon warn"><?= icon('folder') ?></span><span><strong><?= $stats['projects']['projects'] ?></strong><span><?= $stats['projects']['screens'] ?> màn hình · <?= $stats['projects']['versions'] ?> phiên bản</span></span></a>
<div class="stat"><span class="stat-icon pin"><?= icon('pin') ?></span><span><strong><?= $stats['annotations']['open'] ?></strong><span>Góp ý chưa xử lý · <?= $stats['annotations']['overdue'] ?> quá hạn</span></span></div>
<div class="stat"><span class="stat-icon ok"><?= icon('check-circle') ?></span><span><strong><?= $stats['annotations']['resolved'] ?></strong><span>Góp ý đã xử lý</span></span></div>
<div class="stat"><span class="stat-icon"><?= icon('chat') ?></span><span><strong><?= $stats['comments']['comments'] ?></strong><span>Nhận xét · <?= $stats['comments']['attachments'] ?> ảnh đính kèm</span></span></div>
<a class="stat" href="/support_admin.php"><span class="stat-icon warn"><?= icon('inbox') ?></span><span><strong><?= $stats['tickets']['open'] ?></strong><span>Yêu cầu hỗ trợ đang chờ / <?= $stats['tickets']['total'] ?></span></span></a>
</div>

<div class="layout-side mt">
<section class="card">
<div class="section-title"><h2><?= icon('chart') ?>Hoạt động 14 ngày gần nhất</h2><span class="legend"><span class="dot a"></span>Góp ý mới <span class="dot r"></span>Trả lời</span></div>
<svg class="bar-chart" viewBox="0 0 700 220" role="img" aria-label="Biểu đồ số góp ý mới và trả lời mỗi ngày">
<?php foreach ($daily as $i => $d): $x = 20 + $i * 48; $ha = round($d['annotations'] / $max * 160); $hr = round($d['comments'] / $max * 160); ?>
<g><title><?= e($d['day']) ?>: <?= $d['annotations'] ?> góp ý mới, <?= $d['comments'] ?> trả lời</title>
<rect class="bar-a" x="<?= $x ?>" y="<?= 180 - $ha ?>" width="18" height="<?= max(1, $ha) ?>" rx="3"></rect>
<rect class="bar-r" x="<?= $x + 20 ?>" y="<?= 180 - $hr ?>" width="18" height="<?= max(1, $hr) ?>" rx="3"></rect>
<text x="<?= $x + 19 ?>" y="200" text-anchor="middle"><?= e($d['day']) ?></text>
<?php if ($d['annotations'] + $d['comments'] > 0): ?><text class="bar-n" x="<?= $x + 19 ?>" y="<?= 172 - max($ha, $hr) ?>" text-anchor="middle"><?= $d['annotations'] + $d['comments'] ?></text><?php endif; ?>
</g>
<?php endforeach; ?>
<line x1="12" y1="180" x2="690" y2="180" class="axis"></line>
</svg>
</section>
<aside class="stack">
<div class="card">
<h2><?= icon('flag') ?>Dự án nhiều góp ý chưa xử lý</h2>
<ul class="link-list">
<?php $all = $admin->projects(); usort($all, fn ($a, $b) => (int) $b['open'] <=> (int) $a['open']); ?>
<?php foreach (array_slice($all, 0, 5) as $p): ?>
<li><?= icon('folder', 'sm') ?><span><strong><?= e($p['name']) ?></strong> · <?= (int) $p['open'] ?>/<?= (int) $p['annotations'] ?> chưa xử lý · <?= e($p['owner']) ?></span></li>
<?php endforeach; ?>
<?php if ($all === []): ?><li class="hint">Chưa có dự án nào.</li><?php endif; ?>
</ul>
</div>
<div class="card">
<h2><?= icon('history') ?>Nhật ký quản trị</h2>
<?php $log = $admin->recentLog(12); ?>
<?php if ($log === []): ?><p class="hint">Chưa có thao tác quản trị nào.</p><?php endif; ?>
<ul class="feed admin-log">
<?php foreach ($log as $l): ?>
<li><span class="feed-body"><strong><?= e($l['admin'] ?? '—') ?></strong> <span class="badge badge-muted"><?= e($l['action']) ?></span> <?= e($l['target']) ?><?= $l['detail'] !== '' ? ' · ' . e($l['detail']) : '' ?><time><?= e(ago($l['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
</div>
</aside>
</div>

<?php elseif ($tab === 'users'): ?>
<?php $rows = $admin->users($q, $filter); ?>
<?php if ($issued !== null): ?>
<div class="card reset-box mb">
<div class="flash warn" role="status"><?= icon('key') ?><span>Link đặt lại mật khẩu chỉ hiện <strong>một lần</strong>, hiệu lực <?= PasswordReset::TTL_MINUTES ?> phút. Copy và gửi cho người dùng qua kênh liên hệ an toàn.</span></div>
<input class="issued-link" type="text" readonly value="<?= e($issued['link']) ?>" aria-label="Link đặt lại mật khẩu" data-select-all>
</div>
<?php endif; ?>
<form method="get" action="/admin.php" class="filter-bar">
<input type="hidden" name="tab" value="users">
<div class="grow"><label for="aq">Tìm theo tên hoặc email</label><input id="aq" name="q" type="search" value="<?= e($q) ?>" placeholder="vd: trang, @pinpoint.test"></div>
<div><label for="af">Lọc</label><select id="af" name="filter" data-autosubmit>
<?php foreach (['' => 'Tất cả', 'locked' => 'Bị khoá', 'admin' => 'Quản trị viên', 'support' => 'Bộ phận hỗ trợ'] as $k => $label): ?><option value="<?= $k ?>"<?= $filter === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select></div>
<button class="btn" type="submit"><?= icon('search') ?>Tìm</button>
</form>
<div class="table-wrap">
<table class="data admin-users" id="admin-users">
<thead><tr><th>Người dùng</th><th>Vai trò hệ thống</th><th class="num">Dự án</th><th class="num">Góp ý</th><th class="num">Nhận xét</th><th>Đăng nhập gần nhất</th><th>Trạng thái</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $u): $self = (int) $u['id'] === $user['id']; ?>
<tr data-user="<?= e($u['email']) ?>"<?= $u['locked_at'] !== null ? ' class="is-locked"' : '' ?>>
<td><div class="row center"><?= avatar($u['name'], (int) $u['id'], 'sm') ?><div><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="badge badge-muted">bạn</span>' : '' ?><div class="hint"><?= e($u['email']) ?> · tạo <?= e(date('d/m/Y', (int) strtotime($u['created_at']))) ?></div></div></div></td>
<td><?= $u['is_admin'] === 't' ? '<span class="badge badge-role">Quản trị</span> ' : '' ?><?= $u['is_support'] === 't' ? '<span class="badge badge-resolved">Hỗ trợ</span>' : '' ?><?= $u['is_admin'] !== 't' && $u['is_support'] !== 't' ? '<span class="hint">Người dùng</span>' : '' ?></td>
<td class="num"><?= (int) $u['projects'] ?></td><td class="num"><?= (int) $u['annotations'] ?></td><td class="num"><?= (int) $u['comments'] ?></td>
<td><?= $u['last_login_at'] ? e(ago($u['last_login_at'])) : '<span class="hint">chưa</span>' ?></td>
<td><?php if ($u['locked_at'] !== null): ?><span class="badge badge-open" title="<?= e($u['locked_reason']) ?>"><?= icon('lock', 'sm') ?>Bị khoá</span><?php if ($u['locked_reason'] !== ''): ?><div class="hint line-clamp-2"><?= e($u['locked_reason']) ?></div><?php endif; ?><?php else: ?><span class="badge badge-resolved">Hoạt động</span><?php endif; ?></td>
<td class="nowrap">
<details class="dropdown">
<summary class="btn btn-sm btn-ghost" aria-label="Thao tác với <?= e($u['email']) ?>"><?= icon('settings', 'sm') ?>Thao tác</summary>
<div class="menu menu-right admin-menu">
<?php if (!$self): ?>
<?php if ($u['locked_at'] === null): ?>
<form method="post" action="<?= e($back) ?>" class="lock-form"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="lock">
<input name="reason" type="text" maxlength="300" placeholder="Lý do khoá (không bắt buộc)" aria-label="Lý do khoá">
<button class="btn btn-sm btn-danger-solid" type="submit"><?= icon('lock', 'sm') ?>Khoá tài khoản</button></form>
<?php else: ?>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="unlock"><button type="submit"><?= icon('unlock') ?>Mở khoá tài khoản</button></form>
<?php endif; ?>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="<?= $u['is_admin'] === 't' ? 'revoke_admin' : 'grant_admin' ?>"><button type="submit"><?= icon('shield') ?><?= $u['is_admin'] === 't' ? 'Bỏ quyền quản trị' : 'Cấp quyền quản trị' ?></button></form>
<?php endif; ?>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="<?= $u['is_support'] === 't' ? 'revoke_support' : 'grant_support' ?>"><button type="submit"><?= icon('inbox') ?><?= $u['is_support'] === 't' ? 'Bỏ quyền bộ phận hỗ trợ' : 'Cấp quyền bộ phận hỗ trợ' ?></button></form>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="reset_link"><button type="submit"><?= icon('key') ?>Cấp link đặt lại mật khẩu</button></form>
</div>
</details>
</td>
</tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="8" class="hint">Không có người dùng phù hợp.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<?php else: ?>
<?php $rows = $admin->projects($q); ?>
<form method="get" action="/admin.php" class="filter-bar">
<input type="hidden" name="tab" value="projects">
<div class="grow"><label for="pq">Tìm theo tên dự án hoặc người tạo</label><input id="pq" name="q" type="search" value="<?= e($q) ?>"></div>
<button class="btn" type="submit"><?= icon('search') ?>Tìm</button>
</form>
<div class="table-wrap">
<table class="data" id="admin-projects">
<thead><tr><th>Dự án</th><th>Người tạo</th><th class="num">Thành viên</th><th class="num">Màn hình</th><th class="num">Góp ý (chưa xử lý)</th><th class="num">Dung lượng</th><th>Hoạt động gần nhất</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $p): ?>
<tr data-project="<?= e($p['name']) ?>">
<td><strong><?= e($p['name']) ?></strong><div class="hint">#<?= (int) $p['id'] ?> · tạo <?= e(date('d/m/Y', (int) strtotime($p['created_at']))) ?></div></td>
<td><?= e($p['owner']) ?><div class="hint"><?= e($p['owner_email']) ?></div></td>
<td class="num"><?= (int) $p['members'] ?></td><td class="num"><?= (int) $p['screens'] ?></td>
<td class="num"><?= (int) $p['annotations'] ?> <span class="hint">(<?= (int) $p['open'] ?>)</span></td>
<td class="num"><?= e($bytes((int) $p['bytes'])) ?></td>
<td><?= $p['last_activity'] ? e(ago($p['last_activity'])) : '<span class="hint">chưa có</span>' ?></td>
<td class="nowrap">
<form method="post" action="<?= e($back) ?>" class="inline"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="join">
<button class="btn btn-sm btn-ghost" type="submit" title="Tham gia với quyền Quản lý để xem và can thiệp (ghi nhật ký)"><?= icon('arrow-right', 'sm') ?>Mở / can thiệp</button></form>
<details class="dropdown">
<summary class="btn btn-sm btn-subtle" aria-label="Xoá dự án <?= e($p['name']) ?>"><?= icon('trash', 'sm') ?></summary>
<div class="menu menu-right admin-menu">
<form method="post" action="<?= e($back) ?>" class="lock-form"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete_project">
<p class="hint">Xoá vĩnh viễn dự án, ảnh, góp ý. Gõ đúng tên <strong><?= e($p['name']) ?></strong>:</p>
<input name="confirm_name" type="text" required aria-label="Gõ lại tên dự án" placeholder="<?= e($p['name']) ?>">
<button class="btn btn-sm btn-danger-solid" type="submit"><?= icon('trash', 'sm') ?>Xoá dự án</button></form>
</div>
</details>
</td>
</tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="8" class="hint">Không có dự án phù hợp.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
