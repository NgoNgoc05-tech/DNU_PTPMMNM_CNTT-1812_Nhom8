<?php

declare(strict_types=1);

// bash lab.sh seed-users [số lượng]: thêm người dùng ảo (mặc định 100) để thử trang quản trị.
// Email dạng ten.ho.001@demo.pinpoint.test, mật khẩu Pinpoint@123. Chạy lại không tạo trùng.
// Xoá toàn bộ người dùng ảo: php tools/seed_users.php remove
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
try {
    $c = db();
    if (($argv[1] ?? '') === 'remove') {
        $ids = "SELECT id FROM users WHERE email LIKE '%@" . DemoUsers::DOMAIN . "'";
        queryParams($c, "DELETE FROM project_members WHERE user_id IN ($ids)");
        $n = pg_affected_rows(queryParams($c, "DELETE FROM users WHERE email LIKE '%@" . DemoUsers::DOMAIN . "'
            AND id NOT IN (SELECT created_by FROM annotations UNION SELECT author_id FROM comments)"));
        echo "DA_XOA=$n người dùng ảo (giữ lại người đã có góp ý / nhận xét).\n";
        exit(0);
    }
    $n = max(1, min(1000, (int) ($argv[1] ?? 100)));
    $r = DemoUsers::run($c, $n);
    printf("NGUOI_DUNG_AO=OK | tạo mới %d, đã có %d · %d bị khoá · %d bộ phận hỗ trợ · %d được thêm vào dự án mẫu\n",
        $r['created'], $r['skipped'], $r['locked'], $r['support'], $r['members']);
    echo 'Đăng nhập thử bằng bất kỳ email @' . DemoUsers::DOMAIN . " nào (chưa bị khoá) với mật khẩu Pinpoint@123.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'SEED_USERS_FAILED: ' . $e->getMessage() . "\n");
    exit(2);
}
