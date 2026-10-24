<?php

declare(strict_types=1);

// Danh sách thông báo của tôi. POST: đánh dấu đã đọc tất cả (dùng được cả khi tắt JavaScript).
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$repo = new NotificationRepository($c);
if (isPost()) {
    checkCsrf($_POST);
    $repo->markRead($user['id'], intField($_POST, 'id'));
    redirect(intField($_POST, 'id') > 0 && field($_POST, 'go') !== '' && str_starts_with(field($_POST, 'go'), '/screen.php?')
        ? field($_POST, 'go') : '/notifications.php');
}
$onlyUnread = field($_GET, 'filter') === 'unread';
$items = $repo->listFor($user['id'], 100, $onlyUnread);
$unread = $repo->unreadCount($user['id']);
$title = 'Thông báo';
$active = 'notifications';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div><h1>Thông báo</h1><p><?= $unread ?> thông báo chưa đọc. Bạn nhận thông báo khi được @nhắc tên, được giao việc, có trả lời mới
trong góp ý của mình hoặc góp ý của mình đổi trạng thái.</p></div>
<div class="actions">
<nav class="segmented" aria-label="Lọc thông báo">
<a href="/notifications.php"<?= !$onlyUnread ? ' aria-current="page"' : '' ?>>Tất cả</a>
<a href="/notifications.php?filter=unread"<?= $onlyUnread ? ' aria-current="page"' : '' ?>>Chưa đọc <span class="n"><?= $unread ?></span></a>
</nav>
<?php if ($unread > 0): ?>
<form method="post" action="/notifications.php" class="inline"><?= csrfField() ?><input type="hidden" name="id" value="0">
<button class="btn btn-ghost" type="submit"><?= icon('check') ?>Đánh dấu đã đọc tất cả</button></form>
<?php endif; ?>
</div>
</div>
<?php if ($items === []): ?>
<div class="card empty-state"><div class="empty-art"><?= icon('bell') ?></div><h3>Không có thông báo</h3><p>Khi có người nhắc tới bạn hoặc giao việc cho bạn, thông báo sẽ hiện ở đây.</p></div>
<?php else: ?>
<div class="card notif-page">
<ul class="notif-list">
<?php foreach ($items as $n): ?>
<li class="<?= $n['read'] ? '' : 'is-unread' ?>">
<?= avatar($n['actor'] ?? '?', $n['actor_id']) ?>
<div class="notif-main">
<div><strong><?= e($n['actor'] ?? 'Ai đó') ?></strong> <span class="notif-kind k-<?= e($n['kind']) ?>"><?= e($n['label']) ?></span>
<?php if ($n['link'] !== null): ?> · <span class="hint"><?= e($n['where']) ?></span><?php endif; ?></div>
<?php if ($n['body'] !== ''): ?><div class="quote line-clamp-2"><?= e($n['body']) ?></div><?php endif; ?>
<time class="hint"><?= e(dateVi($n['created_at'])) ?> · <?= e(ago($n['created_at'])) ?></time>
</div>
<?php if ($n['link'] !== null): ?>
<form method="post" action="/notifications.php"><?= csrfField() ?><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="go" value="<?= e($n['link']) ?>">
<button class="btn btn-sm btn-ghost" type="submit">Mở<?= icon('arrow-right', 'sm') ?></button></form>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
