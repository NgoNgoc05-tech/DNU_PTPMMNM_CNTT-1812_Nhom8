// Trang xem một phiên bản ảnh: nối khung ảnh (Annotator) với bảng góp ý (ThreadPanel),
// ô soạn góp ý nổi, bộ lọc phía trình duyệt, thanh công cụ nổi, phím tắt và tab hoạt động.
(function () {
  'use strict';
  var cfg = JSON.parse(document.getElementById('config').textContent);
  var api = new Pin.Api(cfg.csrf);
  var el = Pin.el;
  var stage = document.getElementById('stage');
  var zoomLabel = document.getElementById('zoom-label');
  var cursorInfo = document.getElementById('cursor-info');
  var baseInfo = cursorInfo.textContent;
  var all = [];
  var popover = null;
  var filters = { status: '', author: 0, priority: '', category: '', q: '', sort: 'seq', hideResolved: false, mine: 0 };
  var panel;

  var view = new Annotator(stage, {
    imageUrl: cfg.imageUrl, width: cfg.width, height: cfg.height, editable: cfg.canComment, alt: 'Ảnh thiết kế',
    hoverCard: Pin.hoverCard,
    onSelect: function (id) { select(id, false); },
    onDraft: function (pos) { closePopover(); view.select(null); panel.select(null); openPopover(pos); },
    onOutside: function () { Pin.toast('Bấm vào bên trong ảnh; phần lề trống không được tính.', true); },
    onTooShort: function (mode) { Pin.toast(mode === 'arrow' ? 'Kéo từ đuôi tới đầu mũi tên (dài ít nhất 8 px ảnh gốc).' : 'Giữ chuột và kéo để vẽ nét.', true); },
    onLayout: function (v) { zoomLabel.textContent = Math.round(v.content().scale * 100) + '%'; placePopover(); },
    onHover: function (pt) {
      cursorInfo.textContent = pt && pt.inside
        ? 'x ' + Math.round(pt.x) + ' · y ' + Math.round(pt.y) + ' px ảnh gốc (' + cfg.width + '×' + cfg.height + ')'
        : baseInfo;
    },
    onPinHover: function (id) { panel.highlight(id); }
  });

  panel = new Pin.ThreadPanel(document.getElementById('panel-body'), {
    cfg: cfg, api: api,
    onSelect: function (id) { select(id, true); },
    onChanged: function (id) { closePopover(); view.setDraft(null); load(id); },
    onCancelDraft: function () { closePopover(); view.setDraft(null); },
    onHover: function (id) { view.highlight(id); }
  });

  // ---------------------------------------------------------- ô soạn góp ý nổi cạnh dấu nháp
  function openPopover(pos) {
    popover = el('div', { class: 'composer-pop', role: 'dialog', 'aria-label': 'Góp ý mới' });
    popover.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
    popover.addEventListener('wheel', function (e) { e.stopPropagation(); });
    popover.appendChild(panel.draftComposer(pos, function () { closePopover(); view.setDraft(null); }));
    stage.appendChild(popover);
    placePopover();
  }
  function closePopover() { if (popover) { popover.remove(); popover = null; } }
  function placePopover() {
    if (!popover) { return; }
    var b = view.draftScreenBox();
    if (!b) { return; }
    var W = stage.clientWidth;
    var H = stage.clientHeight;
    var pw = popover.offsetWidth || 330;
    var ph = popover.offsetHeight || 260;
    var left = b.right + 24;
    if (left + pw > W - 8) { left = b.left - 24 - pw; }
    if (left < 8) { left = Math.max(8, Math.min(W - pw - 8, b.left - pw / 2)); }
    var top = Math.max(8, Math.min(H - ph - 8, b.top - 20));
    popover.style.left = left + 'px';
    popover.style.top = top + 'px';
  }

  // ---------------------------------------------------------- chọn, lọc, tải danh sách
  function visible() { return Pin.applyFilters(all, filters); }

  function refresh() {
    var list = visible();
    view.setItems(list);
    panel.setItems(list, all);
    document.getElementById('count-all').textContent = all.length;
    var counts = { '': 0, OPEN: 0, RESOLVED: 0 };
    Pin.applyFilters(all, Object.assign({}, filters, { status: '' })).forEach(function (a) { counts[''] += 1; counts[a.status] += 1; });
    document.querySelectorAll('[data-count]').forEach(function (n) { n.textContent = counts[n.dataset.count]; });
  }

  function select(id, center) {
    closePopover();
    view.setDraft(null);
    if (id && !visible().some(function (a) { return a.id === id; }) && all.some(function (a) { return a.id === id; })) {
      resetFilters(); // góp ý được chọn đang bị lọc ẩn -> bỏ lọc để luôn thấy dấu
    }
    view.select(id, center);
    panel.select(id);
    showTab('list');
    var url = new URL(location.href);
    if (id) { url.searchParams.set('a', id); } else { url.searchParams.delete('a'); }
    history.replaceState(null, '', url);
  }

  function load(focusId) {
    return api.list(cfg.versionId).then(function (res) {
      all = res.items;
      refresh();
      if (focusId !== undefined) { select(focusId || null, false); }
      if (activityLoaded) { loadActivity(); }
    }).catch(function (err) { Pin.toast(err.message, true); });
  }

  function resetFilters() {
    filters = { status: '', author: 0, priority: '', category: '', q: '', sort: filters.sort, hideResolved: false, mine: 0 };
    document.getElementById('f-q').value = '';
    ['f-author', 'f-priority', 'f-category'].forEach(function (id) { document.getElementById(id).selectedIndex = 0; });
    document.getElementById('f-mine').checked = false;
    document.querySelectorAll('[data-status]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.status === '')); });
    document.querySelectorAll('[data-toggle=hideResolved]').forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
    refresh();
  }

  document.querySelectorAll('.segmented [data-status]').forEach(function (b) {
    b.addEventListener('click', function () {
      filters.status = b.dataset.status;
      document.querySelectorAll('.segmented [data-status]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      refresh();
    });
  });
  document.getElementById('f-author').addEventListener('change', function (e) { filters.author = Number(e.target.value); refresh(); });
  document.getElementById('f-priority').addEventListener('change', function (e) { filters.priority = e.target.value; refresh(); });
  document.getElementById('f-category').addEventListener('change', function (e) { filters.category = e.target.value; refresh(); });
  document.getElementById('f-sort').addEventListener('change', function (e) { filters.sort = e.target.value; refresh(); });
  document.getElementById('f-mine').addEventListener('change', function (e) { filters.mine = e.target.checked ? cfg.userId : 0; refresh(); });
  var qTimer = null;
  document.getElementById('f-q').addEventListener('input', function (e) {
    clearTimeout(qTimer);
    qTimer = setTimeout(function () { filters.q = e.target.value; refresh(); }, 150);
  });

  // ---------------------------------------------------------- tab Hoạt động
  var activityLoaded = false;
  var listBody = document.getElementById('panel-body');
  var actBody = document.getElementById('activity-body');
  var controls = document.getElementById('list-controls');
  function showTab(name) {
    document.querySelectorAll('[data-tab]').forEach(function (t) { t.setAttribute('aria-selected', String(t.dataset.tab === name)); });
    listBody.hidden = name !== 'list';
    controls.hidden = name !== 'list' || panel.selectedId !== null;
    actBody.hidden = name !== 'activity';
    if (name === 'activity' && !activityLoaded) { loadActivity(); }
  }
  function loadActivity() {
    activityLoaded = true;
    api.activity(cfg.projectId, cfg.screenId).then(function (res) {
      while (actBody.firstChild) { actBody.removeChild(actBody.firstChild); }
      if (!res.events.length) { actBody.appendChild(el('p', { class: 'empty', text: 'Chưa có hoạt động.' })); return; }
      var ul = el('ul', { class: 'feed' });
      res.events.forEach(function (ev) {
        var here = ev.version_id === cfg.versionId;
        var label = el(here ? 'button' : 'a', here
          ? { type: 'button', class: 'link-button', onclick: function () { select(ev.annotation_id, true); } }
          : { href: '/screen.php?id=' + ev.screen_id + '&v=' + ev.version_id + '&a=' + ev.annotation_id }, ['v' + ev.version_no + ' #' + ev.seq_no]);
        ul.appendChild(el('li', {}, [Pin.avatar(ev.actor, ev.actor_id), el('span', { class: 'feed-body' }, [
          el('strong', { text: Pin.cleanName(ev.actor) }), ' ' + describe(ev) + ' · ', label,
          ev.kind === 'REPLIED' || ev.kind === 'CREATED' ? el('span', { class: 'quote line-clamp-2', text: ev.detail }) : null,
          el('time', { title: Pin.fmt(ev.created_at), text: Pin.ago(ev.created_at) })])]));
      });
      actBody.appendChild(ul);
    }).catch(function (err) { Pin.toast(err.message, true); });
  }
  function describe(ev) {
    switch (ev.kind) {
      case 'CREATED': return 'tạo góp ý ' + ({ RECT: 'vùng', ARROW: 'mũi tên', DRAW: 'vẽ tay' }[ev.new_value] || 'điểm');
      case 'REPLIED': return 'trả lời';
      case 'EDITED': return 'sửa nhận xét';
      case 'STATUS': return ev.new_value === 'RESOLVED' ? 'đánh dấu đã xử lý (' + ev.detail.split(' — ')[0] + ')' : 'mở lại';
      case 'PRIORITY': return 'đổi ưu tiên → ' + ev.new_value;
      case 'CATEGORY': return 'đổi nhãn → ' + ev.new_value;
      case 'ASSIGNEE': return ev.new_value ? 'giao cho ' + Pin.cleanName(ev.new_value) : 'bỏ người phụ trách';
      case 'DUE': return ev.new_value ? 'đặt hạn ' + ev.new_value : 'bỏ hạn';
      case 'NOTE': return ev.new_value ? 'cập nhật ghi chú' : 'xoá ghi chú';
      default: return ev.kind;
    }
  }
  document.querySelectorAll('[data-tab]').forEach(function (t) { t.addEventListener('click', function () { showTab(t.dataset.tab); }); });

  // ---------------------------------------------------------- công cụ, phóng to, bật tắt
  function setTool(tool) {
    view.setTool(tool);
    document.querySelectorAll('button[data-tool]').forEach(function (x) { x.setAttribute('aria-pressed', String(x.dataset.tool === tool)); });
  }
  document.querySelectorAll('button[data-tool]').forEach(function (b) { b.addEventListener('click', function () { setTool(b.dataset.tool); }); });
  document.querySelectorAll('[data-zoom]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.dataset.zoom === 'fit') { view.fit(); }
      else { view.setZoom(view.state.zoom * (b.dataset.zoom === 'in' ? 1.25 : 0.8)); }
    });
  });
  function toggle(name) {
    var b = document.querySelector('[data-toggle=' + name + ']');
    var on = b.getAttribute('aria-pressed') !== 'true';
    b.setAttribute('aria-pressed', String(on));
    if (name === 'hideMarks') { stage.classList.toggle('hide-marks', on); }
    else { filters.hideResolved = on; refresh(); }
  }
  document.querySelectorAll('[data-toggle]').forEach(function (b) { b.addEventListener('click', function () { toggle(b.dataset.toggle); }); });
  var viewer = document.getElementById('viewer');
  document.getElementById('toggle-panel').addEventListener('click', function () { viewer.classList.toggle('panel-collapsed'); view.layout(); });

  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) { return; }
    if (document.querySelector('dialog[open]')) { return; }
    var k = e.key;
    if (k === '+' || k === '=') { view.setZoom(view.state.zoom * 1.25); }
    else if (k === '-') { view.setZoom(view.state.zoom * 0.8); }
    else if (k === '0') { view.fit(); }
    else if (cfg.canComment && /^[pPcC]$/.test(k)) { setTool('point'); }
    else if (cfg.canComment && (k === 'r' || k === 'R')) { setTool('rect'); }
    else if (cfg.canComment && (k === 'a' || k === 'A')) { setTool('arrow'); }
    else if (cfg.canComment && (k === 'd' || k === 'D')) { setTool('draw'); }
    else if (/^[hHvV]$/.test(k)) { setTool('pan'); }
    else if (k === 'm' || k === 'M') { toggle('hideMarks'); }
    else if (k === '/') { e.preventDefault(); showTab('list'); if (panel.selectedId) { select(null, false); } document.getElementById('f-q').focus(); }
    else if (k === '\\') { document.getElementById('toggle-panel').click(); }
    else if (k === '?') { document.getElementById('dlg-keys').showModal(); }
    else if (k === 'ArrowRight' || k === 'j' || k === 'J') { var n = panel.neighbour(1); if (n) { select(n, true); } }
    else if (k === 'ArrowLeft' || k === 'k' || k === 'K') { var p = panel.neighbour(-1); if (p) { select(p, true); } }
    else if (k === 'Escape') { if (popover) { closePopover(); view.setDraft(null); } else { select(null, false); } }
    else { return; }
    e.preventDefault();
  });

  // Bản 2.3: có trả lời / @nhắc tên mới trên đúng phiên bản đang xem -> tự tải lại danh sách, giữ góp ý đang mở.
  window.addEventListener('pinpoint:notifications', function (e) {
    if ((e.detail || []).some(function (n) { return n.version_id === cfg.versionId; }) && !popover) { load(panel.selectedId || null); }
  });

  window.pinpointView = view; // dùng cho kiểm thử trên trình duyệt
  load(cfg.focus || undefined);
})();
