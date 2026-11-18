<?php
/** Các nút thao tác với một người dùng — dùng chung cho menu trong bảng và trang chi tiết. Biến: $u, $self, $back. */
?>
<?php if (!$self): ?>
<?php if ($u['locked_at'] === null): ?>
<form method="post" action="<?= e($back) ?>" class="lock-form"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="lock">
<input name="reason" type="text" maxlength="300" placeholder="Lý do khoá (không bắt buộc)" aria-label="Lý do khoá">
<button class="btn btn-sm btn-danger-solid" type="submit"><?= icon('lock', 'sm') ?>Khoá tài khoản</button></form>
<?php else: ?>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="unlock"><button type="submit"><?= icon('unlock') ?>Mở khoá tài khoản</button></form>
<?php endif; ?>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="<?= $u['is_admin'] === 't' ? 'revoke_admin' : 'grant_admin' ?>"><button type="submit"><?= icon('shield') ?><?= $u['is_admin'] === 't' ? 'Bỏ quyền quản trị' : 'Cấp quyền quản trị' ?></button></form>
<?php endif; ?>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="<?= $u['is_support'] === 't' ? 'revoke_support' : 'grant_support' ?>"><button type="submit"><?= icon('inbox') ?><?= $u['is_support'] === 't' ? 'Bỏ quyền bộ phận hỗ trợ' : 'Cấp quyền bộ phận hỗ trợ' ?></button></form>
<form method="post" action="<?= e($back) ?>"><?= csrfField() ?><input type="hidden" name="target" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="reset_link"><button type="submit"><?= icon('key') ?>Cấp link đặt lại mật khẩu</button></form>
