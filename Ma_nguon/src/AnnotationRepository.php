<?php

declare(strict_types=1);

/**
 * Góp ý = annotation (điểm/vùng trên MỘT phiên bản ảnh) + nhận xét gốc + các trả lời
 * + thuộc tính (ưu tiên, nhãn, người phụ trách, hạn, ghi chú xử lý) + lịch sử.
 *
 * Kiểm soát cập nhật đồng thời (YÊU CẦU KỸ THUẬT 2):
 *  - Trả lời và thả cảm xúc là thao tác INSERT, hai người làm cùng lúc không ghi đè nhau.
 *  - Sửa nhận xét, đổi trạng thái và sửa thuộc tính/ghi chú dùng khoá lạc quan (lock_version):
 *    người gửi phải nêu lock_version mình đã đọc. Nếu bản ghi đã bị người khác thay đổi,
 *    máy chủ trả CONFLICT (409) kèm giá trị hiện tại thay vì âm thầm ghi đè.
 *  - Mọi thay đổi được ghi vào annotation_events (nhật ký hoạt động) trong cùng giao dịch.
 */
final class AnnotationRepository
{
    public const PRIORITIES = ['LOW' => 'Thấp', 'MEDIUM' => 'Trung bình', 'HIGH' => 'Cao', 'URGENT' => 'Khẩn cấp'];
    public const CATEGORIES = [
        'LAYOUT' => 'Bố cục', 'COLOR' => 'Màu sắc', 'TYPO' => 'Chữ & phông', 'CONTENT' => 'Nội dung',
        'UX' => 'Trải nghiệm', 'A11Y' => 'Trợ năng', 'OTHER' => 'Khác',
    ];
    public const STATUSES = ['OPEN' => 'Chưa xử lý', 'RESOLVED' => 'Đã xử lý'];
    public const REACTIONS = ['LIKE' => '👍', 'HEART' => '❤️', 'PARTY' => '🎉', 'EYES' => '👀'];
    public const NOTE_MAX = 4000;

    public function __construct(private PgSql\Connection $c)
    {
    }

