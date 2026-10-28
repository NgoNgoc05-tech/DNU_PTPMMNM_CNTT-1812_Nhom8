<?php

declare(strict_types=1);

// Kiểm thử đơn vị: không cần CSDL. Chạy: php tests/run.php
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/bootstrap.php';

echo "-- Phân quyền (ma trận VIEW / COMMENT / ADMIN)\n";
same(Access::allows('VIEW', Access::VIEW), true, 'view-can-view');
same(Access::allows('VIEW', Access::COMMENT), false, 'view-cannot-comment');
same(Access::allows('COMMENT', Access::COMMENT), true, 'comment-can-comment');
same(Access::allows('COMMENT', Access::ADMIN), false, 'comment-cannot-manage');
same(Access::allows('ADMIN', Access::COMMENT), true, 'admin-can-comment');
same(Access::allows(null, Access::VIEW), false, 'non-member-denied');
same(Access::allows('OWNER', Access::VIEW), false, 'unknown-role-denied');

echo "-- Phép thử 1: ảnh 1000×500, điểm mẫu (750; 150)\n";
$a = Coordinates::fitContain(500, 250, 1000, 500);
near($a['scale'], 0.5, 'display-500x250-scale');
[$sx, $sy] = Coordinates::toScreen(750, 150, $a);
near($sx, 375, 'display-500x250-x');
near($sy, 75, 'display-500x250-y');
$b = Coordinates::fitContain(500, 400, 1000, 500);
near($b['top'], 75, 'letterbox-top-margin');
[$sx, $sy] = Coordinates::toScreen(750, 150, $b);
near($sy, 150, 'letterbox-point-y');
[$ix, $iy, $inside] = Coordinates::toImage(375, 150, $b, 1000, 500);
near($ix, 750, 'letterbox-back-x');
near($iy, 150, 'letterbox-back-y');
same($inside, true, 'letterbox-inside');
[, , $inside] = Coordinates::toImage(250, 40, $b, 1000, 500);
same($inside, false, 'click-on-margin-rejected');
$z = Coordinates::fitContain(500, 250, 1000, 500, 2.0);
[$sx, $sy] = Coordinates::toScreen(750, 150, $z);
near($sx, 500, 'zoom-200-x');
near($sy, 25, 'zoom-200-y');

echo "-- Kiểm tra toạ độ ở máy chủ\n";
same(Coordinates::validate('POINT', '750', '150', null, null, 1000, 500)['x'], 750.0, 'point-valid');
throwsCode(fn () => Coordinates::validate('POINT', 1001, 10, null, null, 1000, 500), 'POSITION_OUTSIDE_IMAGE', 'point-outside-x');
throwsCode(fn () => Coordinates::validate('POINT', -1, 10, null, null, 1000, 500), 'POSITION_OUTSIDE_IMAGE', 'point-negative');
throwsCode(fn () => Coordinates::validate('POINT', 'abc', 10, null, null, 1000, 500), 'POSITION_OUTSIDE_IMAGE', 'point-not-number');
$r = Coordinates::validate('RECT', 100.123, 50, 300, 120.456, 1000, 500);
same([$r['x'], $r['w'], $r['h']], [100.12, 300.0, 120.46], 'rect-rounded-2-decimals');
throwsCode(fn () => Coordinates::validate('RECT', 900, 50, 200, 50, 1000, 500), 'POSITION_OUTSIDE_IMAGE', 'rect-overflow-right');
throwsCode(fn () => Coordinates::validate('RECT', 10, 10, 1, 50, 1000, 500), 'RECT_TOO_SMALL', 'rect-too-small');
throwsCode(fn () => Coordinates::validate('CIRCLE', 10, 10, 5, 5, 1000, 500), 'SHAPE_INVALID', 'shape-invalid');

