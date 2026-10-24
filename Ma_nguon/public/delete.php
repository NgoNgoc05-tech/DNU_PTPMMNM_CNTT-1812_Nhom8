<?php

declare(strict_types=1);

// Xoá phiên bản ảnh tải nhầm, xoá màn hình, xoá dự án. Chỉ POST + CSRF; quyền kiểm tra ở máy chủ.
require __DIR__ . '/../src/web.php';
if (!isPost()) {
    redirect('/projects.php');
}
checkCsrf($_POST);
$c = db();
$user = Auth::requireUser($c);
$screens = new ScreenRepository($c);
$back = '/projects.php';
try {
    switch (field($_POST, 'what')) {
        case 'version':
            $versionId = intField($_POST, 'version_id');
            $v = $screens->version($versionId);
            $back = '/screen.php?id=' . $v['screen_id'] . '&v=' . $versionId;
            Access::require($c, $user['id'], $v['project_id'], Access::ADMIN);
            $r = $screens->deleteVersion($versionId);
            flash('Đã xoá phiên bản v' . $r['version_no'] . ' cùng các góp ý trên phiên bản đó.');
            redirect('/screen.php?id=' . $r['screen_id']);
        case 'screen':
            $screenId = intField($_POST, 'screen_id');
            $projectId = Access::projectOfScreen($c, $screenId);
            $back = '/screen.php?id=' . $screenId;
            Access::require($c, $user['id'], $projectId, Access::ADMIN);
            $screens->deleteScreen($screenId);
            flash('Đã xoá màn hình và mọi phiên bản của nó.');
            redirect('/project.php?id=' . $projectId);
        case 'project':
            $projectId = intField($_POST, 'project_id');
            $back = '/project.php?id=' . $projectId;
            Access::require($c, $user['id'], $projectId, Access::ADMIN);
            (new ProjectRepository($c))->delete($projectId, $user['id'], field($_POST, 'confirm_name'));
            flash('Đã xoá dự án.');
            redirect('/projects.php');
        default:
            throw new AppError('NOT_FOUND', 404);
    }
} catch (AppError $ex) {
    if ($ex->errorCode === 'NOT_FOUND' || $ex->errorCode === 'FORBIDDEN') {
        throw $ex;
    }
    flash('Lỗi: ' . errorMessage($ex->errorCode));
    redirect($back);
}
