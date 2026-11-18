</div>
</div>

<dialog class="modal" id="dlg-new-user" aria-labelledby="dlg-new-user-title">
<form method="post" action="/admin.php?tab=users">
<?= csrfField() ?>
<input type="hidden" name="action" value="create_user">
<div class="modal-head"><h2 id="dlg-new-user-title">Thêm người dùng</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="field"><label for="nu-name">Họ tên</label><input id="nu-name" name="name" type="text" required maxlength="120" placeholder="Ví dụ: Trần Minh Anh"></div>
<div class="field"><label for="nu-email">Email</label><input id="nu-email" name="email" type="email" required maxlength="200" placeholder="ten@congty.vn"></div>
<div class="field"><label for="nu-title">Chức danh (không bắt buộc)</label><input id="nu-title" name="title" type="text" maxlength="120" placeholder="Thiết kế UI, Kiểm thử…"></div>
<label class="check"><input type="checkbox" name="support" value="1"> Thuộc bộ phận hỗ trợ</label>
<p class="hint">Hệ thống không đặt mật khẩu thay người dùng. Sau khi tạo, bạn nhận một link (hiệu lực <?= PasswordReset::TTL_MINUTES ?> phút) để gửi cho họ tự đặt mật khẩu.</p>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn" type="submit"><?= icon('plus') ?>Tạo tài khoản</button></div>
</form>
</dialog>
