<?php

declare(strict_types=1);

final class Auth
{
    public static function attempt(PgSql\Connection $c, string $email, string $password): ?array
    {
        $user = fetchOne($c, 'SELECT id, email, name, password_hash FROM users WHERE email = $1', [strtolower(trim($email))]);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        session_regenerate_id(true);
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
            $cache = fetchOne($c, 'SELECT id, email, name FROM users WHERE id = $1', [(int) $_SESSION['user_id']]);
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
        session_regenerate_id(true);
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
}
