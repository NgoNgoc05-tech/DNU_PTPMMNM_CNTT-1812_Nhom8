<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$projectId = intField($_GET, 'project');
Access::require($c, $user['id'], $projectId, Access::VIEW);
$projects = new ProjectRepository($c);
$project = $projects->find($projectId);
$members = $projects->members($projectId);
$screens = (new ScreenRepository($c))->listForProject($projectId);
$repo = new AnnotationRepository($c);
$status = field($_GET, 'status');
$author = intField($_GET, 'author');
$screen = intField($_GET, 'screen');
$q = field($_GET, 'q');
$more = [
    'priority' => field($_GET, 'priority'), 'category' => field($_GET, 'category'), 'assignee' => intField($_GET, 'assignee'),
    'overdue' => field($_GET, 'overdue') === '1', 'sort' => field($_GET, 'sort'),
];
$items = $repo->search($projectId, $status, $author, $q, $screen, $more);
$stats = $repo->stats($projectId);
$activity = $repo->activity($projectId, 40);
$query = http_build_query(array_filter(['project' => $projectId, 'status' => $status, 'author' => $author, 'screen' => $screen, 'q' => $q,
    'priority' => $more['priority'], 'category' => $more['category'], 'assignee' => $more['assignee'],
    'overdue' => $more['overdue'] ? '1' : '', 'sort' => $more['sort']], fn ($v) => $v !== '' && $v !== 0));
$base = '/issues.php?project=' . $projectId;
$filtered = $status !== '' || $author > 0 || $screen > 0 || $q !== '' || $more['priority'] !== '' || $more['category'] !== '' || $more['assignee'] !== 0 || $more['overdue'];
$title = 'Góp ý · ' . $project['name'];
$active = 'projects';
$sel = fn (string $a, string $b): string => $a === $b ? ' selected' : '';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div><div class="crumbs"><a href="/projects.php">Dự án</a><span class="sep">/</span><a href="/project.php?id=<?= $projectId ?>"><?= e($project['name']) ?></a><span class="sep">/</span><span>Tìm góp ý</span></div>
<h1>Tìm góp ý</h1><p>Lọc theo trạng thái, người góp ý, ưu tiên, nhãn, người phụ trách, hạn và nội dung. Xuất đúng danh sách đang lọc.</p></div>
<div class="actions">
<button type="button" class="btn btn-ghost" data-print><?= icon('printer') ?>In / PDF</button>
<a class="btn" href="/export.php?<?= e($query) ?>"><?= icon('download') ?>Xuất CSV (<?= count($items) ?> dòng)</a>
</div>
</div>
<p class="only-print"><strong><?= e($project['name']) ?></strong> — danh sách góp ý, in lúc <?= e(date('d/m/Y H:i')) ?></p>

<div class="grid grid-4 no-print">
<a class="stat" href="<?= $base ?>&amp;status=OPEN"><span class="stat-icon pin"><?= icon('pin') ?></span><span><strong><?= $stats['open'] ?></strong><span>Chưa xử lý</span></span></a>
<a class="stat" href="<?= $base ?>&amp;status=RESOLVED"><span class="stat-icon ok"><?= icon('check-circle') ?></span><span><strong><?= $stats['resolved'] ?></strong><span>Đã xử lý · <?= $stats['progress'] ?>%</span></span></a>
<a class="stat" href="<?= $base ?>&amp;overdue=1"><span class="stat-icon danger"><?= icon('clock') ?></span><span><strong><?= $stats['overdue'] ?></strong><span>Quá hạn</span></span></a>
<a class="stat" href="<?= $base ?>&amp;status=OPEN&amp;assignee=<?= (int) $user['id'] ?>"><span class="stat-icon"><?= icon('user') ?></span><span><strong><?= count($repo->search($projectId, 'OPEN', 0, '', 0, ['assignee' => (int) $user['id']])) ?></strong><span>Giao cho tôi</span></span></a>
</div>

