<?php

declare(strict_types=1);

/**
 * Quên mật khẩu (bản 2.3).
 *
 *  1. Người dùng nhập email ở /forgot.php. Trang luôn trả lời giống nhau (không lộ email nào đã đăng ký).
 *  2. Nếu máy chủ cấu hình gửi thư (biến môi trường PINPOINT_MAIL_FROM), link được gửi thẳng tới email.
 *     Nếu không, hệ thống tạo một yêu cầu hỗ trợ loại "Đặt lại mật khẩu"; bộ phận hỗ trợ / quản trị viên
 *     bấm "Cấp link" rồi gửi link cho người dùng qua kênh liên hệ họ ghi lại.
 *  3. Link /reset_password.php?token=... dùng MỘT lần, hết hạn sau 30 phút. CSDL chỉ lưu SHA-256 của mã,
 *     nên người đọc được CSDL cũng không dùng được link. Cấp link mới làm link cũ hết hiệu lực.
 */
final class PasswordReset
{
    public const TTL_MINUTES = 30;

    /** Tạo mã mới cho người dùng (huỷ các mã chưa dùng trước đó). @return string mã gốc, chỉ hiện một lần */
    public static function issue(PgSql\Connection $c, int $userId, ?int $byUserId = null): string
    {
        $token = bin2hex(random_bytes(24));
        transaction($c, function () use ($c, $userId, $byUserId, $token): void {
            queryParams($c, 'UPDATE password_resets SET used_at = now() WHERE user_id = $1 AND used_at IS NULL', [$userId]);
            queryParams($c,
                "INSERT INTO password_resets (user_id, token_hash, created_by, expires_at) VALUES ($1, $2, $3, now() + interval '" . self::TTL_MINUTES . " minutes')",
                [$userId, hash('sha256', $token), $byUserId]);
        });
        return $token;
    }

    public static function link(string $token): string
    {
        return self::baseUrl() . '/reset_password.php?token=' . $token;
    }

    /** Địa chỉ gốc của trang: PINPOINT_BASE_URL nếu có, nếu không lấy từ yêu cầu hiện tại (Render gửi X-Forwarded-Proto). */
    public static function baseUrl(): string
    {
        $env = getenv('PINPOINT_BASE_URL');
        if (is_string($env) && preg_match('#^https?://[A-Za-z0-9.\-:]+$#', rtrim($env, '/'))) {
            return rtrim($env, '/');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8115');
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            $host = '127.0.0.1:8115';
        }
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    /**
     * Xử lý biểu mẫu "Quên mật khẩu". Không báo email có tồn tại hay không.
     * @return string 'MAILED' | 'TICKET' | 'IGNORED' (chỉ dùng cho kiểm thử và nhật ký, không hiện cho người dùng)
     */
    public static function request(PgSql\Connection $c, string $email, string $contact = ''): string
    {
        $user = fetchOne($c, 'SELECT id, name, email, locked_at FROM users WHERE email = $1', [strtolower(trim($email))]);
        if ($user === null || $user['locked_at'] !== null) {
            return 'IGNORED';
        }
        $uid = (int) $user['id'];
        if (self::mailConfigured()) {
            $token = self::issue($c, $uid);
            if (self::sendMail($user['email'], $user['name'], self::link($token))) {
                return 'MAILED';
            }
        }
        // Không gửi được thư: chuyển cho bộ phận hỗ trợ. Một yêu cầu đang chờ là đủ (bấm nhiều lần không tạo thêm).
        $open = fetchOne($c, "SELECT id FROM support_tickets WHERE user_id = $1 AND is_password_reset AND status = 'OPEN'", [$uid]);
        $contact = mb_substr(trim(str_replace(["\r", "\n"], ' ', $contact)), 0, 200);
        $message = "Yêu cầu gửi từ trang Quên mật khẩu lúc " . date('H:i d/m/Y') . ".\n"
            . 'Cách liên hệ để nhận link: ' . ($contact !== '' ? $contact : '(không ghi — dùng email ' . $user['email'] . ')');
        if ($open !== null) {
            queryParams($c, "UPDATE support_tickets SET message = message || E'\\n' || $2, updated_at = now() WHERE id = $1",
                [(int) $open['id'], '— Yêu cầu lại: ' . mb_substr($message, 0, 400)]);
            return 'TICKET';
        }
        $id = (new SupportRepository($c))->create($uid, 'ACCOUNT', 'Quên mật khẩu — cần link đặt lại', $message);
        queryParams($c, 'UPDATE support_tickets SET is_password_reset = true WHERE id = $1', [$id]);
        return 'TICKET';
    }

    /** Người dùng của một mã còn hiệu lực, hoặc null. */
    public static function find(PgSql\Connection $c, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        return fetchOne($c,
            'SELECT r.id AS reset_id, u.id, u.name, u.email FROM password_resets r JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = $1 AND r.used_at IS NULL AND r.expires_at > now() AND u.locked_at IS NULL',
            [hash('sha256', $token)]);
    }

    /** Đặt mật khẩu mới bằng mã; mã bị đánh dấu đã dùng. @return int user id */
    public static function reset(PgSql\Connection $c, string $token, string $new, string $repeat): int
    {
        if (strlen($new) < 8) {
            throw new AppError('PASSWORD_TOO_SHORT', 422);
        }
        if ($new !== $repeat) {
            throw new AppError('PASSWORD_MISMATCH', 422);
        }
        return transaction($c, function () use ($c, $token, $new): int {
            $row = self::find($c, $token);
            if ($row === null) {
                throw new AppError('RESET_TOKEN_INVALID', 422);
            }
            // Khoá hàng mã để hai lần bấm cùng lúc không cùng dùng được một mã.
            $locked = fetchOne($c, 'SELECT id FROM password_resets WHERE id = $1 AND used_at IS NULL FOR UPDATE', [(int) $row['reset_id']]);
            if ($locked === null) {
                throw new AppError('RESET_TOKEN_INVALID', 422);
            }
            $uid = (int) $row['id'];
            queryParams($c, 'UPDATE users SET password_hash = $2 WHERE id = $1', [$uid, password_hash($new, PASSWORD_DEFAULT)]);
            queryParams($c, 'UPDATE password_resets SET used_at = now() WHERE user_id = $1 AND used_at IS NULL', [$uid]);
            queryParams($c,
                "UPDATE support_tickets SET status = 'CLOSED', updated_at = now(),
                        reply = CASE WHEN reply = '' THEN 'Người dùng đã tự đặt lại mật khẩu bằng đường link.' ELSE reply END
                  WHERE user_id = $1 AND is_password_reset AND status <> 'CLOSED'", [$uid]);
            return $uid;
        });
    }

    public static function mailConfigured(): bool
    {
        $from = getenv('PINPOINT_MAIL_FROM');
        return is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function sendMail(string $to, string $name, string $link): bool
    {
        $from = (string) getenv('PINPOINT_MAIL_FROM');
        $subject = '=?UTF-8?B?' . base64_encode('Pinpoint — đặt lại mật khẩu') . '?=';
        $body = "Chào $name,\r\n\r\nBạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu Pinpoint. Mở link sau trong " . self::TTL_MINUTES
            . " phút để đặt mật khẩu mới (link chỉ dùng được một lần):\r\n\r\n$link\r\n\r\nNếu bạn không yêu cầu, hãy bỏ qua thư này; mật khẩu cũ vẫn giữ nguyên.\r\n";
        $headers = "From: Pinpoint <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
        return @mail($to, $subject, $body, $headers);
    }
}
