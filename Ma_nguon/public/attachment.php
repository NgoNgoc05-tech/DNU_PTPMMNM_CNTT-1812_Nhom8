<?php

declare(strict_types=1);

// Ảnh đính kèm trong trả lời (bản 2.3).
//   GET  ?id=ID                              phát ảnh (chỉ thành viên dự án)
//   POST csrf, comment_id, image (multipart) đính kèm ảnh vào nhận xét của chính mình (quyền Góp ý)
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Session.php';
$c = null;
try {
    $c = db();
    $user = Auth::user($c);
    $repo = new AttachmentRepository($c);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ($user === null) {
            throw new AppError('UNAUTHENTICATED', 401);
        }
        checkCsrf($_POST);
        $commentId = intField($_POST, 'comment_id');
        Access::require($c, (int) $user['id'], Access::projectOfComment($c, $commentId), Access::COMMENT);
        $image = ImageStore::fromUpload($_FILES['image'] ?? null);
        try {
            $item = $repo->add($commentId, $image, (int) $user['id']);
        } catch (Throwable $e) {
            @unlink(ImageStore::path($image['stored_name']));
            throw $e;
        }
        http_response_code(201);
        echo json_encode(['attachment' => $item], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($user === null) {
        throw new AppError('NOT_FOUND', 404);
    }
    $a = $repo->find(intField($_GET, 'id'));
    Access::require($c, (int) $user['id'], $a['project_id'], Access::VIEW);
    $path = ImageStore::path($a['stored_name']);
    if (!is_file($path)) {
        throw new AppError('NOT_FOUND', 404);
    }
    header('Content-Type: ' . $a['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'");
    readfile($path);
} catch (AppError $e) {
    http_response_code($e->httpStatus);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        echo json_encode(['error' => $e->errorCode, 'message' => errorMessage($e->errorCode)], JSON_UNESCAPED_UNICODE);
    } else {
        echo $e->errorCode;
    }
} catch (Throwable $e) {
    error_log('Pinpoint attachment: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'UNAVAILABLE', 'message' => 'Không lưu được ảnh.'], JSON_UNESCAPED_UNICODE);
}
