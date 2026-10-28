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
$repo = new ProjectRepository($c);
try {
    switch (field($_POST, 'action')) {
        case 'add':
            $m = $repo->addMember($projectId, field($_POST, 'email'), field($_POST, 'role'), $user['id']);
            flash('Đã thêm ' . $m['name'] . ' vào dự án.');
            break;
        case 'role':
            $repo->changeRole($projectId, intField($_POST, 'user_id'), field($_POST, 'role'));
            flash('Đã cập nhật quyền.');
            break;
        case 'remove':
            $repo->removeMember($projectId, intField($_POST, 'user_id'));
            flash('Đã xoá thành viên.');
            break;
        default:
            throw new AppError('NOT_FOUND', 404);
    }
} catch (AppError $ex) {
    flash('Lỗi: ' . errorMessage($ex->errorCode));
}
redirect('/project.php?id=' . $projectId);
