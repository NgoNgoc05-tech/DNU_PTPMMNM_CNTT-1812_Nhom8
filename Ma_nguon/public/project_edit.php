<?php

declare(strict_types=1);

// Quản lý sửa thông tin dự án hoặc đổi tên màn hình. Chỉ nhận POST + CSRF + quyền ADMIN.
require __DIR__ . '/../src/web.php';
if (!isPost()) {
    redirect('/projects.php');
}
checkCsrf($_POST);
$c = db();
$user = Auth::requireUser($c);
$action = field($_POST, 'action');
try {
    if ($action === 'project') {
        $projectId = intField($_POST, 'project_id');
        Access::require($c, $user['id'], $projectId, Access::ADMIN);
        (new ProjectRepository($c))->update($projectId, field($_POST, 'name'), field($_POST, 'description'));
        flash('Đã lưu thông tin dự án.');
        redirect('/project.php?id=' . $projectId);
    }
    if ($action === 'screen') {
        $screenId = intField($_POST, 'screen_id');
        Access::require($c, $user['id'], Access::projectOfScreen($c, $screenId), Access::ADMIN);
        (new ScreenRepository($c))->rename($screenId, field($_POST, 'name'));
        flash('Đã đổi tên màn hình.');
        redirect('/screen.php?id=' . $screenId);
    }
    throw new AppError('NOT_FOUND', 404);
} catch (AppError $ex) {
    if ($ex->httpStatus === 404 || $ex->httpStatus === 403) {
        throw $ex;
    }
    flash('Lỗi: ' . errorMessage($ex->errorCode));
    redirect(isset($screenId) ? '/screen.php?id=' . $screenId : '/project.php?id=' . intField($_POST, 'project_id'));
}
