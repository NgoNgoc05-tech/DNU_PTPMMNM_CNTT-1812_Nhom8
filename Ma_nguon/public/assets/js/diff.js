// Bản 2.3 — tự tìm chỗ khác nhau giữa hai phiên bản ảnh (chạy hoàn toàn trên trình duyệt).
// So sánh từng điểm ảnh sau khi đưa hai ảnh về cùng kích thước của bản MỚI, gom các ô 8×8 thay đổi
// thành vùng (thành phần liên thông), trả về khung theo pixel ảnh gốc của bản mới.
(function (root) {
  'use strict';
  var MAX_SIDE = 1400; // phân tích ở độ phân giải tối đa 1400 px cho nhanh
  var CELL = 8;

  function loadImage(url) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = function () { reject(new Error('Không tải được ảnh để so sánh.')); };
      img.src = url;
    });
  }

  function pixels(img, w, h) {
    var c = document.createElement('canvas');
    c.width = w; c.height = h;
    var g = c.getContext('2d', { willReadFrequently: true });
    g.fillStyle = '#fff';
    g.fillRect(0, 0, w, h);
    g.drawImage(img, 0, 0, w, h);
    return g.getImageData(0, 0, w, h).data;
  }

  /**
   * Hàm thuần (kiểm thử được): a, b là mảng RGBA cùng kích thước w×h.
   * @return {{mask: Uint8Array, changed: number, regions: Array<{x:number,y:number,w:number,h:number,pixels:number}>}}
   */
  function compute(a, b, w, h, threshold) {
    var mask = new Uint8Array(w * h);
    var changed = 0;
    for (var i = 0, p = 0; i < mask.length; i++, p += 4) {
      var d = Math.max(Math.abs(a[p] - b[p]), Math.abs(a[p + 1] - b[p + 1]), Math.abs(a[p + 2] - b[p + 2]));
      if (d > threshold) { mask[i] = 1; changed++; }
    }
    // Lưới ô: ô có >= 2 điểm thay đổi được tính là thay đổi (bỏ nhiễu nén JPEG lẻ tẻ).
    var gw = Math.ceil(w / CELL), gh = Math.ceil(h / CELL);
    var cells = new Uint16Array(gw * gh);
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) { if (mask[y * w + x]) { cells[((y / CELL) | 0) * gw + ((x / CELL) | 0)]++; } }
    }
    var seen = new Uint8Array(gw * gh);
    var regions = [];
    for (var ci = 0; ci < cells.length; ci++) {
      if (cells[ci] < 2 || seen[ci]) { continue; }
      // Loang theo 8 hướng, cho phép cách nhau 1 ô trống để gộp các phần sát nhau của cùng một chi tiết.
      var stack = [ci], minX = gw, minY = gh, maxX = 0, maxY = 0, count = 0;
      seen[ci] = 1;
      while (stack.length) {
        var k = stack.pop(), cx = k % gw, cy = (k / gw) | 0;
        count += cells[k];
        if (cx < minX) { minX = cx; } if (cx > maxX) { maxX = cx; }
        if (cy < minY) { minY = cy; } if (cy > maxY) { maxY = cy; }
        for (var dy = -2; dy <= 2; dy++) {
          for (var dx = -2; dx <= 2; dx++) {
            var nx = cx + dx, ny = cy + dy;
            if (nx < 0 || ny < 0 || nx >= gw || ny >= gh) { continue; }
            var nk = ny * gw + nx;
            if (!seen[nk] && cells[nk] >= 2) { seen[nk] = 1; stack.push(nk); }
          }
        }
      }
      if (count >= 6) {
        regions.push({ x: minX * CELL, y: minY * CELL, w: Math.min(w, (maxX + 1) * CELL) - minX * CELL, h: Math.min(h, (maxY + 1) * CELL) - minY * CELL, pixels: count });
      }
    }
    regions.sort(function (r1, r2) { return r2.w * r2.h - r1.w * r1.h; });
    return { mask: mask, changed: changed, regions: regions.slice(0, 40) };
  }

  /** Phân tích hai ảnh; kết quả theo pixel ảnh gốc của bản mới (right). */
  function analyse(left, right, threshold) {
    return Promise.all([loadImage(left.imageUrl), loadImage(right.imageUrl)]).then(function (imgs) {
      var k = Math.min(1, MAX_SIDE / Math.max(right.width, right.height));
      var w = Math.max(1, Math.round(right.width * k)), h = Math.max(1, Math.round(right.height * k));
      var a = pixels(imgs[0], w, h), b = pixels(imgs[1], w, h);
      return { w: w, h: h, scale: k, a: a, b: b, run: function (t) {
        var r = compute(a, b, w, h, t);
        r.regions = r.regions.map(function (g) { return { x: g.x / k, y: g.y / k, w: g.w / k, h: g.h / k, pixels: g.pixels }; });
        r.percent = Math.round(r.changed / (w * h) * 1000) / 10;
        return r;
      }, sameSize: left.width === right.width && left.height === right.height };
    });
  }

  /** Vẽ lớp phủ: điểm thay đổi màu đỏ, phần giống nhau làm mờ (nếu bật), khung đỏ đánh số từng vùng. */
  function paint(canvas, data, result, dim) {
    canvas.width = data.w; canvas.height = data.h;
    var g = canvas.getContext('2d');
    var out = g.createImageData(data.w, data.h);
    var o = out.data, m = result.mask;
    for (var i = 0, p = 0; i < m.length; i++, p += 4) {
      if (m[i]) { o[p] = 239; o[p + 1] = 35; o[p + 2] = 35; o[p + 3] = 170; }
      else if (dim) { o[p] = 255; o[p + 1] = 255; o[p + 2] = 255; o[p + 3] = 150; }
    }
    g.putImageData(out, 0, 0);
    var lw = Math.max(2, Math.round(data.w / 500));
    g.lineWidth = lw;
    g.font = '700 ' + Math.max(12, lw * 7) + 'px system-ui, sans-serif';
    result.regions.forEach(function (r, idx) {
      var x = r.x * data.scale, y = r.y * data.scale, w = r.w * data.scale, h = r.h * data.scale;
      g.strokeStyle = '#dc2626';
      g.strokeRect(x - lw, y - lw, w + 2 * lw, h + 2 * lw);
      var label = String(idx + 1), tw = g.measureText(label).width + lw * 4, th = lw * 9;
      g.fillStyle = '#dc2626';
      g.fillRect(x - lw, Math.max(0, y - lw - th), tw, th);
      g.fillStyle = '#fff';
      g.fillText(label, x + lw, Math.max(th - lw * 2, y - lw * 3));
    });
  }

  root.PinDiff = { compute: compute, analyse: analyse, paint: paint };
})(typeof window !== 'undefined' ? window : globalThis);
