/*
 * Khung xem ảnh có dấu góp ý (dùng chung cho trang xem và trang so sánh).
 *
 * - Ảnh được vẽ trong khung theo kiểu contain, có lề trống, phóng to/thu nhỏ và kéo.
 * - Dấu được đặt bằng PinCoords.toScreen() từ toạ độ ẢNH GỐC, nên luôn bám đúng nội dung
 *   khi khung đổi kích thước (ResizeObserver), khi zoom hoặc khi có lề trống.
 * - Khi người dùng bấm/kéo, vị trí chuột được đổi bằng PinCoords.toImage(); bấm vào lề
 *   trống bị bỏ qua.
 * - Mọi văn bản đều gán bằng textContent (không dùng innerHTML) để nội dung góp ý chỉ là dữ liệu.
 */
(function (root) {
  'use strict';
  var C = root.PinCoords;
  var MIN_ZOOM = 0.25;
  var MAX_ZOOM = 8;

  function Annotator(box, options) {
    this.box = box;
    this.opt = options;
    this.imageW = options.width;
    this.imageH = options.height;
    this.items = [];
    this.selectedId = null;
    this.draft = null;
    this.tool = options.editable ? 'point' : 'pan';
    this.state = { zoom: 1, panX: 0, panY: 0 };
    this.drag = null;

    box.classList.add('annotator');
    this.img = document.createElement('img');
    this.img.className = 'annotator-image';
    this.img.alt = options.alt || '';
    this.img.draggable = false;
    this.img.src = options.imageUrl;
    this.layer = document.createElement('div');
    this.layer.className = 'annotator-layer';
    this.preview = document.createElement('div');
    this.preview.className = 'mark-rect mark-preview';
    this.preview.hidden = true;
    this.hoverCard = document.createElement('div');
    this.hoverCard.className = 'pin-hover';
    this.hoverCard.hidden = true;
    this.hoverId = null;
    box.appendChild(this.img);
    box.appendChild(this.layer);
    box.appendChild(this.preview);
    box.appendChild(this.hoverCard);

    var self = this;
    box.addEventListener('pointerdown', function (e) { self.onDown(e); });
    box.addEventListener('pointermove', function (e) { self.onMove(e); });
    box.addEventListener('pointerup', function (e) { self.onUp(e); });
    box.addEventListener('pointercancel', function () { self.drag = null; self.preview.hidden = true; box.classList.remove('is-panning'); });
    box.addEventListener('pointerleave', function () { if (self.opt.onHover) { self.opt.onHover(null); } });
    box.addEventListener('wheel', function (e) { self.onWheel(e); }, { passive: false });
    if (root.ResizeObserver) {
      new ResizeObserver(function () { self.layout(); }).observe(box);
    } else {
      root.addEventListener('resize', function () { self.layout(); });
    }
    this.layout();
  }

  Annotator.prototype.content = function () {
    return C.fitContain(this.box.clientWidth, this.box.clientHeight, this.imageW, this.imageH,
      this.state.zoom, this.state.panX, this.state.panY);
  };

  Annotator.prototype.layout = function () {
    var c = this.content();
    var s = this.img.style;
    s.left = c.left + 'px';
    s.top = c.top + 'px';
    s.width = c.width + 'px';
    s.height = c.height + 'px';
    this.render();
    if (this.opt.onLayout) { this.opt.onLayout(this); }
  };

  Annotator.prototype.setItems = function (items) {
    this.items = items;
    this.render();
  };

  Annotator.prototype.select = function (id, center) {
    this.selectedId = id;
    if (center) { this.centerOn(id); } else { this.render(); }
  };

  Annotator.prototype.setTool = function (tool) {
    this.tool = tool;
    this.box.dataset.tool = tool;
  };

  Annotator.prototype.setZoom = function (zoom, cx, cy) {
    zoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom));
    if (cx === undefined) { cx = this.box.clientWidth / 2; cy = this.box.clientHeight / 2; }
    this.state = C.zoomAround(this.state, zoom, cx, cy, this.box.clientWidth, this.box.clientHeight, this.imageW, this.imageH);
    this.layout();
  };

  Annotator.prototype.fit = function () {
    this.state = { zoom: 1, panX: 0, panY: 0 };
    this.layout();
  };

  Annotator.prototype.setView = function (state) {
    this.state = { zoom: state.zoom, panX: state.panX, panY: state.panY };
    this.layout();
  };

  Annotator.prototype.centerOn = function (id) {
    var a = this.find(id);
    if (!a) { this.render(); return; }
    var cx = a.x + (a.w || 0) / 2;
    var cy = a.y + (a.h || 0) / 2;
    var base = C.fitContain(this.box.clientWidth, this.box.clientHeight, this.imageW, this.imageH, this.state.zoom, 0, 0);
    this.state.panX = this.box.clientWidth / 2 - (base.left + cx * base.scale);
    this.state.panY = this.box.clientHeight / 2 - (base.top + cy * base.scale);
    if (this.state.zoom <= 1) { this.state.panX = 0; this.state.panY = 0; }
    this.layout();
  };

  Annotator.prototype.find = function (id) {
    for (var i = 0; i < this.items.length; i++) { if (this.items[i].id === id) { return this.items[i]; } }
    return null;
  };

  Annotator.prototype.render = function () {
    var c = this.content();
    var layer = this.layer;
    while (layer.firstChild) { layer.removeChild(layer.firstChild); }
    var self = this;
    var list = this.items.slice();
    if (this.draft) { list.push(this.draft); }
    list.forEach(function (a) {
      var p = C.toScreen(a.x, a.y, c);
      var cls = (a.status === 'RESOLVED' ? ' is-resolved' : '') + (a.id === self.selectedId ? ' is-selected' : '') +
        (a.draft ? ' is-draft' : '') + (a.priority ? ' prio-' + a.priority : '') + (a.id && a.id === self.hoverId ? ' is-hover' : '');
      if (a.shape === 'RECT') {
        var r = document.createElement('div');
        r.className = 'mark-rect' + cls;
        r.style.left = p.left + 'px';
        r.style.top = p.top + 'px';
        r.style.width = (a.w * c.scale) + 'px';
        r.style.height = (a.h * c.scale) + 'px';
        r.dataset.rect = a.id || '';
        layer.appendChild(r);
      }
      var pin = document.createElement('button');
      pin.type = 'button';
      pin.className = 'mark-pin' + cls + (a.shape === 'RECT' ? ' on-rect' : '');
      pin.style.left = p.left + 'px';
      pin.style.top = p.top + 'px';
      pin.textContent = a.draft ? '+' : String(a.seq_no);
      pin.dataset.id = a.id || '';
      pin.dataset.seq = a.seq_no || '';
      pin.dataset.x = a.x;
      pin.dataset.y = a.y;
      pin.setAttribute('aria-label', a.draft ? 'Góp ý mới' : 'Góp ý số ' + a.seq_no);
      if (!a.draft) {
        pin.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
        pin.addEventListener('click', function (e) {
          e.stopPropagation();
          self.showHover(null);
          if (self.opt.onSelect) { self.opt.onSelect(a.id); }
        });
        pin.addEventListener('mouseenter', function () { self.showHover(a); if (self.opt.onPinHover) { self.opt.onPinHover(a.id); } });
        pin.addEventListener('mouseleave', function () { self.showHover(null); if (self.opt.onPinHover) { self.opt.onPinHover(null); } });
      }
      layer.appendChild(pin);
    });
  };

  /** Thẻ xem nhanh khi rê chuột lên dấu (nội dung gán bằng textContent). */
  Annotator.prototype.showHover = function (a) {
    var card = this.hoverCard;
    if (!a || a.draft || a.id === this.selectedId || !this.opt.hoverCard) { card.hidden = true; return; }
    while (card.firstChild) { card.removeChild(card.firstChild); }
    this.opt.hoverCard(a, card);
    var c = this.content();
    var p = C.toScreen(a.x + (a.w || 0), a.y, c);
    var bw = this.box.clientWidth;
    var left = p.left + 22;
    if (left + 270 > bw) { left = Math.max(8, p.left - 22 - 260); }
    card.style.left = left + 'px';
    card.style.top = Math.max(8, Math.min(p.top - 16, this.box.clientHeight - 140)) + 'px';
    card.hidden = false;
  };

  /** Làm nổi một dấu khi rê chuột trên danh sách. */
  Annotator.prototype.highlight = function (id) {
    if (this.hoverId === id) { return; }
    this.hoverId = id;
    this.render();
  };

  /** Vị trí màn hình (px trong khung) của dấu nháp, để đặt ô soạn góp ý cạnh dấu. */
  Annotator.prototype.draftScreenBox = function () {
    if (!this.draft) { return null; }
    var c = this.content();
    var d = this.draft;
    var tl = C.toScreen(d.x, d.y, c);
    return { left: tl.left, top: tl.top, right: tl.left + (d.w || 0) * c.scale, bottom: tl.top + (d.h || 0) * c.scale };
  };

  Annotator.prototype.local = function (e) {
    var b = this.box.getBoundingClientRect();
    return { x: e.clientX - b.left, y: e.clientY - b.top };
  };

  Annotator.prototype.onDown = function (e) {
    if (e.button !== 0 && e.button !== 1) { return; }
    var p = this.local(e);
    var mode = (this.tool === 'pan' || e.button === 1 || e.shiftKey) ? 'pan' : this.tool;
    this.drag = { mode: mode, start: p, startState: { zoom: this.state.zoom, panX: this.state.panX, panY: this.state.panY },
      img: C.toImage(p.x, p.y, this.content(), this.imageW, this.imageH) };
    if (mode === 'pan') { this.box.classList.add('is-panning'); }
    this.box.setPointerCapture(e.pointerId);
    e.preventDefault();
  };

  Annotator.prototype.onMove = function (e) {
    if (!this.drag) {
      if (this.opt.onHover) {
        var q = this.local(e);
        this.opt.onHover(C.toImage(q.x, q.y, this.content(), this.imageW, this.imageH));
      }
      return;
    }
    var p = this.local(e);
    var d = this.drag;
    if (d.mode === 'pan') {
      this.state.panX = d.startState.panX + (p.x - d.start.x);
      this.state.panY = d.startState.panY + (p.y - d.start.y);
      this.layout();
    } else if (d.mode === 'rect' && d.img.inside) {
      var c = this.content();
      var now = C.toImage(p.x, p.y, c, this.imageW, this.imageH);
      var r = C.rectFromPoints(d.img, now, this.imageW, this.imageH);
      var tl = C.toScreen(r.x, r.y, c);
      var s = this.preview.style;
      s.left = tl.left + 'px'; s.top = tl.top + 'px';
      s.width = (r.w * c.scale) + 'px'; s.height = (r.h * c.scale) + 'px';
      this.preview.hidden = false;
      d.rect = r;
    }
  };

  Annotator.prototype.onUp = function (e) {
    var d = this.drag;
    this.drag = null;
    this.preview.hidden = true;
    this.box.classList.remove('is-panning');
    if (!d || d.mode === 'pan' || !this.opt.editable) { return; }
    var p = this.local(e);
    var moved = Math.abs(p.x - d.start.x) + Math.abs(p.y - d.start.y) > 4;
    if (!d.img.inside) {
      if (this.opt.onOutside) { this.opt.onOutside(); }
      return;
    }
    if (d.mode === 'rect' && moved && d.rect && d.rect.w >= 2 && d.rect.h >= 2) {
      this.setDraft({ shape: 'RECT', x: d.rect.x, y: d.rect.y, w: d.rect.w, h: d.rect.h });
    } else if (!moved) {
      this.setDraft({ shape: 'POINT', x: C.round2(d.img.x), y: C.round2(d.img.y), w: null, h: null });
    }
  };

  Annotator.prototype.setDraft = function (pos) {
    this.draft = pos ? { draft: true, shape: pos.shape, x: pos.x, y: pos.y, w: pos.w, h: pos.h, status: 'OPEN' } : null;
    this.render();
    if (pos && this.opt.onDraft) { this.opt.onDraft(pos); }
  };

  Annotator.prototype.onWheel = function (e) {
    e.preventDefault();
    var p = this.local(e);
    var factor = Math.exp(-e.deltaY * 0.0015);
    this.setZoom(this.state.zoom * factor, p.x, p.y);
  };

  root.Annotator = Annotator;
})(window);
