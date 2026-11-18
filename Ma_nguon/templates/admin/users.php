<?php
/**
 * Danh sách người dùng. Nút lọc theo nhóm (kèm số), tìm, sắp xếp, chọn nhiều dòng để khoá / mở khoá cùng lúc, phân trang.
 * Dữ liệu: $data['rows'], $pg, $q, $filter, $sort, $userFilters, $userCounts, $back, $user.
 */
$rows = $data['rows'];
?>
<nav class="chip-tabs" aria-label="Nhóm người dùng">
<?php foreach ($userFilters as $key => [$label, $ico, $countKey]): ?>
<a href="/admin.php?tab=users<?= $key !== '' ? '&amp;filter=' . $key : '' ?><?= $q !== '' ? '&amp;q=' . e(rawurlencode($q)) : '' ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= icon($ico, 'sm') ?><?= e($key === '' ? 'Tất cả' : $label) ?> <span class="n"><?= $userCounts[$countKey] ?? 0 ?></span></a>
<?php endforeach; ?>
</nav>

<form method="get" action="/admin.php" class="filter-bar admin-toolbar">
<input type="hidden" name="tab" value="users">
<?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
<div class="grow"><label for="aq">Tìm theo tên hoặc email</label><input id="aq" name="q" type="search" value="<?= e($q) ?>" placeholder="vd: trang, @pinpoint.test"></div>
<div><label for="as">Sắp xếp</label><select id="as" name="sort" data-autosubmit>
<?php foreach (AdminRepository::USER_SORTS as $k => $label): ?><option value="<?= $k ?>"<?= ($sort ?: 'new') === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select></div>
<button class="btn" type="submit"><?= icon('search') ?>Tìm</button>
<?php if ($q !== ''): ?><a class="btn btn-subtle" href="/admin.php?tab=users<?= $filter !== '' ? '&amp;filter=' . e($filter) : '' ?>"><?= icon('x', 'sm') ?>Bỏ tìm</a><?php endif; ?>
</form>

<?php /* Form thao tác hàng loạt: các ô chọn trong bảng gắn vào form này bằng thuộc tính form="bulk-form". */ ?>
<form method="post" action="<?= e($back) ?>" id="bulk-form" class="bulk-bar" data-bulk hidden>
<?= csrfField() ?>
<span class="bulk-count"><strong data-bulk-count>0</strong> người dùng đã chọn</span>
<input type="text" name="reason" maxlength="300" placeholder="Lý do khoá (không bắt buộc)" aria-label="Lý do khoá hàng loạt">
<button class="btn btn-sm btn-danger-solid" type="submit" name="action" value="bulk_lock"><?= icon('lock', 'sm') ?>Khoá đã chọn</button>
<button class="btn btn-sm btn-ok" type="submit" name="action" value="bulk_unlock"><?= icon('unlock', 'sm') ?>Mở khoá đã chọn</button>
<button class="btn btn-sm btn-subtle" type="button" data-bulk-clear>Bỏ chọn</button>
</form>

<div class="table-wrap">
<table class="data admin-users" id="admin-users">
<thead><tr><th class="col-check"><input type="checkbox" data-bulk-all aria-label="Chọn tất cả người dùng trên trang này"></th><th>Người dùng</th><th>Vai trò</th><th>Hoạt động</th><th>Đăng nhập</th><th>Trạng thái</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $u): $self = (int) $u['id'] === $user['id']; ?>
<tr data-user="<?= e($u['email']) ?>"<?= $u['locked_at'] !== null ? ' class="is-locked"' : '' ?>>
<td class="col-check"><?php if (!$self): ?><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" form="bulk-form" data-bulk-item aria-label="Chọn <?= e($u['email']) ?>"><?php endif; ?></td>
<td><a class="user-cell" href="/admin.php?tab=user&amp;id=<?= (int) $u['id'] ?>"><?= avatar($u['name'], (int) $u['id'], 'sm') ?><span><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="badge badge-muted">bạn</span>' : '' ?><span class="hint"><?= e($u['email']) ?></span><?php if ($u['title'] !== ''): ?><span class="hint faint"><?= e($u['title']) ?></span><?php endif; ?></span></a></td>
<td><?= $u['is_admin'] === 't' ? '<span class="badge badge-role">Quản trị</span> ' : '' ?><?= $u['is_support'] === 't' ? '<span class="badge badge-resolved">Hỗ trợ</span>' : '' ?><?= $u['is_admin'] !== 't' && $u['is_support'] !== 't' ? '<span class="hint">Người dùng</span>' : '' ?></td>
<td class="activity-cell"><span title="Dự án tham gia"><?= icon('folder', 'sm') ?><?= (int) $u['projects'] ?></span><span title="Góp ý đã tạo"><?= icon('pin', 'sm') ?><?= (int) $u['annotations'] ?></span><span title="Nhận xét, trả lời"><?= icon('chat', 'sm') ?><?= (int) $u['comments'] ?></span></td>
<td class="nowrap"><?= $u['last_login_at'] ? e(ago($u['last_login_at'])) : '<span class="hint">chưa</span>' ?></td>
<td><?php if ($u['locked_at'] !== null): ?><span class="badge badge-open" title="<?= e($u['locked_reason']) ?>"><?= icon('lock', 'sm') ?>Bị khoá</span><?php if ($u['locked_reason'] !== ''): ?><div class="hint line-clamp-2"><?= e($u['locked_reason']) ?></div><?php endif; ?><?php else: ?><span class="badge badge-resolved">Hoạt động</span><?php endif; ?></td>
<td class="nowrap">
<a class="btn btn-sm btn-subtle btn-icon" href="/admin.php?tab=user&amp;id=<?= (int) $u['id'] ?>" title="Xem chi tiết" aria-label="Xem chi tiết <?= e($u['email']) ?>"><?= icon('eye', 'sm') ?></a>
<details class="dropdown">
<summary class="btn btn-sm btn-ghost" aria-label="Thao tác với <?= e($u['email']) ?>"><?= icon('settings', 'sm') ?></summary>
<div class="menu menu-right admin-menu">
<?php require __DIR__ . '/user_actions.php'; ?>
</div>
</details>
</td>
</tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="7" class="hint">Không có người dùng phù hợp.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?= $pg->render('/admin.php', ['tab' => 'users', 'q' => $q, 'filter' => $filter, 'sort' => $sort], 'người dùng') ?>