    /**
     * Tạo dấu + nhận xét gốc + dòng lịch sử "mở" trong một giao dịch.
     * $meta (không bắt buộc): priority, category, assignee_id, due_date.
     */
    public function create(int $versionId, string $shape, mixed $x, mixed $y, mixed $w, mixed $h, string $body, int $userId, array $meta = []): array
    {
        $body = cleanBody($body);
        return transaction($this->c, function () use ($versionId, $shape, $x, $y, $w, $h, $body, $userId, $meta): array {
            // Khoá phiên bản ảnh để hai người tạo dấu cùng lúc không trùng số thứ tự.
            $version = fetchOne($this->c,
                'SELECT v.id, v.width, v.height, s.project_id FROM image_versions v JOIN screens s ON s.id = v.screen_id
                  WHERE v.id = $1 FOR UPDATE OF v', [$versionId]);
            if ($version === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            $pos = Coordinates::validate($shape, $x, $y, $w, $h, (int) $version['width'], (int) $version['height']);
            $m = $this->cleanMeta($meta, (int) $version['project_id']);
            $row = fetchOne($this->c,
                'INSERT INTO annotations (image_version_id, seq_no, shape, x, y, w, h, created_by, priority, category, assignee_id, due_date)
                 VALUES ($1, (SELECT COALESCE(MAX(seq_no), 0) + 1 FROM annotations WHERE image_version_id = $1),
                         $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
                 RETURNING id',
                [$versionId, $pos['shape'], $pos['x'], $pos['y'], $pos['w'], $pos['h'], $userId,
                 $m['priority'] ?? 'MEDIUM', $m['category'] ?? 'OTHER', $m['assignee_id'] ?? null, $m['due_date'] ?? null]);
            $annotationId = (int) $row['id'];
            queryParams($this->c, 'INSERT INTO comments (annotation_id, author_id, body) VALUES ($1, $2, $3)',
                [$annotationId, $userId, $body]);
            queryParams($this->c,
                "INSERT INTO status_history (annotation_id, from_status, to_status, changed_by, checked_version_id, note)
                 VALUES ($1, NULL, 'OPEN', $2, $3, 'Tạo góp ý')", [$annotationId, $userId, $versionId]);
            $this->event($annotationId, $userId, 'CREATED', '', $pos['shape'], mb_substr($body, 0, 200));
            $n = new NotificationRepository($this->c);
            $sent = isset($m['assignee_id']) ? $n->notify([$m['assignee_id']], $userId, 'ASSIGNED', $annotationId, $body) : [];
            $n->notify($n->mentionedMembers((int) $version['project_id'], $body), $userId, 'MENTION', $annotationId, $body, $sent);
            return $this->find($annotationId);
        });
    }

    public function reply(int $annotationId, string $body, int $userId): array
    {
        $body = cleanBody($body);
        return transaction($this->c, function () use ($annotationId, $body, $userId): array {
            $root = fetchOne($this->c,
                'SELECT c.id, a.created_by, a.assignee_id, s.project_id FROM comments c JOIN annotations a ON a.id = c.annotation_id
                   JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                  WHERE c.annotation_id = $1 AND c.parent_id IS NULL', [$annotationId]);
            if ($root === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            $row = fetchOne($this->c,
                'INSERT INTO comments (annotation_id, parent_id, author_id, body) VALUES ($1, $2, $3, $4) RETURNING id',
                [$annotationId, (int) $root['id'], $userId, $body]);
            $this->event($annotationId, $userId, 'REPLIED', '', '', mb_substr($body, 0, 200));
            $n = new NotificationRepository($this->c);
            $sent = $n->notify($n->mentionedMembers((int) $root['project_id'], $body), $userId, 'MENTION', $annotationId, $body);
            $n->notify([(int) $root['created_by'], (int) ($root['assignee_id'] ?? 0)], $userId, 'REPLY', $annotationId, $body, $sent);
            return ['id' => (int) $row['id']];
        });
    }

    /**
     * Chỉ tác giả được sửa nội dung của mình. $expectedLock là lock_version lúc người
     * dùng mở ô sửa; khác với hiện tại nghĩa là có người đã sửa trước -> CONFLICT.
     */
    public function editComment(int $commentId, string $body, int $expectedLock, int $userId): array
    {
        $body = cleanBody($body);
        return transaction($this->c, function () use ($commentId, $body, $expectedLock, $userId): array {
            $current = fetchOne($this->c, 'SELECT id, annotation_id, author_id, body, lock_version FROM comments WHERE id = $1 FOR UPDATE', [$commentId]);
            if ($current === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            if ((int) $current['author_id'] !== $userId) {
                throw new AppError('FORBIDDEN', 403);
            }
            if ((int) $current['lock_version'] !== $expectedLock) {
                throw new AppError('CONFLICT', 409, [
                    'current_body' => $current['body'],
                    'current_lock' => (int) $current['lock_version'],
                ]);
            }
            if ($current['body'] === $body) {
                return ['id' => $commentId, 'lock_version' => $expectedLock];
            }
            queryParams($this->c, 'INSERT INTO comment_revisions (comment_id, old_body, edited_by) VALUES ($1, $2, $3)',
                [$commentId, $current['body'], $userId]);
            $row = fetchOne($this->c,
                'UPDATE comments SET body = $2, updated_at = now(), lock_version = lock_version + 1
                  WHERE id = $1 AND lock_version = $3 RETURNING lock_version',
                [$commentId, $body, $expectedLock]);
            $this->event((int) $current['annotation_id'], $userId, 'EDITED', mb_substr($current['body'], 0, 200), mb_substr($body, 0, 200));
            return ['id' => $commentId, 'lock_version' => (int) $row['lock_version']];
        });
    }

    /**
     * Đánh dấu đã xử lý (RESOLVED) hoặc mở lại (OPEN). Mỗi lần đổi ghi một dòng
     * status_history; $checkedVersionId ghi nhận phiên bản ảnh đã dùng để kiểm tra
     * (phải thuộc cùng màn hình với góp ý).
     */
    public function changeStatus(int $annotationId, string $to, int $expectedLock, int $userId, ?int $checkedVersionId = null, string $note = ''): array
    {
        if (!isset(self::STATUSES[$to])) {
            throw new AppError('STATUS_INVALID', 422);
        }
        $note = mb_substr(trim($note), 0, 500);
        return transaction($this->c, function () use ($annotationId, $to, $expectedLock, $userId, $checkedVersionId, $note): array {
            $current = fetchOne($this->c,
                'SELECT a.id, a.status, a.lock_version, v.screen_id, a.image_version_id
                   FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                  WHERE a.id = $1 FOR UPDATE OF a', [$annotationId]);
            if ($current === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            if ((int) $current['lock_version'] !== $expectedLock) {
                throw new AppError('CONFLICT', 409, [
                    'current_status' => $current['status'],
                    'current_lock' => (int) $current['lock_version'],
                ]);
            }
            if ($current['status'] === $to) {
                throw new AppError('STATUS_UNCHANGED', 422);
            }
            $checked = $checkedVersionId ?: (int) $current['image_version_id'];
            $sameScreen = fetchOne($this->c, 'SELECT version_no FROM image_versions WHERE id = $1 AND screen_id = $2',
                [$checked, (int) $current['screen_id']]);
            if ($sameScreen === null) {
                throw new AppError('CHECKED_VERSION_INVALID', 422);
            }
            $row = fetchOne($this->c,
                'UPDATE annotations SET status = $2, updated_at = now(), lock_version = lock_version + 1
                  WHERE id = $1 AND lock_version = $3 RETURNING lock_version',
                [$annotationId, $to, $expectedLock]);
            queryParams($this->c,
                'INSERT INTO status_history (annotation_id, from_status, to_status, changed_by, checked_version_id, note)
                 VALUES ($1, $2, $3, $4, $5, $6)',
                [$annotationId, $current['status'], $to, $userId, $checked, $note]);
            $this->event($annotationId, $userId, 'STATUS', $current['status'], $to,
                'v' . (int) $sameScreen['version_no'] . ($note !== '' ? ' — ' . $note : ''));
            $author = (int) fetchOne($this->c, 'SELECT created_by FROM annotations WHERE id = $1', [$annotationId])['created_by'];
            (new NotificationRepository($this->c))->notify([$author], $userId, 'STATUS', $annotationId,
                ($to === 'RESOLVED' ? 'Đã xử lý (kiểm tra trên v' . (int) $sameScreen['version_no'] . ')' : 'Mở lại') . ($note !== '' ? ': ' . $note : ''));
            return ['id' => $annotationId, 'status' => $to, 'lock_version' => (int) $row['lock_version']];
        });
    }

    /**
     * Sửa thuộc tính và GHI CHÚ XỬ LÝ của góp ý: priority, category, assignee_id, due_date, note.
     * Chỉ các khoá có trong $changes mới được cập nhật. Dùng chung lock_version với trạng thái.
     */
    public function update(int $annotationId, array $changes, int $expectedLock, int $userId): array
    {
        return transaction($this->c, function () use ($annotationId, $changes, $expectedLock, $userId): array {
            $cur = fetchOne($this->c,
                'SELECT a.id, a.priority, a.category, a.assignee_id, a.due_date, a.note, a.lock_version, s.project_id
                   FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
                  WHERE a.id = $1 FOR UPDATE OF a', [$annotationId]);
            if ($cur === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            if ((int) $cur['lock_version'] !== $expectedLock) {
                throw new AppError('CONFLICT', 409, [
                    'current_lock' => (int) $cur['lock_version'],
                    'current' => [
                        'priority' => $cur['priority'], 'category' => $cur['category'],
                        'assignee_id' => $cur['assignee_id'] === null ? null : (int) $cur['assignee_id'],
                        'due_date' => $cur['due_date'], 'note' => $cur['note'],
                    ],
                ]);
            }
            $new = $this->cleanMeta($changes, (int) $cur['project_id']);
            if (array_key_exists('note', $changes)) {
                $note = trim(str_replace("\r\n", "\n", (string) $changes['note']));
                if (mb_strlen($note) > self::NOTE_MAX) {
                    throw new AppError('NOTE_TOO_LONG', 422);
                }
                $new['note'] = $note;
            }
            $kinds = ['priority' => 'PRIORITY', 'category' => 'CATEGORY', 'assignee_id' => 'ASSIGNEE', 'due_date' => 'DUE', 'note' => 'NOTE'];
            $diff = [];
            foreach ($new as $field => $value) {
                $old = $cur[$field];
                if ((string) ($old ?? '') !== (string) ($value ?? '')) {
                    $diff[$field] = [$old, $value];
                }
            }
            if ($diff === []) {
                return $this->find($annotationId);
            }
            $sets = [];
            $params = [$annotationId, $expectedLock];
            foreach ($diff as $field => [, $value]) {
                $params[] = $value;
                $sets[] = "$field = \$" . count($params);
            }
            if (isset($diff['note'])) {
                $params[] = $userId;
                $sets[] = 'note_updated_by = $' . count($params) . ', note_updated_at = now()';
            }
            $row = fetchOne($this->c,
                'UPDATE annotations SET ' . implode(', ', $sets) . ', updated_at = now(), lock_version = lock_version + 1
                  WHERE id = $1 AND lock_version = $2 RETURNING lock_version', $params);
            if ($row === null) {
                throw new AppError('CONFLICT', 409, ['current_lock' => $expectedLock + 1]);
            }
            foreach ($diff as $field => [$old, $value]) {
                $this->event($annotationId, $userId, $kinds[$field],
                    $this->display($field, $old), $this->display($field, $value));
            }
            if (isset($diff['assignee_id']) && $diff['assignee_id'][1] !== null) {
                $body = (string) fetchOne($this->c, 'SELECT body FROM comments WHERE annotation_id = $1 AND parent_id IS NULL', [$annotationId])['body'];
                (new NotificationRepository($this->c))->notify([(int) $diff['assignee_id'][1]], $userId, 'ASSIGNED', $annotationId, $body);
            }
            return $this->find($annotationId);
        });
    }

    /** Bật/tắt một cảm xúc của người dùng trên một nhận xét. Trả về tổng hợp cảm xúc của nhận xét đó. */
    public function toggleReaction(int $commentId, string $kind, int $userId): array
    {
        if (!isset(self::REACTIONS[$kind])) {
            throw new AppError('REACTION_INVALID', 422);
        }
        $deleted = queryParams($this->c,
            'DELETE FROM comment_reactions WHERE comment_id = $1 AND user_id = $2 AND kind = $3', [$commentId, $userId, $kind]);
        if (pg_affected_rows($deleted) === 0) {
            queryParams($this->c,
                'INSERT INTO comment_reactions (comment_id, user_id, kind) VALUES ($1, $2, $3) ON CONFLICT DO NOTHING',
                [$commentId, $userId, $kind]);
        }
        return ['comment_id' => $commentId, 'reactions' => $this->reactionsFor([$commentId], $userId)[$commentId] ?? []];
    }

    public function find(int $annotationId, int $viewerId = 0): array
    {
        $rows = $this->query('a.id = $1', [$annotationId], true, $viewerId);
        if ($rows === []) {
            throw new AppError('NOT_FOUND', 404);
        }
        return $rows[0];
    }

    /** Danh sách góp ý của MỘT phiên bản ảnh (chỉ phiên bản đó), có lọc trạng thái/người góp ý. */
    public function listForVersion(int $versionId, string $status = '', int $authorId = 0, int $viewerId = 0): array
    {
        [$where, $params] = $this->filters('a.image_version_id = $1', [$versionId], $status, $authorId, '', []);
        return $this->query($where, $params, true, $viewerId);
    }

    /**
     * Tìm góp ý trong toàn dự án: lọc trạng thái, người góp ý, màn hình, từ khoá (nội dung,
     * trả lời và ghi chú) và các bộ lọc mở rộng trong $more: priority, category,
     * assignee (id; -1 = chưa giao), overdue (true), sort (seq|newest|priority|due).
     */
    public function search(int $projectId, string $status = '', int $authorId = 0, string $q = '', int $screenId = 0, array $more = []): array
    {
        $where = 's.project_id = $1';
        $params = [$projectId];
        if ($screenId > 0) {
            $params[] = $screenId;
            $where .= ' AND s.id = $' . count($params);
        }
        [$where, $params] = $this->filters($where, $params, $status, $authorId, $q, $more);
        $order = match ($more['sort'] ?? '') {
            'newest' => 'a.created_at DESC, a.id DESC',
            'priority' => "CASE a.priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 ELSE 4 END, a.created_at DESC",
            'due' => 'a.due_date ASC NULLS LAST, a.id',
            default => 's.id, v.version_no, a.seq_no',
        };
        return $this->query($where, $params, false, 0, $order);
    }

    /** Thống kê cho bảng tổng quan dự án. */
    public function stats(int $projectId): array
    {
        $row = fetchOne($this->c,
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE a.status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE a.status = 'RESOLVED') AS resolved,
                    COUNT(*) FILTER (WHERE a.status = 'OPEN' AND a.due_date < CURRENT_DATE) AS overdue,
                    COUNT(*) FILTER (WHERE a.status = 'OPEN' AND a.priority IN ('HIGH', 'URGENT')) AS high,
                    COUNT(*) FILTER (WHERE a.status = 'OPEN' AND a.assignee_id IS NULL) AS unassigned
               FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
              WHERE s.project_id = $1", [$projectId]);
        $stats = array_map('intval', $row);
        $stats['progress'] = $stats['total'] > 0 ? (int) round($stats['resolved'] * 100 / $stats['total']) : 0;
        $stats['by_category'] = array_column(fetchAll($this->c,
            "SELECT a.category, COUNT(*) AS n FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id WHERE s.project_id = $1 AND a.status = 'OPEN' GROUP BY a.category ORDER BY n DESC",
            [$projectId]), 'n', 'category');
        return $stats;
    }

    /** Góp ý chưa xử lý được giao cho một người (trong các dự án họ còn là thành viên). */
    public function assignedTo(int $userId, int $limit = 20): array
    {
        $rows = fetchAll($this->c,
            "SELECT a.id, a.seq_no, a.priority, a.due_date, a.image_version_id, v.version_no, s.id AS screen_id, s.name AS screen_name,
                    p.id AS project_id, p.name AS project_name, rc.body,
                    (a.due_date IS NOT NULL AND a.due_date < CURRENT_DATE) AS overdue
               FROM annotations a
               JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id
               JOIN projects p ON p.id = s.project_id
               JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = $1
               JOIN comments rc ON rc.annotation_id = a.id AND rc.parent_id IS NULL
              WHERE a.assignee_id = $1 AND a.status = 'OPEN'
              ORDER BY a.due_date ASC NULLS LAST,
                       CASE a.priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 ELSE 4 END, a.id
              LIMIT $2", [$userId, $limit]);
        foreach ($rows as &$r) {
            $r['overdue'] = $r['overdue'] === 't';
        }
        return $rows;
    }

    /** Hoạt động gần đây của dự án (nhật ký), mới nhất trước. */
    public function activity(int $projectId, int $limit = 30, int $screenId = 0): array
    {
        $params = [$projectId, $limit];
        $extra = '';
        if ($screenId > 0) {
            $params[] = $screenId;
            $extra = ' AND s.id = $3';
        }
        return fetchAll($this->c,
            "SELECT e.id, e.kind, e.old_value, e.new_value, e.detail, e.created_at, u.id AS actor_id, u.name AS actor,
                    a.id AS annotation_id, a.seq_no, v.id AS version_id, v.version_no, s.id AS screen_id, s.name AS screen_name
               FROM annotation_events e
               JOIN users u ON u.id = e.actor_id
               JOIN annotations a ON a.id = e.annotation_id
               JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id
              WHERE s.project_id = $1$extra
              ORDER BY e.id DESC LIMIT $2", $params);
    }

    // ------------------------------------------------------------------ nội bộ

    /** Kiểm tra và chuẩn hoá thuộc tính. Chỉ trả về các khoá có mặt trong $meta. */
    private function cleanMeta(array $meta, int $projectId): array
    {
        $out = [];
        if (array_key_exists('priority', $meta)) {
            if (!isset(self::PRIORITIES[$meta['priority']])) {
                throw new AppError('PRIORITY_INVALID', 422);
            }
            $out['priority'] = $meta['priority'];
        }
        if (array_key_exists('category', $meta)) {
            if (!isset(self::CATEGORIES[$meta['category']])) {
                throw new AppError('CATEGORY_INVALID', 422);
            }
            $out['category'] = $meta['category'];
        }
        if (array_key_exists('assignee_id', $meta)) {
            $id = (int) ($meta['assignee_id'] ?? 0);
            if ($id > 0 && Access::roleFor($this->c, $id, $projectId) === null) {
                throw new AppError('ASSIGNEE_NOT_MEMBER', 422);
            }
            $out['assignee_id'] = $id > 0 ? $id : null;
        }
        if (array_key_exists('due_date', $meta)) {
            $d = trim((string) ($meta['due_date'] ?? ''));
            if ($d !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) !== 1 || !checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)))) {
                throw new AppError('DUE_DATE_INVALID', 422);
            }
            $out['due_date'] = $d === '' ? null : $d;
        }
        return $out;
    }

    /** Giá trị dễ đọc để ghi vào nhật ký. */
    private function display(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return match ($field) {
            'priority' => self::PRIORITIES[$value] ?? (string) $value,
            'category' => self::CATEGORIES[$value] ?? (string) $value,
            'assignee_id' => (string) (fetchOne($this->c, 'SELECT name FROM users WHERE id = $1', [(int) $value])['name'] ?? ''),
            'due_date' => date('m/d/Y', (int) strtotime((string) $value)),
            'note' => mb_substr((string) $value, 0, 200),
            default => (string) $value,
        };
    }

    private function event(int $annotationId, int $userId, string $kind, string $old, string $new, string $detail = ''): void
    {
        queryParams($this->c,
            'INSERT INTO annotation_events (annotation_id, actor_id, kind, old_value, new_value, detail) VALUES ($1, $2, $3, $4, $5, $6)',
            [$annotationId, $userId, $kind, mb_substr($old, 0, 500), mb_substr($new, 0, 500), mb_substr($detail, 0, 500)]);
    }

    private function filters(string $where, array $params, string $status, int $authorId, string $q, array $more): array
    {
        if (isset(self::STATUSES[$status])) {
            $params[] = $status;
            $where .= ' AND a.status = $' . count($params);
        }
        if ($authorId > 0) {
            $params[] = $authorId;
            $where .= ' AND a.created_by = $' . count($params);
        }
        if (isset($more['priority']) && isset(self::PRIORITIES[$more['priority']])) {
            $params[] = $more['priority'];
            $where .= ' AND a.priority = $' . count($params);
        }
        if (isset($more['category']) && isset(self::CATEGORIES[$more['category']])) {
            $params[] = $more['category'];
            $where .= ' AND a.category = $' . count($params);
        }
        $assignee = (int) ($more['assignee'] ?? 0);
        if ($assignee === -1) {
            $where .= ' AND a.assignee_id IS NULL';
        } elseif ($assignee > 0) {
            $params[] = $assignee;
            $where .= ' AND a.assignee_id = $' . count($params);
        }
        if (!empty($more['overdue'])) {
            $where .= " AND a.status = 'OPEN' AND a.due_date < CURRENT_DATE";
        }
        if ($q !== '') {
            // Ký tự % và _ do người dùng gõ được coi là chữ thường, không phải ký tự đại diện.
            $params[] = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $n = count($params);
            $where .= " AND (a.note ILIKE \$$n OR EXISTS (SELECT 1 FROM comments qc WHERE qc.annotation_id = a.id AND qc.body ILIKE \$$n))";
        }
        return [$where, $params];
    }

    private function query(string $where, array $params, bool $withThread = true, int $viewerId = 0, string $order = 's.id, v.version_no, a.seq_no'): array
    {
        $rows = fetchAll($this->c,
            "SELECT a.id, a.image_version_id, a.seq_no, a.shape, a.x, a.y, a.w, a.h, a.status, a.lock_version,
                    a.created_at, a.updated_at, a.created_by, u.name AS author,
                    a.priority, a.category, a.assignee_id, asg.name AS assignee, a.due_date,
                    (a.status = 'OPEN' AND a.due_date IS NOT NULL AND a.due_date < CURRENT_DATE) AS overdue,
                    a.note, nu.name AS note_updated_by, a.note_updated_at,
                    v.version_no, v.width AS image_width, v.height AS image_height,
                    s.id AS screen_id, s.name AS screen_name, s.project_id,
                    rc.id AS comment_id, rc.body, rc.lock_version AS comment_lock, rc.author_id AS comment_author_id,
                    (SELECT COUNT(*) FROM comments r WHERE r.parent_id = rc.id) AS reply_count,
                    (SELECT MAX(lv.version_no) FROM image_versions lv WHERE lv.screen_id = s.id) AS latest_version_no,
                    (SELECT cv.version_no FROM status_history sh JOIN image_versions cv ON cv.id = sh.checked_version_id
                      WHERE sh.annotation_id = a.id AND sh.to_status = 'RESOLVED' AND a.status = 'RESOLVED'
                      ORDER BY sh.id DESC LIMIT 1) AS resolved_on_version_no
               FROM annotations a
               JOIN users u ON u.id = a.created_by
               LEFT JOIN users asg ON asg.id = a.assignee_id
               LEFT JOIN users nu ON nu.id = a.note_updated_by
               JOIN image_versions v ON v.id = a.image_version_id
               JOIN screens s ON s.id = v.screen_id
               JOIN comments rc ON rc.annotation_id = a.id AND rc.parent_id IS NULL
              WHERE $where
              ORDER BY $order", $params);
        $items = array_map([$this, 'normalize'], $rows);
        if (!$withThread || $items === []) {
            return $items;
        }
        $ids = '{' . implode(',', array_column($items, 'id')) . '}';
        $replies = fetchAll($this->c,
            'SELECT c.id, c.annotation_id, c.body, c.lock_version, c.author_id, u.name AS author, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM comment_revisions r WHERE r.comment_id = c.id) AS revisions
               FROM comments c JOIN users u ON u.id = c.author_id
              WHERE c.annotation_id = ANY($1::bigint[]) AND c.parent_id IS NOT NULL ORDER BY c.id', [$ids]);
        $history = fetchAll($this->c,
            'SELECT h.annotation_id, h.from_status, h.to_status, h.note, h.created_at, u.name AS changed_by,
                    cv.version_no AS checked_version_no
               FROM status_history h JOIN users u ON u.id = h.changed_by
               LEFT JOIN image_versions cv ON cv.id = h.checked_version_id
              WHERE h.annotation_id = ANY($1::bigint[]) ORDER BY h.id', [$ids]);
        $events = fetchAll($this->c,
            'SELECT e.annotation_id, e.kind, e.old_value, e.new_value, e.detail, e.created_at, u.name AS actor, u.id AS actor_id
               FROM annotation_events e JOIN users u ON u.id = e.actor_id
              WHERE e.annotation_id = ANY($1::bigint[]) ORDER BY e.id', [$ids]);
        $rootRevisions = fetchAll($this->c,
            'SELECT r.comment_id, COUNT(*) AS n FROM comment_revisions r
               JOIN comments c ON c.id = r.comment_id
              WHERE c.annotation_id = ANY($1::bigint[]) GROUP BY r.comment_id', [$ids]);
        $revCount = array_column($rootRevisions, 'n', 'comment_id');
        $commentIds = array_merge(array_column($items, 'comment_id'), array_map(fn ($r) => (int) $r['id'], $replies));
        $reactions = $this->reactionsFor($commentIds, $viewerId);
        $byId = [];
        foreach ($items as $i => $item) {
            $items[$i]['replies'] = [];
            $items[$i]['history'] = [];
            $items[$i]['events'] = [];
            $items[$i]['comment_revisions'] = (int) ($revCount[$item['comment_id']] ?? 0);
            $items[$i]['reactions'] = $reactions[$item['comment_id']] ?? [];
            $byId[$item['id']] = $i;
        }
        foreach ($replies as $r) {
            $items[$byId[(int) $r['annotation_id']]]['replies'][] = [
                'id' => (int) $r['id'], 'body' => $r['body'], 'author' => $r['author'], 'author_id' => (int) $r['author_id'],
                'lock_version' => (int) $r['lock_version'], 'created_at' => $r['created_at'],
                'edited' => (int) $r['revisions'] > 0, 'reactions' => $reactions[(int) $r['id']] ?? [],
            ];
        }
        foreach ($history as $h) {
            $items[$byId[(int) $h['annotation_id']]]['history'][] = [
                'from' => $h['from_status'], 'to' => $h['to_status'], 'by' => $h['changed_by'], 'note' => $h['note'],
                'checked_version_no' => $h['checked_version_no'] === null ? null : (int) $h['checked_version_no'],
                'at' => $h['created_at'],
            ];
        }
        foreach ($events as $ev) {
            $items[$byId[(int) $ev['annotation_id']]]['events'][] = [
                'kind' => $ev['kind'], 'old' => $ev['old_value'], 'new' => $ev['new_value'], 'detail' => $ev['detail'],
                'by' => $ev['actor'], 'by_id' => (int) $ev['actor_id'], 'at' => $ev['created_at'],
            ];
        }
        return $items;
    }

    /** @return array<int, list<array{kind:string,emoji:string,count:int,mine:bool,names:list<string>}>> */
    private function reactionsFor(array $commentIds, int $viewerId): array
    {
        if ($commentIds === []) {
            return [];
        }
        $rows = fetchAll($this->c,
            'SELECT r.comment_id, r.kind, r.user_id, u.name FROM comment_reactions r JOIN users u ON u.id = r.user_id
              WHERE r.comment_id = ANY($1::bigint[]) ORDER BY r.created_at',
            ['{' . implode(',', array_map('intval', $commentIds)) . '}']);
        $out = [];
        foreach ($rows as $r) {
            $cid = (int) $r['comment_id'];
            $k = $r['kind'];
            $out[$cid][$k] ??= ['kind' => $k, 'emoji' => self::REACTIONS[$k], 'count' => 0, 'mine' => false, 'names' => []];
            $out[$cid][$k]['count']++;
            $out[$cid][$k]['names'][] = $r['name'];
            if ((int) $r['user_id'] === $viewerId) {
                $out[$cid][$k]['mine'] = true;
            }
        }
        return array_map('array_values', $out);
    }

    private function normalize(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'image_version_id' => (int) $r['image_version_id'],
            'version_no' => (int) $r['version_no'],
            'latest_version_no' => (int) $r['latest_version_no'],
            'image_width' => (int) $r['image_width'],
            'image_height' => (int) $r['image_height'],
            'screen_id' => (int) $r['screen_id'],
            'screen_name' => $r['screen_name'],
            'project_id' => (int) $r['project_id'],
            'seq_no' => (int) $r['seq_no'],
            'shape' => $r['shape'],
            'x' => (float) $r['x'],
            'y' => (float) $r['y'],
            'w' => $r['w'] === null ? null : (float) $r['w'],
            'h' => $r['h'] === null ? null : (float) $r['h'],
            'status' => $r['status'],
            'lock_version' => (int) $r['lock_version'],
            'author' => $r['author'],
            'author_id' => (int) $r['created_by'],
            'priority' => $r['priority'],
            'category' => $r['category'],
            'assignee_id' => $r['assignee_id'] === null ? null : (int) $r['assignee_id'],
            'assignee' => $r['assignee'],
            'due_date' => $r['due_date'],
            'overdue' => $r['overdue'] === 't',
            'note' => $r['note'],
            'note_updated_by' => $r['note_updated_by'],
            'note_updated_at' => $r['note_updated_at'],
            'resolved_on_version_no' => $r['resolved_on_version_no'] === null ? null : (int) $r['resolved_on_version_no'],
            'comment_id' => (int) $r['comment_id'],
            'body' => $r['body'],
            'comment_lock' => (int) $r['comment_lock'],
            'comment_author_id' => (int) $r['comment_author_id'],
            'reply_count' => (int) $r['reply_count'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
        ];
    }
}
