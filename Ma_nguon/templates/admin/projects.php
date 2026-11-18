<?php
/** Mọi dự án của hệ thống. Dữ liệu: $data['rows'], $pg, $q, $sort, $state, $projectCounts, $back, $bytes. */
$rows = $data['rows'];
?>
<nav class="chip-tabs" aria-label="Trạng thái dự án">
<?php foreach (['' => ['Tất cả', 'folder', 'all'], 'active' => ['Đang hoạt động', 'activity', 'active'], 'archived' => ['Đã lưu trữ', 'archive', 'archived']] as $k => [$label, $ico, $ck]): ?>
<a href="/admin.php?tab=projects<?= $k !== '' ? '&amp;state=' . $k : '' ?>"<?= $state === $k ? ' aria-current="page"' : '' ?>><?= icon($ico, 'sm') ?><?= $label ?> <span class="n"><?= $projectCounts[$ck] ?? 0 ?></span></a>
<?php endforeach; ?>
</nav>
<form method="get" action="/admin.php" class="filter-bar admin-toolbar">
<input type="hidden" name="tab" value="projects">
<?php if ($state !== ''): ?><input type="hidden" name="state" value="<?= e($state) ?>"><?php endif; ?>
<div class="grow"><label for="pq">Tìm theo tên dự án hoặc người tạo</label><input id="pq" name="q" type="search" value="<?= e($q) ?>"></div>
<div><label for="ps">Sắp xếp</label><select id="ps" name="sort" data-autosubmit>
<?php foreach (AdminRepository::PROJECT_SORTS as $k => $label): ?><option value="<?= $k ?>"<?= ($sort ?: 'new') === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select></div>
<button class="btn" type="submit"><?= icon('search') ?>Tìm</button>
</form>
<div class="table-wrap">
<table class="data" id="admin-projects">
<thead><tr><th>Dự án</th><th>Người tạo</th><th class="num">Thành viên</th><th class="num">Màn hình</th><th class="num">Góp ý (chưa xử lý)</th><th class="num">Dung lượng</th><th>Hoạt động gần nhất</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $p): ?>
<tr data-project="<?= e($p['name']) ?>">
<td><strong><?= e($p['name']) ?></strong><?= $p['archived_at'] !== null ? ' <span class="badge badge-muted">Lưu trữ</span>' : '' ?><div class="hint">#<?= (int) $p['id'] ?> · tạo <?= e(date('d/m/Y', (int) strtotime($p['created_at']))) ?></div></td>
<td><?= e($p['owner']) ?><div class="hint"><?= e($p['owner_email']) ?></div></td>
<td class="num"><?= (int) $p['members'] ?></td><td class="num"><?= (int) $p['screens'] ?></td>
<td class="num"><?= (int) $p['annotations'] ?> <span class="hint">(<?= (int) $p['open'] ?>)</span></td>
<td class="num"><?= e($bytes((int) $p['bytes'])) ?></td>
<td><?= $p['last_activity'] ? e(ago($p['last_activity'])) : '<span class="hint">chưa có</span>' ?></td>
<td class="nowrap">
<form method="post" action="<?= e($back) ?>" class="inline"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="join">
<button class="btn btn-sm btn-ghost" type="submit" title="Tham gia với quyền Quản lý để xem và can thiệp (ghi nhật ký)"><?= icon('arrow-right', 'sm') ?>Mở / can thiệp</button></form>
<details class="dropdown">
<summary class="btn btn-sm btn-subtle" aria-label="Xoá dự án <?= e($p['name']) ?>"><?= icon('trash', 'sm') ?></summary>
<div class="menu menu-right admin-menu">
<form method="post" action="<?= e($back) ?>" class="lock-form"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete_project">
<p class="hint">Xoá vĩnh viễn dự án, ảnh, góp ý. Gõ đúng tên <strong><?= e($p['name']) ?></strong>:</p>
<input name="confirm_name" type="text" required aria-label="Gõ lại tên dự án" placeholder="<?= e($p['name']) ?>">
<button class="btn btn-sm btn-danger-solid" type="submit"><?= icon('trash', 'sm') ?>Xoá dự án</button></form>
</div>
</details>
</td>
</tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="8" class="hint">Không có dự án phù hợp.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?= $pg->render('/admin.php', ['tab' => 'projects', 'state' => $state, 'q' => $q, 'sort' => $sort], 'dự án') ?>
