<?php
/** Nhật ký quản trị, lọc theo loại thao tác và từ khoá. Dữ liệu: $data['rows'|'actions'], $pg, $action, $q. */
$labels = ['LOCK' => 'Khoá tài khoản', 'UNLOCK' => 'Mở khoá', 'GRANT_ADMIN' => 'Cấp quyền quản trị', 'REVOKE_ADMIN' => 'Bỏ quyền quản trị',
    'GRANT_SUPPORT' => 'Cấp quyền hỗ trợ', 'REVOKE_SUPPORT' => 'Bỏ quyền hỗ trợ', 'RESET_LINK' => 'Cấp link đặt lại mật khẩu', 'CREATE_USER' => 'Tạo tài khoản',
    'JOIN_PROJECT' => 'Tham gia dự án', 'DELETE_PROJECT' => 'Xoá dự án', 'EXPORT_USERS' => 'Xuất danh sách người dùng'];
?>
<form method="get" action="/admin.php" class="filter-bar admin-toolbar">
<input type="hidden" name="tab" value="log">
<div><label for="la">Loại thao tác</label><select id="la" name="action" data-autosubmit>
<option value="">Tất cả thao tác</option>
<?php foreach ($data['actions'] as $a): ?><option value="<?= e($a) ?>"<?= $action === $a ? ' selected' : '' ?>><?= e($labels[$a] ?? $a) ?></option><?php endforeach; ?>
</select></div>
<div class="grow"><label for="lq">Tìm theo đối tượng hoặc chi tiết</label><input id="lq" name="q" type="search" value="<?= e($q) ?>" placeholder="email, tên dự án…"></div>
<button class="btn" type="submit"><?= icon('search') ?>Lọc</button>
</form>
<div class="table-wrap">
<table class="data" id="admin-log">
<thead><tr><th>Thời điểm</th><th>Quản trị viên</th><th>Thao tác</th><th>Đối tượng</th><th>Chi tiết</th></tr></thead>
<tbody>
<?php foreach ($data['rows'] as $l): ?>
<tr><td class="nowrap"><?= e(dateVi($l['created_at'])) ?><div class="hint"><?= e(ago($l['created_at'])) ?></div></td><td><?= e($l['admin'] ?? '—') ?></td>
<td><span class="badge badge-role" title="<?= e($l['action']) ?>"><?= e($labels[$l['action']] ?? $l['action']) ?></span></td><td><?= e($l['target']) ?></td><td class="hint"><?= e($l['detail']) ?></td></tr>
<?php endforeach; ?>
<?php if ($data['rows'] === []): ?><tr><td colspan="5" class="hint">Chưa có thao tác quản trị nào phù hợp.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?= $pg->render('/admin.php', ['tab' => 'log', 'action' => $action, 'q' => $q], 'thao tác') ?>
