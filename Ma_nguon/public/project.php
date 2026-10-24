<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$projectId = intField($_GET, 'id');
$role = Access::require($c, $user['id'], $projectId, Access::VIEW);
$projects = new ProjectRepository($c);
$project = $projects->find($projectId);
$screens = (new ScreenRepository($c))->listForProject($projectId);
$members = $projects->members($projectId);
$annotations = new AnnotationRepository($c);
$stats = $annotations->stats($projectId);
$activity = $annotations->activity($projectId, 8);
$isAdmin = Access::allows($role, Access::ADMIN);
$title = $project['name'];
$active = 'projects';
$issues = '/issues.php?project=' . $projectId;
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div>
<div class="crumbs"><a href="/projects.php">Dự án</a><span class="sep">/</span><span><?= e($project['name']) ?></span></div>
<h1><?= e($project['name']) ?></h1>
<p><?= e($project['description'] !== '' ? $project['description'] : 'Chưa có mô tả.') ?></p>
</div>
<div class="actions">
<span class="badge badge-role"><?= icon('user', 'sm') ?>Quyền của bạn: <?= e(Access::LABELS[$role]) ?></span>
<a class="btn btn-ghost" href="<?= $issues ?>&amp;status=OPEN"><?= icon('search') ?>Góp ý chưa xử lý</a>
<a class="btn btn-ghost" href="/export.php?project=<?= $projectId ?>"><?= icon('download') ?>Xuất CSV</a>
<?php if ($isAdmin): ?>
<button type="button" class="btn btn-ghost btn-icon" data-open="dlg-edit-project" title="Sửa thông tin dự án" aria-label="Sửa thông tin dự án"><?= icon('settings') ?></button>
<button type="button" class="btn" data-open="dlg-new-screen"><?= icon('upload') ?>Thêm màn hình</button>
<?php endif; ?>
</div>
</div>

<div class="grid grid-4">
<a class="stat" href="<?= $issues ?>&amp;status=OPEN"><span class="stat-icon pin"><?= icon('pin') ?></span><span><strong><?= $stats['open'] ?></strong><span>Chưa xử lý</span></span></a>
<a class="stat" href="<?= $issues ?>&amp;status=RESOLVED"><span class="stat-icon ok"><?= icon('check-circle') ?></span><span><strong><?= $stats['resolved'] ?></strong><span>Đã xử lý · <?= $stats['progress'] ?>%</span></span></a>
<a class="stat" href="<?= $issues ?>&amp;overdue=1"><span class="stat-icon danger"><?= icon('clock') ?></span><span><strong><?= $stats['overdue'] ?></strong><span>Quá hạn</span></span></a>
<a class="stat" href="<?= $issues ?>&amp;status=OPEN&amp;sort=priority"><span class="stat-icon warn"><?= icon('flag') ?></span><span><strong><?= $stats['high'] ?></strong><span>Ưu tiên cao/khẩn</span></span></a>
<a class="stat" href="<?= $issues ?>&amp;status=OPEN&amp;assignee=-1"><span class="stat-icon"><?= icon('user') ?></span><span><strong><?= $stats['unassigned'] ?></strong><span>Chưa giao người</span></span></a>
</div>
<div class="progress-row mt"><progress class="bar" max="<?= max(1, $stats['total']) ?>" value="<?= $stats['resolved'] ?>"></progress><span>Tiến độ xử lý <?= $stats['progress'] ?>% (<?= $stats['resolved'] ?>/<?= $stats['total'] ?>)</span></div>

