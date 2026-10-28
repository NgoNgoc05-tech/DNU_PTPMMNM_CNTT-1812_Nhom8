<?php

declare(strict_types=1);

/**
 * Thông báo trong ứng dụng (bản 2.1).
 *  - MENTION : ai đó viết "@Tên" trong nhận xét/trả lời.
 *  - ASSIGNED: được giao làm người phụ trách một góp ý.
 *  - REPLY   : có trả lời mới trong góp ý mình tạo hoặc mình phụ trách.
 *  - STATUS  : góp ý mình tạo được đánh dấu đã xử lý / mở lại.
 * Người thực hiện thao tác không tự nhận thông báo; mỗi người chỉ nhận một thông báo cho một thao tác.
 */
final class NotificationRepository
{
    public const KINDS = ['MENTION' => 'nhắc tới bạn', 'ASSIGNED' => 'giao việc cho bạn', 'REPLY' => 'trả lời', 'STATUS' => 'đổi trạng thái'];

    public function __construct(private PgSql\Connection $c)
    {
    }

    /** Tên không kèm phần trong ngoặc: "Nguyễn Thu Trang (Thiết kế)" -> "Nguyễn Thu Trang" (giống @ trong giao diện). */
    public static function mentionName(string $name): string
    {
        return trim((string) preg_replace('/\s*\(.*?\)\s*/u', ' ', $name));
    }

    /** Thành viên của dự án được nhắc tên trong $text bằng "@Tên". @return int[] */
    public function mentionedMembers(int $projectId, string $text): array
    {
        if (!str_contains($text, '@')) {
            return [];
        }
        $members = fetchAll($this->c,
            'SELECT u.id, u.name FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.project_id = $1', [$projectId]);
        $ids = [];
        foreach ($members as $m) {
            $n = self::mentionName($m['name']);
            if ($n !== '' && mb_stripos($text, '@' . $n) !== false) {
                $ids[] = (int) $m['id'];
            }
        }
        return $ids;
    }

    /**
     * Gửi thông báo cho danh sách người nhận (bỏ người thực hiện, bỏ trùng, chỉ người còn là thành viên dự án).
     * @param int[] $userIds
     * @param int[] $skip người đã nhận thông báo khác cho cùng thao tác
     * @return int[] người đã được gửi
     */
    public function notify(array $userIds, int $actorId, string $kind, int $annotationId, string $body, array $skip = []): array
    {
        if (!isset(self::KINDS[$kind])) {
            throw new AppError('NOTIFICATION_KIND_INVALID', 422);
        }
        $sent = [];
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid <= 0 || $uid === $actorId || in_array($uid, $skip, true)) {
                continue;
            }
            $member = fetchOne($this->c,
                'SELECT 1 FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                   JOIN project_members pm ON pm.project_id = s.project_id AND pm.user_id = $2 WHERE a.id = $1', [$annotationId, $uid]);
            if ($member === null) {
                continue;
            }
            queryParams($this->c,
                'INSERT INTO notifications (user_id, actor_id, kind, annotation_id, body) VALUES ($1, $2, $3, $4, $5)',
                [$uid, $actorId, $kind, $annotationId, mb_substr($body, 0, 300)]);
            $sent[] = $uid;
        }
        return $sent;
    }

    public function unreadCount(int $userId): int
    {
        return (int) fetchOne($this->c, 'SELECT COUNT(*) AS n FROM notifications WHERE user_id = $1 AND read_at IS NULL', [$userId])['n'];
    }

    public function listFor(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $rows = fetchAll($this->c,
            'SELECT n.id, n.kind, n.body, n.created_at, n.read_at, n.annotation_id, u.id AS actor_id, u.name AS actor,
                    a.seq_no, v.id AS version_id, v.version_no, s.id AS screen_id, s.name AS screen_name, p.name AS project_name
               FROM notifications n
               LEFT JOIN users u ON u.id = n.actor_id
               LEFT JOIN annotations a ON a.id = n.annotation_id
               LEFT JOIN image_versions v ON v.id = a.image_version_id
               LEFT JOIN screens s ON s.id = v.screen_id
               LEFT JOIN projects p ON p.id = s.project_id
              WHERE n.user_id = $1' . ($unreadOnly ? ' AND n.read_at IS NULL' : '') . '
              ORDER BY n.id DESC LIMIT $2', [$userId, $limit]);
        return array_map(fn ($r) => [
            'id' => (int) $r['id'], 'kind' => $r['kind'], 'label' => self::KINDS[$r['kind']], 'body' => $r['body'],
            'created_at' => $r['created_at'], 'read' => $r['read_at'] !== null,
            'actor' => $r['actor'], 'actor_id' => $r['actor_id'] === null ? 0 : (int) $r['actor_id'],
            'link' => $r['screen_id'] === null ? null
                : '/screen.php?id=' . (int) $r['screen_id'] . '&v=' . (int) $r['version_id'] . '&a=' . (int) $r['annotation_id'],
            'where' => $r['screen_id'] === null ? '' : $r['project_name'] . ' · ' . $r['screen_name'] . ' v' . $r['version_no'] . ' #' . $r['seq_no'],
        ], $rows);
    }

    /** Đánh dấu đã đọc: một thông báo ($id > 0) hoặc tất cả ($id = 0). Chỉ thông báo của chính người dùng. */
    public function markRead(int $userId, int $id = 0): int
    {
        $result = $id > 0
            ? queryParams($this->c, 'UPDATE notifications SET read_at = now() WHERE user_id = $1 AND id = $2 AND read_at IS NULL', [$userId, $id])
            : queryParams($this->c, 'UPDATE notifications SET read_at = now() WHERE user_id = $1 AND read_at IS NULL', [$userId]);
        return pg_affected_rows($result);
    }
}
