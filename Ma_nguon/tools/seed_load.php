<?php

declare(strict_types=1);

// Bản 2.5 — nạp dữ liệu thử tải ở quy mô ứng dụng thật, rồi đo tốc độ.
//   php tools/seed_load.php            tạo 100 người dùng (load001…load100@pinpoint.test, mật khẩu Pinpoint@123),
//                                      30 dự án, 240 phiên bản ảnh, ~2 000 góp ý, ~6 000 nhận xét, nhật ký, thông báo
//   php tools/seed_load.php --clean    xoá toàn bộ dữ liệu thử tải (không đụng dữ liệu khác)
//   php tools/bench.php                đo thời gian các truy vấn chính
// Dữ liệu sinh ngẫu nhiên nhưng cố định (mt_srand) để mỗi lần chạy giống nhau.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
$c = db();
const LOAD_EMAIL = 'load%@pinpoint.test';

function cleanLoad(PgSql\Connection $c): void
{
    $projects = array_column(fetchAll($c,
        'SELECT p.id FROM projects p JOIN users u ON u.id = p.created_by WHERE u.email LIKE $1', [LOAD_EMAIL]), 'id');
    $repo = new ProjectRepository($c);
    foreach ($projects as $pid) {
        $repo->purge((int) $pid);
    }
    queryParams($c, 'DELETE FROM project_members WHERE user_id IN (SELECT id FROM users WHERE email LIKE $1)', [LOAD_EMAIL]);
    $n = pg_affected_rows(queryParams($c, 'DELETE FROM users WHERE email LIKE $1', [LOAD_EMAIL]));
    echo 'CLEAN=OK | đã xoá ' . count($projects) . " dự án và $n người dùng thử tải\n";
}

if (in_array('--clean', $argv, true)) {
    cleanLoad($c);
    exit(0);
}
if ((int) fetchOne($c, 'SELECT COUNT(*) AS n FROM users WHERE email LIKE $1', [LOAD_EMAIL])['n'] > 0) {
    echo "Đã có dữ liệu thử tải. Chạy php tools/seed_load.php --clean trước nếu muốn tạo lại.\n";
    exit(0);
}

mt_srand(2025);
$t0 = microtime(true);
$ho = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];
$dem = ['Văn', 'Thị', 'Minh', 'Thu', 'Gia', 'Quốc', 'Ngọc', 'Hữu', 'Thanh', 'Đức', 'Bảo', 'Khánh'];
$ten = ['An', 'Bình', 'Chi', 'Dũng', 'Giang', 'Hà', 'Huy', 'Khoa', 'Lan', 'Linh', 'Long', 'Mai', 'Nam', 'Ngọc', 'Phúc', 'Quân', 'Sơn', 'Tâm', 'Trang', 'Tú', 'Vy', 'Yến'];
$pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];
$fixtures = [['Trang chủ', 'a-trang-chu'], ['Thực đơn', 'a-thuc-don'], ['Giỏ hàng', 'a-gio-hang'],
    ['Đăng nhập', 'b-dang-nhap'], ['Lịch học', 'b-lich-hoc'], ['Hồ sơ', 'b-ho-so']];
$dir = __DIR__ . '/../database/fixtures/';
$ext = fn (string $base, int $v) => is_file("$dir$base-v$v.png") ? "$dir$base-v$v.png" : "$dir$base-v$v.jpg";
$bodies = ['Nút này hơi nhỏ, khó bấm trên điện thoại.', 'Khoảng cách giữa tiêu đề và nội dung chưa đều.', 'Màu chữ chưa đủ tương phản với nền.',
    'Nên căn trái để thẳng hàng với lưới 8 px.', 'Biểu tượng chưa rõ nghĩa, cần thêm nhãn.', 'Thiếu trạng thái khi đang tải dữ liệu.',
    'Phông chữ khác với các màn hình còn lại.', 'Ô nhập cần báo lỗi ngay dưới ô.', 'Ảnh minh hoạ bị méo tỉ lệ.', 'Nút chính nên nổi bật hơn nút phụ.'];
