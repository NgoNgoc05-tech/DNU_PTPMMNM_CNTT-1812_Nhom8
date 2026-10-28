<?php

declare(strict_types=1);

/*
 * API JSON cho trình xem ảnh. Mọi thao tác đều:
 *   1) yêu cầu đăng nhập; 2) kiểm tra CSRF với POST; 3) tìm dự án sở hữu đối tượng
 *   rồi kiểm tra quyền ở máy chủ; 4) trả mã lỗi ổn định (FORBIDDEN, CONFLICT...).
 *
 * GET  ?action=list&version=ID[&status=OPEN|RESOLVED][&author=ID]
 * POST action=create   {version_id, shape: POINT|RECT|ARROW|DRAW, x, y, w, h, points[[x,y]...], body}
 * POST action=reply    {annotation_id, body}
 * POST action=edit     {comment_id, body, lock_version}
 * POST action=status   {annotation_id, to, lock_version, checked_version_id, note}
 * POST action=update   {annotation_id, lock_version, [priority], [category], [assignee_id], [due_date], [note]}
 * POST action=react    {comment_id, kind: LIKE|HEART|PARTY|EYES}
 * GET  ?action=activity&project=ID[&screen=ID]   nhật ký hoạt động gần đây
 * GET  ?action=notifications                     thông báo của tôi + số chưa đọc (bản 2.1)
 * GET  ?action=notif_poll&after=ID               số chưa đọc + thông báo mới hơn ID (bản 2.3, chuông tự cập nhật)
 * POST action=notif_read {id (0 = tất cả)}       đánh dấu đã đọc
 * POST action=chat   {message, history[]}        trợ lý ảo trả lời câu hỏi về ứng dụng
 */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Session.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $c = db();
    $user = Auth::user($c);
    if ($user === null) {
        respond(401, ['error' => 'UNAUTHENTICATED', 'message' => 'Phiên đăng nhập đã hết. Hãy đăng nhập lại.']);
    }
    $userId = (int) $user['id'];
    $repo = new AnnotationRepository($c);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if (field($_GET, 'action') === 'notif_poll') {
            // Gọi vài giây một lần: rất nhẹ (một COUNT + tối đa 5 thông báo mới).
            $n = new NotificationRepository($c);
            $after = intField($_GET, 'after');
            respond(200, ['unread' => $n->unreadCount($userId), 'latest' => $n->latestId($userId), 'items' => $after > 0 ? $n->since($userId, $after) : []]);
        }
        if (field($_GET, 'action') === 'notifications') {
            $n = new NotificationRepository($c);
            respond(200, ['unread' => $n->unreadCount($userId), 'items' => $n->listFor($userId, 15)]);
        }
        if (field($_GET, 'action') === 'activity') {
            $projectId = intField($_GET, 'project');
            Access::require($c, $userId, $projectId, Access::VIEW);
            respond(200, ['events' => $repo->activity($projectId, 40, intField($_GET, 'screen'))]);
        }
        if (field($_GET, 'action') !== 'list') {
            throw new AppError('NOT_FOUND', 404);
        }
        $versionId = intField($_GET, 'version');
        $role = Access::require($c, $userId, Access::projectOfVersion($c, $versionId), Access::VIEW);
        respond(200, [
            'items' => $repo->listForVersion($versionId, field($_GET, 'status'), intField($_GET, 'author'), $userId),
            'role' => $role,
        ]);
    }
    if ($method !== 'POST') {
        throw new AppError('NOT_FOUND', 404);
    }
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new AppError('BAD_REQUEST', 400);
    }
    checkCsrf($input);

    switch ($input['action'] ?? '') {
        case 'create':
            $versionId = (int) ($input['version_id'] ?? 0);
            Access::require($c, $userId, Access::projectOfVersion($c, $versionId), Access::COMMENT);
            $meta = array_intersect_key($input, array_flip(['priority', 'category', 'assignee_id', 'due_date']));
            $item = $repo->create($versionId, (string) ($input['shape'] ?? ''), $input['x'] ?? null, $input['y'] ?? null,
                $input['w'] ?? null, $input['h'] ?? null, (string) ($input['body'] ?? ''), $userId, $meta, $input['points'] ?? null);
            respond(201, ['item' => $item]);
        case 'reply':
            $annotationId = (int) ($input['annotation_id'] ?? 0);
            Access::require($c, $userId, Access::projectOfAnnotation($c, $annotationId), Access::COMMENT);
            respond(201, $repo->reply($annotationId, (string) ($input['body'] ?? ''), $userId));
        case 'edit':
            $commentId = (int) ($input['comment_id'] ?? 0);
            Access::require($c, $userId, Access::projectOfComment($c, $commentId), Access::COMMENT);
            respond(200, $repo->editComment($commentId, (string) ($input['body'] ?? ''), (int) ($input['lock_version'] ?? 0), $userId));
        case 'status':
            $annotationId = (int) ($input['annotation_id'] ?? 0);
            Access::require($c, $userId, Access::projectOfAnnotation($c, $annotationId), Access::COMMENT);
            respond(200, $repo->changeStatus($annotationId, (string) ($input['to'] ?? ''), (int) ($input['lock_version'] ?? 0),
                $userId, isset($input['checked_version_id']) ? (int) $input['checked_version_id'] : null, (string) ($input['note'] ?? '')));
        case 'update':
            $annotationId = (int) ($input['annotation_id'] ?? 0);
            Access::require($c, $userId, Access::projectOfAnnotation($c, $annotationId), Access::COMMENT);
            $changes = array_intersect_key($input, array_flip(['priority', 'category', 'assignee_id', 'due_date', 'note']));
            $repo->update($annotationId, $changes, (int) ($input['lock_version'] ?? 0), $userId);
            respond(200, ['item' => $repo->find($annotationId, $userId)]);
        case 'notif_read':
            respond(200, ['updated' => (new NotificationRepository($c))->markRead($userId, (int) ($input['id'] ?? 0))]);
        case 'chat':
            respond(200, Assistant::answer((string) ($input['message'] ?? ''), is_array($input['history'] ?? null) ? $input['history'] : []));
        case 'react':
            $commentId = (int) ($input['comment_id'] ?? 0);
            Access::require($c, $userId, Access::projectOfComment($c, $commentId), Access::COMMENT);
            respond(200, $repo->toggleReaction($commentId, (string) ($input['kind'] ?? ''), $userId));
        default:
            throw new AppError('NOT_FOUND', 404);
    }
} catch (AppError $e) {
    respond($e->httpStatus, ['error' => $e->errorCode, 'message' => errorMessage($e->errorCode), 'details' => $e->details]);
} catch (Throwable $e) {
    error_log('Pinpoint API: ' . $e->getMessage());
    respond(503, ['error' => 'UNAVAILABLE', 'message' => errorMessage('UNAVAILABLE')]);
}
