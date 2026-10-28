<?php

declare(strict_types=1);

// bash lab.sh setup: tạo bảng (CREATE TABLE IF NOT EXISTS) và nạp dữ liệu mẫu nếu CSDL còn trống.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
try {
    $c = db();
    $sql = (string) file_get_contents(__DIR__ . '/../database/schema.sql');
    if (!@pg_query($c, $sql)) {
        throw new RuntimeException('SCHEMA_FAILED: ' . pg_last_error($c));
    }
    echo "SCHEMA=OK\n";
    $count = (int) fetchOne($c, 'SELECT COUNT(*) AS n FROM users')['n'];
    if ($count > 0) {
        echo "SEED=BO_QUA | CSDL đã có $count tài khoản, không ghi đè dữ liệu đang có.\n";
        exit(0);
    }
    $r = Seeder::run($c);
    printf("SEED=OK | users=%d projects=%d versions=%d annotations=%d\n",
        count($r['users']), count($r['projects']), count($r['versions']), count($r['annotations']));
    echo "Đăng nhập: designer@pinpoint.test / reviewer@pinpoint.test / viewer@pinpoint.test — mật khẩu Pinpoint@123\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'SETUP_FAILED: ' . $e->getMessage() . "\n");
    exit(2);
}
