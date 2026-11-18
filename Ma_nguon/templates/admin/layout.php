<?php
/**
 * Khung bảng điều khiển quản trị (bản 2.6): thanh bên trái chia nhóm + đầu trang có đường dẫn và nút thao tác chính.
 * Biến dùng: $tab, $filter, $state, $userFilters, $userCounts, $projectCounts, $stats, $pageTitles, $flashMsg (từ header).
 */
$navLink = function (string $href, string $label, string $ico, bool $on, ?int $n = null, string $tone = ''): string {
    return '<a href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . icon($ico, 'sm') . '<span>' . e($label) . '</span>'
        . ($n !== null ? '<span class="side-n' . ($tone !== '' ? ' ' . $tone : '') . '">' . $n . '</span>' : '') . '</a>';
};
?>
<div class="admin-shell">
<aside class="admin-side" aria-label="Menu quản trị">
<div class="side-brand"><span class="side-logo"><?= icon('shield') ?></span><div><strong>Bảng quản trị</strong><small><?= $stats['users']['total'] ?> người dùng · <?= $stats['projects']['projects'] ?> dự án</small></div></div>
<nav>
<p class="side-group">Bảng điều khiển</p>
<?= $navLink('/admin.php', 'Tổng quan', 'chart', $tab === 'overview') ?>
<p class="side-group">Người dùng</p>
<?php foreach ($userFilters as $key => [$label, $ico, $countKey]): ?>
<?= $navLink('/admin.php?tab=users' . ($key !== '' ? '&filter=' . $key : ''), $label, $ico, ($tab === 'users' && $filter === $key), $userCounts[$countKey] ?? 0,
    $key === 'locked' && ($userCounts['locked'] ?? 0) > 0 ? 'danger' : '') ?>
<?php endforeach; ?>
<p class="side-group">Nội dung</p>
<?= $navLink('/admin.php?tab=projects', 'Tất cả dự án', 'folder', $tab === 'projects' && $state === '', $projectCounts['all'] ?? 0) ?>
<?= $navLink('/admin.php?tab=projects&state=active', 'Đang hoạt động', 'activity', $tab === 'projects' && $state === 'active', $projectCounts['active'] ?? 0) ?>
<?= $navLink('/admin.php?tab=projects&state=archived', 'Đã lưu trữ', 'archive', $tab === 'projects' && $state === 'archived', $projectCounts['archived'] ?? 0) ?>
<p class="side-group">Hệ thống</p>
<?= $navLink('/admin.php?tab=log', 'Nhật ký quản trị', 'history', $tab === 'log') ?>
<?= $navLink('/support_admin.php', 'Yêu cầu hỗ trợ', 'inbox', false, $stats['tickets']['open'], $stats['tickets']['open'] > 0 ? 'accent' : '') ?>
</nav>
</aside>
<div class="admin-main">
<?php if ($flashMsg !== null): ?>
<div class="flash<?= str_starts_with($flashMsg, 'Lỗi') ? ' error' : '' ?>" role="status"><?= icon(str_starts_with($flashMsg, 'Lỗi') ? 'alert' : 'check-circle') ?><span><?= e($flashMsg) ?></span></div>
<?php endif; ?>
<div class="admin-top">
<div>
<nav class="crumbs" aria-label="Đường dẫn"><a href="/admin.php">Quản trị</a><span class="sep">›</span>
<?php if ($tab === 'user'): ?><a href="/admin.php?tab=users">Người dùng</a><span class="sep">›</span><?php elseif ($tab === 'users'): ?><span>Người dùng</span><span class="sep">›</span><?php elseif ($tab === 'projects'): ?><span>Dự án</span><span class="sep">›</span><?php endif; ?>
<span aria-current="page"><?= e($pageTitles[$tab]) ?></span></nav>
<h1><?= e($pageTitles[$tab]) ?></h1>
</div>
<div class="actions">
<?php if ($tab === 'users' || $tab === 'overview'): ?>
<button type="button" class="btn" data-open="dlg-new-user"><?= icon('plus') ?>Thêm người dùng</button>
<?php endif; ?>
<?php if ($tab === 'users'): ?>
<a class="btn btn-ghost" href="/admin.php?<?= e(http_build_query(array_filter(['tab' => 'users', 'filter' => $filter, 'q' => $q, 'sort' => $sort, 'export' => 'csv']))) ?>"><?= icon('download') ?>Xuất CSV</a>
<?php endif; ?>
</div>
</div>
<?php if ($issued !== null): ?>
<div class="card reset-box mb">
<div class="flash warn" role="status"><?= icon('key') ?><span><strong><?= e($issued['title'] ?? 'Link đặt lại mật khẩu') ?>.</strong> Link chỉ hiện <strong>một lần</strong>, hiệu lực <?= PasswordReset::TTL_MINUTES ?> phút. Copy và gửi cho người dùng qua kênh liên hệ an toàn.</span></div>
<input class="issued-link" type="text" readonly value="<?= e($issued['link']) ?>" aria-label="Link đặt mật khẩu" data-select-all>
</div>
<?php endif; ?>
