<?php

declare(strict_types=1);

// Bản 2.3 — Đặt mật khẩu mới bằng link (dùng một lần, hết hạn sau 30 phút).
require __DIR__ . '/../src/web.php';
header('Referrer-Policy: no-referrer'); // không để mã trong URL lọt sang trang khác
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$c = db();
$error = '';
$done = false;
if (isPost()) {
    checkCsrf($_POST);
    try {
        PasswordReset::reset($c, $token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
        Auth::logout();
        $done = true;
    } catch (AppError $ex) {
        $error = errorMessage($ex->errorCode);
    }
}
$target = $done ? null : PasswordReset::find($c, $token);
$title = 'Đặt lại mật khẩu';
$user = null;
$wide = true;
require __DIR__ . '/../templates/header.php';
?>
<section class="auth-form auth-solo">
<div class="auth-card card">
<div class="auth-icon"><?= icon('lock') ?></div>
<h1>Đặt lại mật khẩu</h1>
<?php if ($done): ?>
<div class="flash ok" role="status" data-reset-done><?= icon('check') ?><span>Đã đổi mật khẩu. Hãy đăng nhập bằng mật khẩu mới.</span></div>
<a class="btn btn-block mt" href="/login.php">Đăng nhập</a>
<?php elseif ($target === null): ?>
<div class="flash error" role="alert" data-reset-invalid><?= icon('alert') ?><span><?= e(errorMessage('RESET_TOKEN_INVALID')) ?></span></div>
<a class="btn btn-block mt" href="/forgot.php">Yêu cầu link mới</a>
<?php else: ?>
<p class="hint">Tài khoản <strong><?= e($target['email']) ?></strong>. Link còn hiệu lực trong tối đa <?= PasswordReset::TTL_MINUTES ?> phút kể từ lúc cấp.</p>
<?php if ($error !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="/reset_password.php">
<?= csrfField() ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<div class="field"><label for="password">Mật khẩu mới (≥ 8 ký tự)</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
<div class="field"><label for="password2">Nhập lại mật khẩu mới</label><input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div>
<button class="btn btn-block" type="submit">Lưu mật khẩu mới</button>
</form>
<?php endif; ?>
</div>
</section>
<?php require __DIR__ . '/../templates/footer.php'; ?>
