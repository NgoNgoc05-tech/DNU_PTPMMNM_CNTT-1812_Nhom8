<?php

declare(strict_types=1);

final class ProjectRepository
{
    public function __construct(private PgSql\Connection $c)
    {
    }

    /** Người tạo dự án trở thành thành viên ADMIN trong cùng giao dịch. */
    public function create(string $name, string $description, int $userId): int
    {
        $name = cleanName($name, 150);
        $description = mb_substr(trim($description), 0, 2000);
        return transaction($this->c, function () use ($name, $description, $userId): int {
            $row = fetchOne($this->c,
                'INSERT INTO projects (name, description, created_by) VALUES ($1, $2, $3) RETURNING id',
                [$name, $description, $userId]);
            $projectId = (int) $row['id'];
            queryParams($this->c,
                "INSERT INTO project_members (project_id, user_id, role, added_by) VALUES ($1, $2, 'ADMIN', $2)",
                [$projectId, $userId]);
            return $projectId;
        });
    }

    public function listForUser(int $userId): array
    {
        return fetchAll($this->c,
            "SELECT p.id, p.name, p.description, pm.role, p.created_at, p.created_by, p.archived_at, ou.name AS owner_name,
                    (SELECT COUNT(*) FROM screens s WHERE s.project_id = p.id) AS screens,
                    (SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS members,
                    (SELECT string_agg(u.id || ':' || u.name, '|' ORDER BY m.created_at) FROM project_members m JOIN users u ON u.id = m.user_id
                      WHERE m.project_id = p.id) AS member_names,
                    st.open_items, st.total_items,
                    (SELECT v.id FROM screens s JOIN image_versions v ON v.screen_id = s.id
                      WHERE s.project_id = p.id ORDER BY s.id, v.version_no DESC LIMIT 1) AS cover_version_id,
                    (SELECT MAX(e.created_at) FROM annotation_events e JOIN annotations a ON a.id = e.annotation_id
                       JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                      WHERE s.project_id = p.id) AS last_activity
               FROM projects p JOIN project_members pm ON pm.project_id = p.id
               JOIN users ou ON ou.id = p.created_by
               CROSS JOIN LATERAL (SELECT COUNT(*) FILTER (WHERE a.status = 'OPEN') AS open_items, COUNT(*) AS total_items
                                     FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                                     JOIN screens s ON s.id = v.screen_id WHERE s.project_id = p.id) st
              WHERE pm.user_id = $1
              ORDER BY p.id", [$userId]);
    }

    /**
     * Bản 2.5: tìm kiếm, lọc, sắp xếp và PHÂN TRANG dự án ngay trong CSDL (không tải hết về PHP).
     * $f như filter(): q, view (active|archived), role (ADMIN|COMMENT|VIEW|OWNER), state (open|done|empty), sort.
     * @return array{0:string,1:array} [câu SQL "base" đã lọc, tham số]
     */
    private function scoped(int $userId, array $f): array
    {
        $params = [$userId];
        $where = [($f['view'] ?? 'active') === 'archived' ? 'b.archived_at IS NOT NULL' : 'b.archived_at IS NULL'];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $params[] = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $n = count($params);
            $where[] = "(b.name ILIKE \$$n OR b.description ILIKE \$$n OR b.owner_name ILIKE \$$n)";
        }
        $role = (string) ($f['role'] ?? '');
        if ($role === 'OWNER') {
            $where[] = 'b.created_by = $1';
        } elseif (in_array($role, Access::ROLES, true)) {
            $params[] = $role;
            $where[] = 'b.role = $' . count($params);
        }
        $where[] = match ((string) ($f['state'] ?? '')) {
            'open' => 'b.open_items > 0',
            'done' => 'b.total_items > 0 AND b.open_items = 0',
            'empty' => 'b.total_items = 0',
            default => 'TRUE',
        };
        // Số liệu mỗi dự án tính một lần bằng LATERAL (dùng chỉ mục screens_project, annotations_version_status).
        $base = "SELECT p.id, p.name, p.description, p.created_at, p.created_by, p.archived_at, pm.role, ou.name AS owner_name,
                        COALESCE(st.open_items, 0) AS open_items, COALESCE(st.total_items, 0) AS total_items, st.last_activity
                   FROM project_members pm JOIN projects p ON p.id = pm.project_id JOIN users ou ON ou.id = p.created_by
                   LEFT JOIN LATERAL (
                        SELECT COUNT(a.id) FILTER (WHERE a.status = 'OPEN') AS open_items, COUNT(a.id) AS total_items,
                               MAX(a.updated_at) AS last_activity
                          FROM screens s JOIN image_versions v ON v.screen_id = s.id JOIN annotations a ON a.image_version_id = v.id
                         WHERE s.project_id = p.id) st ON true
                  WHERE pm.user_id = \$1";
        return ["SELECT * FROM ($base) b WHERE " . implode(' AND ', $where), $params];
    }

    public function countForUser(int $userId, array $f): int
    {
        [$sql, $params] = $this->scoped($userId, $f);
        return (int) fetchOne($this->c, "SELECT COUNT(*) AS n FROM ($sql) x", $params)['n'];
    }

    /** Số dự án đang hoạt động / đã lưu trữ của một người (cho hai tab). */
    public function viewCounts(int $userId): array
    {
        $r = fetchOne($this->c,
            'SELECT COUNT(*) FILTER (WHERE p.archived_at IS NULL) AS active, COUNT(*) FILTER (WHERE p.archived_at IS NOT NULL) AS archived
               FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.user_id = $1', [$userId]);
        return ['active' => (int) $r['active'], 'archived' => (int) $r['archived']];
    }

    /** Một trang dự án đã lọc + sắp xếp; thông tin hiển thị thẻ (ảnh bìa, thành viên) chỉ tính cho các dòng của trang. */
    public function pageForUser(int $userId, array $f, int $limit, int $offset): array
    {
        [$sql, $params] = $this->scoped($userId, $f);
        $order = match ((string) ($f['sort'] ?? 'activity')) {
            'name' => 'lower(b.name), b.id',
            'open' => 'b.open_items DESC, b.id',
            'created' => 'b.id DESC',
            default => 'COALESCE(b.last_activity, b.created_at) DESC, b.id DESC',
        };
        $params[] = $limit;
        $params[] = $offset;
        $n = count($params);
        return fetchAll($this->c,
            "SELECT b.*,
                    (SELECT COUNT(*) FROM screens s WHERE s.project_id = b.id) AS screens,
                    (SELECT COUNT(*) FROM project_members m WHERE m.project_id = b.id) AS members,
                    (SELECT string_agg(u.id || ':' || u.name, '|' ORDER BY m.created_at) FROM project_members m JOIN users u ON u.id = m.user_id
                      WHERE m.project_id = b.id) AS member_names,
                    (SELECT v.id FROM screens s JOIN image_versions v ON v.screen_id = s.id
                      WHERE s.project_id = b.id ORDER BY s.id, v.version_no DESC LIMIT 1) AS cover_version_id
               FROM ($sql) b ORDER BY $order LIMIT \$" . ($n - 1) . " OFFSET \$$n", $params);
    }

    /**
     * Bản 2.4: tìm kiếm, lọc, sắp xếp danh sách dự án (làm trên mảng đã lấy — mỗi người chỉ có vài chục dự án).
     * $f: q (tên/mô tả/người tạo), view (active|archived), role (ADMIN|COMMENT|VIEW|OWNER), state (open|done|empty),
     *     sort (activity|name|open|created).
     */
    public static function filter(array $projects, array $f, int $userId): array
    {
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $archived = ($f['view'] ?? 'active') === 'archived';
        $role = (string) ($f['role'] ?? '');
        $state = (string) ($f['state'] ?? '');
        $list = array_values(array_filter($projects, function (array $p) use ($q, $archived, $role, $state, $userId): bool {
            if (($p['archived_at'] !== null) !== $archived) {
                return false;
            }
            if ($q !== '' && !str_contains(mb_strtolower($p['name'] . ' ' . $p['description'] . ' ' . ($p['owner_name'] ?? '')), $q)) {
                return false;
            }
            if ($role === 'OWNER' ? (int) $p['created_by'] !== $userId : ($role !== '' && $p['role'] !== $role)) {
                return false;
            }
            return match ($state) {
                'open' => (int) $p['open_items'] > 0,
                'done' => (int) $p['total_items'] > 0 && (int) $p['open_items'] === 0,
                'empty' => (int) $p['total_items'] === 0,
                default => true,
            };
        }));
        $sort = (string) ($f['sort'] ?? 'activity');
        usort($list, match ($sort) {
            'name' => fn ($a, $b) => strcoll(mb_strtolower($a['name']), mb_strtolower($b['name'])) ?: $a['id'] <=> $b['id'],
            'open' => fn ($a, $b) => (int) $b['open_items'] <=> (int) $a['open_items'] ?: $a['id'] <=> $b['id'],
            'created' => fn ($a, $b) => (int) $b['id'] <=> (int) $a['id'],
            default => fn ($a, $b) => strcmp((string) ($b['last_activity'] ?? $b['created_at']), (string) ($a['last_activity'] ?? $a['created_at'])) ?: (int) $b['id'] <=> (int) $a['id'],
        });
        return $list;
    }

    /** Lưu trữ / khôi phục dự án — chỉ Quản lý (kể cả khi dự án đang lưu trữ). */
    public function setArchived(int $projectId, int $userId, bool $archived): void
    {
        if (Access::roleFor($this->c, $userId, $projectId) !== Access::ADMIN) {
            throw new AppError('FORBIDDEN', 403);
        }
        queryParams($this->c,
            'UPDATE projects SET archived_at = CASE WHEN $2 THEN COALESCE(archived_at, now()) ELSE NULL END,
                                 archived_by = CASE WHEN $2 THEN $3::bigint ELSE NULL END WHERE id = $1',
            [$projectId, $archived ? 't' : 'f', $userId]);
    }

    public function find(int $projectId): array
    {
        $row = fetchOne($this->c, 'SELECT id, name, description, created_by, archived_at FROM projects WHERE id = $1', [$projectId]);
        if ($row === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        return $row;
    }

    /** Đổi tên và mô tả dự án (chỉ Quản lý gọi). */
    public function update(int $projectId, string $name, string $description): void
    {
        queryParams($this->c, 'UPDATE projects SET name = $2, description = $3 WHERE id = $1',
            [$projectId, cleanName($name, 150), mb_substr(trim($description), 0, 2000)]);
    }

    /** Xoá dự án (chỉ người tạo). Phải gõ đúng tên dự án để xác nhận; xoá cả tệp ảnh. */
    public function delete(int $projectId, int $userId, string $confirmName): void
    {
        $p = $this->find($projectId);
        if ((int) $p['created_by'] !== $userId) {
            throw new AppError('OWNER_ONLY', 403);
        }
        if (trim($confirmName) !== $p['name']) {
            throw new AppError('CONFIRM_MISMATCH', 422);
        }
        $this->purge($projectId);
    }

    /** Xoá hẳn dự án cùng mọi tệp ảnh (dùng sau khi đã kiểm tra quyền: người tạo, hoặc quản trị hệ thống). */
    public function purge(int $projectId): void
    {
        $files = array_merge(array_column(fetchAll($this->c,
            'SELECT v.stored_name FROM image_versions v JOIN screens s ON s.id = v.screen_id WHERE s.project_id = $1', [$projectId]), 'stored_name'),
            AttachmentRepository::storedNames($this->c, 'project', $projectId));
        queryParams($this->c, 'DELETE FROM projects WHERE id = $1', [$projectId]);
        foreach ($files as $f) {
            @unlink(ImageStore::path($f));
        }
    }

    public function members(int $projectId): array
    {
        return fetchAll($this->c,
            'SELECT u.id, u.name, u.email, pm.role, pm.created_at AS joined_at,
                    (SELECT COUNT(*) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                       JOIN screens s ON s.id = v.screen_id WHERE s.project_id = pm.project_id AND a.created_by = u.id) AS authored,
                    (SELECT COUNT(*) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                       JOIN screens s ON s.id = v.screen_id WHERE s.project_id = pm.project_id AND a.assignee_id = u.id
                        AND a.status = \'OPEN\') AS assigned_open
               FROM project_members pm JOIN users u ON u.id = pm.user_id
              WHERE pm.project_id = $1
              ORDER BY CASE pm.role WHEN \'ADMIN\' THEN 1 WHEN \'COMMENT\' THEN 2 ELSE 3 END, u.name',
            [$projectId]);
    }

    /**
     * Mời thành viên bằng email (bản 2.2): tạo LỜI MỜI đang chờ, người được mời phải bấm Chấp nhận mới trở thành
     * thành viên. Nếu người đó đã là thành viên thì chỉ đổi quyền. Nếu đã có lời mời đang chờ thì cập nhật quyền
     * trong lời mời và gửi lại thông báo.
     * @return array{status:string,name:string,user_id:int}  status: INVITED | RESENT | ROLE_UPDATED
     */
    public function invite(int $projectId, string $email, string $role, int $byUserId, string $message = ''): array
    {
        $this->assertRole($role);
        $user = fetchOne($this->c, 'SELECT id, name FROM users WHERE email = $1', [strtolower(trim($email))]);
        if ($user === null) {
            throw new AppError('USER_NOT_FOUND', 422);
        }
        $uid = (int) $user['id'];
        $message = mb_substr(trim($message), 0, 300);
        return transaction($this->c, function () use ($projectId, $uid, $user, $role, $byUserId, $message): array {
            if (Access::roleFor($this->c, $uid, $projectId) !== null) {
                $this->changeRole($projectId, $uid, $role);
                return ['status' => 'ROLE_UPDATED', 'name' => $user['name'], 'user_id' => $uid];
            }
            $pending = fetchOne($this->c,
                "SELECT id FROM project_invitations WHERE project_id = $1 AND user_id = $2 AND status = 'PENDING' FOR UPDATE", [$projectId, $uid]);
            if ($pending !== null) {
                queryParams($this->c, 'UPDATE project_invitations SET role = $2, invited_by = $3, message = $4, created_at = now() WHERE id = $1',
                    [(int) $pending['id'], $role, $byUserId, $message]);
                $status = 'RESENT';
            } else {
                queryParams($this->c,
                    'INSERT INTO project_invitations (project_id, user_id, role, invited_by, message) VALUES ($1, $2, $3, $4, $5)',
                    [$projectId, $uid, $role, $byUserId, $message]);
                $status = 'INVITED';
            }
            $name = $this->find($projectId)['name'];
            (new NotificationRepository($this->c))->notifyProject($uid, $byUserId, 'INVITE', $projectId,
                $name . ' · quyền ' . Access::LABELS[$role] . ($message !== '' ? ' — ' . $message : ''));
            return ['status' => $status, 'name' => $user['name'], 'user_id' => $uid];
        });
    }

    /** Lời mời đang chờ của một dự án (hiển thị cho Quản lý). */
    public function pendingInvitations(int $projectId): array
    {
        return fetchAll($this->c,
            "SELECT i.id, i.role, i.created_at, u.id AS user_id, u.name, u.email, ib.name AS invited_by
               FROM project_invitations i JOIN users u ON u.id = i.user_id LEFT JOIN users ib ON ib.id = i.invited_by
              WHERE i.project_id = $1 AND i.status = 'PENDING' ORDER BY i.id", [$projectId]);
    }

    /** Lời mời đang chờ gửi tới một người. */
    public function invitationsFor(int $userId): array
    {
        return fetchAll($this->c,
            "SELECT i.id, i.role, i.message, i.created_at, p.id AS project_id, p.name AS project_name, p.description,
                    ib.id AS invited_by_id, ib.name AS invited_by,
                    (SELECT COUNT(*) FROM screens s WHERE s.project_id = p.id) AS screens,
                    (SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS members
               FROM project_invitations i JOIN projects p ON p.id = i.project_id LEFT JOIN users ib ON ib.id = i.invited_by
              WHERE i.user_id = $1 AND i.status = 'PENDING' ORDER BY i.id DESC", [$userId]);
    }

    /**
     * Người được mời chấp nhận / từ chối. Chỉ chính người được mời, chỉ lời mời đang chờ.
     * Chấp nhận thì thêm vào project_members với quyền ghi trong lời mời. @return int project_id
     */
    public function respondInvitation(int $invitationId, int $userId, bool $accept): int
    {
        return transaction($this->c, function () use ($invitationId, $userId, $accept): int {
            $inv = fetchOne($this->c,
                "SELECT i.id, i.project_id, i.role, i.invited_by, p.name FROM project_invitations i JOIN projects p ON p.id = i.project_id
                  WHERE i.id = $1 AND i.user_id = $2 AND i.status = 'PENDING' FOR UPDATE OF i", [$invitationId, $userId]);
            if ($inv === null) {
                throw new AppError('INVITATION_NOT_FOUND', 404);
            }
            $projectId = (int) $inv['project_id'];
            if ($accept) {
                queryParams($this->c,
                    'INSERT INTO project_members (project_id, user_id, role, added_by) VALUES ($1, $2, $3, $4) ON CONFLICT (project_id, user_id) DO NOTHING',
                    [$projectId, $userId, $inv['role'], $inv['invited_by']]);
            }
            queryParams($this->c, 'UPDATE project_invitations SET status = $2, responded_at = now() WHERE id = $1',
                [$invitationId, $accept ? 'ACCEPTED' : 'DECLINED']);
            if ($inv['invited_by'] !== null) {
                (new NotificationRepository($this->c))->notifyProject((int) $inv['invited_by'], $userId, 'INVITE_REPLY', $projectId,
                    ($accept ? 'Đã chấp nhận' : 'Đã từ chối') . ' lời mời vào ' . $inv['name']);
            }
            return $projectId;
        });
    }

    /** Quản lý huỷ một lời mời đang chờ của dự án mình. */
    public function cancelInvitation(int $projectId, int $invitationId): void
    {
        $r = queryParams($this->c,
            "UPDATE project_invitations SET status = 'CANCELLED', responded_at = now() WHERE id = $1 AND project_id = $2 AND status = 'PENDING'",
            [$invitationId, $projectId]);
        if (pg_affected_rows($r) !== 1) {
            throw new AppError('INVITATION_NOT_FOUND', 404);
        }
        queryParams($this->c, "DELETE FROM notifications WHERE kind = 'INVITE' AND project_id = $1 AND read_at IS NULL
                                 AND user_id = (SELECT user_id FROM project_invitations WHERE id = $2)", [$projectId, $invitationId]);
    }

    public function changeRole(int $projectId, int $userId, string $role): void
    {
        $this->assertRole($role);
        $this->guardOwner($projectId, $userId, $role);
        $result = queryParams($this->c,
            'UPDATE project_members SET role = $3 WHERE project_id = $1 AND user_id = $2', [$projectId, $userId, $role]);
        if (pg_affected_rows($result) !== 1) {
            throw new AppError('NOT_FOUND', 404);
        }
    }

    public function removeMember(int $projectId, int $userId): void
    {
        $this->guardOwner($projectId, $userId, null);
        queryParams($this->c, 'DELETE FROM project_members WHERE project_id = $1 AND user_id = $2', [$projectId, $userId]);
    }

    /** Người tạo dự án luôn giữ quyền ADMIN để dự án không bị "mồ côi". */
    private function guardOwner(int $projectId, int $userId, ?string $newRole): void
    {
        $owner = (int) $this->find($projectId)['created_by'];
        if ($owner === $userId && $newRole !== Access::ADMIN) {
            throw new AppError('OWNER_MUST_STAY_ADMIN', 422);
        }
    }

    private function assertRole(string $role): void
    {
        if (!in_array($role, Access::ROLES, true)) {
            throw new AppError('ROLE_INVALID', 422);
        }
    }
}
