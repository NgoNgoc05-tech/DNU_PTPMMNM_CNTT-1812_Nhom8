<?php

declare(strict_types=1);

/**
 * Phân quyền theo dự án — luôn kiểm tra ở MÁY CHỦ, không tin giao diện.
 *
 * | Hành động                                  | VIEW | COMMENT | ADMIN |
 * |--------------------------------------------|:----:|:-------:|:-----:|
 * | Xem ảnh, góp ý, lịch sử; xuất danh sách     |  ✓   |    ✓    |   ✓   |
 * | Tạo điểm/vùng, trả lời, sửa nội dung của mình|      |    ✓    |   ✓   |
 * | Đánh dấu đã xử lý / mở lại                  |      |    ✓    |   ✓   |
 * | Tạo màn hình, tải phiên bản ảnh, đổi tên     |      |         |   ✓   |
 * | Mời thành viên, đổi quyền, xoá thành viên    |      |         |   ✓   |
 */
final class Access
{
    public const VIEW = 'VIEW';
    public const COMMENT = 'COMMENT';
    public const ADMIN = 'ADMIN';
    public const ROLES = [self::VIEW, self::COMMENT, self::ADMIN];

    private const RANK = [self::VIEW => 1, self::COMMENT => 2, self::ADMIN => 3];

    public const LABELS = [
        self::VIEW => 'Chỉ xem',
        self::COMMENT => 'Góp ý',
        self::ADMIN => 'Quản lý',
    ];

    /** Quyền $role có đủ để làm việc cần $required hay không (hàm thuần, dễ kiểm thử). */
    public static function allows(?string $role, string $required): bool
    {
        if ($role === null || !isset(self::RANK[$role], self::RANK[$required])) {
            return false;
        }
        return self::RANK[$role] >= self::RANK[$required];
    }

    public static function roleFor(PgSql\Connection $c, int $userId, int $projectId): ?string
    {
        $row = fetchOne($c, 'SELECT role FROM project_members WHERE project_id = $1 AND user_id = $2', [$projectId, $userId]);
        return $row['role'] ?? null;
    }

    /**
     * Ném lỗi nếu không đủ quyền. Người không phải thành viên nhận NOT_FOUND (404) để
     * không tiết lộ dự án của nhóm khác có tồn tại hay không.
     */
    public static function require(PgSql\Connection $c, int $userId, int $projectId, string $required): string
    {
        $role = self::roleFor($c, $userId, $projectId);
        if ($role === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        if (!self::allows($role, $required)) {
            throw new AppError('FORBIDDEN', 403);
        }
        return $role;
    }

    // Tìm dự án sở hữu một đối tượng con, để mọi URL đều kiểm tra quyền theo dự án.
    public static function projectOfScreen(PgSql\Connection $c, int $screenId): int
    {
        return self::idOrFail(fetchOne($c, 'SELECT project_id FROM screens WHERE id = $1', [$screenId]));
    }

    public static function projectOfVersion(PgSql\Connection $c, int $versionId): int
    {
        return self::idOrFail(fetchOne($c,
            'SELECT s.project_id FROM image_versions v JOIN screens s ON s.id = v.screen_id WHERE v.id = $1',
            [$versionId]));
    }

    public static function projectOfAnnotation(PgSql\Connection $c, int $annotationId): int
    {
        return self::idOrFail(fetchOne($c,
            'SELECT s.project_id FROM annotations a
               JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id
              WHERE a.id = $1', [$annotationId]));
    }

    public static function projectOfComment(PgSql\Connection $c, int $commentId): int
    {
        return self::idOrFail(fetchOne($c,
            'SELECT s.project_id FROM comments cm
               JOIN annotations a ON a.id = cm.annotation_id
               JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id
              WHERE cm.id = $1', [$commentId]));
    }

    private static function idOrFail(?array $row): int
    {
        if ($row === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        return (int) $row['project_id'];
    }
}
