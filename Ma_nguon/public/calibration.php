<?php

declare(strict_types=1);

// PHÉP THỬ 1 (trực quan): ảnh 1000×500, điểm mẫu (750;150) ở nhiều kích thước hiển thị.
// Trang dùng ĐÚNG mã coords.js + annotator.js của trình xem thật; không cần CSDL.
require __DIR__ . '/../src/web.php';
$user = null;
try {
    $user = Auth::user(db());
} catch (Throwable $e) {
    $user = null;
}
$cases = [
    // [mã, mô tả, rộng khung, cao khung, zoom, x màn hình mong đợi, y màn hình mong đợi]
    ['A', 'Hiển thị 500×250 (đúng một nửa, không có lề)', 500, 250, 1, 375, 75],
    ['B', 'Khung 500×400: ảnh 500×250, lề trên/dưới 75 px', 500, 400, 1, 375, 150],
    ['C', 'Khung 800×300: ảnh 600×300, lề trái/phải 100 px', 800, 300, 1, 550, 90],
    ['D', 'Khung 500×250, phóng to 200% quanh tâm khung', 500, 250, 2, 500, 25],
];
$title = 'Phép thử toạ độ';
$active = 'calibration';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head"><div>
<h1>Phép thử 1 — Điểm (750; 150) trên ảnh 1000×500</h1>
<p>Mỗi khung dùng cùng mã đặt dấu của trình xem. Dấu phải nằm đúng tâm nút “Đặt ngay” và vị trí màn hình phải bằng giá trị mong đợi (sai số ≤ 0,5 px).</p>
</div></div>
<div class="stack" id="calibration">
<?php foreach ($cases as [$id, $label, $w, $h, $zoom, $ex, $ey]): ?>
<div class="card">
<h2>Trường hợp <?= $id ?></h2>
<p class="hint"><?= e($label) ?></p>
<div class="calib-box annotator" data-case="<?= $id ?>" data-w="<?= $w ?>" data-h="<?= $h ?>" data-zoom="<?= $zoom ?>" data-ex="<?= $ex ?>" data-ey="<?= $ey ?>"></div>
<p class="calib-result" data-result="<?= $id ?>">Đang kiểm tra…</p>
</div>
<?php endforeach; ?>
<div class="card">
<h2>Thử bằng tay</h2>
<p class="hint">Bấm lên ảnh trong khung B (có lề trống). Toạ độ ảnh gốc hiển thị bên dưới; bấm vào lề trống sẽ bị từ chối.</p>
<p class="calib-result" id="manual">Chưa bấm.</p>
</div>
</div>
<script src="/assets/js/coords.js"></script>
<script src="/assets/js/annotator.js"></script>
<script src="/assets/js/calibration.js"></script>
<?php require __DIR__ . '/../templates/footer.php'; ?>
