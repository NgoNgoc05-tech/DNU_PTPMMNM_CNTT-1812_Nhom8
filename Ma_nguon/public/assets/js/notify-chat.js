// Chuông thông báo (tải danh sách khi mở, tự cập nhật mỗi 8 giây + thẻ nổi khi có thông báo mới) và trợ lý ảo.
// Nội dung từ máy chủ luôn gán bằng textContent.
(function () {
  'use strict';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined) { n.textContent = text; }
    return n;
  }
  function initials(name) {
    var p = String(name || '?').replace(/\s*\(.*?\)\s*/g, ' ').trim().split(/\s+/);
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }
  function ago(ts) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):?(\d{2})?/.exec(ts || '');
    if (!m) { return ''; }
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0)) - 7 * 3600000);
    var s = (Date.now() - d.getTime()) / 1000;
    if (s < 60) { return 'vừa xong'; }
    if (s < 3600) { return Math.floor(s / 60) + ' phút trước'; }
    if (s < 86400) { return Math.floor(s / 3600) + ' giờ trước'; }
    return Math.floor(s / 86400) + ' ngày trước';
  }
  function post(csrf, payload) {
    return fetch('/api.php', { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(payload) }).then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw new Error(d.message || 'Lỗi'); } return d; }); });
  }

  // ------------------------------------------------------------------ thông báo
  var menu = document.getElementById('notif-menu');
  if (menu) {
    var csrf = menu.dataset.csrf;
    var count = document.getElementById('notif-count');
    var list = document.getElementById('notif-items');
    var setCount = function (n) {
      count.hidden = n === 0;
      count.textContent = n > 9 ? '9+' : String(n);
      menu.querySelector('summary').setAttribute('aria-label', 'Thông báo' + (n ? ' (' + n + ' chưa đọc)' : ''));
    };
    var load = function () {
      return fetch('/api.php?action=notifications', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.items) { return; }
          setCount(d.unread);
          while (list.firstChild) { list.removeChild(list.firstChild); }
          if (!d.items.length) { list.appendChild(el('p', 'hint notif-empty', 'Chưa có thông báo nào.')); return; }
          d.items.forEach(function (n) {
            var a = el(n.link ? 'a' : 'div', 'notif-item' + (n.read ? '' : ' is-unread'));
            if (n.link) { a.href = n.link; }
            a.addEventListener('click', function () { if (!n.read) { post(csrf, { action: 'notif_read', id: n.id }).catch(function () {}); } });
            var av = el('span', 'avatar avatar-sm avatar-c' + (n.actor_id % 8), initials(n.actor));
            var body = el('span', 'notif-text');
            body.appendChild(el('strong', '', String(n.actor || 'Ai đó').replace(/\s*\(.*?\)\s*/g, ' ').trim()));
            body.appendChild(document.createTextNode(' ' + n.label + (n.where ? ' · ' + n.where : '')));
            if (n.body) { body.appendChild(el('span', 'quote line-clamp-2', n.body)); }
            body.appendChild(el('time', '', ago(n.created_at)));
            a.appendChild(av);
            a.appendChild(body);
            list.appendChild(a);
          });
        }).catch(function () {});
    };
    menu.addEventListener('toggle', function () { if (menu.open) { load(); } });
    document.getElementById('notif-read-all').addEventListener('click', function () {
      post(csrf, { action: 'notif_read', id: 0 }).then(load).catch(function () {});
    });
    // Bản 2.3: thông báo tức thời — hỏi máy chủ mỗi 8 giây khi tab đang mở (dừng khi tab ẩn, hỏi ngay khi quay lại),
    // hiện thẻ nổi cho thông báo mới và báo cho trang đang mở (ví dụ trình xem tự tải lại góp ý).
    var latest = Number(menu.dataset.latest || 0);
    var polling = false;
    var POLL_MS = Number(menu.dataset.poll || 8000);
    var pop = function (n) {
      var wrap = document.querySelector('.live-wrap');
      if (!wrap) { wrap = el('div', 'live-wrap'); wrap.setAttribute('aria-live', 'polite'); document.body.appendChild(wrap); }
      var card = el(n.link ? 'a' : 'div', 'live-card');
      if (n.link) { card.href = n.link; }
      card.addEventListener('click', function () { post(csrf, { action: 'notif_read', id: n.id }).catch(function () {}); });
      var av = el('span', 'avatar avatar-sm avatar-c' + (n.actor_id % 8), initials(n.actor));
      var t = el('span', 'live-text');
      t.appendChild(el('strong', '', String(n.actor || 'Pinpoint').replace(/\s*\(.*?\)\s*/g, ' ').trim()));
      t.appendChild(document.createTextNode(' ' + n.label + (n.where ? ' · ' + n.where : '')));
      if (n.body) { t.appendChild(el('span', 'quote line-clamp-2', n.body)); }
      var x = el('button', 'live-x', '×');
      x.type = 'button';
      x.setAttribute('aria-label', 'Đóng');
      x.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); card.remove(); });
      card.appendChild(av); card.appendChild(t); card.appendChild(x);
      wrap.appendChild(card);
      while (wrap.children.length > 3) { wrap.removeChild(wrap.firstChild); }
      setTimeout(function () { card.classList.add('is-leaving'); setTimeout(function () { card.remove(); }, 400); }, 9000);
    };
    var poll = function () {
      if (polling || document.hidden) { return; }
      polling = true;
      fetch('/api.php?action=notif_poll&after=' + latest, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (typeof d.unread !== 'number') { return; }
          setCount(d.unread);
          (d.items || []).slice().reverse().forEach(pop);
          if (d.items && d.items.length) {
            if (menu.open) { load(); }
            window.dispatchEvent(new CustomEvent('pinpoint:notifications', { detail: d.items }));
          }
          latest = Math.max(latest, d.latest || 0);
        })
        .catch(function () {})
        .then(function () { polling = false; });
    };
    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
    window.pinpointPoll = poll; // dùng cho kiểm thử trên trình duyệt
  }

  // ------------------------------------------------------------------ trợ lý ảo
  var form = document.getElementById('chat-form');
  if (!form) { return; }
  var csrfChat = (menu && menu.dataset.csrf) || '';
  var log = document.getElementById('chat-log');
  var input = document.getElementById('chat-input');
  var suggest = document.getElementById('chat-suggest');
  var history = [];
  var STORE = 'pinpoint-chat';

  function bubble(role, text) {
    var b = el('div', 'chat-msg ' + (role === 'user' ? 'from-user' : 'from-bot'));
    b.appendChild(el('div', 'chat-bubble', text));
    log.appendChild(b);
    log.scrollTop = log.scrollHeight;
    return b;
  }
  function save() { try { sessionStorage.setItem(STORE, JSON.stringify(history.slice(-12))); } catch (e) { /* bỏ qua */ } }
  function restore() {
    try { history = JSON.parse(sessionStorage.getItem(STORE) || '[]') || []; } catch (e) { history = []; }
    if (!history.length) {
      bubble('bot', 'Xin chào! Mình là trợ lý ảo của Pinpoint. Bạn cần hỏi gì về cách dùng ứng dụng? Ví dụ: “làm sao xoá ảnh tải nhầm?”');
    }
    history.forEach(function (h) { bubble(h.role, h.text); });
  }
  function setSuggestions(list) {
    if (!suggest || !list || !list.length) { return; }
    while (suggest.firstChild) { suggest.removeChild(suggest.firstChild); }
    list.forEach(function (q) {
      var b = el('button', '', q);
      b.type = 'button';
      b.dataset.ask = q;
      suggest.appendChild(b);
    });
  }
  function ask(text) {
    text = text.trim();
    if (!text) { return; }
    bubble('user', text);
    var wait = bubble('bot', '…');
    wait.classList.add('is-typing');
    var prior = history.slice(-6);
    history.push({ role: 'user', text: text });
    input.value = '';
    post(csrfChat, { action: 'chat', message: text, history: prior }).then(function (d) {
      wait.remove();
      bubble('bot', d.answer);
      history.push({ role: 'assistant', text: d.answer });
      var mode = document.getElementById('chat-mode');
      if (mode) { mode.textContent = d.source === 'ai' ? 'AI' : 'trả lời tức thì'; }
      setSuggestions(d.suggestions);
      save();
    }).catch(function (err) {
      wait.remove();
      bubble('bot', 'Xin lỗi, chưa trả lời được (' + err.message + '). Bạn có thể gửi yêu cầu ở trang Hỗ trợ.');
    });
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
  if (suggest) { suggest.addEventListener('click', function (e) { var b = e.target.closest('button[data-ask]'); if (b) { ask(b.dataset.ask); } }); }
  restore();

  // Nút nổi mở/đóng khung chat
  var open = document.getElementById('chat-open');
  var panel = document.getElementById('chat-panel');
  if (open && panel) {
    var toggle = function (show) {
      panel.hidden = !show;
      open.setAttribute('aria-expanded', String(show));
      if (show) { input.focus(); log.scrollTop = log.scrollHeight; }
    };
    open.addEventListener('click', function () { toggle(panel.hidden); });
    document.getElementById('chat-close').addEventListener('click', function () { toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { toggle(false); } });
  }
})();
