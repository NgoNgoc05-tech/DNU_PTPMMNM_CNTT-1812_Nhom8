// Kiểm thử public/assets/js/coords.js bằng trình chạy có sẵn của Node: node --test tests/js/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const C = require('../../public/assets/js/coords.js');
const near = (a, b, msg) => assert.ok(Math.abs(a - b) <= 0.01, `${msg}: expected ${b}, got ${a}`);

test('ảnh 1000×500 hiển thị 500×250: điểm (750;150) ở (375;75)', () => {
  const c = C.fitContain(500, 250, 1000, 500);
  const p = C.toScreen(750, 150, c);
  near(p.left, 375, 'x');
  near(p.top, 75, 'y');
});

test('có lề trống (khung 500×400 và 800×300): dấu vẫn đúng nội dung', () => {
  const b = C.fitContain(500, 400, 1000, 500);
  near(b.top, 75, 'lề trên');
  near(C.toScreen(750, 150, b).top, 150, 'y khi có lề trên/dưới');
  const c = C.fitContain(800, 300, 1000, 500);
  near(c.left, 100, 'lề trái');
  const p = C.toScreen(750, 150, c);
  near(p.left, 550, 'x khi có lề trái/phải');
  near(p.top, 90, 'y khi có lề trái/phải');
});

test('bấm vào lề trống không tạo toạ độ trong ảnh', () => {
  const b = C.fitContain(500, 400, 1000, 500);
  assert.equal(C.toImage(250, 30, b, 1000, 500).inside, false);
  assert.equal(C.toImage(250, 380, b, 1000, 500).inside, false);
  const back = C.toImage(375, 150, b, 1000, 500);
  assert.equal(back.inside, true);
  near(back.x, 750, 'x ngược');
  near(back.y, 150, 'y ngược');
});

test('phóng to quanh con trỏ: điểm ảnh dưới con trỏ đứng yên', () => {
  const box = [500, 250];
  let state = { zoom: 1, panX: 0, panY: 0 };
  const before = C.fitContain(...box, 1000, 500, state.zoom, state.panX, state.panY);
  const cursor = C.toScreen(750, 150, before);
  for (const z of [1.5, 2, 4, 0.5]) {
    state = C.zoomAround(state, z, cursor.left, cursor.top, ...box, 1000, 500);
    const after = C.fitContain(...box, 1000, 500, state.zoom, state.panX, state.panY);
    const p = C.toScreen(750, 150, after);
    near(p.left, cursor.left, `x ở zoom ${z}`);
    near(p.top, cursor.top, `y ở zoom ${z}`);
  }
});

test('đổi kích thước khung nhiều lần: màn hình -> ảnh gốc -> màn hình khớp', () => {
  for (const [w, h] of [[320, 240], [1024, 768], [1920, 400], [333, 999]]) {
    const c = C.fitContain(w, h, 1000, 500, 1.7, 13, -21);
    const s = C.toScreen(750, 150, c);
    const back = C.toImage(s.left, s.top, c, 1000, 500);
    near(back.x, 750, `x ${w}×${h}`);
    near(back.y, 150, `y ${w}×${h}`);
  }
});

test('vùng chữ nhật kéo ngược chiều và vượt mép được cắt trong ảnh', () => {
  const r = C.rectFromPoints({ x: 900, y: 450 }, { x: 1100, y: 300 }, 1000, 500);
  assert.deepEqual(r, { x: 900, y: 300, w: 100, h: 150 });
});
