// Trang so sánh: hai phiên bản cạnh nhau (mỗi bên chỉ hiện góp ý của chính nó), hoặc chồng
// lên nhau với thanh trượt / chồng mờ để thấy chỗ đã sửa. Xác nhận đã xử lý ghi nhận bản bên phải.
(function () {
  'use strict';
  var cfg = JSON.parse(document.getElementById('config').textContent);
  var api = new Pin.Api(cfg.csrf);
  var sync = document.getElementById('sync');
  var fStatus = document.getElementById('f-status');
  var compare = document.getElementById('compare');
  var range = document.getElementById('overlay-range');
  var control = document.getElementById('overlay-control');
  var legend = document.getElementById('overlay-legend');
  var form = document.getElementById('compare-form');
  var side = 'left';
  var mode = cfg.mode || 'side';
  var syncing = false;
  var views = {};
  var items = { left: [], right: [] };
  var overlay = null;
  var overlayWrap = null;
  var handle = null;

  function makeView(key) {
    var v = cfg[key];
    views[key] = new Annotator(document.getElementById('stage-' + key), {
      imageUrl: v.imageUrl, width: v.width, height: v.height, editable: false, alt: 'Phiên bản v' + v.no,
      hoverCard: Pin.hoverCard,
      onSelect: function (id) { setSide(key); choose(id); },
      onPinHover: function (id) { panel.highlight(id); },
      onLayout: function (view) {
        if (key === 'right' && overlay) { overlay.setView(view.state); }
        if (!sync.checked || syncing) { return; }
        var other = views[key === 'left' ? 'right' : 'left'];
        if (!other) { return; }
        syncing = true;
        other.setView(view.state);
        syncing = false;
      }
    });
    views[key].setTool('pan');
  }
  makeView('left');
  makeView('right');

  var panel = new Pin.ThreadPanel(document.getElementById('panel-body'), {
    cfg: panelCfg(), api: api, checkedVersion: { id: cfg.right.id, no: cfg.right.no },
    onSelect: function (id) { choose(id); },
    onChanged: function (id) { load().then(function () { choose(id || null); }); },
    onCancelDraft: function () {},
    onHover: function (id) { views[side].highlight(id); }
  });

  function panelCfg() {
    var v = cfg[side];
    return { versionId: v.id, versionNo: v.no, userId: cfg.userId, userName: cfg.userName, canComment: cfg.canComment,
      members: cfg.members, projectId: cfg.projectId };
  }

  function choose(id) {
    views.left.select(side === 'left' ? id : null);
    views.right.select(side === 'right' ? id : null);
    panel.select(id);
  }

  function setSide(key) {
    side = key;
    panel.opt.cfg = panelCfg();
    document.querySelectorAll('button[data-side]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.side === key)); });
    panel.setItems(items[key]);
  }

  function load() {
    return Promise.all([api.list(cfg.left.id, fStatus.value), api.list(cfg.right.id, fStatus.value)]).then(function (r) {
      items.left = r[0].items;
      items.right = r[1].items;
      views.left.setItems(items.left);
      views.right.setItems(items.right);
      panel.setItems(items[side]);
    }).catch(function (err) { Pin.toast(err.message, true); });
  }

  // ---------------------------------------------------- chế độ chồng ảnh (thanh trượt / chồng mờ)
  function applyOverlay() {
    if (!overlay) { return; }
    var p = Number(range.value);
    if (mode === 'swipe') {
      overlayWrap.style.opacity = '1';
      overlayWrap.style.clipPath = 'inset(0 ' + (100 - p) + '% 0 0)';
      handle.style.left = p + '%';
      handle.hidden = false;
    } else {
      overlayWrap.style.clipPath = 'none';
      overlayWrap.style.opacity = String(1 - p / 100);
      handle.hidden = true;
    }
  }
  function setMode(m) {
    mode = m;
    document.getElementById('mode-input').value = m;
    compare.className = 'compare mode-' + m;
    document.querySelectorAll('[data-mode]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.mode === m)); });
    var url = new URL(location.href);
    url.searchParams.set('mode', m);
    history.replaceState(null, '', url);
    var stage = document.getElementById('stage-right');
    if (m === 'side') {
      if (overlayWrap) { overlayWrap.remove(); handle.remove(); overlay = null; overlayWrap = null; }
      control.hidden = true;
      legend.hidden = true;
      views.left.layout();
      views.right.layout();
      return;
    }
    control.hidden = false;
    legend.hidden = false;
    if (!overlay) {
      overlayWrap = Pin.el('div', { class: 'overlay-left', 'aria-hidden': 'true' });
      var box = Pin.el('div', {});
      overlayWrap.appendChild(box);
      stage.insertBefore(overlayWrap, views.right.layer); // trên ảnh mới, dưới các dấu của bản mới
      overlay = new Annotator(box, { imageUrl: cfg.left.imageUrl, width: cfg.left.width, height: cfg.left.height, editable: false, alt: '' });
      handle = Pin.el('div', { class: 'swipe-handle' }, [Pin.el('span', { text: '⇆' })]);
      stage.appendChild(handle);
    }
    if (m === 'swipe' && range.value === '0') { range.value = '50'; }
    views.right.layout();
    overlay.setView(views.right.state);
    applyOverlay();
  }
  range.addEventListener('input', applyOverlay);
  document.querySelectorAll('[data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.mode); }); });
  document.getElementById('left-proxy').addEventListener('change', function (e) {
    form.querySelector('select[name=left]').value = e.target.value;
    form.submit();
  });

  document.querySelectorAll('button[data-side]').forEach(function (b) {
    b.addEventListener('click', function () { setSide(b.dataset.side); choose(null); });
  });
  document.querySelectorAll('[data-zoom]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = views.right;
      if (b.dataset.zoom === 'fit') { v.fit(); if (!sync.checked) { views.left.fit(); } }
      else { v.setZoom(v.state.zoom * (b.dataset.zoom === 'in' ? 1.25 : 0.8)); }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey) { return; }
    if (e.key === '1') { setMode('side'); } else if (e.key === '2') { setMode('swipe'); } else if (e.key === '3') { setMode('onion'); }
    else if (e.key === 'Escape') { choose(null); }
  });
  fStatus.addEventListener('change', load);
  sync.addEventListener('change', function () { if (sync.checked) { views.left.setView(views.right.state); } });
  window.pinpointViews = views;
  load().then(function () { if (mode !== 'side') { setMode(mode); } });
})();
