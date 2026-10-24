<?php

declare(strict_types=1);

// "Việc của tôi": góp ý chưa xử lý được giao cho người đang đăng nhập, trong mọi dự án họ còn là thành viên.
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$items = (new AnnotationRepository($c))->assignedTo($user['id'], 200);
$overdue = array_filter($items, fn ($m) => $m['overdue']);
$groups = ['Quá hạn' => [], 'Đến hạn trong 3 ngày' => [], 'Còn lại' => []];
foreach ($items as $m) {
    if ($m['overdue']) {
        $groups['Quá hạn'][] = $m;
    } elseif ($m['due_date'] !== null && strtotime($m['due_date']) <= strtotime('+3 days')) {
        $groups['Đến hạn trong 3 ngày'][] = $m;
    } else {
        $groups['Còn lại'][] = $m;
    }
}
$title = 'Việc của tôi';
$active = 'inbox';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div><h1>Việc của tôi</h1><p><?= count($items) ?> góp ý chưa xử lý được giao cho bạn<?= $overdue ? ' · <strong>' . count($overdue) . ' quá hạn</strong>' : '' ?>. Sắp theo hạn rồi theo mức ưu tiên.</p></div>
</div>
<?php if ($items === []): ?>
<div class="card empty-state"><div class="empty-art"><?= icon('check-circle') ?></div><h3>Không còn việc nào</h3><p>Khi ai đó giao góp ý cho bạn, nó sẽ xuất hiện ở đây.</p></div>
<?php else: ?>
<?php foreach ($groups as $label => $list): if ($list === []) { continue; } ?>
<div class="section-title mt"><h2><?= icon($label === 'Quá hạn' ? 'alert' : 'calendar') ?><?= e($label) ?> <span class="count"><?= count($list) ?></span></h2></div>
<div class="table-wrap">
<table class="data">
<thead><tr><th>#</th><th>Dự án · màn hình</th><th>Nội dung</th><th>Ưu tiên</th><th>Hạn</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $m): $href = '/screen.php?id=' . (int) $m['screen_id'] . '&v=' . (int) $m['image_version_id'] . '&a=' . (int) $m['id']; ?>
<tr>
<td><a class="seq-link" href="<?= e($href) ?>">#<?= (int) $m['seq_no'] ?></a></td>
<td><strong><?= e($m['screen_name']) ?></strong> <span class="hint">v<?= (int) $m['version_no'] ?></span><br><span class="hint"><?= e($m['project_name']) ?></span></td>
<td class="body-cell"><?= e($m['body']) ?></td>
<td><?= prioBadge($m['priority']) ?></td>
<td><?= dueBadge($m['due_date'], $m['overdue']) ?: '<span class="faint">—</span>' ?></td>
<td><a class="btn btn-sm btn-ghost" href="<?= e($href) ?>">Mở<?= icon('arrow-right', 'sm') ?></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
