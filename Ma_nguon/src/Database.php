<?php

declare(strict_types=1);

// Cấu hình kết nối giống Lab 05: đọc .local/db.json (tạo bằng `bash lab.sh db-config`)
// rồi đặt biến môi trường PG*. Nếu không có tệp (ví dụ trên GitHub Actions) thì dùng
// biến môi trường PG* sẵn có. Không bao giờ in mật khẩu ra màn hình.
$configPath = __DIR__ . '/../.local/db.json';
if (is_file($configPath)) {
    $config = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($config)) {
        throw new RuntimeException('DATABASE_CONFIG_INVALID');
    }
    foreach (['PGHOST', 'PGPORT', 'PGDATABASE', 'PGUSER', 'PGPASSWORD'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key])) {
            throw new RuntimeException('DATABASE_CONFIG_INVALID');
        }
        if (getenv($key) === false || getenv('PINPOINT_ENV_OVERRIDE') !== '1') {
            putenv($key . '=' . $config[$key]);
        }
    }
}

function db(): PgSql\Connection
{
    static $connection = null;
    if ($connection instanceof PgSql\Connection) {
        return $connection;
    }
    $connection = openConnection();
    return $connection;
}

/** Mở một kết nối MỚI (dùng trong kiểm thử để mô phỏng hai người dùng đồng thời). */
function openConnection(): PgSql\Connection
{
    if (!extension_loaded('pgsql')) {
        throw new RuntimeException('PGSQL_EXTENSION_MISSING');
    }
    foreach (['PGHOST', 'PGDATABASE', 'PGUSER'] as $key) {
        if (!getenv($key)) {
            throw new RuntimeException('DATABASE_CONFIG_MISSING');
        }
    }
    // libpq tự đọc PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD từ môi trường.
    $connection = @pg_connect('connect_timeout=3', PGSQL_CONNECT_FORCE_NEW);
    if (!$connection) {
        throw new RuntimeException('DATABASE_UNAVAILABLE');
    }
    pg_query($connection, "SET TIME ZONE 'Asia/Ho_Chi_Minh'");
    return $connection;
}

/** SQL và dữ liệu luôn được truyền tách biệt ($1, $2...), không ghép chuỗi đầu vào. */
function queryParams(PgSql\Connection $connection, string $sql, array $params = []): PgSql\Result
{
    $result = @pg_query_params($connection, $sql, $params);
    if ($result === false) {
        $message = pg_last_error($connection);
        if (str_contains($message, 'duplicate key')) {
            throw new AppError('DUPLICATE', 409);
        }
        error_log('Pinpoint SQL error: ' . $message);
        throw new RuntimeException('DATABASE_OPERATION_FAILED');
    }
    return $result;
}

function fetchAll(PgSql\Connection $connection, string $sql, array $params = []): array
{
    return pg_fetch_all(queryParams($connection, $sql, $params)) ?: [];
}

function fetchOne(PgSql\Connection $connection, string $sql, array $params = []): ?array
{
    $row = pg_fetch_assoc(queryParams($connection, $sql, $params));
    return $row === false ? null : $row;
}

/**
 * Chạy $work trong một giao dịch. Có lỗi thì ROLLBACK để không ghi dở dang.
 * Nếu đã ở trong giao dịch (ví dụ kiểm thử tích hợp) thì dùng SAVEPOINT.
 */
function transaction(PgSql\Connection $connection, callable $work): mixed
{
    $nested = pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS;
    $savepoint = 'sp_' . bin2hex(random_bytes(4));
    queryParams($connection, $nested ? "SAVEPOINT $savepoint" : 'BEGIN');
    try {
        $result = $work($connection);
        queryParams($connection, $nested ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
        return $result;
    } catch (Throwable $e) {
        @pg_query($connection, $nested ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
        throw $e;
    }
}
