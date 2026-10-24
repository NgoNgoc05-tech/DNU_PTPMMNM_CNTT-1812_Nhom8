<?php

declare(strict_types=1);

/*
 * Kiểm thử tích hợp với PostgreSQL thật — năm phép thử bắt buộc của đề tài.
 * Bộ kiểm thử tự nạp một bộ dữ liệu mẫu RIÊNG (email có hậu tố ngẫu nhiên, ảnh lưu ở thư
 * mục tạm) rồi XOÁ sạch khi kết thúc, nên không ảnh hưởng dữ liệu demo. Dữ liệu được ghi
 * thật (COMMIT) để mô phỏng hai người dùng trên hai kết nối khác nhau.
 * Chạy: php tests/integration.php   (hoặc bash lab.sh test)
 */
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/bootstrap.php';

$storage = sys_get_temp_dir() . '/pinpoint-it-' . bin2hex(random_bytes(4));
putenv('PINPOINT_STORAGE=' . $storage);
try {
    $c = openConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "NOT_RUN: chưa kết nối được PostgreSQL ({$e->getMessage()}). Chạy bash lab.sh db-config rồi bash lab.sh setup.\n");
    exit(2);
}
if (fetchOne($c, "SELECT to_regclass('public.annotations') AS t")['t'] === null) {
    fwrite(STDERR, "NOT_RUN: chưa có bảng. Chạy bash lab.sh setup.\n");
    exit(2);
}

