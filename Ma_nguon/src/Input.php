<?php

declare(strict_types=1);

function field(array $input, string $key): string
{
    $value = $input[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function intField(array $input, string $key): int
{
    $value = filter_var(field($input, $key), FILTER_VALIDATE_INT);
    return $value === false ? 0 : (int) $value;
}

/** Mọi dữ liệu người dùng đều được escape khi in ra HTML: hiển thị như dữ liệu, không thực thi. */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Kiểm tra văn bản nhận xét: 1–2000 ký tự, không có ký tự NUL. */
function cleanBody(string $body): string
{
    $body = trim(str_replace("\r\n", "\n", $body));
    if ($body === '' || mb_strlen($body) > 2000 || str_contains($body, "\0")) {
        throw new AppError('BODY_INVALID', 422);
    }
    return $body;
}

function cleanName(string $name, int $max = 120): string
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > $max || str_contains($name, "\0")) {
        throw new AppError('NAME_INVALID', 422);
    }
    return $name;
}
