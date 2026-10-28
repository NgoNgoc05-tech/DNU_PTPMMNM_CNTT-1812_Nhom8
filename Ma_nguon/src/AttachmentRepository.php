<?php

declare(strict_types=1);

/**
 * Ảnh đính kèm trong trả lời (bản 2.3): ảnh chụp màn hình dán bằng Ctrl+V hoặc chọn tệp.
 * Dùng chung kiểm tra của ImageStore (chỉ PNG/JPEG thật, ≤ 5 MB); tệp lưu ngoài thư mục public,
 * chỉ phát qua /attachment.php sau khi kiểm tra người xem thuộc dự án.
 */
final class AttachmentRepository
{
    public const MAX_PER_COMMENT = 4;

    public function __construct(private PgSql\Connection $c)
    {
    }

    /** Chỉ tác giả của nhận xét được đính kèm ảnh vào nhận xét đó. */
    public function add(int $commentId, array $image, int $userId): array
    {
        return transaction($this->c, function () use ($commentId, $image, $userId): array {
            $comment = fetchOne($this->c, 'SELECT id, author_id FROM comments WHERE id = $1 FOR UPDATE', [$commentId]);
            if ($comment === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            if ((int) $comment['author_id'] !== $userId) {
                throw new AppError('FORBIDDEN', 403);
            }
            $n = (int) fetchOne($this->c, 'SELECT COUNT(*) AS n FROM comment_attachments WHERE comment_id = $1', [$commentId])['n'];
            if ($n >= self::MAX_PER_COMMENT) {
                throw new AppError('ATTACHMENT_LIMIT', 422);
            }
            $row = fetchOne($this->c,
                'INSERT INTO comment_attachments (comment_id, stored_name, original_name, mime_type, width, height, byte_size, uploaded_by)
                 VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id',
                [$commentId, $image['stored_name'], $image['original_name'], $image['mime'], $image['width'], $image['height'],
                 $image['byte_size'], $userId]);
            return self::shape(['id' => $row['id'], 'original_name' => $image['original_name'], 'width' => $image['width'], 'height' => $image['height']]);
        });
    }

    /** @return array{stored_name:string,mime_type:string,project_id:int} */
    public function find(int $id): array
    {
        $row = fetchOne($this->c,
            'SELECT ca.stored_name, ca.mime_type, s.project_id FROM comment_attachments ca
               JOIN comments c ON c.id = ca.comment_id JOIN annotations a ON a.id = c.annotation_id
               JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
              WHERE ca.id = $1', [$id]);
        if ($row === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        $row['project_id'] = (int) $row['project_id'];
        return $row;
    }

    /** @param int[] $commentIds @return array<int, list<array>> theo comment_id */
    public function forComments(array $commentIds): array
    {
        if ($commentIds === []) {
            return [];
        }
        $rows = fetchAll($this->c,
            'SELECT id, comment_id, original_name, width, height FROM comment_attachments WHERE comment_id = ANY($1::bigint[]) ORDER BY id',
            ['{' . implode(',', array_map('intval', $commentIds)) . '}']);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['comment_id']][] = self::shape($r);
        }
        return $out;
    }

    /**
     * Tên tệp đính kèm thuộc một phiên bản / màn hình / dự án, để xoá tệp sau khi xoá dữ liệu.
     * @param 'version'|'screen'|'project' $scope
     * @return string[]
     */
    public static function storedNames(PgSql\Connection $c, string $scope, int $id): array
    {
        $where = ['version' => 'v.id = $1', 'screen' => 'v.screen_id = $1', 'project' => 's.project_id = $1'][$scope];
        return array_column(fetchAll($c,
            "SELECT ca.stored_name FROM comment_attachments ca JOIN comments cm ON cm.id = ca.comment_id
               JOIN annotations a ON a.id = cm.annotation_id JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id WHERE $where", [$id]), 'stored_name');
    }

    private static function shape(array $r): array
    {
        return ['id' => (int) $r['id'], 'name' => $r['original_name'], 'width' => (int) $r['width'], 'height' => (int) $r['height'],
            'url' => '/attachment.php?id=' . (int) $r['id']];
    }
}
