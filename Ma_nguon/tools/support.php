<?php

declare(strict_types=1);

// Bộ phận hỗ trợ trả lời yêu cầu từ dòng lệnh:
//   php tools/support.php list                 — liệt kê yêu cầu đang chờ
//   php tools/support.php reply <id> "nội dung" — trả lời (trạng thái: Đã trả lời)
//   php tools/support.php close <id>           — đóng yêu cầu
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
$c = db();
$repo = new SupportRepository($c);
$cmd = $argv[1] ?? 'list';
if ($cmd === 'list') {
    $rows = fetchAll($c, "SELECT t.id, t.category, t.subject, t.message, t.created_at, u.email FROM support_tickets t
                            JOIN users u ON u.id = t.user_id WHERE t.status = 'OPEN' ORDER BY t.id");
    foreach ($rows as $r) {
        printf("#%d [%s] %s — %s (%s)\n    %s\n", $r['id'], SupportRepository::CATEGORIES[$r['category']], $r['subject'], $r['email'],
            substr($r['created_at'], 0, 16), str_replace("\n", "\n    ", $r['message']));
    }
    echo count($rows) . " yêu cầu đang chờ.\n";
} elseif ($cmd === 'reply' && isset($argv[2], $argv[3])) {
    $repo->answer((int) $argv[2], $argv[3]);
    echo "Đã trả lời yêu cầu #{$argv[2]}.\n";
} elseif ($cmd === 'close' && isset($argv[2])) {
    $repo->answer((int) $argv[2], (string) (fetchOne($c, 'SELECT reply FROM support_tickets WHERE id = $1', [(int) $argv[2]])['reply'] ?? ''), 'CLOSED');
    echo "Đã đóng yêu cầu #{$argv[2]}.\n";
} else {
    fwrite(STDERR, "Cách dùng: php tools/support.php list | reply <id> \"nội dung\" | close <id>\n");
    exit(2);
}
