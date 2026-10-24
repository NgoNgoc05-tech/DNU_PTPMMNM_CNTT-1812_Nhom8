<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/View.php';

securityHeaders();

/** Hiển thị trang lỗi thân thiện; không lộ thông tin kỹ thuật. */
function renderError(Throwable $e): never
{
    if ($e instanceof AppError) {
        http_response_code($e->httpStatus);
        $message = errorMessage($e->errorCode);
    } else {
        error_log('Pinpoint: ' . $e->getMessage());
        http_response_code(503);
        $message = 'Không kết nối được cơ sở dữ liệu hoặc có lỗi máy chủ. Hãy chạy "bash lab.sh check".';
    }
    $title = 'Lỗi';
    $user = null;
    require __DIR__ . '/../templates/header.php';
    echo '<div class="card"><h1>Không thực hiện được</h1><p>' . e($message) . '</p><p><a href="/projects.php">← Về danh sách dự án</a></p></div>';
    require __DIR__ . '/../templates/footer.php';
    exit;
}

set_exception_handler('renderError');

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function dateVi(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '';
    }
    $t = strtotime($ts);
    return $t === false ? '' : date('d/m/Y H:i', $t);
}

