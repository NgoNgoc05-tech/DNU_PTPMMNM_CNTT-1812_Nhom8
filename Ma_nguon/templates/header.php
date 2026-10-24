<?php
/** @var string $title  @var array|null $user */
$wide = $wide ?? false;
$active = $active ?? '';
$flashMsg = flash();
$myCount = 0;
if (!empty($user)) {
    try {
        $myCount = (int) (fetchOne(db(),
            "SELECT COUNT(*) AS n FROM annotations a JOIN image_versions v ON v.id = a.image_version_id JOIN screens s ON s.id = v.screen_id
               JOIN project_members pm ON pm.project_id = s.project_id AND pm.user_id = $1
              WHERE a.assignee_id = $1 AND a.status = 'OPEN'", [(int) $user['id']])['n'] ?? 0);
    } catch (Throwable $e) {
        $myCount = 0;
    }
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= e($title ?? 'Pinpoint') ?> · Pinpoint</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<script src="/assets/js/theme-init.js"></script>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="skip-link" href="#main">Đến nội dung chính</a>
<header class="site-header">
<div class="header-inner">
<a class="brand" href="<?= !empty($user) ? '/projects.php' : '/login.php' ?>"><span class="brand-mark"><?= icon('pin') ?></span>Pinpoint<small>2.0</small></a>
<?php if (!empty($user)): ?>
<nav class="main-nav" aria-label="Điều hướng chính">
<a href="/projects.php"<?= $active === 'projects' ? ' aria-current="page"' : '' ?>><?= icon('folder') ?>Dự án</a>
<a href="/inbox.php"<?= $active === 'inbox' ? ' aria-current="page"' : '' ?>><?= icon('inbox') ?>Việc của tôi<?php if ($myCount > 0): ?> <span class="nav-count"><?= $myCount ?></span><?php endif; ?></a>
<a href="/calibration.php"<?= $active === 'calibration' ? ' aria-current="page"' : '' ?>><?= icon('target') ?>Phép thử toạ độ</a>
</nav>
<div class="user-box">
<button type="button" class="btn btn-subtle btn-icon" data-theme-toggle title="Đổi giao diện sáng/tối" aria-label="Đổi giao diện sáng/tối"><?= icon('moon') ?></button>
<details class="dropdown user-menu">
<summary aria-label="Tài khoản"><?= avatar($user['name'], (int) $user['id']) ?><span class="user-name"><?= e($user['name']) ?></span><?= icon('chevron-down', 'sm') ?></summary>
<div class="menu menu-right">
<div class="menu-head"><strong><?= e($user['name']) ?></strong><small><?= e($user['email'] ?? '') ?></small></div>
<a href="/projects.php"><?= icon('folder') ?>Dự án của tôi</a>
<a href="/inbox.php"><?= icon('inbox') ?>Việc được giao</a>
<hr>
<form method="post" action="/logout.php"><?= csrfField() ?><button type="submit"><?= icon('logout') ?>Đăng xuất</button></form>
</div>
</details>
</div>
<?php else: ?>
<nav class="main-nav"></nav>
<div class="user-box"><button type="button" class="btn btn-subtle btn-icon" data-theme-toggle title="Đổi giao diện sáng/tối" aria-label="Đổi giao diện sáng/tối"><?= icon('moon') ?></button></div>
<?php endif; ?>
</div>
</header>
<main id="main" tabindex="-1"<?= $wide ? ' class="wide"' : '' ?>>
<?php if ($flashMsg !== null): ?>
<?php if ($wide): ?>
<div hidden data-flash="<?= e($flashMsg) ?>" data-flash-error="<?= str_starts_with($flashMsg, 'Lỗi') ? '1' : '' ?>"></div>
<?php else: ?>
<div class="flash<?= str_starts_with($flashMsg, 'Lỗi') ? ' error' : '' ?>" role="status"><?= icon(str_starts_with($flashMsg, 'Lỗi') ? 'alert' : 'check-circle') ?><span><?= e($msg = $flashMsg) ?></span></div>
<?php endif; ?>
<?php endif; ?>
