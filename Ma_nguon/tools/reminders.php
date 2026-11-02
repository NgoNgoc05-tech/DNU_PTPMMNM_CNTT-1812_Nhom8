<?php

declare(strict_types=1);

// Bản 2.4: quét và gửi nhắc hạn ngay (dùng cho cron, ví dụ mỗi giờ: 0 * * * * cd /duong-dan/Ma_nguon && php tools/reminders.php).
// Không có cron thì ứng dụng tự quét khi có người dùng mở trang (tối đa 10 phút một lần).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
$n = (new NotificationRepository(db()))->remindDue(true);
echo "REMINDERS=$n\n";
