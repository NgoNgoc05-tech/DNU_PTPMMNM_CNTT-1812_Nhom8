<?php

declare(strict_types=1);

/*
 * Kiểm thử qua HTTP (giống người dùng thật gửi URL/requests): kiểm tra quyền ở MÁY CHỦ,
 * CSRF, truy cập chéo dự án bằng URL, và nội dung nhận xét được trả về như dữ liệu.
 * Tự chạy máy chủ PHP ở cổng 8199, tự tạo và tự xoá dữ liệu thử.
 */
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/bootstrap.php';

$port = (int) (getenv('PINPOINT_TEST_PORT') ?: 8199);
$base = "http://127.0.0.1:$port";
$storage = sys_get_temp_dir() . '/pinpoint-http-' . bin2hex(random_bytes(4));
$sessions = $storage . '-sessions';
@mkdir($sessions, 0700, true);
putenv('PINPOINT_STORAGE=' . $storage);
try {
    $c = openConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "NOT_RUN: chưa kết nối được PostgreSQL.\n");
    exit(2);
}

$server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $sessions, '-S', "127.0.0.1:$port", '-t', __DIR__ . '/../public'],
    [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null,
    array_merge(getenv(), ['PINPOINT_STORAGE' => $storage]));
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
    usleep(100000);
}

/** Một "trình duyệt" tối giản: giữ cookie phiên và token CSRF. */
final class Browser
{
    public string $cookie = '';
    public string $csrf = '';

    public function __construct(private string $base)
    {
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    public function request(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $h = array_merge(['Cookie: ' . $this->cookie], $headers);
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $h), 'content' => $body ?? '',
            'ignore_errors' => true, 'follow_location' => 0]]);
        $content = (string) @file_get_contents($this->base . $path, false, $ctx);
        $status = 0;
        $out = [];
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $out[strtolower(trim($k))] = trim($v);
                if (strtolower(trim($k)) === 'set-cookie') {
                    $this->cookie = explode(';', trim($v))[0];
                }
            }
        }
        if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $content, $m)) {
            $this->csrf = $m[1];
        }
        return [$status, $out, $content];
    }

    public function login(string $email): void
    {
        $this->request('GET', '/login.php');
        $this->request('POST', '/login.php', http_build_query(['csrf' => $this->csrf, 'email' => $email, 'password' => 'Pinpoint@123']),
            ['Content-Type: application/x-www-form-urlencoded']);
        $this->request('GET', '/projects.php'); // lấy token CSRF mới sau khi đổi phiên
    }

    public function api(array $payload, bool $withCsrf = true): array
    {
        return $this->request('POST', '/api.php', json_encode($payload),
            array_merge(['Content-Type: application/json'], $withCsrf ? ['X-CSRF-Token: ' . $this->csrf] : []));
    }
}

