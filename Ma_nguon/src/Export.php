<?php

declare(strict_types=1);

/**
 * Xuất danh sách góp ý ra CSV (mở được bằng Excel/LibreOffice): nội dung, vị trí,
 * người viết, trạng thái và phiên bản ảnh; kèm ưu tiên, nhãn, người phụ trách, hạn,
 * ghi chú xử lý và phiên bản đã dùng để kiểm tra sau khi sửa.
 */
final class Export
{
    public const HEADER = [
        'Màn hình', 'Phiên bản', 'Số thứ tự', 'Kiểu dấu', 'X (px ảnh gốc)', 'Y (px ảnh gốc)',
        'Rộng (px)', 'Cao (px)', 'Kích thước ảnh', 'Trạng thái', 'Người viết', 'Nội dung', 'Số trả lời', 'Thời điểm tạo',
        'Ưu tiên', 'Nhãn', 'Người phụ trách', 'Hạn xử lý (tháng/ngày/năm)', 'Quá hạn', 'Ghi chú xử lý', 'Kiểm tra sau sửa trên', 'Bản mới nhất',
    ];

    public static function rows(array $items): array
    {
        $rows = [self::HEADER];
        foreach ($items as $a) {
            $rows[] = [
                $a['screen_name'],
                'v' . $a['version_no'],
                '#' . $a['seq_no'],
                $a['shape'] === 'POINT' ? 'Điểm' : 'Vùng',
                self::num($a['x']),
                self::num($a['y']),
                $a['w'] === null ? '' : self::num($a['w']),
                $a['h'] === null ? '' : self::num($a['h']),
                $a['image_width'] . '×' . $a['image_height'],
                $a['status'] === 'OPEN' ? 'Chưa xử lý' : 'Đã xử lý',
                $a['author'],
                $a['body'],
                (string) $a['reply_count'],
                substr((string) $a['created_at'], 0, 19),
                AnnotationRepository::PRIORITIES[$a['priority'] ?? 'MEDIUM'] ?? '',
                AnnotationRepository::CATEGORIES[$a['category'] ?? 'OTHER'] ?? '',
                (string) ($a['assignee'] ?? ''),
                empty($a['due_date']) ? '' : date('m/d/Y', (int) strtotime((string) $a['due_date'])),
                !empty($a['overdue']) ? 'Có' : '',
                (string) ($a['note'] ?? ''),
                empty($a['resolved_on_version_no']) ? '' : 'v' . $a['resolved_on_version_no'],
                empty($a['latest_version_no']) ? '' : 'v' . $a['latest_version_no'],
            ];
        }
        return $rows;
    }

    public static function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '');
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }

    /**
     * Chống "CSV injection": ô bắt đầu bằng = + - @ có thể bị Excel hiểu là công thức.
     * Thêm dấu nháy đơn để nội dung luôn được hiển thị như văn bản.
     */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
