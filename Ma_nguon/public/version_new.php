<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
if (!isPost()) {
    redirect('/projects.php');
}
checkCsrf($_POST);
$c = db();
$user = Auth::requireUser($c);
$screenId = intField($_POST, 'screen_id');
Access::require($c, $user['id'], Access::projectOfScreen($c, $screenId), Access::ADMIN);
try {
    $image = ImageStore::fromUpload($_FILES['image'] ?? null);
    $v = (new ScreenRepository($c))->addVersion($screenId, $image, $user['id'], field($_POST, 'note'));
    flash('Đã tải phiên bản v' . $v['version_no'] . '. Góp ý cũ vẫn thuộc phiên bản trước.');
    redirect('/compare.php?screen=' . $screenId . '&right=' . $v['id']);
} catch (AppError $ex) {
    flash('Lỗi: ' . errorMessage($ex->errorCode));
    redirect('/screen.php?id=' . $screenId);
}
