<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$repo = new ProjectRepository($c);
$error = '';
if (isPost()) {
    checkCsrf($_POST);
    try {
        $id = $repo->create(field($_POST, 'name'), field($_POST, 'description'), $user['id']);
        flash('Đã tạo dự án. Bạn là Quản lý của dự án này — hãy thêm màn hình thiết kế đầu tiên.');
        redirect('/project.php?id=' . $id);
    } catch (AppError $ex) {
        $error = errorMessage($ex->errorCode);
    }
}
$f = ['q' => field($_GET, 'q'), 'view' => field($_GET, 'view') === 'archived' ? 'archived' : 'active',
    'role' => in_array(field($_GET, 'role'), ['ADMIN', 'COMMENT', 'VIEW', 'OWNER'], true) ? field($_GET, 'role') : '',
    'state' => in_array(field($_GET, 'state'), ['open', 'done', 'empty'], true) ? field($_GET, 'state') : '',
    'sort' => in_array(field($_GET, 'sort'), ['activity', 'name', 'open', 'created'], true) ? field($_GET, 'sort') : 'activity'];
// Bản 2.5: lọc, sắp xếp và phân trang ngay trong CSDL — mỗi trang chỉ lấy 9/18/36 dự án.
$views = $repo->viewCounts($user['id']);
$pg = Paginator::fromRequest($repo->countForUser($user['id'], $f), $_GET, 9, [9, 18, 36]);
$projects = $repo->pageForUser($user['id'], $f, $pg->perPage, $pg->offset());
$filtering = $f['q'] !== '' || $f['role'] !== '' || $f['state'] !== '';
$annotations = new AnnotationRepository($c);
$mine = $annotations->assignedTo($user['id'], 5);
$myCountAll = $annotations->countAssigned($user['id']);
$invites = $repo->invitationsFor($user['id']);
$hour = (int) date('G');
$greet = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
$firstName = preg_split('/\s+/u', trim((string) preg_replace('/\(.*?\)/u', '', $user['name'])));
$title = 'Dự án';
$active = 'projects';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div>
<h1 class="greet"><?= e($greet) ?>, <?= e(end($firstName)) ?> <span class="greet-icon" aria-hidden="true"><?= icon('wave') ?></span></h1>
<?php if ($invites !== []): ?><p class="summary-line"><a class="summary-pill invite" href="#invites"><?= icon('users', 'sm') ?><strong><?= count($invites) ?></strong> lời mời tham gia dự án đang chờ bạn trả lời</a></p><?php endif; ?>
</div>
<div class="actions"><button type="button" class="btn" data-open="dlg-new-project"><?= icon('plus') ?>Dự án mới</button></div>
</div>
<?php if ($error !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

<?php if ($invites !== []): ?>
<section class="invites" id="invites">
<div class="section-title"><h2><?= icon('inbox') ?>Lời mời tham gia dự án <span class="count"><?= count($invites) ?></span></h2></div>
<div class="invite-list">
<?php foreach ($invites as $iv): ?>
<div class="card invite-card" data-invitation="<?= (int) $iv['id'] ?>">
<div class="invite-main">
<?= avatar($iv['invited_by'] ?? '?', (int) ($iv['invited_by_id'] ?? 0), 'lg') ?>
<div>
<div><strong><?= e($iv['invited_by'] ?? 'Ai đó') ?></strong> mời bạn tham gia <strong><?= e($iv['project_name']) ?></strong> với quyền <span class="badge badge-role"><?= e(Access::LABELS[$iv['role']]) ?></span></div>
<div class="hint"><?= e($iv['description'] !== '' ? $iv['description'] : 'Chưa có mô tả') ?> · <?= (int) $iv['screens'] ?> màn hình · <?= (int) $iv['members'] ?> thành viên · <?= e(ago($iv['created_at'])) ?></div>
<?php if ($iv['message'] !== ''): ?><div class="quote">“<?= e($iv['message']) ?>”</div><?php endif; ?>
</div>
</div>
<div class="invite-actions">
<form method="post" action="/invitation.php"><?= csrfField() ?><input type="hidden" name="invitation_id" value="<?= (int) $iv['id'] ?>"><input type="hidden" name="answer" value="decline">
<button class="btn btn-ghost" type="submit">Từ chối</button></form>
<form method="post" action="/invitation.php"><?= csrfField() ?><input type="hidden" name="invitation_id" value="<?= (int) $iv['id'] ?>"><input type="hidden" name="answer" value="accept">
<button class="btn" type="submit"><?= icon('check') ?>Chấp nhận</button></form>
</div>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<div class="layout-side">
<section>
<div class="section-title"><h2><?= icon('folder') ?><?= $f['view'] === 'archived' ? 'Dự án đã lưu trữ' : 'Dự án của tôi' ?> <span class="count"><?= $pg->total ?></span></h2>
<nav class="segmented" aria-label="Dự án đang hoạt động hoặc đã lưu trữ">
<a href="/projects.php"<?= $f['view'] === 'active' ? ' aria-current="page"' : '' ?>>Đang hoạt động <span class="n"><?= $views['active'] ?></span></a>
<a href="/projects.php?view=archived"<?= $f['view'] === 'archived' ? ' aria-current="page"' : '' ?>><?= icon('archive', 'sm') ?>Đã lưu trữ <span class="n"><?= $views['archived'] ?></span></a>
</nav></div>
<form method="get" action="/projects.php" class="project-filter" role="search" id="project-filter">
<?php if ($f['view'] === 'archived'): ?><input type="hidden" name="view" value="archived"><?php endif; ?>
<input type="hidden" name="per" value="<?= $pg->perPage ?>">
<div class="pf-search"><?= icon('search', 'sm') ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Tìm theo tên dự án, mô tả, người tạo… (Enter để tìm mọi trang)" aria-label="Tìm dự án" data-live-filter></div>
<select name="role" aria-label="Lọc theo quyền" data-autosubmit>
<?php foreach (['' => 'Mọi quyền', 'OWNER' => 'Tôi tạo', 'ADMIN' => 'Quản lý', 'COMMENT' => 'Góp ý', 'VIEW' => 'Chỉ xem'] as $k => $label): ?><option value="<?= $k ?>"<?= $f['role'] === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select>
<select name="state" aria-label="Lọc theo tiến độ" data-autosubmit>
<?php foreach (['' => 'Mọi tiến độ', 'open' => 'Còn góp ý chưa xử lý', 'done' => 'Đã xử lý hết', 'empty' => 'Chưa có góp ý'] as $k => $label): ?><option value="<?= $k ?>"<?= $f['state'] === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select>
<select name="sort" aria-label="Sắp xếp" data-autosubmit>
<?php foreach (['activity' => 'Hoạt động gần nhất', 'open' => 'Nhiều góp ý chờ nhất', 'name' => 'Tên A → Z', 'created' => 'Mới tạo trước'] as $k => $label): ?><option value="<?= $k ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>>Sắp xếp: <?= $label ?></option><?php endforeach; ?>
</select>
<noscript><button class="btn btn-ghost btn-sm" type="submit">Lọc</button></noscript>
<?php if ($filtering): ?><a class="btn btn-subtle btn-sm" href="/projects.php<?= $f['view'] === 'archived' ? '?view=archived' : '' ?>"><?= icon('x', 'sm') ?>Bỏ lọc</a><?php endif; ?>
</form>
<?php if ($projects === [] && ($filtering || $f['view'] === 'archived')): ?>
<div class="card empty-state">
<div class="empty-art"><?= icon($f['view'] === 'archived' ? 'archive' : 'search') ?></div>
<h3><?= $filtering ? 'Không có dự án phù hợp' : 'Chưa có dự án nào được lưu trữ' ?></h3>
<p><?= $filtering ? 'Thử từ khoá khác hoặc bỏ bớt bộ lọc.' : 'Dự án đã xong có thể lưu trữ ở nút Cài đặt (bánh răng) trong trang dự án để danh sách gọn hơn; dữ liệu vẫn giữ nguyên.' ?></p>
</div>
<?php elseif ($projects === []): ?>
<div class="card empty-state">
<div class="empty-art"><?= icon('folder') ?></div>
<h3>Bạn chưa thuộc dự án nào</h3>
<p>Tạo dự án mới hoặc nhờ quản lý dự án mời bạn bằng email đã đăng ký, rồi chấp nhận lời mời ở đầu trang này.</p>
<button type="button" class="btn" data-open="dlg-new-project"><?= icon('plus') ?>Tạo dự án đầu tiên</button>
</div>
<?php else: ?>
<div class="grid grid-3">
<?php foreach ($projects as $p): ?>
<?php $names = $p['member_names'] !== null ? explode('|', $p['member_names']) : []; $total = (int) $p['total_items']; $resolved = $total - (int) $p['open_items']; ?>
<a class="card project-card<?= $p['archived_at'] !== null ? ' is-archived' : '' ?>" href="/project.php?id=<?= (int) $p['id'] ?>" data-project="<?= (int) $p['id'] ?>" data-name="<?= e(mb_strtolower($p['name'] . ' ' . $p['description'] . ' ' . $p['owner_name'])) ?>">
<span class="thumb<?= $p['cover_version_id'] === null ? ' thumb-empty' : '' ?>">
<?php if ($p['cover_version_id'] !== null): ?><img src="/image.php?id=<?= (int) $p['cover_version_id'] ?>" alt="" loading="lazy"><?php else: ?><?= icon('image') ?><?php endif; ?>
<span class="thumb-badges"><?php if ($p['archived_at'] !== null): ?><span class="badge badge-muted"><?= icon('archive', 'sm') ?>Lưu trữ</span><?php endif; ?><span class="badge badge-role"><?= e(Access::LABELS[$p['role']]) ?></span><?php if ((int) $p['open_items'] > 0): ?><span class="badge badge-open"><?= (int) $p['open_items'] ?> chưa xử lý</span><?php endif; ?></span>
</span>
<span class="card-body">
<h2><?= e($p['name']) ?></h2>
<span class="hint line-clamp-2"><?= e($p['description'] !== '' ? $p['description'] : 'Chưa có mô tả') ?></span>
<span class="progress-row"><progress class="bar" max="<?= max(1, $total) ?>" value="<?= $resolved ?>"></progress><span class="nowrap"><?= $resolved ?>/<?= $total ?> đã xử lý</span></span>
<span class="card-foot">
<span class="meta"><span><?= icon('image') ?><?= (int) $p['screens'] ?> màn hình</span><span><?= icon('clock') ?><?= e($p['last_activity'] ? ago($p['last_activity']) : 'chưa có hoạt động') ?></span></span>
<span class="avatar-stack"><?php foreach (array_slice($names, 0, 4) as $n): [$nid, $nname] = explode(':', $n, 2); ?><?= avatar($nname, (int) $nid, 'sm') ?><?php endforeach; ?><?php if (count($names) > 4): ?><span class="avatar avatar-sm avatar-more">+<?= count($names) - 4 ?></span><?php endif; ?></span>
</span>
</span>
</a>
<?php endforeach; ?>
<?php if ($f['view'] === 'active' && !$filtering && $pg->page === $pg->pages): ?><button type="button" class="card new-card" data-open="dlg-new-project"><span><?= icon('plus') ?><br><strong>Tạo dự án mới</strong><br><span class="hint">Mời thành viên, tải ảnh thiết kế</span></span></button><?php endif; ?>
<p class="hint live-empty" id="live-empty" hidden>Không có dự án nào khớp từ khoá ở trang này — bấm Enter để tìm trong mọi trang.</p>
</div>
<?= $pg->render('/projects.php', ['view' => $f['view'] === 'archived' ? 'archived' : '', 'q' => $f['q'], 'role' => $f['role'], 'state' => $f['state'], 'sort' => $f['sort'] === 'activity' ? '' : $f['sort']], 'dự án') ?>
<?php endif; ?>
</section>

<aside class="stack">
<div class="card">
<div class="section-title"><h2><?= icon('inbox') ?>Việc của tôi <span class="count"><?= $myCountAll ?></span></h2><a class="hint" href="/inbox.php">Xem tất cả</a></div>
<p class="hint mt-0">Góp ý chưa xử lý giao cho bạn phụ trách, gần hạn trước.</p>
<?php if ($mine === []): ?>
<p class="hint empty-ok"><?= icon('check-circle', 'sm') ?>Không có góp ý nào đang chờ bạn xử lý.</p>
<?php else: ?>
<ul class="feed">
<?php foreach ($mine as $m): ?>
<li><span class="feed-icon k-CREATED"><?= icon('pin') ?></span><span class="feed-body">
<a href="/screen.php?id=<?= (int) $m['screen_id'] ?>&amp;v=<?= (int) $m['image_version_id'] ?>&amp;a=<?= (int) $m['id'] ?>"><strong><?= e($m['screen_name']) ?> v<?= (int) $m['version_no'] ?> #<?= (int) $m['seq_no'] ?></strong></a>
<?= prioBadge($m['priority']) ?> <?= dueBadge($m['due_date'], $m['overdue']) ?>
<span class="quote line-clamp-2"><?= e($m['body']) ?></span><time><?= e($m['project_name']) ?></time></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
<div class="card">
<h2><?= icon('sparkle') ?>Bắt đầu nhanh</h2>
<ol class="hint">
<li>Tạo dự án và mời thành viên với quyền <em>Chỉ xem</em>, <em>Góp ý</em> hoặc <em>Quản lý</em>.</li>
<li>Tải ảnh PNG/JPEG cho từng màn hình thiết kế.</li>
<li>Bấm <kbd>C</kbd> đặt điểm hoặc <kbd>R</kbd> khoanh vùng, rồi viết góp ý.</li>
<li>Tải phiên bản mới, so sánh và đánh dấu đã xử lý.</li>
</ol>
</div>
</aside>
</div>

<dialog class="modal" id="dlg-new-project" aria-labelledby="dlg-new-title">
<form method="post" action="/projects.php">
<?= csrfField() ?>
<div class="modal-head"><h2 id="dlg-new-title">Tạo dự án mới</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" data-close aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="modal-body">
<div class="field"><label for="name">Tên dự án</label><input id="name" name="name" type="text" required maxlength="150" placeholder="Ví dụ: Ứng dụng đặt món FoodNow"></div>
<div class="field"><label for="description">Mô tả</label><textarea id="description" name="description" maxlength="2000" placeholder="Mục tiêu, phạm vi, hạn góp ý…"></textarea></div>
<p class="hint">Bạn sẽ là <strong>Quản lý</strong> của dự án và có thể mời thêm thành viên sau.</p>
</div>
<div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Huỷ</button><button class="btn" type="submit">Tạo dự án</button></div>
</form>
</dialog>
<?php require __DIR__ . '/../templates/footer.php'; ?>
