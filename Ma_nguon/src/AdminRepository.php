<?php

declare(strict_types=1);

/**
 * Quản trị hệ thống (bản 2.3) — chỉ tài khoản users.is_admin.
 * Thống kê toàn hệ thống, quản lý người dùng (khoá/mở khoá, cấp quyền quản trị / hỗ trợ, cấp link đặt lại mật khẩu),
 * quản lý mọi dự án (xem, tham gia với quyền Quản lý để can thiệp, xoá). Mọi thao tác thay đổi được ghi vào admin_log.
 */
final class AdminRepository
{
    public function __construct(private PgSql\Connection $c)
    {
    }

    public static function isAdmin(PgSql\Connection $c, int $userId): bool
    {
        return (fetchOne($c, 'SELECT is_admin FROM users WHERE id = $1', [$userId])['is_admin'] ?? 'f') === 't';
    }

    public static function require(PgSql\Connection $c, int $userId): void
    {
        if (!self::isAdmin($c, $userId)) {
            throw new AppError('FORBIDDEN', 403);
        }
    }

    public function stats(): array
    {
        $one = fn (string $sql) => array_map('intval', fetchOne($this->c, $sql) ?? []);
        return [
            'users' => $one("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE locked_at IS NOT NULL) AS locked,
                                    COUNT(*) FILTER (WHERE is_admin) AS admins, COUNT(*) FILTER (WHERE is_support) AS support,
                                    COUNT(*) FILTER (WHERE created_at > now() - interval '7 days') AS new7,
                                    COUNT(*) FILTER (WHERE last_login_at > now() - interval '7 days') AS active7 FROM users"),
            'projects' => $one('SELECT (SELECT COUNT(*) FROM projects) AS projects, (SELECT COUNT(*) FROM screens) AS screens,
                                       (SELECT COUNT(*) FROM image_versions) AS versions'),
            'annotations' => $one("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 'OPEN') AS open,
                                          COUNT(*) FILTER (WHERE status = 'RESOLVED') AS resolved,
                                          COUNT(*) FILTER (WHERE status = 'OPEN' AND due_date < CURRENT_DATE) AS overdue FROM annotations"),
            'comments' => $one('SELECT (SELECT COUNT(*) FROM comments) AS comments, (SELECT COUNT(*) FROM comment_attachments) AS attachments'),
            'tickets' => $one("SELECT COUNT(*) FILTER (WHERE status = 'OPEN') AS open, COUNT(*) AS total FROM support_tickets"),
            'storage' => (int) (fetchOne($this->c, 'SELECT COALESCE((SELECT SUM(byte_size) FROM image_versions), 0)
                                                         + COALESCE((SELECT SUM(byte_size) FROM comment_attachments), 0) AS n')['n'] ?? 0),
        ];
    }

    /** Số góp ý tạo mới và số trả lời theo ngày, 14 ngày gần nhất (kể cả ngày không có gì). */
    public function daily(int $days = 14): array
    {
        return array_map(fn ($r) => ['day' => $r['day'], 'annotations' => (int) $r['annotations'], 'comments' => (int) $r['comments']], fetchAll($this->c,
            "SELECT to_char(d, 'DD/MM') AS day,
                    (SELECT COUNT(*) FROM annotations a WHERE a.created_at >= d AND a.created_at < d + interval '1 day') AS annotations,
                    (SELECT COUNT(*) FROM comments c WHERE c.parent_id IS NOT NULL AND c.created_at >= d AND c.created_at < d + interval '1 day') AS comments
               FROM generate_series(CURRENT_DATE - ($1::int - 1), CURRENT_DATE, interval '1 day') AS d ORDER BY d", [$days]));
    }

    public function users(string $q = '', string $filter = ''): array
    {
        $params = [];
        $where = ['TRUE'];
        if (trim($q) !== '') {
            $params[] = '%' . addcslashes(mb_substr(trim($q), 0, 100), '%_\\') . '%';
            $where[] = '(u.email ILIKE $1 OR u.name ILIKE $1)';
        }
        $where[] = match ($filter) {
            'locked' => 'u.locked_at IS NOT NULL',
            'admin' => 'u.is_admin',
            'support' => 'u.is_support',
            default => 'TRUE',
        };
        return fetchAll($this->c,
            'SELECT u.id, u.email, u.name, u.title, u.created_at, u.last_login_at, u.is_admin, u.is_support, u.locked_at, u.locked_reason,
                    (SELECT COUNT(*) FROM project_members pm WHERE pm.user_id = u.id) AS projects,
                    (SELECT COUNT(*) FROM annotations a WHERE a.created_by = u.id) AS annotations,
                    (SELECT COUNT(*) FROM comments c WHERE c.author_id = u.id) AS comments
               FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id LIMIT 500', $params);
    }

    public function projects(string $q = ''): array
    {
        $params = [];
        $where = 'TRUE';
        if (trim($q) !== '') {
            $params[] = '%' . addcslashes(mb_substr(trim($q), 0, 100), '%_\\') . '%';
            $where = '(p.name ILIKE $1 OR o.email ILIKE $1 OR o.name ILIKE $1)';
        }
        return fetchAll($this->c,
            "SELECT p.id, p.name, p.created_at, o.name AS owner, o.email AS owner_email,
                    (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS members,
                    (SELECT COUNT(*) FROM screens s WHERE s.project_id = p.id) AS screens,
                    (SELECT COUNT(*) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                      WHERE s.project_id = p.id) AS annotations,
                    (SELECT COUNT(*) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                      WHERE s.project_id = p.id AND a.status = 'OPEN') AS open,
                    (SELECT COALESCE(SUM(v.byte_size), 0) FROM image_versions v JOIN screens s ON s.id = v.screen_id WHERE s.project_id = p.id) AS bytes,
                    (SELECT MAX(a.updated_at) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                      WHERE s.project_id = p.id) AS last_activity
               FROM projects p JOIN users o ON o.id = p.created_by WHERE $where ORDER BY p.id DESC LIMIT 500", $params);
    }

    public function setLocked(int $adminId, int $userId, bool $locked, string $reason = ''): void
    {
        if ($adminId === $userId) {
            throw new AppError('CANNOT_CHANGE_SELF', 422);
        }
        $u = $this->user($userId);
        queryParams($this->c, 'UPDATE users SET locked_at = CASE WHEN $2 THEN now() ELSE NULL END, locked_reason = $3 WHERE id = $1',
            [$userId, $locked ? 't' : 'f', $locked ? mb_substr(trim($reason), 0, 300) : '']);
        $this->log($adminId, $locked ? 'LOCK' : 'UNLOCK', $u['email'], $reason);
    }

    /** @param 'admin'|'support' $role */
    public function setRole(int $adminId, int $userId, string $role, bool $on): void
    {
        $column = ['admin' => 'is_admin', 'support' => 'is_support'][$role] ?? throw new AppError('ROLE_INVALID', 422);
        if ($role === 'admin' && !$on) {
            if ($adminId === $userId) {
                throw new AppError('CANNOT_CHANGE_SELF', 422);
            }
            if ((int) fetchOne($this->c, 'SELECT COUNT(*) AS n FROM users WHERE is_admin AND id <> $1', [$userId])['n'] === 0) {
                throw new AppError('LAST_ADMIN', 422);
            }
        }
        $u = $this->user($userId);
        queryParams($this->c, "UPDATE users SET $column = $2 WHERE id = $1", [$userId, $on ? 't' : 'f']);
        $this->log($adminId, ($on ? 'GRANT_' : 'REVOKE_') . strtoupper($role), $u['email']);
    }

    /** Cấp link đặt lại mật khẩu cho một người dùng (quản trị viên gửi link cho họ). @return string link */
    public function resetLink(int $adminId, int $userId): string
    {
        $u = $this->user($userId);
        $link = PasswordReset::link(PasswordReset::issue($this->c, $userId, $adminId));
        $this->log($adminId, 'RESET_LINK', $u['email'], 'hết hạn sau ' . PasswordReset::TTL_MINUTES . ' phút');
        return $link;
    }

    /** Quản trị viên tham gia một dự án với quyền Quản lý để xem / can thiệp dữ liệu (có ghi nhật ký). */
    public function joinProject(int $adminId, int $projectId): void
    {
        $p = (new ProjectRepository($this->c))->find($projectId);
        queryParams($this->c,
            "INSERT INTO project_members (project_id, user_id, role) VALUES ($1, $2, 'ADMIN')
             ON CONFLICT (project_id, user_id) DO UPDATE SET role = 'ADMIN'", [$projectId, $adminId]);
        $this->log($adminId, 'JOIN_PROJECT', '#' . $projectId . ' ' . $p['name']);
    }

    public function deleteProject(int $adminId, int $projectId, string $confirmName): void
    {
        $repo = new ProjectRepository($this->c);
        $p = $repo->find($projectId);
        if (trim($confirmName) !== $p['name']) {
            throw new AppError('CONFIRM_MISMATCH', 422);
        }
        $repo->purge($projectId);
        $this->log($adminId, 'DELETE_PROJECT', '#' . $projectId . ' ' . $p['name']);
    }

    public function log(int $adminId, string $action, string $target = '', string $detail = ''): void
    {
        queryParams($this->c, 'INSERT INTO admin_log (admin_id, action, target, detail) VALUES ($1, $2, $3, $4)',
            [$adminId, $action, mb_substr($target, 0, 200), mb_substr($detail, 0, 300)]);
    }

    public function recentLog(int $limit = 30): array
    {
        return fetchAll($this->c,
            'SELECT l.action, l.target, l.detail, l.created_at, u.name AS admin FROM admin_log l LEFT JOIN users u ON u.id = l.admin_id
              ORDER BY l.id DESC LIMIT $1', [$limit]);
    }

    private function user(int $userId): array
    {
        return fetchOne($this->c, 'SELECT id, email FROM users WHERE id = $1', [$userId]) ?? throw new AppError('NOT_FOUND', 404);
    }
}
