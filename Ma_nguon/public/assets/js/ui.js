// Tiện ích giao diện dùng chung cho mọi trang. Khi tắt JavaScript các form vẫn hoạt động.
(function () {
  'use strict';

  function toast(message, isError) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-wrap'; document.body.appendChild(wrap); }
    var t = document.createElement('div');
    t.className = 'toast ' + (isError ? 'error' : 'ok');
    t.setAttribute('role', 'status');
    t.textContent = message;
    wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 4500);
  }
  window.pinToast = window.pinToast || toast;

  // Giao diện sáng/tối
  var root = document.documentElement;
  var MOON = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
  var SUN = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  function paintToggle() {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.innerHTML = root.getAttribute('data-theme') === 'dark' ? SUN : MOON;
    });
  }
  document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('pinpoint-theme', next); } catch (e) { /* bỏ qua */ }
      paintToggle();
    });
  });
  paintToggle();

  // Form: tự gửi khi đổi lựa chọn; hỏi lại trước thao tác xoá.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });
  // Ô chỉ đọc chứa link (ví dụ link đặt lại mật khẩu): bấm vào là chọn hết để copy.
  document.querySelectorAll('input[data-select-all]').forEach(function (el) {
    el.addEventListener('focus', function () { el.select(); });
    el.addEventListener('click', function () { el.select(); });
  });
  // Bản 2.4: lọc danh sách dự án ngay khi gõ (Enter để tìm ở máy chủ, giữ lại khi tải lại trang).
  document.querySelectorAll('input[data-live-filter]').forEach(function (input) {
    var cards = document.querySelectorAll('.project-card[data-name]');
    var empty = document.getElementById('live-empty');
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var shown = 0;
      cards.forEach(function (c) { var ok = !q || c.dataset.name.indexOf(q) !== -1; c.hidden = !ok; if (ok) { shown++; } });
      if (empty) { empty.hidden = shown !== 0 || cards.length === 0; }
    });
  });

  // Bản 2.4: hộp xác nhận của ứng dụng, hiện giữa màn hình (thay hộp confirm() của trình duyệt).
  // Dùng cho form có data-confirm hoặc nút gửi có data-confirm. Nội dung gán bằng textContent.
  function confirmBox(message, danger) {
    return new Promise(function (resolve) {
      var d = document.createElement('dialog');
      d.className = 'modal confirm-dialog';
      d.setAttribute('aria-labelledby', 'confirm-title');
      var body = document.createElement('div');
      body.className = 'confirm-body';
      var ic = document.createElement('span');
      ic.className = 'confirm-icon' + (danger ? ' is-danger' : '');
      ic.textContent = danger ? '!' : '?';
      var txt = document.createElement('div');
      var h = document.createElement('h2');
      h.id = 'confirm-title';
      h.textContent = danger ? 'Xác nhận xoá' : 'Xác nhận';
      var p = document.createElement('p');
      p.textContent = message;
      txt.appendChild(h); txt.appendChild(p);
      body.appendChild(ic); body.appendChild(txt);
      var foot = document.createElement('div');
      foot.className = 'modal-foot';
      var no = document.createElement('button');
      no.type = 'button'; no.className = 'btn btn-ghost'; no.textContent = 'Huỷ';
      var yes = document.createElement('button');
      yes.type = 'button'; yes.className = 'btn ' + (danger ? 'btn-danger-solid' : ''); yes.textContent = danger ? 'Xoá' : 'Đồng ý';
      yes.setAttribute('data-confirm-ok', '');
      foot.appendChild(no); foot.appendChild(yes);
      d.appendChild(body); d.appendChild(foot);
      document.body.appendChild(d);
      var done = function (v) { d.close(); d.remove(); resolve(v); };
      no.addEventListener('click', function () { done(false); });
      yes.addEventListener('click', function () { done(true); });
      d.addEventListener('cancel', function (e) { e.preventDefault(); done(false); });
      d.addEventListener('click', function (e) { if (e.target === d) { done(false); } }); // bấm ra ngoài = huỷ
      d.showModal();
      (danger ? no : yes).focus();
    });
  }
  window.PinConfirm = confirmBox;
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var btn = e.submitter;
    var msg = (btn && btn.dataset && btn.dataset.confirm) || form.dataset.confirm;
    if (!msg || form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
    e.preventDefault();
    var danger = /^(Xoá|Xóa|Huỷ lời mời|Khoá)/.test(msg) || form.hasAttribute('data-confirm-danger');
    confirmBox(msg, danger).then(function (ok) {
      if (!ok) { return; }
      form.dataset.confirmed = '1';
      if (form.requestSubmit) { form.requestSubmit(btn || undefined); } else { form.submit(); }
    });
  }, true);

  // Hộp thoại: nút có data-open="id-dialog" mở <dialog>; data-close đóng.
  document.querySelectorAll('[data-open]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.dataset.open);
      if (d && d.showModal) { d.showModal(); var f = d.querySelector('input:not([type=hidden]), textarea, select'); if (f) { f.focus(); } }
    });
  });
  document.querySelectorAll('dialog [data-close]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });
  document.querySelectorAll('dialog.modal').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
  });

  // Kéo thả ảnh vào vùng tải lên.
  document.querySelectorAll('.dropzone').forEach(function (z) {
    var input = z.querySelector('input[type=file]');
    var label = z.querySelector('[data-file-name]');
    function show() { if (label && input.files.length) { label.textContent = input.files[0].name + ' · ' + Math.round(input.files[0].size / 1024) + ' KB'; } }
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(function (ev) { z.addEventListener(ev, function (e) { e.preventDefault(); z.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { z.addEventListener(ev, function () { z.classList.remove('is-over'); }); });
    z.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); } });
  });

  // Đóng menu thả xuống khi bấm ra ngoài hoặc nhấn Esc.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.dropdown[open]').forEach(function (d) { if (!d.contains(e.target)) { d.removeAttribute('open'); } });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { document.querySelectorAll('details.dropdown[open]').forEach(function (d) { d.removeAttribute('open'); }); }
  });

  // Trang đăng nhập: bấm tài khoản mẫu để điền sẵn.
  document.querySelectorAll('[data-demo-email]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('email').value = b.dataset.demoEmail;
      document.getElementById('password').value = 'Pinpoint@123';
      document.getElementById('password').focus();
    });
  });

  // In / lưu PDF danh sách góp ý.
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  // Thông báo flash trên các trang toàn màn hình.
  var f = document.querySelector('[data-flash]');
  if (f) { toast(f.dataset.flash, f.dataset.flashError === '1'); }
})();