<div class="layout-side mt">
<section>
<div class="section-title"><h2><?= icon('image') ?>Màn hình thiết kế <span class="count"><?= count($screens) ?></span></h2></div>
<?php if ($screens === []): ?>
<div class="card empty-state">
<div class="empty-art"><?= icon('image') ?></div>
<h3>Dự án chưa có màn hình thiết kế</h3>
<p><?= $isAdmin ? 'Tải ảnh PNG hoặc JPEG đầu tiên và đặt tên cho màn hình.' : 'Quản lý dự án sẽ tải ảnh thiết kế lên.' ?></p>
<?php if ($isAdmin): ?><button type="button" class="btn" data-open="dlg-new-screen"><?= icon('upload') ?>Thêm màn hình</button><?php endif; ?>
</div>
<?php else: ?>
<div class="grid grid-3">
<?php foreach ($screens as $s): ?>
<?php $tot = (int) $s['total_items']; $open = (int) $s['open_items']; ?>
<div class="card screen-card" data-screen="<?= (int) $s['id'] ?>">
<a class="thumb" href="/screen.php?id=<?= (int) $s['id'] ?>"><img src="/image.php?id=<?= (int) $s['latest_version_id'] ?>" alt="<?= e($s['name']) ?>" loading="lazy">
<span class="thumb-badges"><span class="badge">v<?= (int) $s['latest_version_no'] ?></span><?php if ($open > 0): ?><span class="badge badge-open"><?= $open ?> chưa xử lý</span><?php endif; ?></span></a>
<div class="card-body">
<h2><a href="/screen.php?id=<?= (int) $s['id'] ?>"><?= e($s['name']) ?></a></h2>
<span class="progress-row"><progress class="bar" max="<?= max(1, $tot) ?>" value="<?= $tot - $open ?>"></progress><span class="nowrap"><?= $tot - $open ?>/<?= $tot ?></span></span>
<div class="card-foot">
<span class="meta"><span><?= icon('layers') ?><?= (int) $s['versions'] ?> phiên bản</span><span><?= (int) $s['width'] ?>×<?= (int) $s['height'] ?></span><span><?= icon('clock') ?><?= e(ago($s['updated_at'])) ?></span></span>
</div>
<?php if ((int) $s['versions'] > 1): ?>
<a class="btn btn-ghost btn-sm" href="/compare.php?screen=<?= (int) $s['id'] ?>"><?= icon('compare') ?>So sánh hai phiên bản</a>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php if ($isAdmin): ?>
<button type="button" class="card new-card" data-open="dlg-new-screen"><span><?= icon('upload') ?><br><strong>Thêm màn hình</strong><br><span class="hint">PNG/JPEG ≤ 5 MB</span></span></button>
<?php endif; ?>
</div>
<?php endif; ?>
</section>

<aside class="stack">
<div class="card">
<div class="section-title"><h2><?= icon('users') ?>Thành viên <span class="count"><?= count($members) ?></span></h2></div>
<ul class="member-list">
<?php foreach ($members as $m): ?>
<li>
<?= avatar($m['name'], (int) $m['id']) ?>
<div class="who"><?= e($m['name']) ?><?= (int) $m['id'] === $user['id'] ? ' <span class="faint">(bạn)</span>' : '' ?><small><?= e($m['email']) ?> · <?= (int) $m['authored'] ?> góp ý<?= (int) $m['assigned_open'] > 0 ? ' · đang phụ trách ' . (int) $m['assigned_open'] : '' ?></small></div>
<?php if ($isAdmin && (int) $m['id'] !== (int) $project['created_by']): ?>
<form method="post" action="/members.php">
<?= csrfField() ?>
<input type="hidden" name="project_id" value="<?= $projectId ?>">
<input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
<input type="hidden" name="action" value="role">
<select name="role" aria-label="Quyền của <?= e($m['name']) ?>" data-autosubmit>
<?php foreach (Access::ROLES as $r): ?>
<option value="<?= $r ?>"<?= $r === $m['role'] ? ' selected' : '' ?>><?= e(Access::LABELS[$r]) ?></option>
<?php endforeach; ?>
</select>
<noscript><button class="btn btn-sm" type="submit">Lưu</button></noscript>
</form>
<form method="post" action="/members.php" data-confirm="Xoá <?= e($m['name']) ?> khỏi dự án?">
<?= csrfField() ?>
<input type="hidden" name="project_id" value="<?= $projectId ?>">
<input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
<input type="hidden" name="action" value="remove">
<button class="btn btn-subtle btn-icon btn-sm" type="submit" aria-label="Xoá <?= e($m['name']) ?>" title="Xoá khỏi dự án"><?= icon('x') ?></button>
</form>
<?php else: ?>
<span class="badge badge-role"><?= e(Access::LABELS[$m['role']]) ?><?= (int) $m['id'] === (int) $project['created_by'] ? ' · chủ dự án' : '' ?></span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php if ($isAdmin): ?>
<form method="post" action="/members.php" class="mt">
<?= csrfField() ?>
<input type="hidden" name="project_id" value="<?= $projectId ?>">
<input type="hidden" name="action" value="add">
<label for="memail">Mời thành viên</label>
<div class="field"><input id="memail" name="email" type="email" required placeholder="email đã đăng ký tài khoản"></div>
<div class="row center">
<div class="grow"><select id="mrole" name="role" aria-label="Quyền khi mời">
<option value="VIEW">Chỉ xem — xem ảnh, góp ý, xuất danh sách</option><option value="COMMENT" selected>Góp ý — tạo, trả lời, xử lý góp ý</option><option value="ADMIN">Quản lý — thêm ảnh, quản lý thành viên</option>
</select></div>
<button class="btn" type="submit"><?= icon('plus') ?>Mời</button>
</div>
</form>
<?php endif; ?>
</div>

