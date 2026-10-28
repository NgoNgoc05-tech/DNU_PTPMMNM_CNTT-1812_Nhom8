<?php

declare(strict_types=1);

// Người được mời chấp nhận hoặc từ chối lời mời tham gia dự án (POST + CSRF).
require __DIR__ . '/../src/web.php';
if (!isPost()) {
    redirect('/projects.php');
}
checkCsrf($_POST);
$c = db();
$user = Auth::requireUser($c);
$accept = field($_POST, 'answer') === 'accept';
try {
    $projectId = (new ProjectRepository($c))->respondInvitation(intField($_POST, 'invitation_id'), $user['id'], $accept);
    if ($accept) {
        flash('Đã tham gia dự án. Chào mừng bạn!');
        redirect('/project.php?id=' . $projectId);
    }
    flash('Đã từ chối lời mời.');
} catch (AppError $ex) {
    flash('Lỗi: ' . errorMessage($ex->errorCode));
}
redirect('/projects.php');
