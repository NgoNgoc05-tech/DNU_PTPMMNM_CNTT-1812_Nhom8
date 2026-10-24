<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$screenId = intField($_GET, 'id');
$role = Access::require($c, $user['id'], Access::projectOfScreen($c, $screenId), Access::VIEW);
$screens = new ScreenRepository($c);
$screen = $screens->find($screenId);
$versions = $screens->versions($screenId);
$versionId = intField($_GET, 'v');
$current = null;
foreach ($versions as $v) {
    if ((int) $v['id'] === $versionId) {
        $current = $v;
    }
}
$current ??= end($versions);
$projectRepo = new ProjectRepository($c);
$project = $projectRepo->find((int) $screen['project_id']);
$members = $projectRepo->members((int) $screen['project_id']);
$siblings = $screens->listForProject((int) $screen['project_id']);
$canComment = Access::allows($role, Access::COMMENT);
$isAdmin = Access::allows($role, Access::ADMIN);
$latest = end($versions);
$latestNo = (int) $latest['version_no'];
$config = [
    'versionId' => (int) $current['id'],
    'versionNo' => (int) $current['version_no'],
    'latestNo' => $latestNo,
    'width' => (int) $current['width'],
    'height' => (int) $current['height'],
    'imageUrl' => '/image.php?id=' . (int) $current['id'],
    'userId' => $user['id'],
    'userName' => $user['name'],
    'canComment' => $canComment,
    'csrf' => csrfToken(),
    'focus' => intField($_GET, 'a'),
    'projectId' => (int) $project['id'],
    'screenId' => $screenId,
    'members' => array_map(fn ($m) => ['id' => (int) $m['id'], 'name' => $m['name'], 'role' => $m['role']], $members),
];
$title = $screen['name'] . ' v' . $current['version_no'];
$wide = true;
$active = 'projects';
require __DIR__ . '/../templates/header.php';
?>
<div class="viewer" id="viewer">
<div class="viewer-main">
<div class="toolbar">
<span class="crumbs"><a href="/project.php?id=<?= (int) $project['id'] ?>"><?= e($project['name']) ?></a><span class="sep">/</span></span>
<details class="dropdown screen-switch">
<summary class="screen-title" title="Chuyển màn hình"><strong><?= e($screen['name']) ?></strong><?= icon('chevron-down', 'sm') ?></summary>
<div class="menu">
<?php foreach ($siblings as $s): ?>
<a href="/screen.php?id=<?= (int) $s['id'] ?>"<?= (int) $s['id'] === $screenId ? ' class="is-current" aria-current="page"' : '' ?>><?= icon('image') ?><?= e($s['name']) ?><span class="n badge<?= (int) $s['open_items'] > 0 ? ' badge-open' : ' badge-resolved' ?>"><?= (int) $s['open_items'] ?></span></a>
<?php endforeach; ?>
</div>
</details>
<nav class="version-pills" aria-label="Phiên bản ảnh">
<?php foreach ($versions as $v): ?>
<a href="/screen.php?id=<?= $screenId ?>&amp;v=<?= (int) $v['id'] ?>"<?= (int) $v['id'] === (int) $current['id'] ? ' aria-current="page"' : '' ?> title="v<?= (int) $v['version_no'] ?> · <?= (int) $v['open_items'] ?>/<?= (int) $v['annotations'] ?> chưa xử lý · <?= e(dateVi($v['created_at'])) ?>">
<span class="dot<?= (int) $v['open_items'] === 0 ? ' zero' : '' ?>"></span>v<?= (int) $v['version_no'] ?></a>
<?php endforeach; ?>
</nav>
<form method="get" action="/screen.php" class="inline sr-only">
<input type="hidden" name="id" value="<?= $screenId ?>">
<select name="v" aria-label="Phiên bản ảnh" data-autosubmit tabindex="-1">
<?php foreach (array_reverse($versions) as $v): ?>
<option value="<?= (int) $v['id'] ?>"<?= (int) $v['id'] === (int) $current['id'] ? ' selected' : '' ?>>v<?= (int) $v['version_no'] ?><?= (int) $v['version_no'] === $latestNo ? ' (mới nhất)' : '' ?> · <?= (int) $v['open_items'] ?>/<?= (int) $v['annotations'] ?> chưa xử lý</option>
<?php endforeach; ?>
</select>
</form>
<?php if (count($versions) > 1): ?><a class="btn btn-sm btn-ghost" href="/compare.php?screen=<?= $screenId ?>&amp;right=<?= (int) $latest['id'] ?>"><?= icon('compare') ?>So sánh phiên bản</a><?php endif; ?>
<span class="spacer"></span>
<?php if (!$canComment): ?><span class="badge badge-muted"><?= icon('eye', 'sm') ?>Chế độ chỉ xem</span><?php endif; ?>
<?php if ($isAdmin): ?>
<button type="button" class="btn btn-sm" data-open="dlg-version"><?= icon('upload') ?>Tải phiên bản mới</button>
<details class="dropdown"><summary class="btn btn-sm btn-ghost btn-icon" title="Thêm" aria-label="Thêm thao tác"><?= icon('settings') ?></summary>
<div class="menu menu-right"><button type="button" data-open="dlg-rename"><?= icon('edit') ?>Đổi tên màn hình</button>
<a href="/export.php?project=<?= (int) $project['id'] ?>&amp;screen=<?= $screenId ?>"><?= icon('download') ?>Xuất CSV màn hình này</a>
<hr>
<?php if (count($versions) > 1): ?><button type="button" class="menu-danger" data-open="dlg-del-version"><?= icon('trash') ?>Xoá phiên bản đang xem (v<?= (int) $current['version_no'] ?>)</button><?php endif; ?>
<button type="button" class="menu-danger" data-open="dlg-del-screen"><?= icon('trash') ?>Xoá màn hình</button></div></details>
<?php else: ?>
<a class="btn btn-sm btn-ghost btn-icon" href="/export.php?project=<?= (int) $project['id'] ?>&amp;screen=<?= $screenId ?>" title="Xuất CSV màn hình này" aria-label="Xuất CSV"><?= icon('download') ?></a>
<?php endif; ?>
<button type="button" class="btn btn-sm btn-subtle btn-icon" data-open="dlg-keys" title="Phím tắt (?)" aria-label="Phím tắt"><?= icon('keyboard') ?></button>
<button type="button" class="btn btn-sm btn-subtle btn-icon" id="toggle-panel" title="Ẩn/hiện bảng góp ý (\)" aria-label="Ẩn/hiện bảng góp ý"><?= icon('sidebar') ?></button>
</div>
<?php if ((int) $current['version_no'] !== $latestNo): ?>
<div class="old-version-bar"><?= icon('alert') ?><span>Bạn đang xem phiên bản cũ <strong>v<?= (int) $current['version_no'] ?></strong>. Góp ý ở đây chỉ thuộc v<?= (int) $current['version_no'] ?>.</span>
<a href="/screen.php?id=<?= $screenId ?>">Mở bản mới nhất v<?= $latestNo ?></a> · <a href="/compare.php?screen=<?= $screenId ?>&amp;left=<?= (int) $current['id'] ?>&amp;right=<?= (int) $latest['id'] ?>">So sánh với v<?= $latestNo ?></a></div>
<?php endif; ?>
<div id="stage" class="annotator" data-tool="<?= $canComment ? 'point' : 'pan' ?>"><div class="viewer-status" id="cursor-info">Ảnh gốc <?= (int) $current['width'] ?>×<?= (int) $current['height'] ?> px · v<?= (int) $current['version_no'] ?> tải lên bởi <?= e($current['uploader']) ?> lúc <?= e(dateVi($current['created_at'])) ?><?= $current['note'] !== '' ? ' · “' . e($current['note']) . '”' : '' ?></div></div>
<div class="dock" role="toolbar" aria-label="Công cụ">
<?php if ($canComment): ?>
<div class="tool-group-dock" role="group" aria-label="Công cụ vẽ">
<button type="button" data-tool="pan" aria-pressed="false" aria-label="Chọn và kéo ảnh"><?= icon('hand') ?><span class="tip">Chọn / kéo<kbd>V</kbd></span></button>
<button type="button" data-tool="point" aria-pressed="true" aria-label="Đặt điểm"><?= icon('pin') ?>Điểm<span class="tip">Đặt điểm góp ý<kbd>C</kbd></span></button>
<button type="button" data-tool="rect" aria-pressed="false" aria-label="Khoanh vùng"><?= icon('square') ?>Vùng<span class="tip">Khoanh vùng chữ nhật<kbd>R</kbd></span></button>
</div>
<span class="sep"></span>
<?php endif; ?>
<button type="button" data-zoom="out" aria-label="Thu nhỏ"><?= icon('minus') ?><span class="tip">Thu nhỏ<kbd>−</kbd></span></button>
<span class="zoom-label" id="zoom-label">100%</span>
<button type="button" data-zoom="in" aria-label="Phóng to"><?= icon('plus') ?><span class="tip">Phóng to<kbd>+</kbd></span></button>
<button type="button" data-zoom="fit" aria-label="Vừa khung"><?= icon('maximize') ?><span class="tip">Vừa khung<kbd>0</kbd></span></button>
<span class="sep"></span>
<button type="button" class="toggle" data-toggle="hideResolved" aria-pressed="false" aria-label="Ẩn góp ý đã xử lý"><?= icon('check-circle') ?><span class="tip">Ẩn góp ý đã xử lý</span></button>
<button type="button" class="toggle" data-toggle="hideMarks" aria-pressed="false" aria-label="Ẩn mọi dấu"><?= icon('eye-off') ?><span class="tip">Ẩn mọi dấu để xem ảnh gốc<kbd>M</kbd></span></button>
</div>
</div>

