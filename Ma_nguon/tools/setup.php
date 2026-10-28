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
        // Bản 2.2: CSDL cũ chưa có tài khoản bộ phận hỗ trợ thì tạo một tài khoản để xử lý yêu cầu hỗ trợ.
        if ((int) fetchOne($c, 'SELECT COUNT(*) AS n FROM users WHERE is_support')['n'] === 0) {
            $data = require __DIR__ . '/../database/seed-data.php';
            $existing = fetchOne($c, 'SELECT id FROM users WHERE email = $1', [$data['support']['email']]);
            $sid = $existing !== null ? (int) $existing['id'] : Auth::createUser($c, $data['support']['email'], $data['support']['name'], $data['password']);
            queryParams($c, 'UPDATE users SET is_support = true WHERE id = $1', [$sid]);
            echo "SUPPORT=OK | Tài khoản bộ phận hỗ trợ: {$data['support']['email']} / {$data['password']}\n";
        }
        // Bản 2.3: CSDL cũ chưa có quản trị viên thì tạo admin@pinpoint.test (nhớ đổi mật khẩu, hoặc dùng tools/admin.php grant).
        if ((int) fetchOne($c, 'SELECT COUNT(*) AS n FROM users WHERE is_admin')['n'] === 0) {
            $data = require __DIR__ . '/../database/seed-data.php';
            $existing = fetchOne($c, 'SELECT id FROM users WHERE email = $1', [$data['admin']['email']]);
            $aid = $existing !== null ? (int) $existing['id'] : Auth::createUser($c, $data['admin']['email'], $data['admin']['name'], $data['password']);
            queryParams($c, 'UPDATE users SET is_admin = true WHERE id = $1', [$aid]);
            echo "ADMIN=OK | Tài khoản quản trị: {$data['admin']['email']} / {$data['password']} — hãy đổi mật khẩu ở trang Hồ sơ.\n";
            echo "           Muốn dùng tài khoản của bạn làm quản trị: php tools/admin.php grant email-cua-ban\n";
        }
        exit(0);
    }
    $r = Seeder::run($c);
    printf("SEED=OK | users=%d projects=%d versions=%d annotations=%d\n",
        count($r['users']), count($r['projects']), count($r['versions']), count($r['annotations']));
    echo "Đăng nhập: designer@pinpoint.test / reviewer@pinpoint.test / viewer@pinpoint.test — mật khẩu Pinpoint@123\n";
    echo "Bộ phận hỗ trợ: hotro@pinpoint.test / Pinpoint@123 (trang /support_admin.php)\n";
    echo "Quản trị hệ thống: admin@pinpoint.test / Pinpoint@123 (trang /admin.php)\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'SETUP_FAILED: ' . $e->getMessage() . "\n");
    exit(2);
}