<form method="get" action="/issues.php" class="filter-bar mt">
<input type="hidden" name="project" value="<?= $projectId ?>">
<div class="grow"><label for="q">Nội dung / ghi chú chứa</label><div class="input-icon"><?= icon('search') ?><input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="100" placeholder="Ví dụ: nút, màu, khoảng cách…"></div></div>
<div><label for="status">Trạng thái</label><select id="status" name="status">
<option value="">Tất cả</option><option value="OPEN"<?= $sel($status, 'OPEN') ?>>Chưa xử lý</option><option value="RESOLVED"<?= $sel($status, 'RESOLVED') ?>>Đã xử lý</option></select></div>
<div><label for="author">Người góp ý</label><select id="author" name="author"><option value="0">Tất cả</option>
<?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $author ? ' selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="assignee">Phụ trách</label><select id="assignee" name="assignee"><option value="0">Tất cả</option><option value="-1"<?= $more['assignee'] === -1 ? ' selected' : '' ?>>— Chưa giao —</option>
<?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $more['assignee'] ? ' selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="priority">Ưu tiên</label><select id="priority" name="priority"><option value="">Tất cả</option>
<?php foreach (AnnotationRepository::PRIORITIES as $k => $label): ?><option value="<?= $k ?>"<?= $sel($more['priority'], $k) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div><label for="category">Nhãn</label><select id="category" name="category"><option value="">Tất cả</option>
<?php foreach (AnnotationRepository::CATEGORIES as $k => $label): ?><option value="<?= $k ?>"<?= $sel($more['category'], $k) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div><label for="screen">Màn hình</label><select id="screen" name="screen"><option value="0">Tất cả</option>
<?php foreach ($screens as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (int) $s['id'] === $screen ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="sort">Sắp xếp</label><select id="sort" name="sort">
<option value="">Màn hình, phiên bản, số</option><option value="newest"<?= $sel($more['sort'], 'newest') ?>>Mới nhất</option><option value="priority"<?= $sel($more['sort'], 'priority') ?>>Ưu tiên cao trước</option><option value="due"<?= $sel($more['sort'], 'due') ?>>Hạn gần nhất</option></select></div>
<label class="check"><input type="checkbox" name="overdue" value="1"<?= $more['overdue'] ? ' checked' : '' ?>> Chỉ quá hạn</label>
<button class="btn" type="submit"><?= icon('search') ?>Lọc</button>
<?php if ($filtered): ?><a class="btn btn-subtle" href="<?= $base ?>"><?= icon('x') ?>Xoá lọc</a><?php endif; ?>
</form>

<div class="section-title"><h2><?= icon('chat') ?>Kết quả <span class="count"><?= count($items) ?></span></h2></div>
<?php if ($items === []): ?>
<div class="card empty-state"><div class="empty-art"><?= icon('search') ?></div><h3>Không có góp ý phù hợp</h3><p class="empty">Không có góp ý phù hợp.</p></div>
<?php else: ?>
<div class="table-wrap">
<table class="data" id="issues">
<thead><tr><th>#</th><th>Màn hình · bản</th><th>Nội dung</th><th>Ưu tiên</th><th>Nhãn</th><th>Phụ trách</th><th>Hạn</th><th>Trạng thái</th><th>Người viết</th><th>Vị trí (px ảnh gốc)</th></tr></thead>
<tbody>
<?php foreach ($items as $a): ?>
<tr>
<td><a class="seq-link<?= $a['status'] === 'RESOLVED' ? ' is-resolved' : '' ?>" href="/screen.php?id=<?= $a['screen_id'] ?>&amp;v=<?= $a['image_version_id'] ?>&amp;a=<?= $a['id'] ?>" title="Mở góp ý">#<?= $a['seq_no'] ?></a></td>
<td class="nowrap"><strong><?= e($a['screen_name']) ?></strong><br><span class="hint">v<?= $a['version_no'] ?><?= $a['version_no'] < $a['latest_version_no'] ? ' · bản mới nhất v' . $a['latest_version_no'] : '' ?></span></td>
<td class="body-cell"><?= e($a['body']) ?>
<?php if ($a['note'] !== ''): ?><div class="hint mt-s"><?= icon('note', 'sm') ?> <?= e(mb_strimwidth($a['note'], 0, 120, '…')) ?></div><?php endif; ?>
<?php if ($a['reply_count'] > 0): ?><span class="hint"> · <?= icon('chat', 'sm') ?> <?= $a['reply_count'] ?> trả lời</span><?php endif; ?></td>
<td><?= prioBadge($a['priority']) ?></td>
<td><?= catBadge($a['category']) ?></td>
<td class="nowrap"><?= $a['assignee'] !== null ? avatar($a['assignee'], (int) $a['assignee_id'], 'sm') . ' ' . e(trim((string) preg_replace('/\(.*?\)/u', '', $a['assignee']))) : '<span class="faint">—</span>' ?></td>
<td><?= dueBadge($a['due_date'], $a['overdue']) ?: '<span class="faint">—</span>' ?></td>
<td><?= statusBadge($a['status']) ?><?= $a['resolved_on_version_no'] ? '<br><span class="hint">kiểm tra trên v' . $a['resolved_on_version_no'] . '</span>' : '' ?></td>
<td class="nowrap"><?= avatar($a['author'], $a['author_id'], 'sm') ?> <?= e(trim((string) preg_replace('/\(.*?\)/u', '', $a['author']))) ?></td>
<td class="num coord"><?= $a['shape'] === 'POINT' ? '● (' . e($a['x']) . '; ' . e($a['y']) . ')' : '▭ (' . e($a['x']) . '; ' . e($a['y']) . ') ' . e($a['w']) . '×' . e($a['h']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<div class="card mt" id="activity">
<div class="section-title"><h2><?= icon('history') ?>Lịch sử hoạt động gần đây</h2><span class="hint">40 thay đổi mới nhất trong dự án</span></div>
<?php if ($activity === []): ?>
<p class="hint">Chưa có hoạt động.</p>
<?php else: ?>
<ul class="feed">
<?php foreach ($activity as $ev): ?>
<li><?= eventIcon($ev) ?><span class="feed-body"><strong><?= e($ev['actor']) ?></strong> <?= eventText($ev) ?><?= $ev['kind'] === 'STATUS' && $ev['detail'] !== '' ? ' <span class="hint">(kiểm tra trên ' . e($ev['detail']) . ')</span>' : '' ?> ·
<a href="/screen.php?id=<?= (int) $ev['screen_id'] ?>&amp;v=<?= (int) $ev['version_id'] ?>&amp;a=<?= (int) $ev['annotation_id'] ?>"><?= e($ev['screen_name']) ?> v<?= (int) $ev['version_no'] ?> #<?= (int) $ev['seq_no'] ?></a>
<?php if (in_array($ev['kind'], ['REPLIED', 'CREATED'], true) && $ev['detail'] !== ''): ?><span class="quote line-clamp-2"><?= e($ev['detail']) ?></span><?php endif; ?>
<time><?= e(dateVi($ev['created_at'])) ?> · <?= e(ago($ev['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
