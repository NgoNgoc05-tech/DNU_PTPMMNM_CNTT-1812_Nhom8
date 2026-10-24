/*
 * YÊU CẦU KỸ THUẬT 1 — đổi toạ độ giữa MÀN HÌNH và ẢNH GỐC.
 *
 * Toạ độ góp ý luôn lưu theo pixel của ảnh gốc (ví dụ ảnh 1000×500, điểm (750;150)).
 * Khi hiển thị, ảnh được đặt trong khung theo kiểu "contain" (giữ tỉ lệ, phần thừa là lề
 * trống), có thể phóng to/thu nhỏ và kéo. Mọi phép đổi đi qua "vùng nội dung" của ảnh
 * (content rect) chứ không đi qua khung, nên phần lề trống không bao giờ bị tính vào toạ độ.
 *
 * Tệp này không phụ thuộc DOM: dùng được trong trình duyệt (window.PinCoords) và trong
 * Node để kiểm thử tự động (tests/js/coords.test.mjs).
 */
(function (root) {
  'use strict';

  /**
   * Vùng nội dung của ảnh trong khung boxW×boxH.
   * zoom = 1 là vừa khít; panX/panY là độ kéo thêm (pixel màn hình).
   */
  function fitContain(boxW, boxH, imageW, imageH, zoom, panX, panY) {
    zoom = zoom || 1;
    panX = panX || 0;
    panY = panY || 0;
    var scale = Math.min(boxW / imageW, boxH / imageH) * zoom;
    var width = imageW * scale;
    var height = imageH * scale;
    return {
      left: (boxW - width) / 2 + panX,
      top: (boxH - height) / 2 + panY,
      width: width,
      height: height,
      scale: scale
    };
  }

  /** Pixel ảnh gốc -> vị trí trên màn hình (so với góc trái trên của khung). */
  function toScreen(x, y, content) {
    return { left: content.left + x * content.scale, top: content.top + y * content.scale };
  }

  /** Vị trí trên màn hình (so với khung) -> pixel ảnh gốc; inside=false nếu bấm vào lề trống. */
  function toImage(sx, sy, content, imageW, imageH) {
    var x = (sx - content.left) / content.scale;
    var y = (sy - content.top) / content.scale;
    return { x: x, y: y, inside: x >= 0 && y >= 0 && x <= imageW && y <= imageH };
  }

  /** Hình chữ nhật (pixel ảnh gốc) từ hai góc kéo chuột, cắt gọn trong ảnh. */
  function rectFromPoints(a, b, imageW, imageH) {
    function clamp(v, max) { return Math.min(max, Math.max(0, v)); }
    var x1 = clamp(Math.min(a.x, b.x), imageW);
    var y1 = clamp(Math.min(a.y, b.y), imageH);
    var x2 = clamp(Math.max(a.x, b.x), imageW);
    var y2 = clamp(Math.max(a.y, b.y), imageH);
    return { x: round2(x1), y: round2(y1), w: round2(x2 - x1), h: round2(y2 - y1) };
  }

  /** Phóng to quanh điểm (cx, cy) trên màn hình: điểm ảnh dưới con trỏ giữ nguyên vị trí. */
  function zoomAround(state, newZoom, cx, cy, boxW, boxH, imageW, imageH) {
    var before = fitContain(boxW, boxH, imageW, imageH, state.zoom, state.panX, state.panY);
    var p = toImage(cx, cy, before, imageW, imageH);
    var base = fitContain(boxW, boxH, imageW, imageH, newZoom, 0, 0);
    return {
      zoom: newZoom,
      panX: cx - (base.left + p.x * base.scale),
      panY: cy - (base.top + p.y * base.scale)
    };
  }

  function round2(v) { return Math.round(v * 100) / 100; }

  var api = {
    fitContain: fitContain,
    toScreen: toScreen,
    toImage: toImage,
    rectFromPoints: rectFromPoints,
    zoomAround: zoomAround,
    round2: round2
  };
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.PinCoords = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);
