<?php

declare(strict_types=1);

// Quản trị hệ thống từ dòng lệnh (bản 2.3):
//   php tools/admin.php list                — liệt kê quản trị viên
//   php tools/admin.php grant <email>       — cấp quyền quản trị hệ thống
//   php tools/admin.php revoke <email>      — bỏ quyền quản trị (luôn giữ ít nhất một quản trị viên)
//   php tools/admin.php unlock <email>      — mở khoá tài khoản
//   php tools/admin.php reset-link <email>  — in link đặt lại mật khẩu (30 phút, dùng một lần)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
$c = db();
$cmd = $argv[1] ?? 'list';
$email = strtolower(trim((string) ($argv[2] ?? '')));
$u = $email !== '' ? fetchOne($c, 'SELECT id, email FROM users WHERE email = $1', [$email]) : null;
if ($cmd === 'list') {
    foreach (fetchAll($c, 'SELECT email, name, locked_at FROM users WHERE is_admin ORDER BY id') as $r) {
        echo $r['email'] . ' — ' . $r['name'] . ($r['locked_at'] !== null ? ' (đang bị khoá)' : '') . "\n";
    }
    exit(0);
}
if ($u === null) {
    fwrite(STDERR, $email === '' ? "Cách dùng: php tools/admin.php list | grant <email> | revoke <email> | unlock <email> | reset-link <email>\n"
        : "Không tìm thấy tài khoản $email.\n");
    exit(1);
}
switch ($cmd) {
    case 'grant':
        queryParams($c, 'UPDATE users SET is_admin = true WHERE id = $1', [(int) $u['id']]);
        echo "Đã cấp quyền quản trị hệ thống cho $email. Đăng nhập lại để thấy menu Quản trị.\n";
        break;
    case 'revoke':
        if ((int) fetchOne($c, 'SELECT COUNT(*) AS n FROM users WHERE is_admin AND id <> $1', [(int) $u['id']])['n'] === 0) {
            fwrite(STDERR, "Không bỏ được: hệ thống cần ít nhất một quản trị viên.\n");
            exit(1);
        }
        queryParams($c, 'UPDATE users SET is_admin = false WHERE id = $1', [(int) $u['id']]);
        echo "Đã bỏ quyền quản trị của $email.\n";
        break;
    case 'unlock':
        queryParams($c, "UPDATE users SET locked_at = NULL, locked_reason = '' WHERE id = $1", [(int) $u['id']]);
        echo "Đã mở khoá $email.\n";
        break;
    case 'reset-link':
        $base = getenv('PINPOINT_BASE_URL') ?: 'http://127.0.0.1:8115';
        echo rtrim($base, '/') . '/reset_password.php?token=' . PasswordReset::issue($c, (int) $u['id']) . "\n";
        echo '(hiệu lực ' . PasswordReset::TTL_MINUTES . " phút, dùng một lần)\n";
        break;
    default:
        fwrite(STDERR, "Lệnh không hợp lệ.\n");
        exit(1);
}
