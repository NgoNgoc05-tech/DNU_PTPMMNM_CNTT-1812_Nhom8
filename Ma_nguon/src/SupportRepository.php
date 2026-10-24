<?php

declare(strict_types=1);

/** Yêu cầu hỗ trợ khách hàng (bản 2.1). Người dùng chỉ thấy yêu cầu của chính mình. */
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
        return (int) fetchOne($this->c,
            'INSERT INTO support_tickets (user_id, category, subject, message) VALUES ($1, $2, $3, $4) RETURNING id',
            [$userId, $category, $subject, $message])['id'];
    }

    public function listFor(int $userId): array
    {
        return fetchAll($this->c,
            'SELECT id, category, subject, message, status, reply, created_at, updated_at FROM support_tickets
              WHERE user_id = $1 ORDER BY id DESC LIMIT 50', [$userId]);
    }

    /** Bộ phận hỗ trợ trả lời (dùng từ dòng lệnh: php tools/support.php). */
    public function answer(int $ticketId, string $reply, string $status = 'ANSWERED'): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new AppError('STATUS_INVALID', 422);
        }
        queryParams($this->c, 'UPDATE support_tickets SET reply = $2, status = $3, updated_at = now() WHERE id = $1',
            [$ticketId, trim($reply), $status]);
    }

    public function close(int $userId, int $ticketId): void
    {
        queryParams($this->c, "UPDATE support_tickets SET status = 'CLOSED', updated_at = now() WHERE id = $1 AND user_id = $2",
            [$ticketId, $userId]);
    }
}