<div class="card">
<div class="section-title"><h2><?= icon('activity') ?>Hoạt động gần đây</h2><a class="hint" href="<?= $issues ?>#activity">Tất cả</a></div>
<?php if ($activity === []): ?>
<p class="hint">Chưa có hoạt động.</p>
<?php else: ?>
<ul class="feed">
<?php foreach ($activity as $ev): ?>
<li><?= eventIcon($ev) ?><span class="feed-body"><strong><?= e($ev['actor']) ?></strong> <?= eventText($ev) ?> ·
<a href="/screen.php?id=<?= (int) $ev['screen_id'] ?>&amp;v=<?= (int) $ev['version_id'] ?>&amp;a=<?= (int) $ev['annotation_id'] ?>"><?= e($ev['screen_name']) ?> v<?= (int) $ev['version_no'] ?> #<?= (int) $ev['seq_no'] ?></a>
<time><?= e(ago($ev['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</aside>
</div>

<?php if ($isAdmin): ?>
<dialog class="modal" id="dlg-new-screen" aria-labelledby="dlg-screen-title">
<form method="post" action="/screen_new.php" enctype="multipart/form-data">
<?= csrfField() ?>
<input type="hidden" name="project_id" value="<?= $projectId ?>">
<div class="modal-head"><h2 id="dlg-screen-title">Thêm màn hình thiết kế</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="field"><label for="sname">Tên màn hình</label><input id="sname" name="name" type="text" required maxlength="120" placeholder="Ví dụ: Trang chủ, Giỏ hàng"></div>
<div class="dropzone"><?= icon('upload') ?><div><strong>Kéo thả ảnh vào đây</strong> hoặc chọn tệp</div><div class="hint" data-file-name>PNG hoặc JPEG · tối đa 5 MB · 16–8000 px mỗi chiều</div>
<input id="simage" name="image" type="file" accept="image/png,image/jpeg" required aria-label="Ảnh PNG/JPEG (≤ 5 MB)"></div>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn" type="submit"><?= icon('upload') ?>Tải lên</button></div>
</form>
</dialog>
<dialog class="modal" id="dlg-edit-project" aria-labelledby="dlg-edit-title">
<form method="post" action="/project_edit.php">
<?= csrfField() ?>
<input type="hidden" name="project_id" value="<?= $projectId ?>">
<input type="hidden" name="action" value="project">
<div class="modal-head"><h2 id="dlg-edit-title">Thông tin dự án</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="field"><label for="pname">Tên dự án</label><input id="pname" name="name" type="text" required maxlength="150" value="<?= e($project['name']) ?>"></div>
<div class="field"><label for="pdesc">Mô tả</label><textarea id="pdesc" name="description" maxlength="2000"><?= e($project['description']) ?></textarea></div>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn" type="submit">Lưu</button></div>
</form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
