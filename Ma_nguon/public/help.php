<?php

declare(strict_types=1);

// Hỗ trợ khách hàng: trợ lý ảo, câu hỏi thường gặp, gửi yêu cầu hỗ trợ và theo dõi yêu cầu của tôi.
require __DIR__ . '/../src/web.php';
$c = db();
$user = Auth::requireUser($c);
$support = new SupportRepository($c);
$error = '';
if (isPost()) {
    checkCsrf($_POST);
    try {
        if (field($_POST, 'action') === 'close') {
            $support->close($user['id'], intField($_POST, 'id'));
            flash('Đã đóng yêu cầu.');
        } else {
            $id = $support->create($user['id'], field($_POST, 'category'), field($_POST, 'subject'), (string) ($_POST['message'] ?? ''));
            flash('Đã gửi yêu cầu #' . $id . '. Bộ phận hỗ trợ sẽ trả lời tại mục “Yêu cầu của tôi”.');
        }
        redirect('/help.php#tickets');
    } catch (AppError $ex) {
        $error = errorMessage($ex->errorCode);
    }
}
$tickets = $support->listFor($user['id']);
$title = 'Hỗ trợ';
$active = 'help';
require __DIR__ . '/../templates/header.php';
?>
<div class="help-hero">
<h1>Chúng tôi có thể giúp gì cho bạn?</h1>
<p>Hỏi trợ lý ảo, xem câu hỏi thường gặp hoặc gửi yêu cầu cho bộ phận hỗ trợ.</p>
</div>

<div class="layout-side">
<section class="stack">
<div class="card chat-card" id="assistant">
<div class="section-title"><h2><?= icon('sparkle') ?>Trợ lý ảo Pinpoint</h2><span class="badge badge-muted" id="chat-mode">trả lời tức thì</span></div>
<div class="chat-log" id="chat-log" aria-live="polite"></div>
<div class="chat-suggest" id="chat-suggest">
<button type="button" data-ask="Cách đặt điểm góp ý?">Cách đặt điểm góp ý?</button>
<button type="button" data-ask="Xoá ảnh tải nhầm thế nào?">Xoá ảnh tải nhầm thế nào?</button>
<button type="button" data-ask="So sánh hai phiên bản ra sao?">So sánh hai phiên bản</button>
<button type="button" data-ask="Mời thành viên và cấp quyền">Mời thành viên</button>
<button type="button" data-ask="Thông báo khi được @ nhắc tên">Thông báo khi được @</button>
</div>
<form class="chat-form" id="chat-form">
<input type="text" id="chat-input" maxlength="500" placeholder="Nhập câu hỏi về cách dùng ứng dụng…" aria-label="Câu hỏi cho trợ lý ảo" autocomplete="off">
<button class="btn" type="submit"><?= icon('arrow-right') ?>Gửi</button>
</form>
</div>

<div class="card">
<h2><?= icon('chat') ?>Câu hỏi thường gặp</h2>
<div class="faq">
<?php foreach (Assistant::KB as [$topic, , $answer]): ?>
<details><summary><?= e($topic) ?></summary><p><?= nl2br(e($answer)) ?></p></details>
<?php endforeach; ?>
</div>
</div>
</section>

<aside class="stack">
<div class="card" id="new-ticket">
<h2><?= icon('inbox') ?>Gửi yêu cầu hỗ trợ</h2>
<?php if ($error !== ''): ?><div class="flash error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="/help.php">
<?= csrfField() ?>
<input type="hidden" name="action" value="create">
<div class="field"><label for="tcat">Loại yêu cầu</label><select id="tcat" name="category">
<?php foreach (SupportRepository::CATEGORIES as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
</select></div>
<div class="field"><label for="tsub">Tiêu đề</label><input id="tsub" name="subject" type="text" required maxlength="150" placeholder="Ví dụ: Không tải được ảnh JPEG"></div>
<div class="field"><label for="tmsg">Mô tả chi tiết</label><textarea id="tmsg" name="message" required maxlength="4000" placeholder="Bạn đang làm gì, thấy thông báo gì, mong muốn điều gì…"></textarea></div>
<button class="btn btn-block" type="submit">Gửi yêu cầu</button>
<p class="hint mt-s">Thường được trả lời trong 1 ngày làm việc. Không gửi mật khẩu trong yêu cầu.</p>
</form>
</div>

<div class="card" id="tickets">
<div class="section-title"><h2><?= icon('history') ?>Yêu cầu của tôi</h2><span class="count"><?= count($tickets) ?></span></div>
<?php if ($tickets === []): ?><p class="hint">Bạn chưa gửi yêu cầu nào.</p><?php endif; ?>
<ul class="ticket-list">
<?php foreach ($tickets as $t): ?>
<li>
<div class="row center"><strong>#<?= (int) $t['id'] ?> <?= e($t['subject']) ?></strong><span class="spacer"></span>
<span class="badge <?= $t['status'] === 'ANSWERED' ? 'badge-resolved' : ($t['status'] === 'OPEN' ? 'badge-open' : 'badge-muted') ?>"><?= e(SupportRepository::STATUSES[$t['status']]) ?></span></div>
<div class="hint"><?= e(SupportRepository::CATEGORIES[$t['category']]) ?> · <?= e(dateVi($t['created_at'])) ?></div>
<details><summary class="hint">Nội dung</summary><p class="pre"><?= e($t['message']) ?></p></details>
<?php if ($t['reply'] !== ''): ?><div class="ticket-reply"><strong>Bộ phận hỗ trợ:</strong><p class="pre"><?= e($t['reply']) ?></p></div><?php endif; ?>
<?php if ($t['status'] !== 'CLOSED'): ?>
<form method="post" action="/help.php"><?= csrfField() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
<button class="btn btn-xs btn-subtle" type="submit">Đóng yêu cầu</button></form>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</div>

<div class="card">
<h2><?= icon('target') ?>Tài liệu và kiểm tra kỹ thuật</h2>
<ul class="link-list">
<li><?= icon('target', 'sm') ?><a href="/calibration.php">Phép thử toạ độ (điểm 750; 150 trên ảnh 1000×500)</a></li>
<li><?= icon('note', 'sm') ?><span>Hướng dẫn cài đặt và kịch bản trình diễn: tệp <code>HUONG_DAN.md</code> trong kho mã</span></li>
<li><?= icon('keyboard', 'sm') ?><span>Phím tắt: bấm <kbd>?</kbd> trong trình xem ảnh</span></li>
</ul>
</div>
</aside>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
