<?php
/** Trang chi tiết một người dùng: hồ sơ, số liệu, dự án tham gia, góp ý gần đây, lịch sử quản trị và thao tác. Dữ liệu: $data['u']. */
$u = $data['u'];
$self = (int) $u['id'] === $user['id'];
?>
<div class="admin-profile card">
<?= avatar($u['name'], (int) $u['id'], 'lg') ?>
<div class="ap-main">
<h2><?= e($u['name']) ?><?= $self ? ' <span class="badge badge-muted">bạn</span>' : '' ?></h2>
<p class="hint"><?= e($u['email']) ?><?= $u['title'] !== '' ? ' · ' . e($u['title']) : '' ?> · tạo ngày <?= e(date('d/m/Y', (int) strtotime($u['created_at']))) ?> · đăng nhập <?= $u['last_login_at'] ? e(ago($u['last_login_at'])) : 'chưa lần nào' ?></p>
<p class="ap-badges">
<?php if ($u['locked_at'] !== null): ?><span class="badge badge-open"><?= icon('lock', 'sm') ?>Bị khoá <?= e(ago($u['locked_at'])) ?></span><?php else: ?><span class="badge badge-resolved">Đang hoạt động</span><?php endif; ?>
<?= $u['is_admin'] === 't' ? '<span class="badge badge-role">Quản trị hệ thống</span>' : '' ?>
<?= $u['is_support'] === 't' ? '<span class="badge badge-resolved">Bộ phận hỗ trợ</span>' : '' ?>
<span class="badge badge-muted"><?= $u['email_reminders'] === 't' ? 'Nhận email nhắc hạn' : 'Tắt email nhắc hạn' ?></span>
</p>
<?php if ($u['locked_at'] !== null && $u['locked_reason'] !== ''): ?><p class="quote">Lý do khoá: <?= e($u['locked_reason']) ?></p><?php endif; ?>
</div>
</div>

<div class="grid grid-4 admin-stats mt">
<div class="stat"><span class="stat-icon"><?= icon('folder') ?></span><span><strong><?= (int) $u['projects'] ?></strong><span>Dự án tham gia</span></span></div>
<div class="stat"><span class="stat-icon pin"><?= icon('pin') ?></span><span><strong><?= (int) $u['annotations'] ?></strong><span>Góp ý đã tạo</span></span></div>
<div class="stat"><span class="stat-icon warn"><?= icon('inbox') ?></span><span><strong><?= (int) $u['assigned_open'] ?></strong><span>Việc đang phụ trách</span></span></div>
<div class="stat"><span class="stat-icon ok"><?= icon('chat') ?></span><span><strong><?= (int) $u['comments'] ?></strong><span>Nhận xét, trả lời</span></span></div>
</div>

<div class="admin-cols mt">
<div class="stack">
<section class="card">
<div class="section-title"><h2><?= icon('folder') ?>Dự án tham gia <span class="count"><?= count($u['project_list']) ?></span></h2></div>
<?php if ($u['project_list'] === []): ?><p class="hint">Chưa tham gia dự án nào.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>Dự án</th><th>Quyền</th><th>Tham gia</th></tr></thead><tbody>
<?php foreach ($u['project_list'] as $p): ?>
<tr><td><strong><?= e($p['name']) ?></strong><?= $p['archived_at'] !== null ? ' <span class="badge badge-muted">Lưu trữ</span>' : '' ?><?= $p['owner'] === 't' ? ' <span class="badge badge-role">Người tạo</span>' : '' ?></td>
<td><?= e(Access::LABELS[$p['role']] ?? $p['role']) ?></td><td class="hint"><?= e(ago($p['joined_at'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<section class="card">
<div class="section-title"><h2><?= icon('pin') ?>Góp ý gần đây</h2></div>
<?php if ($u['recent'] === []): ?><p class="hint">Chưa tạo góp ý nào.</p><?php else: ?>
<ul class="feed">
<?php foreach ($u['recent'] as $r): ?>
<li><span class="feed-icon"><?= icon('pin') ?></span><span class="feed-body"><strong><?= e($r['screen_name']) ?> v<?= (int) $r['version_no'] ?> #<?= (int) $r['seq_no'] ?></strong> <?= statusBadge($r['status']) ?>
<span class="quote line-clamp-2"><?= e($r['body']) ?></span><time><?= e($r['project_name']) ?> · <?= e(ago($r['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>
</div>
<div class="stack">
<section class="card admin-actions-card">
<h2><?= icon('settings') ?>Thao tác</h2>
<div class="menu-static"><?php require __DIR__ . '/user_actions.php'; ?></div>
<?php if ($self): ?><p class="hint">Bạn không thể tự khoá hoặc tự bỏ quyền quản trị của chính mình.</p><?php endif; ?>
</section>
<section class="card">
<h2><?= icon('history') ?>Lịch sử quản trị</h2>
<?php if ($u['log'] === []): ?><p class="hint">Chưa có thao tác quản trị nào với tài khoản này.</p><?php else: ?>
<ul class="feed admin-log">
<?php foreach ($u['log'] as $l): ?>
<li><span class="feed-body"><span class="badge badge-muted"><?= e($l['action']) ?></span> bởi <strong><?= e($l['admin'] ?? '—') ?></strong><?= $l['detail'] !== '' ? ' · ' . e($l['detail']) : '' ?><time><?= e(dateVi($l['created_at'])) ?></time></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>
</div>
</div>
