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
$projects = $repo->listForUser($user['id']);
$mine = (new AnnotationRepository($c))->assignedTo($user['id'], 5);
$invites = $repo->invitationsFor($user['id']);
$totalOpen = array_sum(array_map(fn ($p) => (int) $p['open_items'], $projects));
$totalAll = array_sum(array_map(fn ($p) => (int) $p['total_items'], $projects));
$hour = (int) date('G');
$greet = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
$firstName = preg_split('/\s+/u', trim((string) preg_replace('/\(.*?\)/u', '', $user['name'])));
$title = 'Dự án';
$active = 'projects';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div>
<h1><?= e($greet) ?>, <?= e(end($firstName)) ?> 👋</h1>
<p>Bạn tham gia <?= count($projects) ?> dự án · <?= $totalOpen ?> góp ý đang chờ xử lý trên tổng <?= $totalAll ?><?= $invites !== [] ? ' · <a href="#invites">' . count($invites) . ' lời mời đang chờ</a>' : '' ?>.</p>
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
<div class="section-title"><h2><?= icon('folder') ?>Dự án của tôi <span class="count"><?= count($projects) ?></span></h2></div>
<?php if ($projects === []): ?>
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
<a class="card project-card" href="/project.php?id=<?= (int) $p['id'] ?>" data-project="<?= (int) $p['id'] ?>">
<span class="thumb<?= $p['cover_version_id'] === null ? ' thumb-empty' : '' ?>">
<?php if ($p['cover_version_id'] !== null): ?><img src="/image.php?id=<?= (int) $p['cover_version_id'] ?>" alt="" loading="lazy"><?php else: ?><?= icon('image') ?><?php endif; ?>
<span class="thumb-badges"><span class="badge badge-role"><?= e(Access::LABELS[$p['role']]) ?></span><?php if ((int) $p['open_items'] > 0): ?><span class="badge badge-open"><?= (int) $p['open_items'] ?> chưa xử lý</span><?php endif; ?></span>
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
<button type="button" class="card new-card" data-open="dlg-new-project"><span><?= icon('plus') ?><br><strong>Tạo dự án mới</strong><br><span class="hint">Mời thành viên, tải ảnh thiết kế</span></span></button>
</div>
<?php endif; ?>
</section>

<aside class="stack">
<div class="card">
<div class="section-title"><h2><?= icon('inbox') ?>Việc giao cho tôi</h2><a class="hint" href="/inbox.php">Xem tất cả</a></div>
<?php if ($mine === []): ?>
<p class="hint">Không có góp ý nào đang chờ bạn xử lý. 🎉</p>
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
