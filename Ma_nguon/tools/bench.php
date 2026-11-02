<?php

declare(strict_types=1);

// Bản 2.5 — đo thời gian các truy vấn chính (chạy sau php tools/seed_load.php).
// Mỗi truy vấn chạy 5 lần, in thời gian trung bình (ms). Mục tiêu: mọi trang < 50 ms trên máy sinh viên.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
$c = db();
$u = fetchOne($c, "SELECT u.id FROM users u JOIN project_members pm ON pm.user_id = u.id WHERE u.email LIKE 'load%@pinpoint.test'
                   GROUP BY u.id ORDER BY COUNT(*) DESC LIMIT 1");
if ($u === null) {
    fwrite(STDERR, "Chưa có dữ liệu thử tải. Chạy php tools/seed_load.php trước.\n");
    exit(2);
}
$uid = (int) $u['id'];
$pid = (int) fetchOne($c, 'SELECT project_id FROM project_members WHERE user_id = $1 ORDER BY project_id LIMIT 1', [$uid])['project_id'];
$vid = (int) fetchOne($c, 'SELECT v.id FROM image_versions v JOIN screens s ON s.id = v.screen_id WHERE s.project_id = $1 ORDER BY v.id LIMIT 1', [$pid])['id'];
$projects = new ProjectRepository($c);
$ann = new AnnotationRepository($c);
$notes = new NotificationRepository($c);
$admin = new AdminRepository($c);
$counts = fetchOne($c, 'SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM projects) p, (SELECT COUNT(*) FROM annotations) a,
                               (SELECT COUNT(*) FROM comments) c, (SELECT COUNT(*) FROM notifications) n');
printf("Dữ liệu: %d người dùng · %d dự án · %d góp ý · %d nhận xét · %d thông báo\n\n", $counts['u'], $counts['p'], $counts['a'], $counts['c'], $counts['n']);
$cases = [
    'Trang Dự án: đếm + 1 trang (9 dự án), sắp theo hoạt động' => fn () => [$projects->countForUser($uid, []), $projects->pageForUser($uid, [], 9, 0)],
    'Trang Dự án: tìm "app" + lọc còn góp ý chờ' => fn () => $projects->pageForUser($uid, ['q' => 'app', 'state' => 'open', 'sort' => 'open'], 9, 0),
    'Menu "Việc của tôi" (đếm)' => fn () => $ann->countAssigned($uid),
    'Trang Việc của tôi (20 dòng)' => fn () => $ann->assignedTo($uid, 20, 0),
    'Trình xem: góp ý + trao đổi của 1 phiên bản' => fn () => $ann->listForVersion($vid, "", 0, $uid),
    'Tìm góp ý: đếm + trang 25 dòng, ưu tiên cao trước' => fn () => [$ann->countSearch($pid, 'OPEN'), $ann->search($pid, 'OPEN', 0, '', 0, ['sort' => 'priority', 'limit' => 25, 'offset' => 0])],
    'Tìm góp ý theo nội dung "nút"' => fn () => $ann->search($pid, '', 0, 'nút', 0, ['limit' => 25, 'offset' => 0]),
    'Thống kê dự án' => fn () => $ann->stats($pid),
    'Chuông: số chưa đọc + 15 thông báo' => fn () => [$notes->unreadCount($uid), $notes->listFor($uid, 15)],
    'Chuông tự cập nhật (notif_poll)' => fn () => [$notes->unreadCount($uid), $notes->since($uid, $notes->latestId($uid))],
    'Quản trị: thống kê toàn hệ thống' => fn () => $admin->stats(),
    'Quản trị: 20 người dùng, nhiều góp ý nhất' => fn () => [$admin->countUsers(), $admin->users('', '', 20, 0, 'active')],
    'Quản trị: 20 dự án, nhiều góp ý chờ nhất' => fn () => [$admin->countProjects(), $admin->projects('', 20, 0, 'open')],
];
$worst = 0.0;
foreach ($cases as $label => $fn) {
    $fn(); // làm nóng bộ đệm
    $t = microtime(true);
    for ($i = 0; $i < 5; $i++) {
        $fn();
    }
    $ms = (microtime(true) - $t) * 1000 / 5;
    $worst = max($worst, $ms);
    printf("%7.1f ms  %s\n", $ms, $label);
}
printf("\nCHAM_NHAT=%.1f ms | %s\n", $worst, $worst < 50 ? 'DAT (< 50 ms)' : 'CAN XEM LAI');
