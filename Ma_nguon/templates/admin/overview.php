<?php
/** Trang Tổng quan của bảng quản trị. Dữ liệu: $stats, $data['daily'|'topProjects'|'log'|'newUsers'], $bytes. */
$daily = $data['daily'];
$max = max(1, ...array_map(fn ($d) => max($d['annotations'], $d['comments']), $daily));
?>
<div class="grid grid-4 admin-stats">
<a class="stat" href="/admin.php?tab=users"><span class="stat-icon"><?= icon('users') ?></span><span><strong><?= $stats['users']['total'] ?></strong><span>Người dùng · <?= $stats['users']['new7'] ?> mới trong 7 ngày</span></span></a>
<a class="stat" href="/admin.php?tab=users&amp;sort=login"><span class="stat-icon ok"><?= icon('activity') ?></span><span><strong><?= $stats['users']['active7'] ?></strong><span>Đăng nhập trong 7 ngày</span></span></a>
<a class="stat" href="/admin.php?tab=users&amp;filter=locked"><span class="stat-icon danger"><?= icon('lock') ?></span><span><strong><?= $stats['users']['locked'] ?></strong><span>Tài khoản bị khoá</span></span></a>
<a class="stat" href="/admin.php?tab=projects"><span class="stat-icon warn"><?= icon('folder') ?></span><span><strong><?= $stats['projects']['projects'] ?></strong><span><?= $stats['projects']['screens'] ?> màn hình · <?= $stats['projects']['versions'] ?> phiên bản</span></span></a>
<div class="stat"><span class="stat-icon pin"><?= icon('pin') ?></span><span><strong><?= $stats['annotations']['open'] ?></strong><span>Góp ý chưa xử lý · <?= $stats['annotations']['overdue'] ?> quá hạn</span></span></div>
<div class="stat"><span class="stat-icon ok"><?= icon('check-circle') ?></span><span><strong><?= $stats['annotations']['resolved'] ?></strong><span>Góp ý đã xử lý</span></span></div>
<div class="stat"><span class="stat-icon"><?= icon('chat') ?></span><span><strong><?= $stats['comments']['comments'] ?></strong><span>Nhận xét · <?= $stats['comments']['attachments'] ?> ảnh · <?= e($bytes($stats['storage'])) ?></span></span></div>
<a class="stat" href="/support_admin.php"><span class="stat-icon warn"><?= icon('inbox') ?></span><span><strong><?= $stats['tickets']['open'] ?></strong><span>Yêu cầu hỗ trợ đang chờ / <?= $stats['tickets']['total'] ?></span></span></a>
</div>

<div class="admin-cols mt">
<section class="card">
<div class="section-title"><h2><?= icon('chart') ?>Hoạt động 14 ngày gần nhất</h2><span class="legend"><span class="dot a"></span>Góp ý mới <span class="dot r"></span>Trả lời</span></div>
<svg class="bar-chart" viewBox="0 0 700 220" role="img" aria-label="Biểu đồ số góp ý mới và trả lời mỗi ngày">
<?php foreach ($daily as $i => $d): $x = 20 + $i * 48; $ha = round($d['annotations'] / $max * 160); $hr = round($d['comments'] / $max * 160); ?>
<g><title><?= e($d['day']) ?>: <?= $d['annotations'] ?> góp ý mới, <?= $d['comments'] ?> trả lời</title>
<rect class="bar-a" x="<?= $x ?>" y="<?= 180 - $ha ?>" width="18" height="<?= max(1, $ha) ?>" rx="3"></rect>
<rect class="bar-r" x="<?= $x + 20 ?>" y="<?= 180 - $hr ?>" width="18" height="<?= max(1, $hr) ?>" rx="3"></rect>
<text x="<?= $x + 19 ?>" y="200" text-anchor="middle"><?= e($d['day']) ?></text>
<?php if ($d['annotations'] + $d['comments'] > 0): ?><text class="bar-n" x="<?= $x + 19 ?>" y="<?= 172 - max($ha, $hr) ?>" text-anchor="middle"><?= $d['annotations'] + $d['comments'] ?></text><?php endif; ?>
</g>
<?php endforeach; ?>
<line x1="12" y1="180" x2="690" y2="180" class="axis"></line>
</svg>
</section>
<section class="card">
<div class="section-title"><h2><?= icon('user') ?>Người dùng mới</h2><a class="hint" href="/admin.php?tab=users">Xem tất cả</a></div>
<ul class="admin-people">
<?php foreach ($data['newUsers'] as $u): ?>
<li><a href="/admin.php?tab=user&amp;id=<?= (int) $u['id'] ?>"><?= avatar($u['name'], (int) $u['id'], 'sm') ?><span><strong><?= e($u['name']) ?></strong><small><?= e($u['email']) ?></small></span><time><?= e(ago($u['created_at'])) ?></time></a></li>
<?php endforeach; ?>
</ul>
</section>
</div>

<div class="admin-cols mt">
<section class="card">
<div class="section-title"><h2><?= icon('flag') ?>Dự án nhiều góp ý chưa xử lý</h2><a class="hint" href="/admin.php?tab=projects&amp;sort=open">Xem tất cả</a></div>
<ul class="link-list">
<?php foreach ($data['topProjects'] as $p): ?>
<li><?= icon('folder', 'sm') ?><span><strong><?= e($p['name']) ?></strong> · <?= (int) $p['open'] ?>/<?= (int) $p['annotations'] ?> chưa xử lý · <?= e($p['owner']) ?></span></li>
<?php endforeach; ?>
<?php if ($data['topProjects'] === []): ?><li class="hint">Chưa có dự án nào.</li><?php endif; ?>
</ul>
</section>
<section class="card">
<div class="section-title"><h2><?= icon('history') ?>Nhật ký quản trị</h2><a class="hint" href="/admin.php?tab=log">Xem tất cả</a></div>
<?php if ($data['log'] === []): ?><p class="hint">Chưa có thao tác quản trị nào.</p><?php endif; ?>
<ul class="feed admin-log">
<?php foreach ($data['log'] as $l): ?>
<li><span class="feed-body"><strong><?= e($l['admin'] ?? '—') ?></strong> <span class="badge badge-muted"><?= e($l['action']) ?></span> <?= e($l['target']) ?><?= $l['detail'] !== '' ? ' · ' . e($l['detail']) : '' ?><time><?= e(ago($l['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
</section>
</div>
