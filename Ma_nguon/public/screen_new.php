<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
if (!isPost()) {
    redirect('/projects.php');
}
checkCsrf($_POST);
$c = db();
$user = Auth::requireUser($c);
$projectId = intField($_POST, 'project_id');
Access::require($c, $user['id'], $projectId, Access::ADMIN);
try {
    $image = ImageStore::fromUpload($_FILES['image'] ?? null);
    $ids = (new ScreenRepository($c))->create($projectId, field($_POST, 'name'), $image, $user['id']);
    flash('Đã tạo màn hình và phiên bản v1.');
    redirect('/screen.php?id=' . $ids['screen_id']);
} catch (AppError $ex) {
    flash('Lỗi: ' . errorMessage($ex->errorCode));
    redirect('/project.php?id=' . $projectId);
}
