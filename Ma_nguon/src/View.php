<?php

declare(strict_types=1);

// Hàm hiển thị dùng chung (bản 2.0): biểu tượng, ảnh đại diện, thời gian tương đối, nhãn, nhật ký.
// Không có tác dụng phụ (không gửi header, không mở phiên) nên kiểm thử đơn vị nạp được.

/** Biểu tượng SVG nội tuyến (nét vẽ tự làm, phong cách tối giản). */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'pin' => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'square' => '<rect x="4" y="4" width="16" height="16" rx="2" stroke-dasharray="3 2.5"/>',
        'hand' => '<path d="M8 13V5.5a1.5 1.5 0 0 1 3 0V12"/><path d="M11 11.5V4.5a1.5 1.5 0 0 1 3 0V12"/><path d="M14 11.5V6.5a1.5 1.5 0 0 1 3 0V14"/><path d="M8 13V9.5a1.5 1.5 0 0 0-3 0V14a7 7 0 0 0 7 7h1a6 6 0 0 0 6-6v-2"/>',
        'cursor' => '<path d="M5 3l14 7-6 2-2 6z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'maximize' => '<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
        'undo' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'upload' => '<path d="M12 21V9M7 14l5-5 5 5"/><path d="M5 3h14"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'compare' => '<rect x="3" y="4" width="8" height="16" rx="1.5"/><rect x="13" y="4" width="8" height="16" rx="1.5"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 3.9M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'note' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'keyboard' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'alert' => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
        'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'sidebar' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
    ];
    return '<svg class="icon' . ($class !== '' ? ' ' . $class : '') . '" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Chữ viết tắt từ họ tên tiếng Việt: chữ đầu của họ + chữ đầu của tên ("Nguyễn Thu Trang (Thiết kế)" -> "NT"). */
function initials(string $name): string
{
    $clean = trim((string) preg_replace('/\(.*?\)/u', '', $name));
    $parts = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Ảnh đại diện chữ viết tắt; màu cố định theo id người dùng (không cần CSS nội tuyến). */
function avatar(string $name, int $id, string $size = ''): string
{
    return '<span class="avatar avatar-c' . ($id % 8) . ($size !== '' ? ' avatar-' . $size : '') . '" title="' . e($name) . '">'
        . e(initials($name)) . '</span>';
}

/** Thời gian tương đối tiếng Việt: "vừa xong", "5 phút trước", "hôm qua", ... */
function ago(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '';
    }
    $t = strtotime($ts);
    if ($t === false) {
        return '';
    }
    $d = time() - $t;
    return match (true) {
        $d < 60 => 'vừa xong',
        $d < 3600 => intdiv($d, 60) . ' phút trước',
        $d < 86400 => intdiv($d, 3600) . ' giờ trước',
        $d < 172800 => 'hôm qua',
        $d < 7 * 86400 => intdiv($d, 86400) . ' ngày trước',
        default => date('d/m/Y', $t),
    };
}

function statusBadge(string $status): string
{
    return '<span class="badge ' . ($status === 'OPEN' ? 'badge-open' : 'badge-resolved') . '" data-status="' . e($status) . '">'
        . e(AnnotationRepository::STATUSES[$status] ?? $status) . '</span>';
}

function prioBadge(string $p): string
{
    return '<span class="prio prio-' . e($p) . '">' . e(AnnotationRepository::PRIORITIES[$p] ?? $p) . '</span>';
}

function catBadge(string $c): string
{
    return '<span class="cat cat-' . e($c) . '">' . e(AnnotationRepository::CATEGORIES[$c] ?? $c) . '</span>';
}

function dueBadge(?string $date, bool $overdue): string
{
    if ($date === null || $date === '') {
        return '';
    }
    return '<span class="due' . ($overdue ? ' is-overdue' : '') . '">' . icon('calendar') . e(date('d/m', (int) strtotime($date)))
        . ($overdue ? ' · quá hạn' : '') . '</span>';
}

/** Mô tả một dòng nhật ký hoạt động (dùng ở trang dự án và trang tìm góp ý). */
function eventText(array $ev): string
{
    $o = e($ev['old_value']);
    $n = e($ev['new_value']);
    return match ($ev['kind']) {
        'CREATED' => 'tạo góp ý ' . ($ev['new_value'] === 'RECT' ? 'vùng' : 'điểm'),
        'REPLIED' => 'trả lời',
        'EDITED' => 'sửa nhận xét',
        'STATUS' => $ev['new_value'] === 'RESOLVED' ? 'đánh dấu <strong>đã xử lý</strong>' : '<strong>mở lại</strong>',
        'PRIORITY' => "đổi ưu tiên $o → <strong>$n</strong>",
        'CATEGORY' => "đổi nhãn $o → <strong>$n</strong>",
        'ASSIGNEE' => $n === '' ? 'bỏ người phụ trách' : "giao cho <strong>$n</strong>",
        'DUE' => $n === '' ? 'bỏ hạn xử lý' : "đặt hạn <strong>$n</strong>",
        'NOTE' => $n === '' ? 'xoá ghi chú' : 'cập nhật ghi chú',
        default => e($ev['kind']),
    };
}

function eventIcon(array $ev): string
{
    $kind = $ev['kind'] === 'STATUS' ? 'STATUS-' . $ev['new_value'] : $ev['kind'];
    $name = match ($ev['kind']) {
        'CREATED' => 'pin', 'REPLIED' => 'chat', 'EDITED' => 'edit', 'STATUS' => $ev['new_value'] === 'RESOLVED' ? 'check' : 'undo',
        'PRIORITY' => 'flag', 'CATEGORY' => 'tag', 'ASSIGNEE' => 'user', 'DUE' => 'calendar', 'NOTE' => 'note', default => 'activity',
    };
    return '<span class="feed-icon k-' . e($kind) . '">' . icon($name) . '</span>';
}