$replies = ['Đã ghi nhận, mình sửa trong bản sau.', 'Đồng ý, để mình đổi lại.', 'Bản mới đã chỉnh, nhờ kiểm tra giúp.', 'Mình nghĩ giữ như cũ hợp hơn.', 'Cảm ơn góp ý!'];

transaction($c, function () use ($c, $ho, $dem, $ten, $pick, $fixtures, $ext, $bodies, $replies): void {
    // 1) 100 người dùng — băm mật khẩu một lần, dùng chung (chỉ là dữ liệu thử).
    $hash = password_hash('Pinpoint@123', PASSWORD_DEFAULT);
    $users = [];
    for ($i = 1; $i <= 100; $i++) {
        $name = $pick($ho) . ' ' . $pick($dem) . ' ' . $pick($ten);
        $users[] = (int) fetchOne($c,
            "INSERT INTO users (email, name, password_hash, created_at, last_login_at) VALUES ($1, $2, $3, now() - ($4 || ' days')::interval,
                    CASE WHEN $5 THEN now() - ($6 || ' hours')::interval END) RETURNING id",
            [sprintf('load%03d@pinpoint.test', $i), $name, $hash, mt_rand(1, 60), mt_rand(0, 4) > 0 ? 't' : 'f', mt_rand(1, 400)])['id'];
    }
    // 2) 30 dự án, mỗi dự án 6–15 thành viên, 4 màn hình × 2 phiên bản, ~10 góp ý mỗi phiên bản.
    $kinds = ['Ứng dụng', 'Trang web', 'Cổng thông tin', 'Hệ thống'];
    $topics = ['đặt món', 'thư viện', 'đăng ký học phần', 'bán vé xe', 'quản lý ký túc xá', 'tuyển sinh', 'đặt phòng', 'giao hàng', 'phòng khám', 'việc làm'];
    $stats = ['projects' => 0, 'versions' => 0, 'annotations' => 0, 'comments' => 0];
    for ($p = 1; $p <= 30; $p++) {
        $owner = $users[mt_rand(0, 99)];
        $pid = (new ProjectRepository($c))->create(sprintf('%s %s #%02d', $pick($kinds), $pick($topics), $p), 'Dự án thử tải (tools/seed_load.php).', $owner);
        $stats['projects']++;
        if ($p % 10 === 0) {
            queryParams($c, "UPDATE projects SET archived_at = now() - interval '3 days', archived_by = $2 WHERE id = $1", [$pid, $owner]);
        }
        $members = [$owner];
        foreach (array_rand(array_flip($users), mt_rand(6, 15)) as $uid) {
            if ($uid === $owner) {
                continue;
            }
            $role = ['VIEW', 'COMMENT', 'COMMENT', 'COMMENT', 'ADMIN'][mt_rand(0, 4)];
            queryParams($c, 'INSERT INTO project_members (project_id, user_id, role, added_by) VALUES ($1, $2, $3, $4)', [$pid, $uid, $role, $owner]);
            $members[] = $uid;
        }
        $screens = (new ScreenRepository($c));
        foreach (array_rand($fixtures, 4) as $fi) {
            [$sname, $base] = $fixtures[$fi];
            $created = $screens->create($pid, $sname, ImageStore::fromFile($ext($base, 1)), $owner);
            $v2 = $screens->addVersion($created['screen_id'], ImageStore::fromFile($ext($base, 2)), $owner, 'Bản sửa theo góp ý')['id'];
            $stats['versions'] += 2;
            foreach ([$created['version_id'], $v2] as $vid) {
                $dim = fetchOne($c, 'SELECT width, height FROM image_versions WHERE id = $1', [$vid]);
                for ($k = 1; $k <= mt_rand(6, 14); $k++) {
                    $author = $members[mt_rand(0, count($members) - 1)];
                    $status = mt_rand(0, 9) < 6 ? 'OPEN' : 'RESOLVED';
                    $assignee = mt_rand(0, 2) > 0 ? $members[mt_rand(0, count($members) - 1)] : null;
                    $due = mt_rand(0, 2) > 0 ? date('Y-m-d', strtotime(mt_rand(-10, 20) . ' days')) : null;
                    $age = mt_rand(1, 40 * 24);
                    $aid = (int) fetchOne($c,
                        "INSERT INTO annotations (image_version_id, seq_no, shape, x, y, w, h, status, created_by, priority, category, assignee_id, due_date,
                                                  created_at, updated_at)
                         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, now() - ($14 || ' hours')::interval, now() - ($15 || ' hours')::interval)
                         RETURNING id",
                        [$vid, $k, $k % 3 === 0 ? 'RECT' : 'POINT', mt_rand(10, (int) $dim['width'] - 120), mt_rand(10, (int) $dim['height'] - 80),
                         $k % 3 === 0 ? mt_rand(40, 110) : null, $k % 3 === 0 ? mt_rand(30, 70) : null, $status, $author,
                         ['LOW', 'MEDIUM', 'MEDIUM', 'HIGH', 'URGENT'][mt_rand(0, 4)], ['LAYOUT', 'COLOR', 'TYPO', 'CONTENT', 'UX', 'A11Y', 'OTHER'][mt_rand(0, 6)],
                         $assignee, $due, $age, mt_rand(0, $age)])['id'];
                    $root = (int) fetchOne($c, "INSERT INTO comments (annotation_id, author_id, body, created_at) VALUES ($1, $2, $3, now() - ($4 || ' hours')::interval) RETURNING id",
                        [$aid, $author, $pick($bodies), $age])['id'];
                    queryParams($c, "INSERT INTO status_history (annotation_id, from_status, to_status, changed_by, checked_version_id, note) VALUES ($1, NULL, 'OPEN', $2, $3, 'Tạo góp ý')",
                        [$aid, $author, $vid]);
                    queryParams($c, "INSERT INTO annotation_events (annotation_id, actor_id, kind, new_value, detail, created_at) VALUES ($1, $2, 'CREATED', 'POINT', '', now() - ($3 || ' hours')::interval)",
                        [$aid, $author, $age]);
                    for ($r = 0, $nr = mt_rand(0, 4); $r < $nr; $r++) {
                        $who = $members[mt_rand(0, count($members) - 1)];
                        queryParams($c, 'INSERT INTO comments (annotation_id, parent_id, author_id, body) VALUES ($1, $2, $3, $4)', [$aid, $root, $who, $pick($replies)]);
                        queryParams($c, "INSERT INTO annotation_events (annotation_id, actor_id, kind, detail) VALUES ($1, $2, 'REPLIED', 'Trả lời')", [$aid, $who]);
                        if ($who !== $author) {
                            queryParams($c, "INSERT INTO notifications (user_id, actor_id, kind, annotation_id, body, read_at) VALUES ($1, $2, 'REPLY', $3, $4, CASE WHEN $5 THEN now() END)",
                                [$author, $who, $aid, $pick($replies), mt_rand(0, 3) > 0 ? 't' : 'f']);
                        }
                        $stats['comments']++;
                    }
                    $stats['annotations']++;
                    $stats['comments']++;
                }
            }
        }
    }
    printf("LOAD=OK | users=100 projects=%d versions=%d annotations=%d comments=%d\n", $stats['projects'], $stats['versions'], $stats['annotations'], $stats['comments']);
});
printf("Thời gian nạp: %.1f giây. Đăng nhập thử: load001@pinpoint.test / Pinpoint@123. Đo tốc độ: php tools/bench.php\n", microtime(true) - $t0);
queryParams($c, 'ANALYZE');
