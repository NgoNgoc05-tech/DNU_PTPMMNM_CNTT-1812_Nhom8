<?php

declare(strict_types=1);

// Hồ sơ của tôi: thông tin cá nhân, đổi mật khẩu, thống kê hoạt động, thông báo gần đây.
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$profileError = '';
$passwordError = '';
if (isPost()) {
    checkCsrf($_POST);
    try {
        if (field($_POST, 'action') === 'profile') {
            Auth::updateProfile($c, $user['id'], field($_POST, 'name'), field($_POST, 'title'));
            flash('Đã lưu hồ sơ.');
        } else {
            Auth::changePassword($c, $user['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''), (string) ($_POST['repeat'] ?? ''));
            flash('Đã đổi mật khẩu.');
        }
        redirect('/profile.php');
    } catch (AppError $ex) {
        if (field($_POST, 'action') === 'profile') {
            $profileError = errorMessage($ex->errorCode);
        } else {
            $passwordError = errorMessage($ex->errorCode);
        }
    }
}
$stats = fetchOne($c,
    "SELECT (SELECT COUNT(*) FROM project_members WHERE user_id = $1) AS projects,
            (SELECT COUNT(*) FROM annotations WHERE created_by = $1) AS authored,
            (SELECT COUNT(*) FROM annotations WHERE created_by = $1 AND status = 'RESOLVED') AS authored_resolved,
            (SELECT COUNT(*) FROM comments WHERE author_id = $1 AND parent_id IS NOT NULL) AS replies,
            (SELECT COUNT(*) FROM annotations WHERE assignee_id = $1 AND status = 'OPEN') AS assigned_open,
            (SELECT COUNT(*) FROM status_history WHERE changed_by = $1 AND to_status = 'RESOLVED') AS resolved_by_me", [$user['id']]);
$memberships = fetchAll($c,
    'SELECT p.id, p.name, pm.role FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.user_id = $1 ORDER BY p.name', [$user['id']]);
$notes = (new NotificationRepository($c))->listFor($user['id'], 5);
$title = 'Hồ sơ của tôi';
$active = 'profile';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div class="row center">
<?= avatar($user['name'], $user['id'], 'xl') ?>
<div><h1><?= e($user['name']) ?></h1><p><?= e($user['title'] !== '' ? $user['title'] . ' · ' : '') ?><?= e($user['email']) ?> · tham gia từ <?= e(date('d/m/Y', (int) strtotime((string) $user['created_at']))) ?></p></div>
</div>
</div>

<div class="grid grid-4">
<div class="stat"><span class="stat-icon"><?= icon('folder') ?></span><span><strong><?= (int) $stats['projects'] ?></strong><span>Dự án tham gia</span></span></div>
<div class="stat"><span class="stat-icon pin"><?= icon('pin') ?></span><span><strong><?= (int) $stats['authored'] ?></strong><span>Góp ý đã viết · <?= (int) $stats['authored_resolved'] ?> đã xử lý</span></span></div>
<div class="stat"><span class="stat-icon"><?= icon('chat') ?></span><span><strong><?= (int) $stats['replies'] ?></strong><span>Trả lời</span></span></div>
<a class="stat" href="/inbox.php"><span class="stat-icon warn"><?= icon('inbox') ?></span><span><strong><?= (int) $stats['assigned_open'] ?></strong><span>Đang phụ trách</span></span></a>
<div class="stat"><span class="stat-icon ok"><?= icon('check-circle') ?></span><span><strong><?= (int) $stats['resolved_by_me'] ?></strong><span>Lần xác nhận đã xử lý</span></span></div>
</div>

<div class="layout-side mt">
<section class="stack">
<div class="card">
<h2><?= icon('user') ?>Thông tin cá nhân</h2>
<?php if ($profileError !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($profileError) ?></span></div><?php endif; ?>
<form method="post" action="/profile.php">
<?= csrfField() ?>
<input type="hidden" name="action" value="profile">
<div class="row">
<div class="grow field"><label for="pname">Họ tên</label><input id="pname" name="name" type="text" required maxlength="120" value="<?= e($user['name']) ?>"></div>
<div class="grow field"><label for="ptitle">Chức danh / vai trò</label><input id="ptitle" name="title" type="text" maxlength="120" value="<?= e($user['title']) ?>" placeholder="Ví dụ: Sinh viên thiết kế, Giảng viên"></div>
</div>
<div class="field"><label for="pemail">Email đăng nhập</label><input id="pemail" type="email" value="<?= e($user['email']) ?>" disabled></div>
<p class="hint">Email dùng để được mời vào dự án nên không đổi được. Muốn đổi email, gửi yêu cầu ở trang <a href="/help.php">Hỗ trợ</a>.</p>
<button class="btn" type="submit">Lưu hồ sơ</button>
</form>
</div>
<div class="card">
<h2><?= icon('settings') ?>Đổi mật khẩu</h2>
<?php if ($passwordError !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($passwordError) ?></span></div><?php endif; ?>
<form method="post" action="/profile.php">
<?= csrfField() ?>
<input type="hidden" name="action" value="password">
<div class="field"><label for="pcur">Mật khẩu hiện tại</label><input id="pcur" name="current" type="password" required autocomplete="current-password"></div>
<div class="row">
<div class="grow field"><label for="pnew">Mật khẩu mới (≥ 8 ký tự)</label><input id="pnew" name="new" type="password" required minlength="8" autocomplete="new-password"></div>
<div class="grow field"><label for="prep">Nhập lại mật khẩu mới</label><input id="prep" name="repeat" type="password" required minlength="8" autocomplete="new-password"></div>
</div>
<button class="btn" type="submit">Đổi mật khẩu</button>
</form>
</div>
</section>
<aside class="stack">
<div class="card">
<div class="section-title"><h2><?= icon('folder') ?>Dự án và quyền</h2></div>
<?php if ($memberships === []): ?><p class="hint">Bạn chưa thuộc dự án nào.</p><?php endif; ?>
<ul class="member-list">
<?php foreach ($memberships as $m): ?>
<li><span class="who"><a href="/project.php?id=<?= (int) $m['id'] ?>"><?= e($m['name']) ?></a></span><span class="badge badge-role"><?= e(Access::LABELS[$m['role']]) ?></span></li>
<?php endforeach; ?>
</ul>
</div>
<div class="card">
<div class="section-title"><h2><?= icon('bell') ?>Thông báo gần đây</h2><a class="hint" href="/notifications.php">Tất cả</a></div>
<?php if ($notes === []): ?><p class="hint">Chưa có thông báo.</p><?php endif; ?>
<ul class="feed">
<?php foreach ($notes as $n): ?>
<li><?= avatar($n['actor'] ?? '?', $n['actor_id'], 'sm') ?><span class="feed-body"><strong><?= e($n['actor'] ?? 'Ai đó') ?></strong> <?= e($n['label']) ?>
<?php if ($n['link'] !== null): ?> · <a href="<?= e($n['link']) ?>"><?= e($n['where']) ?></a><?php endif; ?>
<?php if ($n['body'] !== ''): ?><span class="quote line-clamp-2"><?= e($n['body']) ?></span><?php endif; ?><time><?= e(ago($n['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
</div>
</aside>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
