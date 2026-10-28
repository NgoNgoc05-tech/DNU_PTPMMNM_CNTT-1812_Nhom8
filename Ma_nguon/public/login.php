<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$error = '';
$next = field($_GET, 'next');
// Chỉ cho phép chuyển về đường dẫn nội bộ (chống open redirect).
if (!preg_match('#^/[A-Za-z0-9_\-./?=&%]*$#', $next) || str_starts_with($next, '//')) {
    $next = '/projects.php';
}
if (isPost()) {
    checkCsrf($_POST);
    try {
        if (Auth::attempt(db(), field($_POST, 'email'), (string) ($_POST['password'] ?? '')) !== null) {
            redirect($next);
        }
        $error = 'Email hoặc mật khẩu không đúng.';
    } catch (AppError $ex) {
        $error = errorMessage($ex->errorCode);
    }
}
$title = 'Đăng nhập';
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
<h1>Chào mừng trở lại</h1>
<p class="hint">Đăng nhập để tiếp tục góp ý trên bản thiết kế.</p>
<?php if ($error !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="/login.php?next=<?= e(rawurlencode($next)) ?>">
<?= csrfField() ?>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="username" value="<?= e(field($_POST, 'email')) ?>" placeholder="ban@truong.edu.vn"></div>
<div class="field"><div class="label-row"><label for="password">Mật khẩu</label><a class="hint" href="/forgot.php">Quên mật khẩu?</a></div><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button class="btn btn-block" type="submit">Đăng nhập</button>
</form>
<p class="hint mt">Chưa có tài khoản? <a href="/register.php">Tạo tài khoản</a></p>
<div class="card card-tight mt">
<div class="block-title"><?= icon('users') ?> Tài khoản dữ liệu mẫu · mật khẩu <code>Pinpoint@123</code></div>
<div class="demo-accounts mt-s">
<button type="button" data-demo-email="designer@pinpoint.test"><?= avatar('Nguyễn Thu Trang', 0) ?><span><strong>designer@pinpoint.test</strong><small>Người thiết kế · Quản lý cả hai dự án</small></span></button>
<button type="button" data-demo-email="reviewer@pinpoint.test"><?= avatar('Lê Minh Đức', 1) ?><span><strong>reviewer@pinpoint.test</strong><small>Giảng viên · quyền Góp ý</small></span></button>
<button type="button" data-demo-email="viewer@pinpoint.test"><?= avatar('Phạm Gia Huy', 2) ?><span><strong>viewer@pinpoint.test</strong><small>Thành viên · Chỉ xem dự án A</small></span></button>
<button type="button" data-demo-email="hotro@pinpoint.test"><?= avatar('Bộ phận hỗ trợ Pinpoint', 3) ?><span><strong>hotro@pinpoint.test</strong><small>Bộ phận hỗ trợ · trả lời yêu cầu</small></span></button>
<button type="button" data-demo-email="admin@pinpoint.test"><?= avatar('Quản trị hệ thống', 4) ?><span><strong>admin@pinpoint.test</strong><small>Quản trị hệ thống · quản lý toàn bộ dữ liệu</small></span></button>
</div>
</div>
</div>
</section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