$suffix = 'it' . bin2hex(random_bytes(3));
$fx = null;
try {
    $fx = Seeder::run($c, null, $suffix);
    [$D, $R, $V] = [$fx['users']['D'], $fx['users']['R'], $fx['users']['V']];
    [$A, $B] = [$fx['projects']['A'], $fx['projects']['B']];
    $v = $fx['versions'];
    $repo = new AnnotationRepository($c);
    $screens = new ScreenRepository($c);

    echo "-- Dữ liệu mẫu có đáp án\n";
    $all = array_merge($repo->search($A), $repo->search($B));
    same(count($all), 20, 'seed-20-annotations');
    same(count(array_filter($all, fn ($a) => $a['shape'] === 'POINT')), 11, 'seed-11-points');
    same(count(array_filter($all, fn ($a) => $a['shape'] === 'RECT')), 9, 'seed-9-rects');
    same(count(array_filter($all, fn ($a) => $a['status'] === 'OPEN')), 13, 'seed-13-open');
    same(count($fx['versions']), 12, 'seed-12-image-versions');
    same([Access::roleFor($c, $D, $A), Access::roleFor($c, $R, $A), Access::roleFor($c, $V, $A)], ['ADMIN', 'COMMENT', 'VIEW'], 'seed-3-roles');

    echo "-- Phép thử 1: điểm (750;150) trên ảnh 1000×500 hiển thị 500×250, có lề và phóng to\n";
    $home1 = $screens->version($v['A|Trang chủ|1']);
    same([$home1['width'], $home1['height']], [1000, 500], 't1-image-1000x500');
    $sample = $repo->listForVersion($home1['id'])[0];
    same([$sample['seq_no'], $sample['x'], $sample['y']], [1, 750.0, 150.0], 't1-sample-stored-in-original-pixels');
    foreach ([[500, 250, 1.0, 375.0, 75.0], [500, 400, 1.0, 375.0, 150.0], [800, 300, 1.0, 550.0, 90.0], [500, 250, 2.0, 500.0, 25.0]] as [$bw, $bh, $zoom, $ex, $ey]) {
        $content = Coordinates::fitContain($bw, $bh, $home1['width'], $home1['height'], $zoom);
        [$sx, $sy] = Coordinates::toScreen($sample['x'], $sample['y'], $content);
        same([round($sx, 2), round($sy, 2)], [$ex, $ey], "t1-screen-{$bw}x{$bh}-zoom{$zoom}");
    }
    throwsCode(fn () => $repo->create($home1['id'], 'POINT', 1000.5, 10, null, null, 'Ngoài ảnh', $R), 'POSITION_OUTSIDE_IMAGE', 't1-margin-click-rejected-by-server');

    echo "-- Phép thử 2: lưu rồi mở lại vùng chữ nhật\n";
    $rect = $repo->create($home1['id'], 'RECT', 123.45, 67.8, 200, 100.25, "Vùng thử <b>đậm</b> & 'nháy'", $R);
    $c2 = openConnection(); // mở lại bằng kết nối khác = đọc từ CSDL, không phải bộ nhớ đệm
    $again = (new AnnotationRepository($c2))->find($rect['id']);
    same([$again['shape'], $again['x'], $again['y'], $again['w'], $again['h']], ['RECT', 123.45, 67.8, 200.0, 100.25], 't2-rect-position-size');
    same($again['body'], "Vùng thử <b>đậm</b> & 'nháy'", 't2-body-literal');
    same($again['image_version_id'], $home1['id'], 't2-bound-to-version');
    same($again['seq_no'], 5, 't2-next-seq-number');
    throwsCode(fn () => $repo->create($home1['id'], 'RECT', 900, 400, 150, 50, 'Tràn mép', $R), 'POSITION_OUTSIDE_IMAGE', 't2-rect-overflow-rejected');

    echo "-- Phép thử 3: phiên bản mới có nút chuyển vị trí\n";
    $tmpFile = sys_get_temp_dir() . '/pinpoint-v3-' . getmypid() . '.png';
    copy(__DIR__ . '/../database/fixtures/a-trang-chu-v2.png', $tmpFile);
    $v3 = $screens->addVersion($home1['screen_id'], ImageStore::fromFile($tmpFile), $D, 'Nút đổi chỗ');
    unlink($tmpFile);
    same($v3['version_no'], 3, 't3-version-number-3');
    same($repo->listForVersion($v3['id']), [], 't3-new-version-has-no-old-marks');
    same(count($repo->listForVersion($home1['id'])), 5, 't3-old-marks-stay-on-v1');
    same($repo->find($sample['id'])['x'], 750.0, 't3-old-mark-keeps-old-coordinates');
    same(array_column($screens->versions($home1['screen_id']), 'version_no'), ['1', '2', '3'], 't3-all-versions-listed');
    same(is_file(ImageStore::path($home1['stored_name'])), true, 't3-old-image-file-kept');
    $full = $repo->listForVersion($home1['id']);
    same(count($full[0]['history']), 2, 't3-history-still-readable');

    echo "-- Phép thử 4: quyền và truy cập chéo dự án\n";
    throwsCode(fn () => Access::require($c, $V, $A, Access::COMMENT), 'FORBIDDEN', 't4-viewer-cannot-create-or-edit');
    same(Access::require($c, $V, $A, Access::VIEW), 'VIEW', 't4-viewer-can-view-A');
    $bVersion = $v['B|Đăng nhập|1'];
    throwsCode(fn () => Access::require($c, $V, Access::projectOfVersion($c, $bVersion), Access::VIEW), 'NOT_FOUND', 't4-member-A-cannot-read-image-B');
    $bAnnotation = $repo->listForVersion($bVersion)[0]['id'];
    throwsCode(fn () => Access::require($c, $V, Access::projectOfAnnotation($c, $bAnnotation), Access::VIEW), 'NOT_FOUND', 't4-member-A-cannot-read-comment-B');
    throwsCode(fn () => Access::require($c, $R, $A, Access::ADMIN), 'FORBIDDEN', 't4-commenter-cannot-manage');
    throwsCode(fn () => $repo->editComment($sample['comment_id'], 'Sửa hộ', $sample['comment_lock'], $D), 'FORBIDDEN', 't4-cannot-edit-others-comment');
    throwsCode(fn () => (new ProjectRepository($c))->changeRole($A, $D, 'VIEW'), 'OWNER_MUST_STAY_ADMIN', 't4-owner-stays-admin');

    echo "-- Phép thử 5: hai người trả lời, sửa từ bản cũ, đóng rồi mở lại\n";
    $target = $repo->listForVersion($v['A|Trang chủ|1'])[1]; // góp ý #2, chưa có trả lời
    $connR = openConnection();
    $connD = openConnection();
    queryParams($connR, 'BEGIN');
    queryParams($connD, 'BEGIN');
    (new AnnotationRepository($connR))->reply($target['id'], 'Trả lời của giảng viên', $R);
    (new AnnotationRepository($connD))->reply($target['id'], 'Trả lời của người thiết kế', $D);
    queryParams($connD, 'COMMIT');
    queryParams($connR, 'COMMIT');
    $after = $repo->find($target['id']);
    same($after['reply_count'], 2, 't5-two-concurrent-replies-kept');

    $mine = $repo->find($rect['id']);
    $staleLock = $mine['comment_lock'];                  // người dùng mở ô sửa ở tab 1
    $first = $repo->editComment($mine['comment_id'], 'Bản sửa ở tab 2', $staleLock, $R); // tab 2 lưu trước
    same($first['lock_version'], $staleLock + 1, 't5-first-edit-saved');
    try {
        $repo->editComment($mine['comment_id'], 'Bản sửa ở tab 1 (cũ)', $staleLock, $R);
        same('NO_ERROR', 'CONFLICT', 't5-stale-edit-reports-conflict');
    } catch (AppError $e) {
        same($e->errorCode, 'CONFLICT', 't5-stale-edit-reports-conflict');
        same($e->details['current_body'], 'Bản sửa ở tab 2', 't5-conflict-returns-current-text');
    }
    same($repo->find($rect['id'])['body'], 'Bản sửa ở tab 2', 't5-no-silent-overwrite');
    same((int) fetchOne($c, 'SELECT COUNT(*) AS n FROM comment_revisions WHERE comment_id = $1', [$mine['comment_id']])['n'], 1, 't5-edit-history-kept');

    $s1 = $repo->changeStatus($rect['id'], 'RESOLVED', $mine['lock_version'], $D, $v3['id'], 'Đã sửa ở v3');
    throwsCode(fn () => $repo->changeStatus($rect['id'], 'OPEN', $mine['lock_version'], $R), 'CONFLICT', 't5-stale-status-reports-conflict');
    $s2 = $repo->changeStatus($rect['id'], 'OPEN', $s1['lock_version'], $R, $v3['id'], 'Kiểm tra lại chưa đạt');
    same($s2['status'], 'OPEN', 't5-reopened');
    $h = $repo->listForVersion($home1['id'], '', 0);
    $h = array_values(array_filter($h, fn ($x) => $x['id'] === $rect['id']))[0]['history'];
    same(array_map(fn ($x) => [$x['from'], $x['to'], $x['checked_version_no']], $h),
        [[null, 'OPEN', 1], ['OPEN', 'RESOLVED', 3], ['RESOLVED', 'OPEN', 3]], 't5-close-reopen-history');
    throwsCode(fn () => $repo->changeStatus($rect['id'], 'RESOLVED', $s2['lock_version'], $D, $v['B|Hồ sơ|2']), 'CHECKED_VERSION_INVALID', 't5-checked-version-same-screen');

    echo "-- Bản 2.0: thuộc tính, ghi chú xử lý, cảm xúc, nhật ký hoạt động\n";
    $statsA = $repo->stats($A);
    // 14 góp ý mẫu của dự án A + 1 vùng tạo ở phép thử 2 phía trên; 2 góp ý mẫu đã quá hạn.
    same([$statsA['total'], $statsA['open'], $statsA['overdue']], [15, 11, 2], 'v2-stats-total-open-overdue');
    same(count(array_filter($repo->search($A), fn ($a) => $a['note'] !== '')), 4, 'v2-seed-notes-project-A');
    $m = $repo->create($home1['id'], 'POINT', 500, 250, null, null, 'Màu nút phụ chưa đủ tương phản', $R,
        ['priority' => 'HIGH', 'category' => 'COLOR', 'assignee_id' => $D, 'due_date' => '2030-01-15']);
    same([$m['priority'], $m['category'], $m['assignee_id'], $m['due_date'], $m['overdue']], ['HIGH', 'COLOR', $D, '2030-01-15', false], 'v2-create-with-meta');
    throwsCode(fn () => $repo->create($v['B|Đăng nhập|1'], 'POINT', 10, 10, null, null, 'x', $R, ['assignee_id' => $V]), 'ASSIGNEE_NOT_MEMBER', 'v2-assignee-must-be-member');
    throwsCode(fn () => $repo->create($home1['id'], 'POINT', 10, 10, null, null, 'x', $R, ['priority' => 'TOP']), 'PRIORITY_INVALID', 'v2-priority-whitelist');
    throwsCode(fn () => $repo->create($home1['id'], 'POINT', 10, 10, null, null, 'x', $R, ['due_date' => '2026-02-30']), 'DUE_DATE_INVALID', 'v2-due-date-validated');
    throwsCode(fn () => $repo->update($m['id'], ['note' => str_repeat('a', 4001)], $m['lock_version'], $D), 'NOTE_TOO_LONG', 'v2-note-length-limit');
    // Hai người cùng mở ghi chú: D lưu trước trên kết nối riêng, R lưu sau bằng lock cũ -> CONFLICT, không ghi đè.
    $c2 = openConnection();
    $repo2 = new AnnotationRepository($c2);
    $afterD = $repo2->update($m['id'], ['note' => 'D: tăng độ đậm lên #C2410C'], $m['lock_version'], $D);
    same([$afterD['note'], $afterD['note_updated_by'] !== null], ['D: tăng độ đậm lên #C2410C', true], 'v2-note-saved');
    try {
        $repo->update($m['id'], ['note' => 'R: bản cũ'], $m['lock_version'], $R);
        same('no-conflict', 'CONFLICT', 'v2-stale-note-conflict');
    } catch (AppError $ex) {
        same([$ex->errorCode, $ex->details['current']['note'] ?? ''], ['CONFLICT', 'D: tăng độ đậm lên #C2410C'], 'v2-stale-note-conflict');
    }
    throwsCode(fn () => $repo->changeStatus($m['id'], 'RESOLVED', $m['lock_version'], $R), 'CONFLICT', 'v2-status-shares-lock-with-note');
    same($repo->find($m['id'])['note'], 'D: tăng độ đậm lên #C2410C', 'v2-note-not-overwritten');
    $u = $repo->update($m['id'], ['priority' => 'URGENT', 'assignee_id' => 0, 'due_date' => ''], $afterD['lock_version'], $R);
    same([$u['priority'], $u['assignee_id'], $u['due_date']], ['URGENT', null, null], 'v2-update-clear-assignee-and-due');
    same(array_column($repo->find($m['id'])['events'], 'kind'), ['CREATED', 'NOTE', 'PRIORITY', 'ASSIGNEE', 'DUE'], 'v2-events-logged-in-order');
    $r1 = $repo->toggleReaction($m['comment_id'], 'LIKE', $R);
    $r2 = $repo2->toggleReaction($m['comment_id'], 'LIKE', $D);
    same([$r1['reactions'][0]['count'], $r2['reactions'][0]['count']], [1, 2], 'v2-two-people-react');
    $r3 = $repo->toggleReaction($m['comment_id'], 'LIKE', $R);
    same([$r3['reactions'][0]['count'], $r3['reactions'][0]['mine']], [1, false], 'v2-reaction-toggle-off');
    throwsCode(fn () => $repo->toggleReaction($m['comment_id'], 'ANGRY', $R), 'REACTION_INVALID', 'v2-reaction-whitelist');
    same(count($repo->search($A, '', 0, '', 0, ['priority' => 'URGENT'])), 2, 'v2-filter-priority');
    same(count($repo->search($A, 'OPEN', 0, '', 0, ['overdue' => true])), 2, 'v2-filter-overdue');
    same(count($repo->search($A, '', 0, 'C2410C')), 1, 'v2-search-matches-note');
    same(count(array_filter($repo->search($A, '', 0, '', 0, ['assignee' => -1]), fn ($a) => $a['assignee_id'] !== null)), 0, 'v2-filter-unassigned');
    same($repo->search($A, '', 0, '', 0, ['sort' => 'priority'])[0]['priority'], 'URGENT', 'v2-sort-priority');
    $mine = $repo->assignedTo($D, 50);
    same(count(array_filter($mine, fn ($x) => $x['overdue'])), 2, 'v2-my-tasks-overdue');
    same($repo->activity($A, 1)[0]['kind'], 'DUE', 'v2-project-activity-newest-first');
    $resolved = array_values(array_filter($repo->search($A, 'RESOLVED'), fn ($a) => $a['screen_name'] === 'Trang chủ' && $a['version_no'] === 1));
    same($resolved[0]['resolved_on_version_no'], 2, 'v2-resolved-on-version');
    $row = Export::rows([$repo->find($m['id'])])[1];
    same([array_slice(Export::HEADER, 14, 3), $row[14], $row[19]], [['Ưu tiên', 'Nhãn', 'Người phụ trách'], 'Khẩn cấp', 'D: tăng độ đậm lên #C2410C'], 'v2-export-new-columns');
    pg_close($c2);

    echo "-- Tìm kiếm và xuất danh sách\n";
    $open = $repo->search($A, 'OPEN');
    same(count(array_filter($open, fn ($a) => $a['status'] !== 'OPEN')), 0, 'search-open-only');
    same(count($repo->search($A, '', 0, 'Đặt ngay')), 1, 'search-by-text');
    same(count($repo->search($A, '', 0, '100%')), 0, 'search-percent-is-literal');
    same(count($repo->search($A, '', $D)), 0, 'filter-by-author');
    $rows = Export::rows($repo->search($A, 'OPEN'));
    same($rows[0][0], 'Màn hình', 'export-header');
    same(count($rows) - 1, count($open), 'export-row-count');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL integration: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    if ($fx !== null) {
        // Dọn dữ liệu thử: xoá dự án (CASCADE màn hình, phiên bản, góp ý) rồi xoá tài khoản thử.
        $ids = '{' . implode(',', $fx['projects']) . '}';
        @pg_query($c, 'ROLLBACK');
        queryParams($c, 'DELETE FROM projects WHERE id = ANY($1::bigint[])', [$ids]);
        queryParams($c, "DELETE FROM users WHERE email LIKE $1", ['%+' . $suffix . '@pinpoint.test']);
    }
    foreach (glob($storage . '/*') ?: [] as $file) {
        unlink($file);
    }
    @rmdir($storage);
}
finish();
