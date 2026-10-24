/*
 * Bảng góp ý bên phải: danh sách thẻ, chi tiết (thuộc tính, ghi chú xử lý, trao đổi, cảm xúc,
 * lịch sử), ô soạn góp ý nổi cạnh dấu mới.
 * Tất cả nội dung người dùng được gán bằng textContent => hiển thị như dữ liệu, không chạy mã.
 */
(function (root) {
  'use strict';

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var ICONS = {
    check: '<path d="M20 6 9 17l-5-5"/>', undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
    left: '<path d="m15 18-6-6 6-6"/>', right: '<path d="m9 18 6-6-6-6"/>', back: '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    chat: '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/>', note: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
    flag: '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>', tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>', calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    pin: '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>', layers: '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
    history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>', edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    smile: '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>', status: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', send: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>', square: '<rect x="4" y="4" width="16" height="16" rx="2" stroke-dasharray="3 2.5"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'
  };
  function icon(name) {
    var s = document.createElementNS(SVG_NS, 'svg');
    s.setAttribute('class', 'icon');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('aria-hidden', 'true');
    s.innerHTML = ICONS[name] || ''; // chỉ chuỗi hằng số trong mã, không có dữ liệu người dùng
    return s;
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (k === 'text') { node.textContent = v; }
      else if (k === 'class') { node.className = v; }
      else if (k.indexOf('on') === 0) { node.addEventListener(k.slice(2), v); }
      else if (v !== null && v !== undefined && v !== false) { node.setAttribute(k, v); }
    });
    (children || []).forEach(function (c) { if (c) { node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
    return node;
  }

  function toast(message, isError) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) { wrap = el('div', { class: 'toast-wrap' }); document.body.appendChild(wrap); }
    var t = el('div', { class: 'toast ' + (isError ? 'error' : 'ok'), role: 'status', text: message });
    wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 4500);
  }

  function Api(csrf) { this.csrf = csrf; }
  Api.prototype.list = function (versionId, status, author) {
    var q = '/api.php?action=list&version=' + encodeURIComponent(versionId) +
      '&status=' + encodeURIComponent(status || '') + '&author=' + encodeURIComponent(author || 0);
    return fetch(q, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(parse);
  };
  Api.prototype.activity = function (projectId, screenId) {
    return fetch('/api.php?action=activity&project=' + encodeURIComponent(projectId) + '&screen=' + encodeURIComponent(screenId || 0),
      { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(parse);
  };
  Api.prototype.post = function (payload) {
    return fetch('/api.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': this.csrf },
      body: JSON.stringify(payload)
    }).then(parse);
  };
  function parse(res) {
    return res.json().catch(function () { return {}; }).then(function (data) {
      if (!res.ok) { var err = new Error(data.message || 'Lỗi'); err.code = data.error; err.data = data; err.status = res.status; throw err; }
      return data;
    });
  }

  // ---------------------------------------------------------------- định dạng
  // Thời điểm do máy chủ trả về theo giờ Việt Nam, ví dụ "2026-10-21 09:05:44.55+07".
  function parseTs(ts) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):?(\d{2})?/.exec(ts || '');
    if (!m) { return null; }
    var tz = /([+-]\d{2})(:?(\d{2}))?$/.exec(ts);
    var off = tz ? (parseInt(tz[1], 10) * 60 + (tz[3] ? (tz[1][0] === '-' ? -1 : 1) * parseInt(tz[3], 10) : 0)) : 420;
    return new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0)) - off * 60000);
  }
  function fmt(ts) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(ts || '');
    return m ? m[3] + '/' + m[2] + ' ' + m[4] + ':' + m[5] : (ts || '');
  }
  function ago(ts) {
    var d = parseTs(ts);
    if (!d) { return ts || ''; }
    var s = (Date.now() - d.getTime()) / 1000;
    if (s < 60) { return 'vừa xong'; }
    if (s < 3600) { return Math.floor(s / 60) + ' phút trước'; }
    if (s < 86400) { return Math.floor(s / 3600) + ' giờ trước'; }
    if (s < 172800) { return 'hôm qua'; }
    if (s < 604800) { return Math.floor(s / 86400) + ' ngày trước'; }
    return fmt(ts).slice(0, 5);
  }
  function dmy(date) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(date || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] : ''; }
  function num(v) { return (Math.round(v * 100) / 100).toString(); }
  function position(a) {
    return a.shape === 'POINT'
      ? 'Điểm (' + num(a.x) + '; ' + num(a.y) + ') px ảnh gốc'
      : 'Vùng (' + num(a.x) + '; ' + num(a.y) + ') ' + num(a.w) + '×' + num(a.h) + ' px';
  }
  function cleanName(n) { return String(n || '').replace(/\s*\(.*?\)\s*/g, ' ').trim(); }
  function initials(n) {
    var p = cleanName(n).split(/\s+/).filter(Boolean);
    if (!p.length) { return '?'; }
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }
  function avatar(name, id, size) {
    return el('span', { class: 'avatar avatar-c' + ((id || 0) % 8) + (size ? ' avatar-' + size : ''), title: name, text: initials(name) });
  }

  var STATUS = { OPEN: 'Chưa xử lý', RESOLVED: 'Đã xử lý' };
  var PRIORITY = { LOW: 'Thấp', MEDIUM: 'Trung bình', HIGH: 'Cao', URGENT: 'Khẩn cấp' };
  var CATEGORY = { LAYOUT: 'Bố cục', COLOR: 'Màu sắc', TYPO: 'Chữ & phông', CONTENT: 'Nội dung', UX: 'Trải nghiệm', A11Y: 'Trợ năng', OTHER: 'Khác' };
  var REACTIONS = { LIKE: '👍', HEART: '❤️', PARTY: '🎉', EYES: '👀' };
  var PRIO_RANK = { URGENT: 1, HIGH: 2, MEDIUM: 3, LOW: 4 };

  function badge(status) { return el('span', { class: 'badge ' + (status === 'OPEN' ? 'badge-open' : 'badge-resolved'), 'data-status': status, text: STATUS[status] }); }
  function prio(p) { return el('span', { class: 'prio prio-' + p, text: PRIORITY[p] || p }); }
  function cat(c) { return el('span', { class: 'cat cat-' + c, text: CATEGORY[c] || c }); }
  function due(a) {
    if (!a.due_date) { return null; }
    var s = el('span', { class: 'due' + (a.overdue ? ' is-overdue' : '') }, [icon('calendar'), dmy(a.due_date).slice(0, 5) + (a.overdue ? ' · quá hạn' : '')]);
    return s;
  }
  function options(select, map, current) {
    Object.keys(map).forEach(function (k) { select.appendChild(el('option', { value: k, text: map[k], selected: k === current ? 'selected' : null })); });
    return select;
  }

  /** Đoạn văn có @tên thành viên được tô màu; mọi phần đều là textContent. */
  function richBody(text, members, cls) {
    var box = el('div', { class: cls || 'thread-body' });
    var names = (members || []).map(function (m) { return cleanName(m.name); }).filter(Boolean)
      .sort(function (a, b) { return b.length - a.length; });
    var i = 0;
    var buf = '';
    while (i < text.length) {
      var hit = null;
      if (text.charAt(i) === '@') {
        for (var k = 0; k < names.length; k++) {
          if (text.substr(i + 1, names[k].length) === names[k]) { hit = names[k]; break; }
        }
      }
      if (hit) {
        if (buf) { box.appendChild(document.createTextNode(buf)); buf = ''; }
        box.appendChild(el('span', { class: 'mention', text: '@' + hit }));
        i += hit.length + 1;
      } else { buf += text.charAt(i); i++; }
    }
    if (buf) { box.appendChild(document.createTextNode(buf)); }
    return box;
  }

  /** Ô soạn có gợi ý @nhắc tên và gửi nhanh bằng Ctrl+Enter. */
  function composer(opts) {
    var area = el('textarea', { maxlength: '2000', 'aria-label': opts.label, placeholder: opts.placeholder, required: opts.required ? 'required' : null });
    var menuWrap = el('div', { class: 'mention-menu', hidden: 'hidden' });
    var active = 0;
    var matches = [];
    function close() { menuWrap.hidden = true; matches = []; }
    function pick(m) {
      var pos = area.selectionStart;
      var before = area.value.slice(0, pos).replace(/@([^\s@]*)$/, '@' + cleanName(m.name) + ' ');
      area.value = before + area.value.slice(pos);
      area.selectionStart = area.selectionEnd = before.length;
      close();
      area.focus();
    }
    function draw() {
      while (menuWrap.firstChild) { menuWrap.removeChild(menuWrap.firstChild); }
      var menu = el('div', { class: 'menu' });
      matches.forEach(function (m, i) {
        menu.appendChild(el('button', { type: 'button', class: i === active ? 'is-active' : '', onmousedown: function (e) { e.preventDefault(); pick(m); } },
          [avatar(m.name, m.id, 'sm'), cleanName(m.name)]));
      });
      menuWrap.appendChild(menu);
      menuWrap.hidden = matches.length === 0;
    }
    area.addEventListener('input', function () {
      var q = /@([^\s@]*)$/.exec(area.value.slice(0, area.selectionStart));
      if (!q) { close(); return; }
      var needle = q[1].toLowerCase();
      matches = (opts.members || []).filter(function (m) { return cleanName(m.name).toLowerCase().indexOf(needle) !== -1; }).slice(0, 6);
      active = 0;
      draw();
    });
    area.addEventListener('keydown', function (e) {
      if (!menuWrap.hidden && matches.length) {
        if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % matches.length; draw(); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); active = (active + matches.length - 1) % matches.length; draw(); return; }
        if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pick(matches[active]); return; }
        if (e.key === 'Escape') { e.stopPropagation(); close(); return; }
      }
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && opts.onSubmit) { e.preventDefault(); opts.onSubmit(); }
    });
    area.addEventListener('blur', function () { setTimeout(close, 150); });
    return { area: area, menu: menuWrap };
  }

  /**
   * opts: { cfg, api, onSelect(id), onChanged(id), onCancelDraft(), onHover(id), checkedVersion: {id, no} (trang so sánh) }
   * cfg: { versionId, versionNo, userId, canComment, members[], projectId }
   */
  function ThreadPanel(body, opts) {
    this.body = body;
    this.opt = opts;
    this.items = [];
    this.all = [];
    this.selectedId = null;
    this.draft = null;
    this.noteEditing = false;
  }

  ThreadPanel.prototype.setItems = function (items, all) { this.items = items; this.all = all || items; this.render(); };
  ThreadPanel.prototype.select = function (id) { this.selectedId = id; this.draft = null; this.noteEditing = false; this.render(); };
  ThreadPanel.prototype.showDraft = function (pos) { this.draft = pos; this.selectedId = null; this.render(); };
  ThreadPanel.prototype.find = function (id) {
    var list = this.all.length ? this.all : this.items;
    for (var i = 0; i < list.length; i++) { if (list[i].id === id) { return list[i]; } }
    return null;
  };
  ThreadPanel.prototype.neighbour = function (step) {
    var idx = -1;
    for (var i = 0; i < this.items.length; i++) { if (this.items[i].id === this.selectedId) { idx = i; } }
    if (!this.items.length) { return null; }
    var n = idx === -1 ? 0 : (idx + step + this.items.length) % this.items.length;
    return this.items[n].id;
  };
  ThreadPanel.prototype.highlight = function (id) {
    this.body.querySelectorAll('.ann-list li').forEach(function (li) { li.classList.toggle('is-hover', Number(li.dataset.id) === id); });
  };

  ThreadPanel.prototype.render = function () {
    var b = this.body;
    var keepScroll = b.scrollTop;
    while (b.firstChild) { b.removeChild(b.firstChild); }
    var sel = this.find(this.selectedId);
    if (sel) { b.appendChild(this.renderThread(sel)); return; }
    b.appendChild(this.renderList());
    b.scrollTop = keepScroll;
  };

  ThreadPanel.prototype.renderList = function () {
    var self = this;
    var cfg = this.opt.cfg;
    var wrap = el('div', {});
    if (this.all.length === 0) {
      wrap.appendChild(el('div', { class: 'empty-state' }, [
        el('div', { class: 'empty-art' }, [icon('pin')]),
        el('h3', { text: 'Chưa có góp ý trên phiên bản này' }),
        el('p', { class: 'empty', text: cfg.canComment
          ? 'Chưa có góp ý. Chọn công cụ Điểm (C) hoặc Vùng (R) rồi bấm lên ảnh.' : 'Chưa có góp ý nào.' })
      ]));
      return wrap;
    }
    var open = this.items.filter(function (a) { return a.status === 'OPEN'; }).length;
    wrap.appendChild(el('div', { class: 'list-summary' }, [
      el('span', { text: 'Hiển thị ' + this.items.length + '/' + this.all.length + ' góp ý' }),
      el('span', { text: open + ' chưa xử lý' })
    ]));
    if (this.items.length === 0) {
      wrap.appendChild(el('p', { class: 'empty', text: 'Không có góp ý khớp bộ lọc.' }));
      return wrap;
    }
    var ul = el('ul', { class: 'ann-list' });
    this.items.forEach(function (a) {
      var tags = el('div', { class: 'ann-tags' }, [prio(a.priority), cat(a.category), due(a), el('span', { class: 'spacer' })]);
      if (a.assignee) { tags.appendChild(el('span', { class: 'mini', title: 'Phụ trách: ' + a.assignee }, [avatar(a.assignee, a.assignee_id, 'sm')])); }
      if (a.note) { tags.appendChild(el('span', { class: 'mini', title: 'Có ghi chú xử lý' }, [icon('note')])); }
      if (a.reply_count) { tags.appendChild(el('span', { class: 'mini', title: a.reply_count + ' trả lời' }, [icon('chat'), String(a.reply_count)])); }
      if (a.shape === 'RECT') { tags.appendChild(el('span', { class: 'mini', title: 'Vùng chữ nhật' }, [icon('square')])); }
      var li = el('li', { 'data-id': a.id, class: (a.id === self.selectedId ? 'is-selected ' : '') + (a.status === 'RESOLVED' ? 'is-resolved' : '') }, [
        el('button', { type: 'button', onclick: function () { self.opt.onSelect(a.id); },
          onmouseenter: function () { if (self.opt.onHover) { self.opt.onHover(a.id); } },
          onmouseleave: function () { if (self.opt.onHover) { self.opt.onHover(null); } } }, [
          el('span', { class: 'seq prio-' + a.priority + (a.status === 'RESOLVED' ? ' is-resolved' : ''), text: String(a.seq_no) }),
          el('span', { class: 'ann-text' }, [
            el('span', { class: 'ann-top' }, [avatar(a.author, a.author_id, 'sm'), el('strong', { text: cleanName(a.author) }), ' ', badge(a.status),
              el('time', { title: fmt(a.created_at), text: ago(a.created_at) })]),
            el('p', { text: a.body }),
            tags
          ])
        ])
      ]);
      ul.appendChild(li);
    });
    wrap.appendChild(ul);
    return wrap;
  };

  /** Ô soạn góp ý nổi trong khung ảnh (cạnh dấu mới). */
  ThreadPanel.prototype.draftComposer = function (d, onDone) {
    var self = this;
    var cfg = this.opt.cfg;
    var comp = composer({ label: 'Nội dung nhận xét', placeholder: 'Mô tả điều cần sửa… (gõ @ để nhắc tên)', required: true, members: cfg.members,
      onSubmit: function () { form.requestSubmit(); } });
    var pSel = options(el('select', { 'aria-label': 'Mức ưu tiên', name: 'priority' }), PRIORITY, 'MEDIUM');
    var cSel = options(el('select', { 'aria-label': 'Nhãn phân loại', name: 'category' }), CATEGORY, 'OTHER');
    var aSel = el('select', { 'aria-label': 'Người phụ trách', name: 'assignee' }, [el('option', { value: '0', text: 'Chưa giao' })]);
    (cfg.members || []).forEach(function (m) { if (m.role !== 'VIEW') { aSel.appendChild(el('option', { value: m.id, text: cleanName(m.name) })); } });
    var dIn = el('input', { type: 'date', 'aria-label': 'Hạn xử lý', name: 'due' });
    var send = el('button', { class: 'btn btn-sm', type: 'submit' }, [icon('send'), 'Lưu góp ý']);
    var form = el('form', { onsubmit: function (e) {
      e.preventDefault();
      if (!comp.area.value.trim()) { comp.area.focus(); return; }
      send.disabled = true;
      self.opt.api.post({ action: 'create', version_id: cfg.versionId, shape: d.shape, x: d.x, y: d.y, w: d.w, h: d.h, body: comp.area.value,
        priority: pSel.value, category: cSel.value, assignee_id: Number(aSel.value), due_date: dIn.value })
        .then(function (res) { toast('Đã lưu góp ý #' + res.item.seq_no); onDone(); self.opt.onChanged(res.item.id); })
        .catch(function (err) { send.disabled = false; toast(err.message, true); });
    } }, [
      el('div', { class: 'cp-head' }, [avatar(cfg.userName || '', cfg.userId, 'sm'), el('strong', { text: 'Góp ý mới' }),
        el('span', { class: 'coord', text: position(d) })]),
      comp.area, comp.menu,
      el('div', { class: 'cp-meta' }, [pSel, cSel, aSel, dIn]),
      el('div', { class: 'cp-foot' }, [el('span', { class: 'hint', text: 'Ctrl+Enter để lưu' }),
        el('span', { class: 'row center' }, [el('button', { class: 'btn btn-sm btn-ghost', type: 'button', text: 'Huỷ',
          onclick: function () { onDone(); self.opt.onCancelDraft(); } }), send])])
    ]);
    form.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); onDone(); self.opt.onCancelDraft(); } });
    setTimeout(function () { comp.area.focus(); }, 0);
    return form;
  };

  ThreadPanel.prototype.renderThread = function (a) {
    var self = this;
    var cfg = this.opt.cfg;
    var wrap = el('div', { class: 'thread', 'data-annotation': a.id });

    // Đầu trang chi tiết: quay lại, số thứ tự, trước/sau
    wrap.appendChild(el('div', { class: 'detail-head' }, [
      el('button', { class: 'btn btn-subtle btn-sm', type: 'button', title: 'Về danh sách (Esc)', onclick: function () { self.opt.onSelect(null); } }, [icon('back'), 'Danh sách']),
      el('span', { class: 'spacer' }),
      el('span', { class: 'title' }, [el('span', { class: 'seq prio-' + a.priority + (a.status === 'RESOLVED' ? ' is-resolved' : ''), text: String(a.seq_no) }),
        '#' + a.seq_no + ' · v' + a.version_no]),
      el('span', { class: 'spacer' }),
      el('button', { class: 'btn btn-subtle btn-icon btn-sm', type: 'button', title: 'Góp ý trước (←)', 'aria-label': 'Góp ý trước', onclick: function () { self.opt.onSelect(self.neighbour(-1)); } }, [icon('left')]),
      el('button', { class: 'btn btn-subtle btn-icon btn-sm', type: 'button', title: 'Góp ý sau (→)', 'aria-label': 'Góp ý sau', onclick: function () { self.opt.onSelect(self.neighbour(1)); } }, [icon('right')])
    ]));

    // Nhận xét gốc
    var rootBox = this.renderComment({ id: a.comment_id, body: a.body, author: a.author, author_id: a.comment_author_id,
      lock_version: a.comment_lock, created_at: a.created_at, edited: a.comment_revisions > 0, reactions: a.reactions }, false);
    wrap.appendChild(rootBox);

    // Trạng thái (ghi nhận phiên bản đã kiểm tra)
    if (cfg.canComment) {
      var checked = this.opt.checkedVersion || { id: cfg.versionId, no: cfg.versionNo };
      var to = a.status === 'OPEN' ? 'RESOLVED' : 'OPEN';
      var note = el('input', { type: 'text', maxlength: '500', 'aria-label': 'Ghi chú khi đổi trạng thái',
        placeholder: a.status === 'OPEN' ? 'Ghi chú kiểm tra (không bắt buộc)' : 'Lý do mở lại (không bắt buộc)' });
      var sbtn = el('button', { class: 'btn status-toggle ' + (to === 'RESOLVED' ? 'is-open' : 'btn-danger'), type: 'submit', 'data-action': 'status' },
        [icon(to === 'RESOLVED' ? 'check' : 'undo'), to === 'RESOLVED' ? 'Đánh dấu đã xử lý (kiểm tra trên v' + checked.no + ')' : 'Mở lại góp ý']);
      wrap.appendChild(el('form', { class: 'status-box', onsubmit: function (e) {
        e.preventDefault();
        sbtn.disabled = true;
        self.opt.api.post({ action: 'status', annotation_id: a.id, to: to, lock_version: a.lock_version, checked_version_id: checked.id, note: note.value })
          .then(function () { toast(to === 'RESOLVED' ? 'Đã đánh dấu đã xử lý' : 'Đã mở lại'); self.opt.onChanged(a.id); })
          .catch(function (err) {
            sbtn.disabled = false;
            if (err.code === 'CONFLICT') {
              toast('Góp ý vừa được người khác thay đổi. Đã tải lại bản mới nhất.', true);
              self.opt.onChanged(a.id);
            } else { toast(err.message, true); }
          });
      } }, [sbtn, note, el('span', { class: 'hint', text: a.status === 'OPEN'
        ? 'Sau khi sửa, hãy kiểm tra trên phiên bản mới rồi xác nhận. Phiên bản kiểm tra được ghi vào lịch sử.'
        : 'Đã xử lý' + (a.resolved_on_version_no ? ' — kiểm tra trên v' + a.resolved_on_version_no : '') + '. Mở lại nếu bản mới vẫn chưa đạt.' })]));
    }

    // Thuộc tính
    wrap.appendChild(el('div', { class: 'block-title' }, [icon('tag'), 'Thuộc tính']));
    wrap.appendChild(this.renderProps(a));

    // Ghi chú xử lý
    wrap.appendChild(el('div', { class: 'block-title' }, [icon('note'), 'Ghi chú xử lý', el('span', { class: 'spacer' }),
      el('span', { class: 'hint', text: 'thấy bởi mọi thành viên' })]));
    wrap.appendChild(this.renderNote(a));

    // Trao đổi
    wrap.appendChild(el('div', { class: 'block-title' }, [icon('chat'), 'Trao đổi · ' + a.replies.length + ' trả lời']));
    if (a.replies.length) {
      var reps = el('div', { class: 'replies' });
      a.replies.forEach(function (r) { reps.appendChild(self.renderComment(r, true)); });
      wrap.appendChild(reps);
    }
    if (cfg.canComment) {
      var btn = el('button', { class: 'btn btn-sm', type: 'submit' }, [icon('send'), 'Gửi trả lời']);
      var rc = composer({ label: 'Trả lời', placeholder: 'Trả lời nhận xét này… (gõ @ để nhắc tên)', members: cfg.members, onSubmit: function () { replyForm.requestSubmit(); } });
      var replyForm = el('form', { class: 'composer', onsubmit: function (e) {
        e.preventDefault();
        if (!rc.area.value.trim()) { return; }
        btn.disabled = true;
        self.opt.api.post({ action: 'reply', annotation_id: a.id, body: rc.area.value })
          .then(function () { toast('Đã gửi trả lời'); self.opt.onChanged(a.id); })
          .catch(function (err) { btn.disabled = false; toast(err.message, true); });
      } }, [rc.menu, rc.area, el('div', { class: 'composer-foot' }, [el('span', { class: 'hint', text: 'Ctrl+Enter để gửi' }), btn])]);
      wrap.appendChild(replyForm);
    }

    // Lịch sử
    wrap.appendChild(el('div', { class: 'block-title' }, [icon('history'), 'Lịch sử']));
    wrap.appendChild(this.renderTimeline(a));
    return wrap;
  };

  ThreadPanel.prototype.renderProps = function (a) {
    var self = this;
    var cfg = this.opt.cfg;
    var dl = el('dl', { class: 'props' });
    function row(ic, label, value) { dl.appendChild(el('dt', {}, [icon(ic), label])); dl.appendChild(el('dd', {}, [value])); }
    function save(changes, okMsg) {
      changes.action = 'update';
      changes.annotation_id = a.id;
      changes.lock_version = a.lock_version;
      return self.opt.api.post(changes).then(function () { toast(okMsg); self.opt.onChanged(a.id); }).catch(function (err) {
        if (err.code === 'CONFLICT') { toast('Góp ý vừa được người khác cập nhật. Đã tải bản mới nhất, hãy thử lại.', true); self.opt.onChanged(a.id); }
        else { toast(err.message, true); self.render(); }
        throw err;
      });
    }
    row('status', 'Trạng thái', badge(a.status));
    if (cfg.canComment) {
      var p = options(el('select', { 'aria-label': 'Mức ưu tiên', 'data-prop': 'priority', onchange: function () { save({ priority: p.value }, 'Đã đổi mức ưu tiên').catch(function () {}); } }), PRIORITY, a.priority);
      var c = options(el('select', { 'aria-label': 'Nhãn phân loại', 'data-prop': 'category', onchange: function () { save({ category: c.value }, 'Đã đổi nhãn').catch(function () {}); } }), CATEGORY, a.category);
      var s = el('select', { 'aria-label': 'Người phụ trách', 'data-prop': 'assignee', onchange: function () { save({ assignee_id: Number(s.value) }, 'Đã giao người phụ trách').catch(function () {}); } },
        [el('option', { value: '0', text: '— Chưa giao —' })]);
      (cfg.members || []).forEach(function (m) {
        if (m.role !== 'VIEW' || m.id === a.assignee_id) { s.appendChild(el('option', { value: m.id, text: cleanName(m.name), selected: m.id === a.assignee_id ? 'selected' : null })); }
      });
      var d = el('input', { type: 'date', value: a.due_date || '', 'aria-label': 'Hạn xử lý', 'data-prop': 'due', onchange: function () { save({ due_date: d.value }, d.value ? 'Đã đặt hạn xử lý' : 'Đã bỏ hạn xử lý').catch(function () {}); } });
      row('flag', 'Ưu tiên', p);
      row('tag', 'Nhãn', c);
      row('user', 'Phụ trách', s);
      row('calendar', 'Hạn xử lý', el('span', { class: 'row center' }, [d, a.overdue ? el('span', { class: 'badge badge-overdue', text: 'Quá hạn' }) : null]));
    } else {
      row('flag', 'Ưu tiên', prio(a.priority));
      row('tag', 'Nhãn', cat(a.category));
      row('user', 'Phụ trách', a.assignee ? el('span', { class: 'row center' }, [avatar(a.assignee, a.assignee_id, 'sm'), cleanName(a.assignee)]) : el('span', { class: 'faint', text: 'Chưa giao' }));
      row('calendar', 'Hạn xử lý', a.due_date ? due(a) : el('span', { class: 'faint', text: 'Không đặt' }));
    }
    row(a.shape === 'POINT' ? 'pin' : 'square', 'Vị trí', el('span', { class: 'pos coord', text: position(a) + ' · ảnh ' + a.image_width + '×' + a.image_height }));
    row('layers', 'Phiên bản', el('span', { text: 'v' + a.version_no + (a.version_no < a.latest_version_no ? ' (bản mới nhất: v' + a.latest_version_no + ')' : ' (mới nhất)') }));
    row('clock', 'Tạo lúc', el('span', { text: fmt(a.created_at) + ' · ' + ago(a.created_at) }));
    return dl;
  };

  ThreadPanel.prototype.renderNote = function (a) {
    var self = this;
    var cfg = this.opt.cfg;
    var box = el('div', { class: 'note-box', 'data-note': a.id });
    if (!this.noteEditing) {
      box.appendChild(a.note ? el('div', { class: 'note-text', text: a.note }) : el('div', { class: 'note-empty', text: cfg.canComment
        ? 'Chưa có ghi chú. Ghi lại cách xử lý, link tài liệu, thông số cần sửa…' : 'Chưa có ghi chú.' }));
      var meta = el('div', { class: 'note-meta' });
      if (a.note_updated_by) { meta.appendChild(document.createTextNode('Cập nhật bởi ' + cleanName(a.note_updated_by) + ' · ' + ago(a.note_updated_at))); }
      meta.appendChild(el('span', { class: 'spacer' }));
      if (cfg.canComment) {
        meta.appendChild(el('button', { class: 'btn btn-xs btn-ghost', type: 'button', 'data-action': 'edit-note',
          onclick: function () { self.noteEditing = true; self.render(); } }, [icon('edit'), a.note ? 'Sửa ghi chú' : 'Thêm ghi chú']));
      }
      box.appendChild(meta);
      return box;
    }
    var area = el('textarea', { maxlength: '4000', 'aria-label': 'Ghi chú xử lý', placeholder: 'Ví dụ: Tăng padding lên 16 px; dùng màu #F97316; xem lại trên iPhone SE.' });
    area.value = a.note || '';
    var lock = a.lock_version;
    var save = el('button', { class: 'btn btn-sm', type: 'submit', text: 'Lưu ghi chú' });
    var form = el('form', { onsubmit: function (e) {
      e.preventDefault();
      save.disabled = true;
      self.opt.api.post({ action: 'update', annotation_id: a.id, lock_version: lock, note: area.value })
        .then(function () { self.noteEditing = false; toast('Đã lưu ghi chú'); self.opt.onChanged(a.id); })
        .catch(function (err) {
          save.disabled = false;
          if (err.code === 'CONFLICT') {
            // Không ghi đè âm thầm: cho xem ghi chú mới nhất, giữ nguyên chữ người dùng đã gõ.
            lock = err.data.details.current_lock;
            var old = form.querySelector('.flash');
            if (old) { old.remove(); }
            form.insertBefore(el('div', { class: 'flash error conflict-box', role: 'alert', 'data-conflict': '1' }, [
              'Xung đột: ghi chú hoặc thuộc tính vừa được người khác cập nhật. Ghi chú hiện tại: “',
              el('span', { text: (err.data.details.current && err.data.details.current.note) || '(trống)' }),
              '”. Nội dung bạn gõ vẫn còn; bấm “Lưu bản của tôi” nếu vẫn muốn thay.'
            ]), area);
            save.textContent = 'Lưu bản của tôi';
          } else { toast(err.message, true); }
        });
    } }, [area, el('div', { class: 'row' }, [el('button', { class: 'btn btn-sm btn-ghost', type: 'button', text: 'Huỷ',
      onclick: function () { self.noteEditing = false; self.render(); } }), save])]);
    box.appendChild(form);
    setTimeout(function () { area.focus(); }, 0);
    return box;
  };

  ThreadPanel.prototype.renderTimeline = function (a) {
    var ul = el('ul', { class: 'feed history', 'aria-label': 'Lịch sử' });
    var source = a.events && a.events.length ? a.events : (a.history || []).map(function (h) {
      return { kind: h.from ? 'STATUS' : 'CREATED', old: h.from || '', new: h.to, detail: h.checked_version_no ? 'v' + h.checked_version_no + (h.note && h.from ? ' — ' + h.note : '') : '', by: h.by, by_id: 0, at: h.at };
    });
    source.forEach(function (ev) {
      var text;
      var ic = 'activity';
      var kind = ev.kind;
      switch (ev.kind) {
        case 'CREATED': text = 'tạo góp ý trên v' + a.version_no; ic = 'pin'; break;
        case 'REPLIED': text = 'trả lời'; ic = 'chat'; break;
        case 'EDITED': text = 'sửa nhận xét'; ic = 'edit'; break;
        case 'STATUS':
          var m = /^v(\d+)(?: — (.*))?$/.exec(ev.detail || '');
          text = STATUS[ev.old] + ' → ' + STATUS[ev.new] + (m ? ' (kiểm tra trên v' + m[1] + ')' : '') + (m && m[2] ? ' — ' + m[2] : '');
          ic = ev.new === 'RESOLVED' ? 'check' : 'undo';
          kind = 'STATUS-' + ev.new;
          break;
        case 'PRIORITY': text = 'đổi ưu tiên ' + (ev.old || '—') + ' → ' + ev.new; ic = 'flag'; break;
        case 'CATEGORY': text = 'đổi nhãn ' + (ev.old || '—') + ' → ' + ev.new; ic = 'tag'; break;
        case 'ASSIGNEE': text = ev.new ? 'giao cho ' + cleanName(ev.new) : 'bỏ người phụ trách'; ic = 'user'; break;
        case 'DUE': text = ev.new ? 'đặt hạn ' + ev.new : 'bỏ hạn xử lý'; ic = 'calendar'; break;
        case 'NOTE': text = ev.new ? 'cập nhật ghi chú' : 'xoá ghi chú'; ic = 'note'; break;
        default: text = ev.kind;
      }
      var quote = (ev.kind === 'REPLIED' || ev.kind === 'CREATED') && ev.detail ? el('span', { class: 'quote line-clamp-2', text: ev.detail }) : null;
      ul.appendChild(el('li', {}, [el('span', { class: 'feed-icon k-' + kind }, [icon(ic)]),
        el('span', { class: 'feed-body' }, [el('strong', { text: cleanName(ev.by) }), ' ' + text, quote,
          el('time', { title: fmt(ev.at), text: fmt(ev.at) + ' · ' + ago(ev.at) })])]));
    });
    return ul;
  };

  ThreadPanel.prototype.renderReactions = function (c) {
    var self = this;
    var cfg = this.opt.cfg;
    var row = el('div', { class: 'c-actions' });
    (c.reactions || []).forEach(function (r) {
      row.appendChild(el('button', { type: 'button', class: 'reaction' + (r.mine ? ' is-mine' : ''), title: r.names.join(', '),
        'data-reaction': r.kind, disabled: cfg.canComment ? null : 'disabled', onclick: function () { self.react(c, r.kind); } },
        [r.emoji + ' ', String(r.count)]));
    });
    if (cfg.canComment) {
      var picker = el('details', { class: 'dropdown react-picker' }, [
        el('summary', { class: 'btn btn-xs btn-subtle', title: 'Thả cảm xúc', 'aria-label': 'Thả cảm xúc' }, [icon('smile')]),
        el('div', { class: 'menu' }, Object.keys(REACTIONS).map(function (k) {
          return el('button', { type: 'button', title: k, text: REACTIONS[k], onclick: function () { picker.removeAttribute('open'); self.react(c, k); } });
        }))
      ]);
      row.appendChild(picker);
    }
    return row;
  };

  ThreadPanel.prototype.react = function (c, kind) {
    var self = this;
    this.opt.api.post({ action: 'react', comment_id: c.id, kind: kind })
      .then(function () { self.opt.onChanged(self.selectedId); })
      .catch(function (err) { toast(err.message, true); });
  };

  ThreadPanel.prototype.renderComment = function (c, isReply) {
    var self = this;
    var cfg = this.opt.cfg;
    var box = el('div', { class: 'comment ' + (isReply ? 'reply' : 'root-comment'), 'data-comment': c.id });
    var main = el('div', { class: 'c-main' });
    var bodyEl = richBody(c.body, cfg.members);
    main.appendChild(el('div', { class: 'c-head' }, [el('span', { class: 'who', text: cleanName(c.author) }),
      el('time', { title: fmt(c.created_at), text: fmt(c.created_at) + ' · ' + ago(c.created_at) }),
      c.edited ? el('span', { class: 'edited', text: 'đã sửa' }) : null]));
    main.appendChild(bodyEl);
    var actions = this.renderReactions(c);
    main.appendChild(actions);
    box.appendChild(avatar(c.author, c.author_id, isReply ? 'sm' : null));
    box.appendChild(main);
    if (cfg.canComment && c.author_id === cfg.userId) {
      actions.appendChild(el('button', { class: 'btn btn-xs btn-subtle', type: 'button', 'data-action': 'edit', onclick: function () {
        var area = el('textarea', { maxlength: '2000', 'aria-label': 'Sửa nội dung' });
        area.value = c.body;
        var save = el('button', { class: 'btn btn-sm', type: 'submit', text: 'Lưu' });
        var form = el('form', { class: 'mt-s', onsubmit: function (e) {
          e.preventDefault();
          save.disabled = true;
          // Gửi kèm lock_version đã đọc; máy chủ báo CONFLICT nếu ai đó đã sửa trước.
          self.opt.api.post({ action: 'edit', comment_id: c.id, body: area.value, lock_version: c.lock_version })
            .then(function () { toast('Đã lưu thay đổi'); self.opt.onChanged(self.selectedId); })
            .catch(function (err) {
              save.disabled = false;
              if (err.code === 'CONFLICT') {
                // Không ghi đè âm thầm: cho người dùng xem bản mới nhất, giữ nguyên chữ họ đã gõ.
                // Chỉ khi họ bấm lưu lần nữa (đã biết có xung đột) thì mới gửi với lock_version mới.
                c.lock_version = err.data.details.current_lock;
                var old = form.querySelector('.flash');
                if (old) { old.remove(); }
                form.insertBefore(el('div', { class: 'flash error conflict-box', role: 'alert', 'data-conflict': '1' }, [
                  'Xung đột: nhận xét đã được sửa ở nơi khác. Bản hiện tại: “', el('span', { text: err.data.details.current_body }),
                  '”. Nội dung bạn gõ vẫn còn bên dưới; bấm “Lưu bản của tôi” nếu vẫn muốn thay.'
                ]), area);
                save.textContent = 'Lưu bản của tôi';
              } else { toast(err.message, true); }
            });
        } }, [area, el('div', { class: 'row mt-s' }, [save, el('button', { class: 'btn btn-sm btn-ghost', type: 'button', text: 'Huỷ', onclick: function () { self.render(); } })])]);
        main.replaceChild(form, bodyEl);
        actions.hidden = true;
        area.focus();
      } }, [icon('edit'), 'Sửa']));
    }
    return box;
  };

  /** Nội dung thẻ xem nhanh khi rê chuột lên dấu. */
  function hoverCard(a, card) {
    card.appendChild(el('div', { class: 'ph-head' }, [avatar(a.author, a.author_id, 'sm'), el('span', { text: '#' + a.seq_no + ' · ' + cleanName(a.author) }),
      el('span', { class: 'ph-time', text: ago(a.created_at) })]));
    card.appendChild(el('p', { text: a.body }));
    card.appendChild(el('div', { class: 'ph-foot' }, [badge(a.status), prio(a.priority), a.reply_count ? el('span', { text: a.reply_count + ' trả lời' }) : null,
      el('span', { text: 'Bấm để mở' })]));
  }

  /** Lọc và sắp xếp phía trình duyệt cho danh sách của một phiên bản. */
  function applyFilters(items, f) {
    var q = (f.q || '').trim().toLowerCase();
    var list = items.filter(function (a) {
      if (f.status && a.status !== f.status) { return false; }
      if (f.author && a.author_id !== Number(f.author)) { return false; }
      if (f.priority && a.priority !== f.priority) { return false; }
      if (f.category && a.category !== f.category) { return false; }
      if (f.hideResolved && a.status === 'RESOLVED') { return false; }
      if (f.mine && a.assignee_id !== f.mine) { return false; }
      if (q) {
        var hay = [a.body, a.note, a.author, a.assignee || ''].concat((a.replies || []).map(function (r) { return r.body; })).join(' ').toLowerCase();
        if (hay.indexOf(q) === -1 && String(a.seq_no) !== q.replace('#', '')) { return false; }
      }
      return true;
    });
    if (f.sort === 'newest') { list.sort(function (x, y) { return y.id - x.id; }); }
    else if (f.sort === 'priority') { list.sort(function (x, y) { return (PRIO_RANK[x.priority] - PRIO_RANK[y.priority]) || (x.seq_no - y.seq_no); }); }
    else if (f.sort === 'status') { list.sort(function (x, y) { return (x.status === y.status ? 0 : x.status === 'OPEN' ? -1 : 1) || (x.seq_no - y.seq_no); }); }
    return list;
  }

  root.Pin = {
    el: el, icon: icon, toast: toast, Api: Api, ThreadPanel: ThreadPanel, hoverCard: hoverCard, applyFilters: applyFilters,
    fmt: fmt, ago: ago, avatar: avatar, cleanName: cleanName, STATUS: STATUS, PRIORITY: PRIORITY, CATEGORY: CATEGORY
  };
})(window);
