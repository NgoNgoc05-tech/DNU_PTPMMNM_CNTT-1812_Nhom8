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

    echo "-- Bản 2.1: thông báo, xoá ảnh tải nhầm, hồ sơ, hỗ trợ khách hàng\n";
    $notes = new NotificationRepository($c);
    $before = $notes->unreadCount($D);
    $mn = $repo->create($home1['id'], 'POINT', 300, 300, null, null, 'Nhờ @Nguyễn Thu Trang và @Phạm Gia Huy xem nút này', $R);
    same($notes->unreadCount($D), $before + 1, 'v21-mention-notifies-designer');
    same($notes->listFor($V, 1)[0]['kind'] ?? '', 'MENTION', 'v21-mention-notifies-viewer-member');
    same((int) fetchOne($c, 'SELECT COUNT(*) AS n FROM notifications WHERE user_id = $1 AND annotation_id = $2', [$R, $mn['id']])['n'], 0, 'v21-no-self-notification');
    $repo->update($mn['id'], ['assignee_id' => $D], $mn['lock_version'], $R);
    same($notes->listFor($D, 1)[0]['kind'], 'ASSIGNED', 'v21-assign-notifies');
    $countR = $notes->unreadCount($R);
    $repo->reply($mn['id'], 'Đã xem, sẽ sửa', $D);
    same([$notes->unreadCount($R), $notes->listFor($R, 1)[0]['kind']], [$countR + 1, 'REPLY'], 'v21-reply-notifies-author');
    $lockNow = $repo->find($mn['id'])['lock_version'];
    $repo->changeStatus($mn['id'], 'RESOLVED', $lockNow, $D);
    same($notes->listFor($R, 1)[0]['kind'], 'STATUS', 'v21-status-notifies-author');
    same($notes->markRead($R) >= 2 && $notes->unreadCount($R) === 0, true, 'v21-mark-all-read');
    same($notes->markRead($D, $notes->listFor($R, 1)[0]['id']), 0, 'v21-cannot-mark-others-notification');
    // Xoá phiên bản tải nhầm: chỉ phiên bản đó và góp ý của nó biến mất
    $wrong = $screens->addVersion($home1['screen_id'], ImageStore::fromFile(__DIR__ . '/../database/fixtures/a-gio-hang-v1.jpg'), $D, 'Tải nhầm');
    $repo->create($wrong['id'], 'POINT', 5, 5, null, null, 'Góp ý trên ảnh tải nhầm', $R);
    $file = ImageStore::path($wrong['stored_name']);
    $countVersions = count($screens->versions($home1['screen_id']));
    $screens->deleteVersion($wrong['id']);
    same([count($screens->versions($home1['screen_id'])), is_file($file), count($repo->listForVersion($home1['id'])) > 0],
        [$countVersions - 1, false, true], 'v21-delete-wrong-version-only');
    $single = $screens->create($A, 'Màn hình thử xoá', ImageStore::fromFile(__DIR__ . '/../database/fixtures/b-ho-so-v1.png'), $D);
    throwsCode(fn () => $screens->deleteVersion($single['version_id']), 'LAST_VERSION', 'v21-cannot-delete-last-version');
    same($screens->deleteScreen($single['screen_id']), $A, 'v21-delete-screen');
    throwsCode(fn () => $screens->find($single['screen_id']), 'NOT_FOUND', 'v21-deleted-screen-gone');
    $tmpProject = (new ProjectRepository($c))->create('Dự án thử xoá ' . $suffix, '', $D);
    queryParams($c, "INSERT INTO project_members (project_id, user_id, role) VALUES ($1, $2, 'ADMIN')", [$tmpProject, $R]);
    throwsCode(fn () => (new ProjectRepository($c))->delete($tmpProject, $R, 'Dự án thử xoá ' . $suffix), 'OWNER_ONLY', 'v21-only-owner-deletes-project');
    throwsCode(fn () => (new ProjectRepository($c))->delete($tmpProject, $D, 'sai tên'), 'CONFIRM_MISMATCH', 'v21-delete-project-needs-name');
    (new ProjectRepository($c))->delete($tmpProject, $D, 'Dự án thử xoá ' . $suffix);
    throwsCode(fn () => (new ProjectRepository($c))->find($tmpProject), 'NOT_FOUND', 'v21-project-deleted');
    // Hồ sơ và mật khẩu
    Auth::updateProfile($c, $V, 'Phạm Gia Huy', 'Sinh viên năm 3');
    same(fetchOne($c, 'SELECT title FROM users WHERE id = $1', [$V])['title'], 'Sinh viên năm 3', 'v21-profile-title');
    throwsCode(fn () => Auth::changePassword($c, $V, 'sai-mat-khau', 'MoiMoi123', 'MoiMoi123'), 'PASSWORD_WRONG', 'v21-password-needs-current');
    throwsCode(fn () => Auth::changePassword($c, $V, 'Pinpoint@123', 'MoiMoi123', 'KhacNhau1'), 'PASSWORD_MISMATCH', 'v21-password-repeat');
    Auth::changePassword($c, $V, 'Pinpoint@123', 'MoiMoi123', 'MoiMoi123');
    same(password_verify('MoiMoi123', fetchOne($c, 'SELECT password_hash FROM users WHERE id = $1', [$V])['password_hash']), true, 'v21-password-changed');
    // Hỗ trợ khách hàng
    $support = new SupportRepository($c);
    $ticket = $support->create($V, 'BUG', 'Không tải được ảnh', 'Ảnh JPEG 3 MB bị từ chối');
    throwsCode(fn () => $support->create($V, 'BUG', '', 'x'), 'TICKET_INVALID', 'v21-ticket-validated');
    $support->answer($ticket, 'Ảnh có thể là WebP đổi đuôi. Hãy xuất lại sang JPEG.');
    same([$support->listFor($V)[0]['status'], in_array($ticket, array_column($support->listFor($R), 'id'), false)], ['ANSWERED', false], 'v21-ticket-answered-and-private');
    $support->close($R, $ticket);
    same($support->listFor($V)[0]['status'], 'ANSWERED', 'v21-cannot-close-others-ticket');

    echo "-- Bản 2.2: bộ phận hỗ trợ xử lý yêu cầu\n";
    $S = (int) $fx['support'];
    same([SupportRepository::isStaff($c, $S), SupportRepository::isStaff($c, $V)], [true, false], 'v22-support-role');
    $notes = new NotificationRepository($c);
    $before = $notes->unreadCount($S);
    $t2 = $support->create($R, 'QUESTION', 'Cách xuất CSV', 'Xuất ở đâu?');
    $staffNote = $notes->listFor($S, 1)[0];
    same([$notes->unreadCount($S) - $before, $staffNote['kind'], $staffNote['link']], [1, 'TICKET', '/support_admin.php#t' . $t2], 'v22-new-ticket-notifies-support');
    same(in_array($t2, array_map('intval', array_column($support->listAll('OPEN'), 'id')), true), true, 'v22-support-sees-all-open');
    $support->answer($t2, 'Mở dự án → Danh sách góp ý → Xuất CSV.', 'ANSWERED', $S);
    $row = $support->listFor($R)[0];
    $userNote = $notes->listFor($R, 1)[0];
    same([$row['status'], $row['handled_by_name'], $userNote['kind'], $userNote['link']],
        ['ANSWERED', 'Bộ phận hỗ trợ Pinpoint', 'TICKET_REPLY', '/help.php#tickets'], 'v22-reply-notifies-user-with-handler');
    $n0 = $notes->unreadCount($R);
    $support->answer($t2, 'Mở dự án → Danh sách góp ý → Xuất CSV.', 'CLOSED', $S);
    same($notes->unreadCount($R), $n0, 'v22-status-only-change-no-duplicate-notice');
    throwsCode(fn () => $support->answer(999999999, 'x'), 'NOT_FOUND', 'v22-answer-missing-ticket');

    echo "-- Bản 2.2: lời mời tham gia dự án phải được chấp nhận\n";
    $projects = new ProjectRepository($c);
    $inviteProject = $projects->create('Dự án thử mời ' . $suffix, '', $D);
    $vEmail = fetchOne($c, 'SELECT email FROM users WHERE id = $1', [$V])['email'];
    same($projects->invite($inviteProject, $vEmail, 'COMMENT', $D, 'Vào góp ý nhé')['status'], 'INVITED', 'v22-invite-created');
    same(Access::roleFor($c, $V, $inviteProject), null, 'v22-invitee-not-member-yet');
    same(count($projects->pendingInvitations($inviteProject)), 1, 'v22-pending-listed-for-manager');
    $iv = array_values(array_filter($projects->invitationsFor($V), fn ($x) => (int) $x['project_id'] === $inviteProject))[0];
    same([$iv['role'], $iv['message']], ['COMMENT', 'Vào góp ý nhé'], 'v22-invitee-sees-invitation');
    same($notes->listFor($V, 1)[0]['kind'], 'INVITE', 'v22-invite-notification');
    same($projects->invite($inviteProject, $vEmail, 'VIEW', $D)['status'], 'RESENT', 'v22-reinvite-updates-pending');
    same(count($projects->pendingInvitations($inviteProject)), 1, 'v22-no-duplicate-pending');
    throwsCode(fn () => $projects->respondInvitation((int) $iv['id'], $R, true), 'INVITATION_NOT_FOUND', 'v22-other-user-cannot-accept');
    same($projects->respondInvitation((int) $iv['id'], $V, true), $inviteProject, 'v22-accept');
    same(Access::roleFor($c, $V, $inviteProject), 'VIEW', 'v22-member-after-accept-with-latest-role');
    same([$notes->listFor($D, 1)[0]['kind'], count($projects->pendingInvitations($inviteProject))], ['INVITE_REPLY', 0], 'v22-inviter-notified');
    throwsCode(fn () => $projects->respondInvitation((int) $iv['id'], $V, true), 'INVITATION_NOT_FOUND', 'v22-cannot-answer-twice');
    $rEmail = fetchOne($c, 'SELECT email FROM users WHERE id = $1', [$R])['email'];
    $projects->invite($inviteProject, $rEmail, 'COMMENT', $D);
    $ivR = (int) $projects->pendingInvitations($inviteProject)[0]['id'];
    $projects->respondInvitation($ivR, $R, false);
    same(Access::roleFor($c, $R, $inviteProject), null, 'v22-decline-not-member');
    $projects->invite($inviteProject, $rEmail, 'COMMENT', $D);
    $ivR2 = (int) $projects->pendingInvitations($inviteProject)[0]['id'];
    $projects->cancelInvitation($inviteProject, $ivR2);
    throwsCode(fn () => $projects->respondInvitation($ivR2, $R, true), 'INVITATION_NOT_FOUND', 'v22-cancelled-invite-unusable');
    same($projects->invite($inviteProject, $vEmail, 'ADMIN', $D)['status'], 'ROLE_UPDATED', 'v22-invite-existing-member-updates-role');
    $projects->delete($inviteProject, $D, 'Dự án thử mời ' . $suffix);

    echo "-- Bản 2.3: mũi tên, vẽ tay, ảnh đính kèm\n";
    $homeV2 = $v['A|Trang chủ|2'];
    $arrow = $repo->create($homeV2, 'ARROW', null, null, null, null, 'Nút này nên dời tới đây', $R, [], [[750, 150], [200, 260]]);
    $again = $repo->find($arrow['id']);
    same([$again['shape'], $again['x'], $again['y'], $again['points']], ['ARROW', 750.0, 150.0, [[750, 150], [200, 260]]], 'v23-arrow-saved-in-original-pixels');
    $drawPts = [[100, 300], [140, 320], [180, 300], [220, 330], [260, 300]];
    $draw = $repo->create($homeV2, 'DRAW', null, null, null, null, 'Khoanh lại cụm thẻ món', $R, [], $drawPts);
    same([$repo->find($draw['id'])['shape'], count($repo->find($draw['id'])['points'])], ['DRAW', 5], 'v23-draw-saved');
    throwsCode(fn () => $repo->create($homeV2, 'ARROW', null, null, null, null, 'x', $R, [], [[10, 10], [5000, 10]]), 'POSITION_OUTSIDE_IMAGE', 'v23-arrow-outside-rejected-by-server');
    same(@pg_query_params($c, "INSERT INTO annotations (image_version_id, seq_no, shape, x, y, created_by) VALUES ($1, 999, 'ARROW', 1, 1, $2)", [$homeV2, $R]) === false,
        true, 'v23-db-constraint-arrow-needs-points');
    @pg_query($c, 'ROLLBACK');
    same(Export::rows([$repo->find($arrow['id'])])[1][3], 'Mũi tên', 'v23-export-shape-label');
    $att = new AttachmentRepository($c);
    $replyId = $repo->reply($arrow['id'], 'Ảnh chụp minh hoạ', $R)['id'];
    $shot = ImageStore::fromFile(__DIR__ . '/../database/fixtures/a-trang-chu-v1.png');
    $file = $att->add($replyId, $shot, $R);
    same([$file['width'], $file['url']], [1000, '/attachment.php?id=' . $file['id']], 'v23-attachment-added');
    throwsCode(fn () => $att->add($replyId, ImageStore::fromFile(__DIR__ . '/../database/fixtures/a-trang-chu-v1.png'), $D), 'FORBIDDEN', 'v23-only-author-attaches');
    $listed = array_values(array_filter($repo->listForVersion($homeV2), fn ($a) => $a['id'] === $arrow['id']))[0];
    same(count($listed['replies'][0]['attachments']), 1, 'v23-attachment-listed-with-reply');
    same($att->find($file['id'])['project_id'], $A, 'v23-attachment-belongs-to-project');
    for ($i = 0; $i < 3; $i++) {
        $att->add($replyId, ImageStore::fromFile(__DIR__ . '/../database/fixtures/a-trang-chu-v1.png'), $R);
    }
    throwsCode(fn () => $att->add($replyId, ImageStore::fromFile(__DIR__ . '/../database/fixtures/a-trang-chu-v1.png'), $R), 'ATTACHMENT_LIMIT', 'v23-max-4-attachments');
    $names = AttachmentRepository::storedNames($c, 'version', $homeV2);
    same(count($names), 4, 'v23-attachment-files-tracked');

    echo "-- Bản 2.3: thông báo tức thời\n";
    $notes23 = new NotificationRepository($c);
    $mark = $notes23->latestId($D);
    $repo->reply($arrow['id'], 'Đã xem, @Nguyễn Thu Trang sửa giúp nhé', $R);
    $fresh = $notes23->since($D, $mark);
    same([count($fresh), $fresh[0]['kind'] ?? '', $fresh[0]['version_id'] ?? 0], [1, 'MENTION', $homeV2], 'v23-poll-returns-only-new');
    same($notes23->since($D, $notes23->latestId($D)), [], 'v23-poll-nothing-new');

    echo "-- Bản 2.3: quên mật khẩu\n";
    $vMail = fetchOne($c, 'SELECT email FROM users WHERE id = $1', [$V])['email'];
    same(PasswordReset::request($c, 'khong-ton-tai@pinpoint.test'), 'IGNORED', 'v23-forgot-unknown-email-ignored');
    same(PasswordReset::request($c, $vMail, 'Zalo 0900000000'), 'TICKET', 'v23-forgot-creates-ticket');
    $rt = fetchAll($c, "SELECT id, message, status FROM support_tickets WHERE user_id = $1 AND is_password_reset", [$V]);
    same([count($rt), str_contains($rt[0]['message'], 'Zalo 0900000000')], [1, true], 'v23-reset-ticket-has-contact');
    PasswordReset::request($c, $vMail);
    same((int) fetchOne($c, "SELECT COUNT(*) AS n FROM support_tickets WHERE user_id = $1 AND is_password_reset", [$V])['n'], 1, 'v23-repeat-request-no-duplicate-ticket');
    same(SupportRepository::isStaff($c, (int) $fx['admin']), true, 'v23-admin-is-support-staff');
    $token = PasswordReset::issue($c, $V, (int) $fx['support']);
    same([strlen($token), (int) (PasswordReset::find($c, $token)['id'] ?? 0)], [48, $V], 'v23-token-valid');
    same(fetchOne($c, 'SELECT COUNT(*) AS n FROM password_resets WHERE token_hash = $1', [$token])['n'], '0', 'v23-token-stored-hashed-only');
    $token2 = PasswordReset::issue($c, $V);
    same(PasswordReset::find($c, $token), null, 'v23-new-link-invalidates-old');
    throwsCode(fn () => PasswordReset::reset($c, $token2, 'MatKhauMoi1', 'KhacNhau'), 'PASSWORD_MISMATCH', 'v23-reset-repeat-must-match');
    same(PasswordReset::reset($c, $token2, 'MatKhauMoi1', 'MatKhauMoi1'), $V, 'v23-reset-password');
    same(password_verify('MatKhauMoi1', fetchOne($c, 'SELECT password_hash FROM users WHERE id = $1', [$V])['password_hash']), true, 'v23-new-password-works');
    throwsCode(fn () => PasswordReset::reset($c, $token2, 'Lan2Lan2Lan2', 'Lan2Lan2Lan2'), 'RESET_TOKEN_INVALID', 'v23-link-single-use');
    same(fetchOne($c, 'SELECT status FROM support_tickets WHERE id = $1', [(int) $rt[0]['id']])['status'], 'CLOSED', 'v23-reset-closes-ticket');
    $old = PasswordReset::issue($c, $V);
    queryParams($c, "UPDATE password_resets SET expires_at = now() - interval '1 minute' WHERE token_hash = $1", [hash('sha256', $old)]);
    same(PasswordReset::find($c, $old), null, 'v23-link-expires-after-30-min');
    same(PasswordReset::find($c, 'abc'), null, 'v23-malformed-token');

    echo "-- Bản 2.3: quản trị hệ thống\n";
    $admin = new AdminRepository($c);
    $AD = (int) $fx['admin'];
    same([AdminRepository::isAdmin($c, $AD), AdminRepository::isAdmin($c, $D)], [true, false], 'v23-admin-role');
    $st = $admin->stats();
    same(isset($st['users']['total'], $st['annotations']['open'], $st['tickets']['open'], $st['storage']), true, 'v23-system-stats');
    same(count($admin->daily(14)), 14, 'v23-daily-14-days');
    same(count($admin->users('+' . $suffix)), 5, 'v23-search-users');
    throwsCode(fn () => $admin->setLocked($AD, $AD, true), 'CANNOT_CHANGE_SELF', 'v23-cannot-lock-self');
    $admin->setLocked($AD, $V, true, 'Thử khoá');
    throwsCode(fn () => Auth::attempt($c, $vMail, 'MatKhauMoi1'), 'ACCOUNT_LOCKED', 'v23-locked-cannot-login');
    same(PasswordReset::request($c, $vMail), 'IGNORED', 'v23-locked-cannot-reset-password');
    same(count($admin->users('+' . $suffix, 'locked')), 1, 'v23-filter-locked');
    $admin->setLocked($AD, $V, false);
    same(Auth::attempt($c, $vMail, 'MatKhauMoi1')['email'] ?? '', $vMail, 'v23-unlocked-can-login');
    throwsCode(fn () => $admin->setRole($AD, $AD, 'admin', false), 'CANNOT_CHANGE_SELF', 'v23-cannot-drop-own-admin');
    $admin->setRole($AD, $D, 'support', true);
    same(SupportRepository::isStaff($c, $D), true, 'v23-grant-support');
    $admin->setRole($AD, $D, 'support', false);
    $link = $admin->resetLink($AD, $R);
    same(str_contains($link, '/reset_password.php?token='), true, 'v23-admin-reset-link');
    $admin->joinProject($AD, $B);
    same(Access::roleFor($c, $AD, $B), 'ADMIN', 'v23-admin-joins-project-as-manager');
    $tmpP = (new ProjectRepository($c))->create('Dự án admin xoá ' . $suffix, '', $D);
    throwsCode(fn () => $admin->deleteProject($AD, $tmpP, 'sai'), 'CONFIRM_MISMATCH', 'v23-admin-delete-needs-name');
    $admin->deleteProject($AD, $tmpP, 'Dự án admin xoá ' . $suffix);
    throwsCode(fn () => (new ProjectRepository($c))->find($tmpP), 'NOT_FOUND', 'v23-admin-deleted-project');
    $actions = array_column($admin->recentLog(10), 'action');
    same(array_slice($actions, 0, 3), ['DELETE_PROJECT', 'JOIN_PROJECT', 'RESET_LINK'], 'v23-admin-actions-logged');
    queryParams($c, "DELETE FROM admin_log WHERE admin_id = $1", [$AD]);

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
