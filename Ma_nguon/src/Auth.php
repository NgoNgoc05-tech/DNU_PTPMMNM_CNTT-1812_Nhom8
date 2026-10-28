<?php

declare(strict_types=1);

final class Auth
{
    public static function attempt(PgSql\Connection $c, string $email, string $password): ?array
    {
        $user = fetchOne($c, 'SELECT id, email, name, password_hash, locked_at FROM users WHERE email = $1', [strtolower(trim($email))]);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        // Bản 2.3: tài khoản bị quản trị viên khoá thì không đăng nhập được (báo rõ sau khi mật khẩu đúng).
        if ($user['locked_at'] !== null) {
            throw new AppError('ACCOUNT_LOCKED', 403);
        }
        queryParams($c, 'UPDATE users SET last_login_at = now() WHERE id = $1', [(int) $user['id']]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int) $user['id'];
        return $user;
    }

    public static function user(PgSql\Connection $c): ?array
    {
        static $cache = null;
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        if ($cache === null || (int) $cache['id'] !== (int) $_SESSION['user_id']) {
            $cache = fetchOne($c, 'SELECT id, email, name, title, created_at, is_support, is_admin, locked_at FROM users WHERE id = $1', [(int) $_SESSION['user_id']]);
            // Bị khoá trong lúc đang đăng nhập: phiên hết hiệu lực ngay ở yêu cầu tiếp theo.
            if ($cache !== null && $cache['locked_at'] !== null) {
                self::logout();
                $cache = null;
            }
        }
        return $cache;
    }

    /** Trang HTML: chưa đăng nhập thì chuyển tới trang đăng nhập. */
    public static function requireUser(PgSql\Connection $c): array
    {
        $user = self::user($c);
        if ($user === null) {
            redirect('/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
        }
        $user['id'] = (int) $user['id'];
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function createUser(PgSql\Connection $c, string $email, string $name, string $password): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
            throw new AppError('EMAIL_INVALID', 422);
        }
        if (strlen($password) < 8) {
            throw new AppError('PASSWORD_TOO_SHORT', 422);
        }
        $row = fetchOne($c,
            'INSERT INTO users (email, name, password_hash) VALUES ($1, $2, $3) RETURNING id',
            [$email, cleanName($name), password_hash($password, PASSWORD_DEFAULT)]);
        return (int) $row['id'];
    }

    /** Hồ sơ: đổi họ tên và chức danh. */
    public static function updateProfile(PgSql\Connection $c, int $userId, string $name, string $title): void
    {
        queryParams($c, 'UPDATE users SET name = $2, title = $3 WHERE id = $1',
            [$userId, cleanName($name), mb_substr(trim($title), 0, 120)]);
    }

    /** Đổi mật khẩu: phải nhập đúng mật khẩu hiện tại; tạo lại mã phiên sau khi đổi. */
    public static function changePassword(PgSql\Connection $c, int $userId, string $current, string $new, string $repeat): void
    {
        $row = fetchOne($c, 'SELECT password_hash FROM users WHERE id = $1', [$userId]);
        if ($row === null || !password_verify($current, $row['password_hash'])) {
            throw new AppError('PASSWORD_WRONG', 422);
        }
        if (strlen($new) < 8) {
            throw new AppError('PASSWORD_TOO_SHORT', 422);
        }
        if ($new !== $repeat) {
            throw new AppError('PASSWORD_MISMATCH', 422);
        }
        queryParams($c, 'UPDATE users SET password_hash = $2 WHERE id = $1', [$userId, password_hash($new, PASSWORD_DEFAULT)]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
