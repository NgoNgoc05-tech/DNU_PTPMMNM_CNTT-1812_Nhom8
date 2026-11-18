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

    public const USER_SORTS = ['new' => 'Mới đăng ký', 'name' => 'Tên A → Z', 'login' => 'Đăng nhập gần nhất', 'active' => 'Nhiều góp ý nhất'];
    public const PROJECT_SORTS = ['new' => 'Mới tạo', 'open' => 'Nhiều góp ý chờ', 'size' => 'Dung lượng lớn', 'activity' => 'Hoạt động gần nhất'];

    /** @return array{0:string,1:array} */
    private function userWhere(string $q, string $filter): array
    {
        $params = [];
        $where = ['TRUE'];
        if (trim($q) !== '') {
            $params[] = '%' . addcslashes(mb_substr(trim($q), 0, 100), '%_\\') . '%';
            $where[] = '(u.email ILIKE $1 OR u.name ILIKE $1)';
        }
        $where[] = match ($filter) {
            'locked' => 'u.locked_at IS NOT NULL',
            'active' => 'u.locked_at IS NULL',
            'admin' => 'u.is_admin',
            'support' => 'u.is_support',
            'inactive' => "(u.last_login_at IS NULL OR u.last_login_at < now() - interval '30 days')",
            default => 'TRUE',
        };
        return [implode(' AND ', $where), $params];
    }

    public function countUsers(string $q = '', string $filter = ''): int
    {
        [$where, $params] = $this->userWhere($q, $filter);
        return (int) fetchOne($this->c, "SELECT COUNT(*) AS n FROM users u WHERE $where", $params)['n'];
    }

    /** Một trang người dùng; số dự án / góp ý / nhận xét chỉ đếm cho các dòng của trang (dùng chỉ mục theo user). */
    public function users(string $q = '', string $filter = '', int $limit = 20, int $offset = 0, string $sort = 'new'): array
    {
        [$where, $params] = $this->userWhere($q, $filter);
        $order = match ($sort) {
            'name' => 'lower(u.name), u.id',
            'login' => 'u.last_login_at DESC NULLS LAST, u.id',
            'active' => '(SELECT COUNT(*) FROM annotations a WHERE a.created_by = u.id) DESC, u.id',
            default => 'u.id DESC',
        };
        $params[] = $limit;
        $params[] = $offset;
        $n = count($params);
        return fetchAll($this->c,
            "SELECT u.id, u.email, u.name, u.title, u.created_at, u.last_login_at, u.is_admin, u.is_support, u.locked_at, u.locked_reason,
                    (SELECT COUNT(*) FROM project_members pm WHERE pm.user_id = u.id) AS projects,
                    (SELECT COUNT(*) FROM annotations a WHERE a.created_by = u.id) AS annotations,
                    (SELECT COUNT(*) FROM comments c WHERE c.author_id = u.id) AS comments
               FROM users u WHERE $where ORDER BY $order LIMIT \$" . ($n - 1) . " OFFSET \$$n", $params);
    }

    public function countProjects(string $q = '', string $state = ''): int
    {
        [$where, $params] = $this->projectWhere($q, $state);
        return (int) fetchOne($this->c, "SELECT COUNT(*) AS n FROM projects p JOIN users o ON o.id = p.created_by WHERE $where", $params)['n'];
    }

    /** @return array{0:string,1:array} */
    private function projectWhere(string $q, string $state = ''): array
    {
        $where = [match ($state) { 'active' => 'p.archived_at IS NULL', 'archived' => 'p.archived_at IS NOT NULL', default => 'TRUE' }];
        $params = [];
        if (trim($q) !== '') {
            $where[] = '(p.name ILIKE $1 OR o.email ILIKE $1 OR o.name ILIKE $1)';
            $params[] = '%' . addcslashes(mb_substr(trim($q), 0, 100), '%_\\') . '%';
        }
        return [implode(' AND ', $where), $params];
    }

    /** Một trang dự án toàn hệ thống; số liệu gom một lần cho mỗi dự án bằng LATERAL. */
    public function projects(string $q = '', int $limit = 20, int $offset = 0, string $sort = 'new', string $state = ''): array
    {
        [$where, $params] = $this->projectWhere($q, $state);
        $order = match ($sort) {
            'open' => 'st.open DESC, p.id DESC',
            'size' => 'sz.bytes DESC, p.id DESC',
            'activity' => 'st.last_activity DESC NULLS LAST, p.id DESC',
            default => 'p.id DESC',
        };
        $params[] = $limit;
        $params[] = $offset;
        $n = count($params);
        return fetchAll($this->c,
            "SELECT p.id, p.name, p.created_at, p.archived_at, o.name AS owner, o.email AS owner_email,
                    (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS members,
                    (SELECT COUNT(*) FROM screens s WHERE s.project_id = p.id) AS screens,
                    st.annotations, st.open, st.last_activity, sz.bytes
               FROM projects p JOIN users o ON o.id = p.created_by
               LEFT JOIN LATERAL (SELECT COUNT(a.id) AS annotations, COUNT(a.id) FILTER (WHERE a.status = 'OPEN') AS open, MAX(a.updated_at) AS last_activity
                                    FROM screens s JOIN image_versions v ON v.screen_id = s.id JOIN annotations a ON a.image_version_id = v.id
                                   WHERE s.project_id = p.id) st ON true
               LEFT JOIN LATERAL (SELECT COALESCE(SUM(v.byte_size), 0) AS bytes FROM screens s JOIN image_versions v ON v.screen_id = s.id
                                   WHERE s.project_id = p.id) sz ON true
              WHERE $where ORDER BY $order LIMIT \$" . ($n - 1) . " OFFSET \$$n", $params);
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

    /** @return array{0:string,1:array} lọc nhật ký theo loại thao tác và từ khoá (đối tượng / chi tiết). */
    private function logWhere(string $action, string $q): array
    {
        $where = ['TRUE'];
        $params = [];
        if ($action !== '') {
            $params[] = $action;
            $where[] = 'l.action = $' . count($params);
        }
        if (trim($q) !== '') {
            $params[] = '%' . addcslashes(mb_substr(trim($q), 0, 100), '%_\\') . '%';
            $where[] = '(l.target ILIKE $' . count($params) . ' OR l.detail ILIKE $' . count($params) . ')';
        }
        return [implode(' AND ', $where), $params];
    }

    public function recentLog(int $limit = 30, int $offset = 0, string $action = '', string $q = ''): array
    {
        [$where, $params] = $this->logWhere($action, $q);
        $params[] = $limit;
        $params[] = $offset;
        $n = count($params);
        return fetchAll($this->c,
            "SELECT l.action, l.target, l.detail, l.created_at, u.name AS admin FROM admin_log l LEFT JOIN users u ON u.id = l.admin_id
              WHERE $where ORDER BY l.id DESC LIMIT \$" . ($n - 1) . " OFFSET \$$n", $params);
    }

    public function countLog(string $action = '', string $q = ''): int
    {
        [$where, $params] = $this->logWhere($action, $q);
        return (int) fetchOne($this->c, "SELECT COUNT(*) AS n FROM admin_log l WHERE $where", $params)['n'];
    }

    /** Các loại thao tác đã có trong nhật ký (cho ô lọc). @return string[] */
    public function logActions(): array
    {
        return array_column(fetchAll($this->c, 'SELECT DISTINCT action FROM admin_log ORDER BY action'), 'action');
    }

    /* ----------------------------------------------------------------------------------------------------------
     * Bản 2.6 — trang quản trị chia thành nhiều trang con (giống bảng điều khiển của các ứng dụng lớn):
     * đếm theo từng nhóm cho thanh bên, thao tác hàng loạt, tạo tài khoản, trang chi tiết một người dùng.
     * ---------------------------------------------------------------------------------------------------------- */

    /** Số người dùng theo từng nhóm lọc — hiện cạnh mỗi mục ở thanh bên và trên các nút lọc. */
    public function userCounts(): array
    {
        return array_map('intval', fetchOne($this->c,
            "SELECT COUNT(*) AS all, COUNT(*) FILTER (WHERE locked_at IS NULL) AS active, COUNT(*) FILTER (WHERE locked_at IS NOT NULL) AS locked,
                    COUNT(*) FILTER (WHERE is_admin) AS admin, COUNT(*) FILTER (WHERE is_support) AS support,
                    COUNT(*) FILTER (WHERE last_login_at IS NULL OR last_login_at < now() - interval '30 days') AS inactive FROM users") ?? []);
    }

    public function projectCounts(): array
    {
        return array_map('intval', fetchOne($this->c,
            'SELECT COUNT(*) AS all, COUNT(*) FILTER (WHERE archived_at IS NULL) AS active, COUNT(*) FILTER (WHERE archived_at IS NOT NULL) AS archived FROM projects') ?? []);
    }

    /**
     * Khoá / mở khoá nhiều tài khoản một lần. Bỏ qua chính mình và tài khoản đã ở đúng trạng thái.
     * @param int[] $userIds
     * @return int số tài khoản thực sự thay đổi
     */
    public function bulkLock(int $adminId, array $userIds, bool $locked, string $reason = ''): int
    {
        $done = 0;
        foreach (array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0 && $id !== $adminId)) as $id) {
            $row = fetchOne($this->c, 'SELECT locked_at FROM users WHERE id = $1', [$id]);
            if ($row === null || ($row['locked_at'] !== null) === $locked) {
                continue;
            }
            $this->setLocked($adminId, $id, $locked, $reason);
            $done++;
        }
        return $done;
    }

    /**
     * Quản trị viên tạo tài khoản cho người khác. Mật khẩu là chuỗi ngẫu nhiên không ai biết;
     * người dùng đặt mật khẩu của mình qua link đặt lại (hiện cho quản trị viên một lần, hết hạn sau 30 phút).
     * @return array{id:int,link:string}
     */
    public function createUser(int $adminId, string $name, string $email, string $title = '', bool $support = false): array
    {
        $email = strtolower(trim($email));
        if (trim($name) === '' || mb_strlen(trim($name)) > 120) {
            throw new AppError('NAME_INVALID', 422);
        }
        if (fetchOne($this->c, 'SELECT 1 FROM users WHERE email = $1', [$email]) !== null) {
            throw new AppError('EMAIL_TAKEN', 422);
        }
        $id = Auth::createUser($this->c, $email, $name, bin2hex(random_bytes(16)));
        queryParams($this->c, 'UPDATE users SET title = $2, is_support = $3 WHERE id = $1', [$id, mb_substr(trim($title), 0, 120), $support ? 't' : 'f']);
        $link = PasswordReset::link(PasswordReset::issue($this->c, $id, $adminId));
        $this->log($adminId, 'CREATE_USER', $email, $support ? 'kèm quyền bộ phận hỗ trợ' : '');
        return ['id' => $id, 'link' => $link];
    }

    /** Hồ sơ đầy đủ một người dùng cho trang chi tiết. */
    public function userDetail(int $userId): array
    {
        $u = fetchOne($this->c,
            "SELECT u.id, u.email, u.name, u.title, u.created_at, u.last_login_at, u.is_admin, u.is_support, u.locked_at, u.locked_reason, u.email_reminders,
                    (SELECT COUNT(*) FROM project_members pm WHERE pm.user_id = u.id) AS projects,
                    (SELECT COUNT(*) FROM annotations a WHERE a.created_by = u.id) AS annotations,
                    (SELECT COUNT(*) FROM annotations a WHERE a.assignee_id = u.id AND a.status = 'OPEN') AS assigned_open,
                    (SELECT COUNT(*) FROM comments c WHERE c.author_id = u.id) AS comments
               FROM users u WHERE u.id = $1", [$userId]) ?? throw new AppError('NOT_FOUND', 404);
        $u['project_list'] = fetchAll($this->c,
            "SELECT p.id, p.name, p.archived_at, pm.role, pm.created_at AS joined_at, (p.created_by = pm.user_id) AS owner
               FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.user_id = $1 ORDER BY pm.created_at DESC LIMIT 50", [$userId]);
        $u['recent'] = fetchAll($this->c,
            "SELECT a.id, a.seq_no, a.status, a.created_at, s.id AS screen_id, s.name AS screen_name, v.id AS version_id, v.version_no, p.name AS project_name, rc.body
               FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id JOIN projects p ON p.id = s.project_id
               JOIN comments rc ON rc.annotation_id = a.id AND rc.parent_id IS NULL
              WHERE a.created_by = $1 ORDER BY a.id DESC LIMIT 8", [$userId]);
        $u['log'] = fetchAll($this->c,
            'SELECT l.action, l.detail, l.created_at, a.name AS admin FROM admin_log l LEFT JOIN users a ON a.id = l.admin_id
              WHERE l.target = $1 ORDER BY l.id DESC LIMIT 10', [$u['email']]);
        return $u;
    }

    private function user(int $userId): array
    {
        return fetchOne($this->c, 'SELECT id, email FROM users WHERE id = $1', [$userId]) ?? throw new AppError('NOT_FOUND', 404);
    }
}
