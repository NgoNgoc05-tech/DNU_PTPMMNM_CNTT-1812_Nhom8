<?php

declare(strict_types=1);

// Xuất CSV danh sách góp ý (cùng bộ lọc với trang Tìm góp ý). Chỉ thành viên dự án.
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$projectId = intField($_GET, 'project');
Access::require($c, $user['id'], $projectId, Access::VIEW);
$project = (new ProjectRepository($c))->find($projectId);
$items = (new AnnotationRepository($c))->search($projectId, field($_GET, 'status'), intField($_GET, 'author'),
    field($_GET, 'q'), intField($_GET, 'screen'), [
        'priority' => field($_GET, 'priority'), 'category' => field($_GET, 'category'),
        'assignee' => intField($_GET, 'assignee'), 'overdue' => field($_GET, 'overdue') === '1', 'sort' => field($_GET, 'sort'),
    ]);
$name = 'gop-y-du-an-' . $projectId . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
echo Export::csv(Export::rows($items));