$suffix = 'http' . bin2hex(random_bytes(3));
$fx = null;
try {
    $fx = Seeder::run($c, null, $suffix);
    $email = fn (string $who) => str_replace('@', '+' . $suffix . '@', $who . '@pinpoint.test');
    $vA = $fx['versions']['A|Trang chủ|1'];
    $vB = $fx['versions']['B|Đăng nhập|1'];
    $repo = new AnnotationRepository($c);
    $annB = $repo->listForVersion($vB)[0];

    $anon = new Browser($base);
    [$st] = $anon->request('GET', "/api.php?action=list&version=$vA");
    same($st, 401, 'http-anonymous-api-401');
    [$st] = $anon->request('GET', "/image.php?id=$vA");
    same($st, 404, 'http-anonymous-image-denied');

    $viewer = new Browser($base);
    $viewer->login($email('viewer'));
    [$st, $hd] = $viewer->request('GET', "/image.php?id=$vA");
    same([$st, $hd['content-type'] ?? ''], [200, 'image/png'], 'http-viewer-reads-image-A');
    [$st] = $viewer->request('GET', "/image.php?id=$vB");
    same($st, 404, 'http-viewer-cannot-read-image-B-by-url');
    [$st] = $viewer->request('GET', "/api.php?action=list&version=$vB");
    same($st, 404, 'http-viewer-cannot-read-comments-B-by-url');
    [$st] = $viewer->request('GET', '/screen.php?id=' . $annB['screen_id']);
    same($st, 404, 'http-viewer-cannot-open-screen-B');
    [$st] = $viewer->request('GET', '/export.php?project=' . $fx['projects']['B']);
    same($st, 404, 'http-viewer-cannot-export-B');
    [$st, , $body] = $viewer->api(['action' => 'create', 'version_id' => $vA, 'shape' => 'POINT', 'x' => 10, 'y' => 10, 'body' => 'Thử']);
    same([$st, json_decode($body, true)['error'] ?? ''], [403, 'FORBIDDEN'], 'http-viewer-cannot-create');
    $first = $repo->listForVersion($vA)[0];
    [$st] = $viewer->api(['action' => 'reply', 'annotation_id' => $first['id'], 'body' => 'Thử']);
    same($st, 403, 'http-viewer-cannot-reply');
    [$st] = $viewer->api(['action' => 'status', 'annotation_id' => $first['id'], 'to' => 'OPEN', 'lock_version' => $first['lock_version']]);
    same($st, 403, 'http-viewer-cannot-change-status');

    $reviewer = new Browser($base);
    $reviewer->login($email('reviewer'));
    [$st, , $body] = $reviewer->api(['action' => 'create', 'version_id' => $vA, 'shape' => 'POINT', 'x' => 10, 'y' => 10, 'body' => 'x'], false);
    same([$st, $body], [403, 'CSRF_INVALID'], 'http-missing-csrf-rejected');
    $xss = '<img src=x onerror="alert(1)"> & <script>alert(2)</script>';
    [$st, , $body] = $reviewer->api(['action' => 'create', 'version_id' => $vA, 'shape' => 'RECT', 'x' => 100, 'y' => 100, 'w' => 50, 'h' => 40, 'body' => $xss]);
    $created = json_decode($body, true)['item'] ?? [];
    same([$st, $created['body'] ?? null], [201, $xss], 'http-comment-stored-literally');
    [$st, , $html] = $reviewer->request('GET', '/issues.php?project=' . $fx['projects']['A'] . '&q=onerror');
    same(str_contains($html, '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;') && !str_contains($html, '<img src=x'), true, 'http-comment-rendered-as-text');
    [, $hd] = $reviewer->request('GET', '/projects.php');
    same(str_contains($hd['content-security-policy'] ?? '', "script-src 'self'"), true, 'http-csp-header');
    [$st, , $body] = $reviewer->api(['action' => 'create', 'version_id' => $vA, 'shape' => 'POINT', 'x' => 1200, 'y' => 10, 'body' => 'x']);
    same([$st, json_decode($body, true)['error'] ?? ''], [422, 'POSITION_OUTSIDE_IMAGE'], 'http-outside-image-422');
    [$st, $hd, $csv] = $reviewer->request('GET', '/export.php?project=' . $fx['projects']['A'] . '&status=OPEN');
    same([$st, str_starts_with($hd['content-type'] ?? '', 'text/csv'), str_contains($csv, 'X (px ảnh gốc)')], [200, true, true], 'http-export-csv');

    $designer = new Browser($base);
    $designer->login($email('designer'));

    echo "-- Bản 2.0: thuộc tính, ghi chú, cảm xúc, nhật ký qua HTTP\n";
    [$st] = $viewer->api(['action' => 'update', 'annotation_id' => $first['id'], 'lock_version' => $first['lock_version'], 'note' => 'x']);
    same($st, 403, 'http-viewer-cannot-edit-note');
    [$st] = $viewer->api(['action' => 'react', 'comment_id' => $first['comment_id'], 'kind' => 'LIKE']);
    same($st, 403, 'http-viewer-cannot-react');
    [$st] = $viewer->request('GET', '/api.php?action=activity&project=' . $fx['projects']['B']);
    same($st, 404, 'http-viewer-cannot-read-activity-B');
    [$st, , $body] = $reviewer->api(['action' => 'update', 'annotation_id' => $first['id'], 'lock_version' => $first['lock_version'],
        'priority' => 'URGENT', 'note' => 'Ghi chú <b>thử</b>']);
    $upd = json_decode($body, true)['item'] ?? [];
    same([$st, $upd['priority'] ?? '', $upd['note'] ?? ''], [200, 'URGENT', 'Ghi chú <b>thử</b>'], 'http-reviewer-updates-priority-and-note');
    [$st, , $body] = $reviewer->api(['action' => 'update', 'annotation_id' => $first['id'], 'lock_version' => $first['lock_version'], 'priority' => 'LOW']);
    same([$st, json_decode($body, true)['error'] ?? ''], [409, 'CONFLICT'], 'http-stale-update-conflict-409');
    [$st, , $body] = $reviewer->api(['action' => 'react', 'comment_id' => $first['comment_id'], 'kind' => 'HEART']);
    $heart = array_values(array_filter(json_decode($body, true)['reactions'] ?? [], fn ($r) => $r['kind'] === 'HEART'));
    same([$st, $heart[0]['count'] ?? 0, $heart[0]['mine'] ?? false], [200, 1, true], 'http-reviewer-reacts');
    [$st, , $body] = $reviewer->api(['action' => 'react', 'comment_id' => $first['comment_id'], 'kind' => '<script>']);
    same($st, 422, 'http-invalid-reaction-422');
    [$st, , $body] = $reviewer->request('GET', '/api.php?action=activity&project=' . $fx['projects']['A']);
    same([$st, json_decode($body, true)['events'][0]['kind'] ?? ''], [200, 'NOTE'], 'http-activity-newest-first');
    [$st] = $reviewer->request('POST', '/project_edit.php', http_build_query(['csrf' => $reviewer->csrf, 'action' => 'project',
        'project_id' => $fx['projects']['A'], 'name' => 'Đổi trộm', 'description' => '']), ['Content-Type: application/x-www-form-urlencoded']);
    same($st, 403, 'http-reviewer-cannot-rename-project');
    [$st, , $html] = $designer->request('GET', '/inbox.php');
    same([$st, str_contains($html, 'Việc của tôi')], [200, true], 'http-inbox-page');

    $boundary = 'pinpointboundary';
    $multipart = "--$boundary\r\nContent-Disposition: form-data; name=\"csrf\"\r\n\r\n{$designer->csrf}\r\n"
        . "--$boundary\r\nContent-Disposition: form-data; name=\"screen_id\"\r\n\r\n{$first['screen_id']}\r\n"
        . "--$boundary\r\nContent-Disposition: form-data; name=\"image\"; filename=\"evil.png\"\r\nContent-Type: image/png\r\n\r\n<?php system('id'); ?>\r\n"
        . "--$boundary--\r\n";
    $before = count((new ScreenRepository($c))->versions($first['screen_id']));
    [$st] = $designer->request('POST', '/version_new.php', $multipart, ["Content-Type: multipart/form-data; boundary=$boundary"]);
    same([$st, count((new ScreenRepository($c))->versions($first['screen_id']))], [303, $before], 'http-fake-image-upload-rejected');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL http: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    proc_terminate($server);
    if ($fx !== null) {
        queryParams($c, 'DELETE FROM projects WHERE id = ANY($1::bigint[])', ['{' . implode(',', $fx['projects']) . '}']);
        queryParams($c, 'DELETE FROM users WHERE email LIKE $1', ['%+' . $suffix . '@pinpoint.test']);
    }
    foreach (array_merge(glob($storage . '/*') ?: [], glob($sessions . '/*') ?: []) as $file) {
        unlink($file);
    }
    @rmdir($storage);
    @rmdir($sessions);
}
finish();
