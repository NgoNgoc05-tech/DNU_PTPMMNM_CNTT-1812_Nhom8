</main>
<?php if (!empty($user) && !($wide ?? false) && ($active ?? '') !== 'help'): ?>
<div class="chat-fab" id="chat-fab">
<button type="button" class="chat-fab-btn" id="chat-open" aria-expanded="false" aria-controls="chat-panel" title="Hỏi trợ lý ảo"><?= icon('sparkle') ?><span>Hỏi trợ lý</span></button>
<div class="chat-panel card" id="chat-panel" hidden role="dialog" aria-label="Trợ lý ảo Pinpoint">
<div class="section-title"><h2><?= icon('sparkle') ?>Trợ lý ảo</h2><button type="button" class="btn btn-subtle btn-icon btn-sm" id="chat-close" aria-label="Đóng"><?= icon('x') ?></button></div>
<div class="chat-log" id="chat-log" aria-live="polite"></div>
<div class="chat-suggest" id="chat-suggest">
<button type="button" data-ask="Cách đặt điểm góp ý?">Đặt điểm góp ý</button>
<button type="button" data-ask="Xoá ảnh tải nhầm thế nào?">Xoá ảnh tải nhầm</button>
<button type="button" data-ask="Liên hệ hỗ trợ">Liên hệ hỗ trợ</button>
</div>
<form class="chat-form" id="chat-form"><input type="text" id="chat-input" maxlength="500" placeholder="Hỏi về cách dùng ứng dụng…" aria-label="Câu hỏi cho trợ lý ảo" autocomplete="off"><button class="btn btn-icon" type="submit" aria-label="Gửi"><?= icon('arrow-right') ?></button></form>
<a class="hint" href="/help.php#new-ticket">Cần người hỗ trợ? Gửi yêu cầu →</a>
</div>
</div>
<?php endif; ?>
<script src="/assets/js/ui.js"></script>
<?php if (!empty($user)): ?><script src="/assets/js/notify-chat.js"></script><?php endif; ?>
</body>
</html>
