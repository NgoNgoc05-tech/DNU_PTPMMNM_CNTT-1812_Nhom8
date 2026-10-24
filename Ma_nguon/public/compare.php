<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$screenId = intField($_GET, 'screen');
$role = Access::require($c, $user['id'], Access::projectOfScreen($c, $screenId), Access::VIEW);
$screens = new ScreenRepository($c);
$screen = $screens->find($screenId);
$versions = $screens->versions($screenId);
if (count($versions) < 2) {
    flash('Màn hình mới có một phiên bản, chưa thể so sánh.');
    redirect('/screen.php?id=' . $screenId);
}
$byId = [];
foreach ($versions as $v) {
    $byId[(int) $v['id']] = $v;
}
$right = $byId[intField($_GET, 'right')] ?? end($versions);
$left = $byId[intField($_GET, 'left')] ?? null;
if ($left === null || (int) $left['id'] === (int) $right['id']) {
    // Mặc định: bản ngay trước bản bên phải.
    $left = $versions[0];
    foreach ($versions as $v) {
        if ((int) $v['version_no'] < (int) $right['version_no']) {
            $left = $v;
        }
    }
    if ((int) $left['id'] === (int) $right['id']) {
        $left = $versions[1];
    }
}
$projectRepo = new ProjectRepository($c);
$project = $projectRepo->find((int) $screen['project_id']);
$members = $projectRepo->members((int) $screen['project_id']);
$mode = in_array(field($_GET, 'mode'), ['side', 'swipe', 'onion'], true) ? field($_GET, 'mode') : 'side';
$pack = fn (array $v): array => [
    'id' => (int) $v['id'], 'no' => (int) $v['version_no'], 'width' => (int) $v['width'], 'height' => (int) $v['height'],
    'imageUrl' => '/image.php?id=' . (int) $v['id'], 'open' => (int) $v['open_items'], 'total' => (int) $v['annotations'],
];
$config = [
    'left' => $pack($left), 'right' => $pack($right), 'userId' => $user['id'], 'userName' => $user['name'],
    'canComment' => Access::allows($role, Access::COMMENT), 'csrf' => csrfToken(), 'mode' => $mode,
    'projectId' => (int) $project['id'], 'screenId' => $screenId,
    'members' => array_map(fn ($m) => ['id' => (int) $m['id'], 'name' => $m['name'], 'role' => $m['role']], $members),
];
$title = 'So sánh ' . $screen['name'];
$wide = true;
$active = 'projects';
$selector = function (string $name, array $selected) use ($versions): string {
    $html = '<select name="' . $name . '" aria-label="Phiên bản ' . ($name === 'left' ? 'bên trái' : 'bên phải') . '" data-autosubmit>';
    foreach ($versions as $v) {
        $html .= '<option value="' . (int) $v['id'] . '"' . ((int) $v['id'] === (int) $selected['id'] ? ' selected' : '') . '>v'
            . (int) $v['version_no'] . ' · ' . (int) $v['open_items'] . '/' . (int) $v['annotations'] . ' chưa xử lý</option>';
    }
    return $html . '</select>';
};
require __DIR__ . '/../templates/header.php';
?>
<form method="get" action="/compare.php" id="compare-form" class="compare-page">
<input type="hidden" name="screen" value="<?= $screenId ?>">
<input type="hidden" name="mode" value="<?= e($mode) ?>" id="mode-input">
<div class="toolbar">
<span class="crumbs"><a href="/project.php?id=<?= (int) $project['id'] ?>"><?= e($project['name']) ?></a><span class="sep">/</span><a href="/screen.php?id=<?= $screenId ?>"><?= e($screen['name']) ?></a><span class="sep">/</span></span>
<strong class="screen-title"><?= icon('compare') ?>So sánh phiên bản</strong>
<div class="segmented" role="group" aria-label="Chế độ so sánh">
<button type="button" data-mode="side" aria-pressed="<?= $mode === 'side' ? 'true' : 'false' ?>"><?= icon('compare', 'sm') ?>Cạnh nhau</button>
<button type="button" data-mode="swipe" aria-pressed="<?= $mode === 'swipe' ? 'true' : 'false' ?>"><?= icon('sliders', 'sm') ?>Thanh trượt</button>
<button type="button" data-mode="onion" aria-pressed="<?= $mode === 'onion' ? 'true' : 'false' ?>"><?= icon('layers', 'sm') ?>Chồng mờ</button>
</div>
<span class="spacer"></span>
<label class="check"><input type="checkbox" id="sync" checked> Đồng bộ phóng to/kéo</label>
<div class="tool-group" role="group" aria-label="Phóng to">
<button type="button" data-zoom="out" aria-label="Thu nhỏ"><?= icon('minus') ?></button><button type="button" data-zoom="fit" aria-label="Vừa khung"><?= icon('maximize') ?>Vừa</button><button type="button" data-zoom="in" aria-label="Phóng to"><?= icon('plus') ?></button>
</div>
<a class="btn btn-sm btn-ghost" href="/screen.php?id=<?= $screenId ?>&amp;v=<?= (int) $right['id'] ?>"><?= icon('arrow-left') ?>Về trình xem</a>
</div>
<div class="compare mode-<?= e($mode) ?>" id="compare">
<section class="compare-pane left">
<div class="pane-head">
<span class="pane-label"><span class="tag">Bản cũ</span></span> <?= $selector('left', $left) ?>
<span class="compare-legend"><span class="badge badge-open"><?= (int) $left['open_items'] ?> chưa xử lý</span></span>
</div>
<div id="stage-left" class="annotator" data-tool="pan"></div>
</section>
<section class="compare-pane right">
<div class="pane-head">
<span class="pane-label"><span class="tag new">Bản mới</span></span> <?= $selector('right', $right) ?>
<span class="compare-legend" id="overlay-legend" hidden>so với <?= str_replace(['name="left-proxy"', 'data-autosubmit'], ['id="left-proxy"', ''], $selector('left-proxy', $left)) ?></span>
<span class="spacer"></span>
<span class="compare-legend"><?= (int) $right['annotations'] === 0 ? 'chưa có góp ý' : '<span class="badge badge-open">' . (int) $right['open_items'] . ' chưa xử lý</span>' ?></span>
</div>
<div id="stage-right" class="annotator" data-tool="pan"></div>
<div class="overlay-control" id="overlay-control" hidden>
<span>v<?= (int) $left['version_no'] ?></span><input type="range" id="overlay-range" min="0" max="100" value="50" aria-label="Tỷ lệ hiển thị hai phiên bản"><span>v<?= (int) $right['version_no'] ?></span>
</div>
</section>
<aside class="panel">
<div class="panel-head">
<div class="segmented" role="group" aria-label="Danh sách góp ý của">
<button type="button" data-side="left" aria-pressed="true">Góp ý trên v<?= (int) $left['version_no'] ?> <span class="n"><?= (int) $left['annotations'] ?></span></button>
<button type="button" data-side="right" aria-pressed="false">Góp ý trên v<?= (int) $right['version_no'] ?> <span class="n"><?= (int) $right['annotations'] ?></span></button>
</div>
<div class="flash info"><?= icon('layers') ?><span>Mỗi góp ý chỉ hiển thị trên đúng phiên bản đã tạo ra nó. Bấm một góp ý cũ, đối chiếu với bản mới rồi xác nhận — lịch sử ghi lại phiên bản dùng để kiểm tra (v<?= (int) $right['version_no'] ?>).</span></div>
<div class="filters">
<select id="f-status" aria-label="Lọc trạng thái"><option value="">Mọi trạng thái</option><option value="OPEN">Chưa xử lý</option><option value="RESOLVED">Đã xử lý</option></select>
<a class="btn btn-sm btn-ghost" href="/export.php?project=<?= (int) $project['id'] ?>&amp;screen=<?= $screenId ?>"><?= icon('download') ?>Xuất CSV</a>
</div>
</div>
<div class="panel-body" id="panel-body"><p class="empty">Đang tải…</p></div>
</aside>
</div>
</form>
<script type="application/json" id="config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="/assets/js/coords.js"></script>
<script src="/assets/js/annotator.js"></script>
<script src="/assets/js/thread.js"></script>
<script src="/assets/js/compare.js"></script>
<?php require __DIR__ . '/../templates/footer.php'; ?>
