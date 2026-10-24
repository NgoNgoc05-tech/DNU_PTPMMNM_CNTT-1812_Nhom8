<?php

declare(strict_types=1);

// Phát ảnh sau khi kiểm tra quyền: thành viên dự án khác không lấy được ảnh bằng URL.
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Session.php';
try {
    $c = db();
    $user = Auth::user($c);
    if ($user === null) {
        throw new AppError('NOT_FOUND', 404);
    }
    $versionId = intField($_GET, 'id');
    Access::require($c, (int) $user['id'], Access::projectOfVersion($c, $versionId), Access::VIEW);
    $v = (new ScreenRepository($c))->version($versionId);
    $path = ImageStore::path($v['stored_name']);
    if (!is_file($path)) {
        throw new AppError('NOT_FOUND', 404);
    }
    header('Content-Type: ' . $v['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'");
    readfile($path);
} catch (AppError $e) {
    http_response_code($e->httpStatus);
    echo $e->errorCode;
} catch (Throwable $e) {
    http_response_code(503);
    echo 'UNAVAILABLE';
}
