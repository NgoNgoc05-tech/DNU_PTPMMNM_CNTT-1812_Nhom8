<?php

declare(strict_types=1);

// Bàn làm việc của bộ phận hỗ trợ (bản 2.2): xem mọi yêu cầu hỗ trợ, trả lời và đổi trạng thái.
// Chỉ tài khoản bộ phận hỗ trợ (users.is_support, mẫu: hotro@pinpoint.test) hoặc quản trị viên mới vào được.
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
if (($user['is_support'] ?? 'f') !== 't' && ($user['is_admin'] ?? 'f') !== 't') {
    throw new AppError('FORBIDDEN', 403);
}
$support = new SupportRepository($c);
$error = '';
$filter = (string) ($_GET['status'] ?? 'OPEN');
if ($filter !== '' && !isset(SupportRepository::STATUSES[$filter])) {
    $filter = 'OPEN';
}
if (isPost()) {
    checkCsrf($_POST);
    $tid = intField($_POST, 'id');
    try {
        if (field($_POST, 'action') === 'reset_link') {
            // Bản 2.3: cấp link đặt lại mật khẩu cho người gửi yêu cầu "Quên mật khẩu".
            $t = fetchOne($c, 'SELECT user_id FROM support_tickets WHERE id = $1 AND is_password_reset', [$tid]);
            if ($t === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            $link = PasswordReset::link(PasswordReset::issue($c, (int) $t['user_id'], (int) $user['id']));
            $support->answer($tid, 'Bộ phận hỗ trợ đã cấp link đặt lại mật khẩu lúc ' . date('H:i d/m/Y') . ' (hiệu lực '
                . PasswordReset::TTL_MINUTES . ' phút) và gửi qua cách liên hệ bạn đã ghi.', 'ANSWERED', (int) $user['id']);
            $_SESSION['issued_link'] = ['ticket' => $tid, 'link' => $link];
            // Yêu cầu đã chuyển sang "Đã trả lời": mở mục Tất cả để vẫn thấy link vừa cấp.
            redirect('/support_admin.php?status=#t' . $tid);
        }
        $status = field($_POST, 'status');
        $support->answer($tid, (string) ($_POST['reply'] ?? ''), $status, (int) $user['id']);
        flash('Đã cập nhật yêu cầu #' . $tid . ($status === 'ANSWERED' ? ' — người gửi đã nhận được thông báo.' : '.'));
        redirect('/support_admin.php?status=' . urlencode($filter) . '#t' . $tid);
    } catch (AppError $ex) {
        $error = errorMessage($ex->errorCode);
    }
}
$issued = $_SESSION['issued_link'] ?? null;
unset($_SESSION['issued_link']);
$counts = $support->counts();
$tickets = $support->listAll($filter);
$title = 'Bộ phận hỗ trợ';
$active = 'support';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-head">
<div>
<h1>Bộ phận hỗ trợ</h1>
<p><?= $counts['OPEN'] ?> yêu cầu đang chờ · <?= $counts['ANSWERED'] ?> đã trả lời · <?= $counts['CLOSED'] ?> đã đóng. Trả lời xong, người gửi nhận thông báo và đọc câu trả lời ở trang Hỗ trợ.</p>
</div>
</div>
<?php if ($error !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

<div class="filter-bar">
<nav class="segmented" aria-label="Lọc theo trạng thái">
<a href="/support_admin.php?status=OPEN"<?= $filter === 'OPEN' ? ' aria-current="page"' : '' ?>>Đang chờ <span class="n"><?= $counts['OPEN'] ?></span></a>
<a href="/support_admin.php?status=ANSWERED"<?= $filter === 'ANSWERED' ? ' aria-current="page"' : '' ?>>Đã trả lời <span class="n"><?= $counts['ANSWERED'] ?></span></a>
<a href="/support_admin.php?status=CLOSED"<?= $filter === 'CLOSED' ? ' aria-current="page"' : '' ?>>Đã đóng <span class="n"><?= $counts['CLOSED'] ?></span></a>
<a href="/support_admin.php?status="<?= $filter === '' ? ' aria-current="page"' : '' ?>>Tất cả <span class="n"><?= array_sum($counts) ?></span></a>
</nav>
</div>

<?php if ($tickets === []): ?>
<div class="card empty-state"><div class="empty-art"><?= icon('check-circle') ?></div><h3>Không có yêu cầu nào ở mục này</h3><p>Yêu cầu mới sẽ hiện ở đây và trong chuông thông báo.</p></div>
<?php endif; ?>

<div class="stack support-desk">
<?php foreach ($tickets as $t): ?>
<article class="card ticket-admin" id="t<?= (int) $t['id'] ?>" data-ticket="<?= (int) $t['id'] ?>">
<div class="row center">
<?= avatar($t['user_name'], (int) $t['user_id']) ?>
<div class="grow">
<strong>#<?= (int) $t['id'] ?> <?= e($t['subject']) ?></strong>
<div class="hint"><?= e($t['user_name']) ?> · <?= e($t['user_email']) ?> · <?= e(SupportRepository::CATEGORIES[$t['category']]) ?> · gửi <?= e(ago($t['created_at'])) ?></div>
</div>
<span class="badge <?= $t['status'] === 'ANSWERED' ? 'badge-resolved' : ($t['status'] === 'OPEN' ? 'badge-open' : 'badge-muted') ?>"><?= e(SupportRepository::STATUSES[$t['status']]) ?></span>
</div>
<p class="pre ticket-message"><?= e($t['message']) ?></p>
<?php if ($t['is_password_reset'] === 't'): ?>
<div class="reset-box">
<?php if ($issued !== null && (int) $issued['ticket'] === (int) $t['id']): ?>
<div class="flash warn" role="status"><?= icon('key') ?><span>Link chỉ hiện <strong>một lần</strong>, hiệu lực <?= PasswordReset::TTL_MINUTES ?> phút. Hãy copy và gửi cho <?= e($t['user_name']) ?> qua cách liên hệ ghi trong yêu cầu.</span></div>
<input class="issued-link" type="text" readonly value="<?= e($issued['link']) ?>" aria-label="Link đặt lại mật khẩu" data-select-all>
<?php endif; ?>
<form method="post" action="/support_admin.php?status=<?= e(urlencode($filter)) ?>">
<?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="reset_link">
<button class="btn btn-sm btn-ghost" type="submit"><?= icon('key') ?><?= $issued !== null && (int) $issued['ticket'] === (int) $t['id'] ? 'Cấp link khác' : 'Cấp link đặt lại mật khẩu (30 phút)' ?></button>
<span class="hint">Cấp link mới làm link cũ hết hiệu lực.</span>
</form>
</div>
<?php endif; ?>
<form method="post" action="/support_admin.php?status=<?= e(urlencode($filter)) ?>" class="ticket-answer">
<?= csrfField() ?>
<input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
<div class="field"><label for="reply<?= (int) $t['id'] ?>">Câu trả lời<?php if ($t['handled_by_name'] !== null): ?> <span class="hint">· lần cuối bởi <?= e($t['handled_by_name']) ?>, <?= e(ago($t['updated_at'])) ?></span><?php endif; ?></label>
<textarea id="reply<?= (int) $t['id'] ?>" name="reply" maxlength="4000" placeholder="Chào bạn, …"><?= e($t['reply']) ?></textarea></div>
<div class="row center">
<label class="hint" for="st<?= (int) $t['id'] ?>">Trạng thái sau khi lưu</label>
<select id="st<?= (int) $t['id'] ?>" name="status">
<?php foreach (SupportRepository::STATUSES as $k => $label): ?><option value="<?= $k ?>"<?= ($t['status'] === 'OPEN' ? 'ANSWERED' : $t['status']) === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
</select>
<span class="spacer"></span>
<button class="btn" type="submit"><?= icon('check') ?>Lưu và gửi trả lời</button>
</div>
</form>
</article>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
