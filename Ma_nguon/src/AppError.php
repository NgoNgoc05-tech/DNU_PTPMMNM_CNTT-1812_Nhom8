<?php

declare(strict_types=1);

/**
 * Lỗi nghiệp vụ có mã ổn định để giao diện và kiểm thử đối chiếu:
 * FORBIDDEN (403), NOT_FOUND (404), CONFLICT (409), INVALID_* (422)...
 */
final class AppError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($errorCode, $httpStatus);
    }
}