echo "-- Bản 2.3: mũi tên và nét vẽ tay (toạ độ ảnh gốc, kiểm tra ở máy chủ)\n";
$arrow = Coordinates::validate('ARROW', null, null, null, null, 1000, 500, [[100, 100], ['400.25', 300]]);
same([$arrow['x'], $arrow['y'], $arrow['w'], $arrow['points']], [100.0, 100.0, null, [[100.0, 100.0], [400.3, 300.0]]], 'arrow-valid-first-point-is-anchor');
throwsCode(fn () => Coordinates::validate('ARROW', 0, 0, null, null, 1000, 500, [[1, 1], [50, 50], [90, 90]]), 'SHAPE_INVALID', 'arrow-needs-exactly-2-points');
throwsCode(fn () => Coordinates::validate('ARROW', 0, 0, null, null, 1000, 500, [[10, 10], [1001, 10]]), 'POSITION_OUTSIDE_IMAGE', 'arrow-end-outside-image');
throwsCode(fn () => Coordinates::validate('ARROW', 0, 0, null, null, 1000, 500, [[10, 10], [13, 13]]), 'STROKE_TOO_SHORT', 'arrow-too-short');
$draw = Coordinates::validate('DRAW', 0, 0, null, null, 1000, 500, [[10, 10], [20, 15], [30, 10], [40, 18]]);
same([$draw['shape'], count($draw['points'])], ['DRAW', 4], 'draw-valid');
throwsCode(fn () => Coordinates::validate('DRAW', 0, 0, null, null, 1000, 500, array_fill(0, 501, [5, 5])), 'SHAPE_INVALID', 'draw-max-500-points');
throwsCode(fn () => Coordinates::validate('DRAW', 0, 0, null, null, 1000, 500, [[10, 10], ['x' => 5, 'y' => 6]]), 'POSITION_OUTSIDE_IMAGE', 'draw-point-must-be-pair');
throwsCode(fn () => Coordinates::validate('DRAW', 0, 0, null, null, 1000, 500, '[[1,1],[50,50]]'), 'SHAPE_INVALID', 'draw-points-must-be-list');
same(Coordinates::SHAPES['ARROW'], 'Mũi tên', 'shape-label-arrow');

echo "-- Nội dung nhận xét là dữ liệu, không phải mã\n";
same(e('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', 'html-escaped');
same(cleanBody("  Nút  \r\nhơi to "), "Nút  \nhơi to", 'body-trimmed');
throwsCode(fn () => cleanBody('   '), 'BODY_INVALID', 'body-empty');
throwsCode(fn () => cleanBody(str_repeat('a', 2001)), 'BODY_INVALID', 'body-too-long');
same(Export::safeCell('=HYPERLINK("http://x")'), '\'=HYPERLINK("http://x")', 'csv-formula-neutralized');
same(Export::safeCell('Nút đẹp'), 'Nút đẹp', 'csv-normal-text');
$csv = Export::csv(Export::rows([[
    'screen_name' => 'Trang chủ', 'version_no' => 1, 'seq_no' => 1, 'shape' => 'POINT', 'x' => 750.0, 'y' => 150.0,
    'w' => null, 'h' => null, 'image_width' => 1000, 'image_height' => 500, 'status' => 'OPEN', 'author' => 'A',
    'body' => 'Dòng "có nháy", dấu phẩy', 'reply_count' => 0, 'created_at' => '2026-10-21 09:00:00+07',
]]));
same(str_contains($csv, '"Dòng ""có nháy"", dấu phẩy"'), true, 'csv-quotes-escaped');
same(str_contains($csv, '"Trang chủ",v1,#1,Điểm,750,150,,,1000×500,"Chưa xử lý"'), true, 'csv-columns');

echo "-- Bản 2.0: hiển thị dùng chung (ảnh đại diện, nhật ký, cột xuất)\n";
require_once __DIR__ . '/../src/View.php';
same(initials('Nguyễn Thu Trang (Thiết kế)'), 'NT', 'initials-vietnamese-name');
same(initials('Đức'), 'Đ', 'initials-single-word');
same(str_contains(avatar('<b>X</b> Y', 9), '&lt;b&gt;') && str_contains(avatar('A B', 9), 'avatar-c1'), true, 'avatar-escaped-and-colored');
same(eventText(['kind' => 'ASSIGNEE', 'old_value' => '', 'new_value' => '<script>']), 'giao cho <strong>&lt;script&gt;</strong>', 'event-text-escaped');
same(count(Export::HEADER), 22, 'export-22-columns');
same(array_keys(AnnotationRepository::PRIORITIES), ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], 'priority-levels');

