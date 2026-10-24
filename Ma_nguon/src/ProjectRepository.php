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
            "SELECT p.id, p.name, p.description, pm.role, p.created_at,
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
               CROSS JOIN LATERAL (SELECT COUNT(*) FILTER (WHERE a.status = 'OPEN') AS open_items, COUNT(*) AS total_items
                                     FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                                     JOIN screens s ON s.id = v.screen_id WHERE s.project_id = p.id) st
              WHERE pm.user_id = $1
              ORDER BY p.id", [$userId]);
    }

    public function find(int $projectId): array
    {
        $row = fetchOne($this->c, 'SELECT id, name, description, created_by FROM projects WHERE id = $1', [$projectId]);
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

    /** Mời thành viên đã có tài khoản bằng email. Nếu đã là thành viên thì cập nhật quyền. */
    public function addMember(int $projectId, string $email, string $role, int $byUserId): array
    {
        $this->assertRole($role);
        $user = fetchOne($this->c, 'SELECT id, name FROM users WHERE email = $1', [strtolower(trim($email))]);
        if ($user === null) {
            throw new AppError('USER_NOT_FOUND', 422);
        }
        $this->guardOwner($projectId, (int) $user['id'], $role);
        queryParams($this->c,
            'INSERT INTO project_members (project_id, user_id, role, added_by) VALUES ($1, $2, $3, $4)
             ON CONFLICT (project_id, user_id) DO UPDATE SET role = EXCLUDED.role',
            [$projectId, (int) $user['id'], $role, $byUserId]);
        return $user;
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