<aside class="panel" id="panel">
<div class="panel-tabs" role="tablist">
<button type="button" role="tab" aria-selected="true" data-tab="list" id="tab-list"><?= icon('chat') ?>Góp ý <span class="n" id="count-all">0</span></button>
<button type="button" role="tab" aria-selected="false" data-tab="activity" id="tab-activity"><?= icon('activity') ?>Hoạt động</button>
</div>
<div class="panel-head" id="list-controls">
<div class="input-icon"><?= icon('search') ?><input type="search" id="f-q" placeholder="Tìm nội dung, ghi chú, #số…  (/)" aria-label="Tìm trong góp ý"></div>
<div class="segmented" role="group" aria-label="Lọc trạng thái">
<button type="button" data-status="" aria-pressed="true">Tất cả <span class="n" data-count=""></span></button>
<button type="button" data-status="OPEN" aria-pressed="false">Chưa xử lý <span class="n" data-count="OPEN"></span></button>
<button type="button" data-status="RESOLVED" aria-pressed="false">Đã xử lý <span class="n" data-count="RESOLVED"></span></button>
</div>
<div class="filters">
<select id="f-author" aria-label="Lọc người góp ý"><option value="0">Mọi người góp ý</option>
<?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
</select>
<select id="f-priority" aria-label="Lọc mức ưu tiên"><option value="">Mọi mức ưu tiên</option>
<?php foreach (AnnotationRepository::PRIORITIES as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
</select>
<select id="f-category" aria-label="Lọc nhãn"><option value="">Mọi nhãn</option>
<?php foreach (AnnotationRepository::CATEGORIES as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
</select>
<select id="f-sort" aria-label="Sắp xếp"><option value="seq">Theo số thứ tự</option><option value="newest">Mới nhất trước</option><option value="priority">Ưu tiên cao trước</option><option value="status">Chưa xử lý trước</option></select>
</div>
<label class="check"><input type="checkbox" id="f-mine"> Chỉ góp ý giao cho tôi</label>
</div>
<div class="panel-body" id="panel-body"><p class="empty">Đang tải…</p></div>
<div class="panel-body" id="activity-body" hidden><p class="empty">Đang tải…</p></div>
</aside>
</div>

<?php if ($isAdmin): ?>
<dialog class="modal" id="dlg-version" aria-labelledby="dlg-version-title">
<form method="post" action="/version_new.php" enctype="multipart/form-data">
<?= csrfField() ?>
<input type="hidden" name="screen_id" value="<?= $screenId ?>">
<div class="modal-head"><h2 id="dlg-version-title">Tải phiên bản mới (v<?= $latestNo + 1 ?>)</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="dropzone"><?= icon('upload') ?><div><strong>Kéo thả ảnh đã sửa vào đây</strong> hoặc chọn tệp</div><div class="hint" data-file-name>PNG hoặc JPEG · tối đa 5 MB</div>
<input name="image" type="file" accept="image/png,image/jpeg" required aria-label="Ảnh phiên bản mới"></div>
<div class="field mt"><label for="vnote">Đã sửa gì?</label><input id="vnote" name="note" type="text" maxlength="255" placeholder="Ví dụ: Chuyển nút Đặt ngay sang trái" aria-label="Ghi chú phiên bản"></div>
<div class="flash info"><?= icon('layers') ?><span>Góp ý cũ giữ nguyên trên v<?= $latestNo ?>, không bị chuyển sang ảnh mới. Sau khi tải, trang so sánh sẽ mở để bạn đối chiếu và xác nhận.</span></div>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn" type="submit"><?= icon('upload') ?>Tải lên</button></div>
</form>
</dialog>
<?php if (count($versions) > 1): ?>
<dialog class="modal" id="dlg-del-version" aria-labelledby="dlg-delv-title">
<form method="post" action="/delete.php">
<?= csrfField() ?>
<input type="hidden" name="what" value="version">
<input type="hidden" name="version_id" value="<?= (int) $current['id'] ?>">
<div class="modal-head"><h2 id="dlg-delv-title">Xoá phiên bản v<?= (int) $current['version_no'] ?>?</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="flash error"><?= icon('alert') ?><span>Ảnh v<?= (int) $current['version_no'] ?> (<?= e($current['original_name']) ?>) cùng <strong><?= (int) $current['annotations'] ?> góp ý</strong> trên phiên bản này sẽ bị xoá vĩnh viễn. Các phiên bản khác giữ nguyên.</span></div>
<p class="hint">Dùng khi tải nhầm ảnh. Không hoàn tác được.</p>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn btn-danger-solid" type="submit"><?= icon('trash') ?>Xoá phiên bản</button></div>
</form>
</dialog>
<?php endif; ?>
<dialog class="modal" id="dlg-del-screen" aria-labelledby="dlg-dels-title">
<form method="post" action="/delete.php">
<?= csrfField() ?>
<input type="hidden" name="what" value="screen">
<input type="hidden" name="screen_id" value="<?= $screenId ?>">
<div class="modal-head"><h2 id="dlg-dels-title">Xoá màn hình “<?= e($screen['name']) ?>”?</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="flash error"><?= icon('alert') ?><span>Toàn bộ <strong><?= count($versions) ?> phiên bản ảnh</strong> và mọi góp ý, trả lời, lịch sử của màn hình này sẽ bị xoá vĩnh viễn.</span></div>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn btn-danger-solid" type="submit"><?= icon('trash') ?>Xoá màn hình</button></div>
</form>
</dialog>
<dialog class="modal" id="dlg-rename" aria-labelledby="dlg-rename-title">
<form method="post" action="/project_edit.php">
<?= csrfField() ?>
<input type="hidden" name="action" value="screen">
<input type="hidden" name="screen_id" value="<?= $screenId ?>">
<div class="modal-head"><h2 id="dlg-rename-title">Đổi tên màn hình</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body"><div class="field"><label for="rname">Tên màn hình</label><input id="rname" name="name" type="text" required maxlength="120" value="<?= e($screen['name']) ?>"></div></div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn" type="submit">Lưu</button></div>
</form>
</dialog>
<?php endif; ?>
<dialog class="modal" id="dlg-keys" aria-labelledby="dlg-keys-title">
<div class="modal-head"><h2 id="dlg-keys-title">Phím tắt</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body shortcuts">
<?php if ($canComment): ?><span><kbd>C</kbd> / <kbd>P</kbd></span><span>Đặt điểm góp ý</span><span><kbd>R</kbd></span><span>Khoanh vùng chữ nhật</span><?php endif; ?>
<span><kbd>V</kbd> / <kbd>H</kbd></span><span>Chọn và kéo ảnh (giữ <kbd>Shift</kbd> hoặc chuột giữa để kéo tạm thời)</span>
<span><kbd>+</kbd> <kbd>−</kbd> <kbd>0</kbd></span><span>Phóng to, thu nhỏ, vừa khung (cuộn chuột để phóng quanh con trỏ)</span>
<span><kbd>←</kbd> <kbd>→</kbd> / <kbd>J</kbd> <kbd>K</kbd></span><span>Góp ý trước / sau</span>
<span><kbd>M</kbd></span><span>Ẩn/hiện mọi dấu</span>
<span><kbd>/</kbd></span><span>Tìm trong góp ý</span>
<span><kbd>\</kbd></span><span>Ẩn/hiện bảng góp ý</span>
<span><kbd>Esc</kbd></span><span>Đóng góp ý đang mở / huỷ dấu nháp</span>
<span><kbd>Ctrl</kbd>+<kbd>Enter</kbd></span><span>Gửi góp ý hoặc trả lời</span>
<span><kbd>@</kbd></span><span>Nhắc tên thành viên trong nhận xét</span>
</div>
</dialog>
<script type="application/json" id="config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="/assets/js/coords.js"></script>
<script src="/assets/js/annotator.js"></script>
<script src="/assets/js/thread.js"></script>
<script src="/assets/js/screen.js"></script>
<?php require __DIR__ . '/../templates/footer.php'; ?>