echo "-- Bản 2.1: trợ lý ảo (chạy trên máy, không cần mạng)\n";
same(Assistant::fold('Xoá ẢNH tải nhầm?'), 'xoa anh tai nham', 'assistant-fold-vietnamese');
same(Assistant::local('Làm sao xoá ảnh tải nhầm?')['topic'], 'Xoá ảnh tải nhầm', 'assistant-delete-image');
same(Assistant::local('so sánh 2 phiên bản')['topic'], 'So sánh hai phiên bản', 'assistant-compare');
same(Assistant::local('mời bạn vào dự án')['topic'], 'Mời thành viên và cấp quyền', 'assistant-invite');
same(Assistant::local('thời tiết hôm nay')['topic'], '', 'assistant-unknown-question');
same(Assistant::local('tin tức link')['topic'] !== 'Xuất danh sách', true, 'assistant-whole-word-match');
throwsCode(fn () => Assistant::answer(str_repeat('a', 501)), 'CHAT_INVALID', 'assistant-question-length');
same(NotificationRepository::mentionName('Nguyễn Thu Trang (Thiết kế)'), 'Nguyễn Thu Trang', 'mention-name-without-role');

same([Assistant::local('Làm sao vẽ mũi tên?')['topic'], Assistant::local('quên mật khẩu')['topic'], Assistant::local('khoá tài khoản')['topic']],
    ['Mũi tên, vẽ tay và ảnh đính kèm', 'Hồ sơ và mật khẩu', 'Quản trị hệ thống'], 'assistant-v23-topics');

echo "-- Giới hạn ảnh tải lên (chỉ PNG/JPEG, ≤ 5 MB, 16–8000 px)\n";
$tmp = sys_get_temp_dir() . '/pinpoint-unit-' . getmypid();
@mkdir($tmp);
$img = imagecreatetruecolor(1000, 500);
imagepng($img, "$tmp/ok.png");
imagejpeg($img, "$tmp/ok.jpg");
imagegif($img, "$tmp/no.gif");
file_put_contents("$tmp/fake.png", '<?php echo "not an image";');
imagepng(imagecreatetruecolor(10, 10), "$tmp/tiny.png");
$meta = ImageStore::inspect("$tmp/ok.png", (int) filesize("$tmp/ok.png"));
same([$meta['mime'], $meta['width'], $meta['height']], ['image/png', 1000, 500], 'png-accepted');
same(ImageStore::inspect("$tmp/ok.jpg", (int) filesize("$tmp/ok.jpg"))['mime'], 'image/jpeg', 'jpeg-accepted');
throwsCode(fn () => ImageStore::inspect("$tmp/no.gif", (int) filesize("$tmp/no.gif")), 'IMAGE_TYPE_NOT_ALLOWED', 'gif-rejected');
throwsCode(fn () => ImageStore::inspect("$tmp/fake.png", (int) filesize("$tmp/fake.png")), 'IMAGE_TYPE_NOT_ALLOWED', 'fake-png-rejected');
throwsCode(fn () => ImageStore::inspect("$tmp/ok.png", ImageStore::MAX_BYTES + 1), 'IMAGE_TOO_LARGE', 'over-5mb-rejected');
throwsCode(fn () => ImageStore::inspect("$tmp/tiny.png", (int) filesize("$tmp/tiny.png")), 'IMAGE_DIMENSIONS_INVALID', 'tiny-rejected');
throwsCode(fn () => ImageStore::path('../../etc/passwd'), 'NOT_FOUND', 'stored-name-path-traversal');
throwsCode(fn () => ImageStore::fromUpload(['name' => 'a.png', 'tmp_name' => "$tmp/ok.png", 'size' => 100, 'error' => UPLOAD_ERR_OK]), 'IMAGE_MISSING', 'non-uploaded-path-rejected');
array_map('unlink', glob("$tmp/*"));
rmdir($tmp);

finish();
