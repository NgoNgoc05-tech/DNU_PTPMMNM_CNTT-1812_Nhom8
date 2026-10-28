<?php

declare(strict_types=1);

// Bản 2.3 — Quên mật khẩu. Trang luôn trả lời giống nhau để không lộ email nào đã đăng ký.
require __DIR__ . '/../src/web.php';
$sent = false;
if (isPost()) {
    checkCsrf($_POST);
    // Chống bấm liên tục: tối đa 5 lần mỗi 10 phút cho một phiên trình duyệt.
    $_SESSION['forgot'] = array_values(array_filter($_SESSION['forgot'] ?? [], fn ($t) => $t > time() - 600));
    if (count($_SESSION['forgot']) < 5) {
        $_SESSION['forgot'][] = time();
        PasswordReset::request(db(), field($_POST, 'email'), field($_POST, 'contact'));
    }
    $sent = true;
}
$title = 'Quên mật khẩu';
$user = null;
$wide = true;
$mail = PasswordReset::mailConfigured();
require __DIR__ . '/../templates/header.php';
?>
<section class="auth-form auth-solo">
<div class="auth-card card">
<div class="auth-icon"><?= icon('key') ?></div>
<h1>Quên mật khẩu</h1>
<?php if ($sent): ?>
<div class="flash ok" role="status" data-forgot-sent><?= icon('check') ?><span>Đã ghi nhận yêu cầu. Nếu email đã đăng ký, <?= $mail
    ? 'bạn sẽ nhận được thư có link đặt lại mật khẩu trong vài phút.'
    : 'bộ phận hỗ trợ sẽ gửi link đặt lại mật khẩu tới bạn qua cách liên hệ bạn đã ghi.' ?></span></div>
<ul class="hint steps">
<li>Link chỉ dùng được <strong>một lần</strong> và hết hạn sau <strong><?= PasswordReset::TTL_MINUTES ?> phút</strong>.</li>
<li>Không nhận được? Hãy gửi lại yêu cầu hoặc liên hệ quản trị viên của lớp/nhóm.</li>
</ul>
<a class="btn btn-block mt" href="/login.php"><?= icon('arrow-left') ?>Về trang đăng nhập</a>
<?php else: ?>
<p class="hint">Nhập email đã đăng ký. <?= $mail
    ? 'Chúng tôi sẽ gửi link đặt lại mật khẩu (hiệu lực ' . PasswordReset::TTL_MINUTES . ' phút) tới email này.'
    : 'Yêu cầu được chuyển tới bộ phận hỗ trợ; họ sẽ gửi cho bạn link đặt lại mật khẩu (hiệu lực ' . PasswordReset::TTL_MINUTES . ' phút).' ?></p>
<form method="post" action="/forgot.php">
<?= csrfField() ?>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="200" autocomplete="username" placeholder="ban@truong.edu.vn"></div>
<?php if (!$mail): ?>
<div class="field"><label for="contact">Cách liên hệ để nhận link <span class="hint">(không bắt buộc)</span></label>
<input id="contact" name="contact" type="text" maxlength="200" placeholder="Ví dụ: Zalo 09xx…, email khác, nhắn trong nhóm lớp"></div>
<?php endif; ?>
<button class="btn btn-block" type="submit">Gửi yêu cầu đặt lại mật khẩu</button>
</form>
<p class="hint mt">Nhớ ra rồi? <a href="/login.php">Đăng nhập</a></p>
<?php endif; ?>
</div>
</section>
<?php require __DIR__ . '/../templates/footer.php'; ?>
