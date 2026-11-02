<?php

declare(strict_types=1);

/**
 * Gửi thư (bản 2.4, dùng chung cho Quên mật khẩu và Nhắc hạn).
 * Chỉ hoạt động khi đặt biến môi trường PINPOINT_MAIL_FROM và máy chủ cấu hình được hàm mail() của PHP;
 * nếu không, ứng dụng vẫn chạy bình thường bằng thông báo trong ứng dụng.
 */
final class Mailer
{
    public static function configured(): bool
    {
        $from = getenv('PINPOINT_MAIL_FROM');
        return is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        if (!self::configured() || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $from = (string) getenv('PINPOINT_MAIL_FROM');
        $headers = "From: Pinpoint <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', str_replace("\n", "\r\n", $body), $headers);
    }
}
