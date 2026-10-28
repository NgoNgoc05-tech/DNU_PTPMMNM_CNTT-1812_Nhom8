// Bản 2.3: hàm thuần của chế độ "Tô chỗ khác" (diff.js) và rút gọn nét vẽ tay (annotator.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
require('../../public/assets/js/diff.js');
const { compute } = globalThis.PinDiff;

function image(w, h, paint) {
  const d = new Uint8ClampedArray(w * h * 4).fill(255);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      if (paint(x, y)) { const p = (y * w + x) * 4; d[p] = 20; d[p + 1] = 20; d[p + 2] = 200; }
    }
  }
  return d;
}

test('hai ảnh giống nhau: không có vùng thay đổi', () => {
  const a = image(100, 60, () => false);
  const r = compute(a, a, 100, 60, 40);
  assert.equal(r.changed, 0);
  assert.equal(r.regions.length, 0);
});

test('một nút dời chỗ: tìm đúng hai vùng (chỗ cũ và chỗ mới)', () => {
  const w = 200, h = 100;
  const a = image(w, h, (x, y) => x >= 140 && x < 180 && y >= 20 && y < 40); // nút ở bên phải
  const b = image(w, h, (x, y) => x >= 20 && x < 60 && y >= 60 && y < 80);   // nút ở bên trái
  const r = compute(a, b, w, h, 40);
  assert.equal(r.regions.length, 2);
  const boxes = r.regions.map((g) => [g.x, g.y, g.w, g.h]).sort((p, q) => p[0] - q[0]);
  assert.deepEqual(boxes, [[16, 56, 48, 24], [136, 16, 48, 24]]);
  assert.equal(r.changed, 2 * 40 * 20);
});

test('nhiễu lẻ tẻ (1 điểm) bị bỏ qua, ngưỡng cao bỏ khác biệt nhỏ', () => {
  const w = 64, h = 64;
  const a = image(w, h, () => false);
  const b = image(w, h, (x, y) => x === 10 && y === 10);
  assert.equal(compute(a, b, w, h, 40).regions.length, 0);
  const faint = new Uint8ClampedArray(a); for (let i = 0; i < faint.length; i += 4) { faint[i] = 230; }
  assert.equal(compute(a, faint, w, h, 40).changed, 0);
  assert.equal(compute(a, faint, w, h, 10).changed, w * h);
});

test('rút gọn nét vẽ tay giữ hai đầu và bỏ điểm thẳng hàng', () => {
  globalThis.window = globalThis;
  globalThis.PinCoords = require('../../public/assets/js/coords.js');
  require('../../public/assets/js/annotator.js');
  const { simplify, pathLength } = globalThis.Annotator;
  const line = Array.from({ length: 50 }, (_, i) => [i * 2, i * 2]);
  assert.deepEqual(simplify(line, 0.6), [[0, 0], [98, 98]]);
  const corner = [[0, 0], [10, 0], [20, 0], [20, 10], [20, 20]];
  assert.deepEqual(simplify(corner, 0.6), [[0, 0], [20, 0], [20, 20]]);
  assert.equal(pathLength([[0, 0], [3, 4], [3, 10]]), 11);
});
