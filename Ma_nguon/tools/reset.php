<?php

declare(strict_types=1);

// bash lab.sh reset: XOÁ toàn bộ bảng của Pinpoint trong CSDL lab riêng rồi tạo lại + nạp mẫu.
// Chỉ dùng khi muốn làm lại dữ liệu demo; phải gõ xác nhận.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
$c = db();
$db = fetchOne($c, 'SELECT current_database() AS d')['d'];
echo "Sẽ XOÁ mọi dữ liệu Pinpoint trong CSDL \"$db\". Gõ XOA để tiếp tục: ";
$answer = trim((string) fgets(STDIN));
if ($answer !== 'XOA') {
    echo "Đã huỷ.\n";
    exit(1);
}
pg_query($c, 'DROP TABLE IF EXISTS notifications, support_tickets, annotation_events, comment_reactions, status_history, comment_revisions, comments, annotations, image_versions, screens, project_members, projects, users CASCADE');
foreach (glob(ImageStore::directory() . '/*.{png,jpg}', GLOB_BRACE) ?: [] as $file) {
    unlink($file);
}
echo "RESET=OK\n";
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/setup.php'), $code);
exit($code);
