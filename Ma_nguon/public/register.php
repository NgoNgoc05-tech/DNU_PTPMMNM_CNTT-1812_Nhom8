<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$error = '';
if (isPost()) {
    checkCsrf($_POST);
    try {
        if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password2'] ?? '')) {
            throw new AppError('PASSWORD_MISMATCH', 422);
        }
        $id = Auth::createUser(db(), field($_POST, 'email'), field($_POST, 'name'), (string) ($_POST['password'] ?? ''));
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        flash('Đã tạo tài khoản. Hãy tạo dự án hoặc nhờ quản lý dự án mời bạn bằng email.');
        redirect('/projects.php');
    } catch (AppError $ex) {
        $error = match ($ex->errorCode) {
            'DUPLICATE' => 'Email này đã được đăng ký.',
            'PASSWORD_MISMATCH' => 'Hai mật khẩu không khớp.',
            default => errorMessage($ex->errorCode),
        };
    }
}
$title = 'Tạo tài khoản';
$user = null;
$wide = true;
require __DIR__ . '/../templates/header.php';
?>
<div class="auth">
<section class="auth-hero" aria-hidden="false">
<div>
<h1>Góp ý đúng chỗ, sửa đúng chỗ.</h1>
<p>Đặt điểm hoặc khoanh vùng ngay trên ảnh thiết kế, trao đổi theo từng vị trí và theo dõi đến khi bản mới được kiểm tra xong.</p>
<ul>
<li><?= icon('pin') ?> Điểm và vùng gắn theo pixel ảnh gốc, không lệch khi phóng to</li>
<li><?= icon('layers') ?> Mỗi phiên bản giữ nguyên góp ý của nó; so sánh cạnh nhau hoặc trượt</li>
<li><?= icon('flag') ?> Ưu tiên, nhãn, người phụ trách, hạn và ghi chú cho từng góp ý</li>
<li><?= icon('download') ?> Lịch sử đầy đủ, tìm góp ý chưa xử lý, xuất CSV</li>
</ul>
</div>
<div class="auth-mock" aria-hidden="true">
<div class="mock-screen"><span class="mock-bar"></span><span class="mock-bar b2"></span><span class="mock-btn"></span><span class="mock-rect"></span>
<span class="mock-pin p1"><span>1</span></span><span class="mock-pin p2"><span>2</span></span>
<span class="mock-bubble"><strong>Lê Minh Đức</strong><br>Nút “Đặt ngay” nên đưa sang trái.</span></div>
</div>
</section>
<section class="auth-form">
<div class="auth-card">
<h1>Tạo tài khoản</h1>
<p class="hint">Sau khi tạo, bạn có thể lập dự án mới hoặc nhờ quản lý dự án mời bằng email.</p>
<?php if ($error !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="/register.php">
<?= csrfField() ?>
<div class="field"><label for="name">Họ tên</label><input id="name" name="name" type="text" required maxlength="120" value="<?= e(field($_POST, 'name')) ?>"></div>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="200" value="<?= e(field($_POST, 'email')) ?>"></div>
<div class="row"><div class="grow field"><label for="password">Mật khẩu (≥ 8 ký tự)</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
<div class="grow field"><label for="password2">Nhập lại</label><input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div></div>
<button class="btn btn-block" type="submit">Tạo tài khoản</button>
</form>
<p class="hint mt">Đã có tài khoản? <a href="/login.php">Đăng nhập</a></p>
</div>
</section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
