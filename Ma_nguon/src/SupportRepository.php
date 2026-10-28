<?php

declare(strict_types=1);

/**
 * Yêu cầu hỗ trợ khách hàng (bản 2.1, 2.2).
 * Người dùng chỉ thấy yêu cầu của chính mình (/help.php). Tài khoản bộ phận hỗ trợ (users.is_support)
 * nhận thông báo khi có yêu cầu mới và trả lời ở /support_admin.php; người gửi nhận thông báo khi được trả lời.
 */
final class SupportRepository
{
    public const CATEGORIES = ['QUESTION' => 'Hỏi cách dùng', 'BUG' => 'Báo lỗi', 'IDEA' => 'Góp ý tính năng', 'ACCOUNT' => 'Tài khoản'];
    public const STATUSES = ['OPEN' => 'Đang chờ', 'ANSWERED' => 'Đã trả lời', 'CLOSED' => 'Đã đóng'];

    public function __construct(private PgSql\Connection $c)
    {
    }

    public function create(int $userId, string $category, string $subject, string $message): int
    {
        $subject = trim($subject);
        $message = trim(str_replace("\r\n", "\n", $message));
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'QUESTION';
        }
        if ($subject === '' || mb_strlen($subject) > 150 || $message === '' || mb_strlen($message) > 4000) {
            throw new AppError('TICKET_INVALID', 422);
        }
        $id = (int) fetchOne($this->c,
            'INSERT INTO support_tickets (user_id, category, subject, message) VALUES ($1, $2, $3, $4) RETURNING id',
            [$userId, $category, $subject, $message])['id'];
        // Báo cho mọi tài khoản thuộc bộ phận hỗ trợ.
        $n = new NotificationRepository($this->c);
        foreach (fetchAll($this->c, 'SELECT id FROM users WHERE (is_support OR is_admin) AND locked_at IS NULL') as $staff) {
            $n->notifyTicket((int) $staff['id'], $userId, 'TICKET', $id, self::CATEGORIES[$category] . ': ' . $subject);
        }
        return $id;
    }

    public static function isStaff(PgSql\Connection $c, int $userId): bool
    {
        return (fetchOne($c, 'SELECT (is_support OR is_admin) AS staff FROM users WHERE id = $1', [$userId])['staff'] ?? 'f') === 't';
    }

    /** Danh sách cho bộ phận hỗ trợ, lọc theo trạng thái ('' = tất cả). */
    public function listAll(string $status = ''): array
    {
        $params = [];
        $where = '';
        if (isset(self::STATUSES[$status])) {
            $params[] = $status;
            $where = 'WHERE t.status = $1';
        }
        return fetchAll($this->c,
            "SELECT t.*, u.name AS user_name, u.email AS user_email, h.name AS handled_by_name
               FROM support_tickets t JOIN users u ON u.id = t.user_id LEFT JOIN users h ON h.id = t.handled_by
               $where ORDER BY CASE t.status WHEN 'OPEN' THEN 0 WHEN 'ANSWERED' THEN 1 ELSE 2 END, t.id DESC LIMIT 200", $params);
    }

    public function counts(): array
    {
        $rows = fetchAll($this->c, 'SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status');
        return array_map('intval', array_column($rows, 'n', 'status')) + ['OPEN' => 0, 'ANSWERED' => 0, 'CLOSED' => 0];
    }

    public function listFor(int $userId): array
    {
        return fetchAll($this->c,
            'SELECT t.id, t.category, t.subject, t.message, t.status, t.reply, t.created_at, t.updated_at, h.name AS handled_by_name
               FROM support_tickets t LEFT JOIN users h ON h.id = t.handled_by
              WHERE t.user_id = $1 ORDER BY t.id DESC LIMIT 50', [$userId]);
    }

    /**
     * Bộ phận hỗ trợ trả lời / đổi trạng thái (trang /support_admin.php hoặc dòng lệnh php tools/support.php).
     * Người gửi yêu cầu nhận thông báo khi có câu trả lời mới.
     */
    public function answer(int $ticketId, string $reply, string $status = 'ANSWERED', ?int $staffId = null): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new AppError('STATUS_INVALID', 422);
        }
        $reply = trim(str_replace("\r\n", "\n", $reply));
        if (mb_strlen($reply) > 4000) {
            throw new AppError('TICKET_INVALID', 422);
        }
        $t = fetchOne($this->c, 'SELECT id, user_id, subject, reply FROM support_tickets WHERE id = $1', [$ticketId]);
        if ($t === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        queryParams($this->c, 'UPDATE support_tickets SET reply = $2, status = $3, handled_by = COALESCE($4, handled_by), updated_at = now() WHERE id = $1',
            [$ticketId, $reply, $status, $staffId]);
        if ($reply !== '' && $reply !== $t['reply']) {
            (new NotificationRepository($this->c))->notifyTicket((int) $t['user_id'], (int) ($staffId ?? 0), 'TICKET_REPLY', $ticketId,
                $t['subject'] . ': ' . $reply);
        }
    }

    public function close(int $userId, int $ticketId): void
    {
        queryParams($this->c, "UPDATE support_tickets SET status = 'CLOSED', updated_at = now() WHERE id = $1 AND user_id = $2",
            [$ticketId, $userId]);
    }
}
