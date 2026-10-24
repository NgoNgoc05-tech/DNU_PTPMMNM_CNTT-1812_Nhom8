// Kiểm thử trên trình duyệt thật (Playwright) cho hai tình huống sử dụng và phép thử 1.
// Cần: máy chủ đang chạy (bash lab.sh run) trên CSDL đã `bash lab.sh setup`.
// Chạy: npm install && bash lab.sh e2e    (biến PINPOINT_URL để đổi địa chỉ)
// Lưu ý: bài kiểm thử tạo thêm góp ý và một phiên bản ảnh, nên chạy trên CSDL thử/demo.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.PINPOINT_URL || 'http://127.0.0.1:8115';
const OUT = path.join(here, 'out');
const FIXTURES = path.join(here, '..', '..', 'database', 'fixtures');
mkdirSync(OUT, { recursive: true });

let tests = 0;
let failed = 0;
function check(ok, name, info = '') {
  tests++;
  if (ok) { console.log(`PASS ${name}`); } else { failed++; console.log(`FAIL ${name} ${info}`); }
}

const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
const pageErrors = [];

async function login(email, viewport = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ viewport, acceptDownloads: true });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => pageErrors.push(`${email}: ${e.message}`));
  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', email);
  await page.fill('#password', 'Pinpoint@123');
  await Promise.all([page.waitForURL('**/projects.php'), page.click('button[type=submit]')]);
  return page;
}

async function openScreen(page, project, screen, versionLabel) {
  await page.goto(`${BASE}/projects.php`);
  await page.click(`.project-card:has-text("${project}")`);
  await page.click(`.screen-card h2 a:has-text("${screen}")`);
  await page.waitForSelector('.mark-pin, .empty');
  if (versionLabel) {
    await Promise.all([page.waitForNavigation(), page.click(`.version-pills a:text-is("${versionLabel}")`)]);
  }
  await page.waitForFunction(() => document.querySelectorAll('.mark-pin').length > 0 || document.querySelector('.empty'));
  await page.waitForTimeout(200);
}

/** Vị trí mong đợi tính ĐỘC LẬP từ khung ảnh thật đang hiển thị trên màn hình. */
async function pinVsImage(page, seq, ox, oy, imageW) {
  return page.evaluate(({ seq, ox, oy, imageW }) => {
    const img = document.querySelector('.annotator-image').getBoundingClientRect();
    const pin = document.querySelector(`.mark-pin[data-seq="${seq}"]`).getBoundingClientRect();
    const k = img.width / imageW;
    return { dx: pin.left + pin.width / 2 - (img.left + ox * k), dy: pin.top + pin.height / 2 - (img.top + oy * k), shown: img.width };
  }, { seq, ox, oy, imageW });
}

try {
  // ---------- Phép thử 1 trên trang phép thử
  const r = await login('reviewer@pinpoint.test');
  await r.goto(`${BASE}/calibration.php`);
  const calib = await r.evaluate(() => window.calibrationResults);
  check(calib.length === 4 && calib.every((c) => c.ok), 't1-calibration-page-4-cases', JSON.stringify(calib));

  // ---------- Phép thử 1 trên trình xem thật: đổi kích thước cửa sổ và phóng to
  await openScreen(r, 'FoodNow', 'Trang chủ', 'v1');
  for (const vp of [{ width: 1440, height: 900 }, { width: 1100, height: 650 }, { width: 900, height: 1000 }]) {
    await r.setViewportSize(vp);
    await r.waitForTimeout(250);
    const d = await pinVsImage(r, 1, 750, 150, 1000);
    check(Math.abs(d.dx) <= 1 && Math.abs(d.dy) <= 1, `t1-viewer-${vp.width}x${vp.height}`, JSON.stringify(d));
  }
  await r.setViewportSize({ width: 1440, height: 900 });
  await r.click('[data-zoom=in]');
  await r.click('[data-zoom=in]');
  await r.waitForTimeout(150);
  let d = await pinVsImage(r, 1, 750, 150, 1000);
  check(Math.abs(d.dx) <= 1 && Math.abs(d.dy) <= 1, 't1-viewer-zoomed', JSON.stringify(d));
  await r.click('[data-zoom=fit]');

  // ---------- Tình huống 1: người xem đánh dấu nút cần sửa → nhận xét → người thiết kế trả lời
  const img = await r.$eval('.annotator-image', (e) => { const b = e.getBoundingClientRect(); return { x: b.left, y: b.top, w: b.width }; });
  const k = img.w / 1000;
  // Bấm vào lề trống bên trên ảnh: không được tạo góp ý.
  await r.mouse.click(img.x + 10, img.y - 15);
  check(await r.$('form textarea[aria-label="Nội dung nhận xét"]') === null, 's1-margin-click-ignored');
  // Khoanh vùng thẻ "Bún chả" (360;320)–(620;470).
  await r.click('button[data-tool=rect]');
  // Ẩn các dấu đang có để lần chạy lại không bấm trúng dấu cũ ở cùng góc.
  await r.click('[data-toggle=hideMarks]');
  await r.mouse.move(img.x + 360 * k, img.y + 320 * k);
  await r.mouse.down();
  await r.mouse.move(img.x + 620 * k, img.y + 470 * k, { steps: 6 });
  await r.mouse.up();
  await r.waitForSelector('.composer-pop');
  check(await r.$('.composer-pop select[aria-label="Mức ưu tiên"]') !== null, 'v2-popover-composer-next-to-mark');
  await r.fill('textarea[aria-label="Nội dung nhận xét"]', 'E2E: thẻ món cần bo góc lớn hơn <b>12px</b>');
  await r.selectOption('.composer-pop select[aria-label="Mức ưu tiên"]', 'HIGH');
  await r.selectOption('.composer-pop select[aria-label="Nhãn phân loại"]', 'LAYOUT');
  const created = r.waitForResponse((res) => res.url().includes('/api.php') && res.request().method() === 'POST');
  await r.click('button:has-text("Lưu góp ý")');
  const item = (await (await created).json()).item;
  await r.click('[data-toggle=hideMarks]');
  check(Math.abs(item.x - 360) <= 1 && Math.abs(item.y - 320) <= 1 && Math.abs(item.w - 260) <= 1 && Math.abs(item.h - 150) <= 1,
    's1-rect-saved-in-original-pixels', JSON.stringify(item));
  check(item.priority === 'HIGH' && item.category === 'LAYOUT', 'v2-create-with-priority-and-label', JSON.stringify([item.priority, item.category]));
  await r.screenshot({ path: path.join(OUT, 's1-reviewer.png') });

  const designer = await login('designer@pinpoint.test');
  await designer.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}&a=${item.id}`);
  await designer.waitForSelector(`[data-annotation="${item.id}"]`);
  check((await designer.textContent('.root-comment .thread-body')).includes('<b>12px</b>'), 's1-comment-shown-as-text');
  await designer.fill('textarea[aria-label="Trả lời"]', 'E2E: đã nhận, sẽ sửa ở bản sau.');
  await designer.click('button:has-text("Gửi trả lời")');
  await designer.waitForSelector('.reply:has-text("E2E: đã nhận")');
  // Ghi chú xử lý + cảm xúc + giao người phụ trách
  await designer.click('[data-action=edit-note]');
  await designer.fill('textarea[aria-label="Ghi chú xử lý"]', 'E2E: bo góc 12 px, kiểm tra lại trên điện thoại');
  await designer.click('button:has-text("Lưu ghi chú")');
  await designer.waitForSelector('.note-text:has-text("E2E: bo góc 12 px")');
  check(true, 'v2-processing-note-saved');
  await designer.click('.root-comment .react-picker summary');
  await designer.click('.root-comment .react-picker .menu button >> nth=0');
  await designer.waitForSelector('.root-comment .reaction.is-mine');
  check(true, 'v2-reaction-on-comment');
  await r.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}&a=${item.id}`);
  await r.waitForSelector('.reply:has-text("E2E: đã nhận")');
  check(true, 's1-reviewer-sees-designer-reply');

  // Người chỉ xem: không có công cụ, bấm lên ảnh không tạo gì.
  const viewer = await login('viewer@pinpoint.test');
  await viewer.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}`);
  await viewer.waitForSelector('.mark-pin');
  check(await viewer.$('button[data-tool=point]') === null, 's1-viewer-has-no-tools');
  const vb = await viewer.$eval('.annotator-image', (e) => { const b = e.getBoundingClientRect(); return { x: b.left + b.width / 2, y: b.top + b.height / 2 }; });
  await viewer.mouse.click(vb.x, vb.y);
  check(await viewer.$('textarea[aria-label="Nội dung nhận xét"]') === null, 's1-viewer-click-creates-nothing');

  // ---------- Xung đột khi sửa cùng nhận xét ở hai tab
  const tab2 = await r.context().newPage();
  await tab2.goto(r.url());
  await tab2.waitForSelector('[data-action=edit]');
  await r.click('.root-comment [data-action=edit]');
  await tab2.click('.root-comment [data-action=edit]');
  await tab2.fill('textarea[aria-label="Sửa nội dung"]', 'E2E: bản sửa ở tab 2');
  await tab2.click('button:has-text("Lưu")');
  await tab2.waitForSelector('.root-comment .thread-body:has-text("tab 2")');
  await r.fill('textarea[aria-label="Sửa nội dung"]', 'E2E: bản sửa ở tab 1 (đã cũ)');
  await r.click('.root-comment button[type=submit]');
  await r.waitForSelector('[data-conflict]');
  check((await r.textContent('[data-conflict]')).includes('tab 2'), 's1-stale-edit-shows-conflict');
  await r.screenshot({ path: path.join(OUT, 'conflict.png') });

  // ---------- Tình huống 2: tải bản mới → so sánh → xác nhận/mở lại → xuất danh sách
  await designer.goto(`${BASE}/screen.php?id=${item.screen_id}`);
  await designer.click('[data-open=dlg-version]');
  await designer.setInputFiles('input[name=image]', path.join(FIXTURES, 'a-trang-chu-v2.png'));
  await designer.fill('input[name=note]', 'E2E: chuyển nút Đặt ngay sang trái');
  await Promise.all([designer.waitForURL('**/compare.php**'), designer.click('form[action="/version_new.php"] button[type=submit]')]);
  // Mở bản cũ chứa góp ý vừa tạo (v1) cạnh bản mới.
  await designer.goto(`${designer.url()}&left=${item.image_version_id}`);
  await designer.waitForSelector('#stage-left .mark-pin');
  const leftPins = await designer.$$eval('#stage-left .mark-pin', (p) => p.length);
  const rightPins = await designer.$$eval('#stage-right .mark-pin', (p) => p.length);
  check(leftPins >= 5 && rightPins === 0, 's2-old-marks-only-on-old-version', `left=${leftPins} right=${rightPins}`);
  const newNo = Number((await designer.textContent('button[data-side=right]')).match(/v(\d+)/)[1]);
  await designer.click(`.ann-list li[data-id="${item.id}"] > button`);
  await designer.fill('input[aria-label="Ghi chú khi đổi trạng thái"]', 'E2E: đã kiểm tra trên bản mới');
  await designer.click('button[data-action=status]');
  await designer.waitForSelector('.badge[data-status=RESOLVED]');
  check((await designer.textContent('.history')).includes(`kiểm tra trên v${newNo}`), 's2-resolve-records-checked-version');
  await designer.screenshot({ path: path.join(OUT, 's2-compare.png') });
  await designer.click('button[data-action=status]');
  await designer.waitForSelector('.badge[data-status=OPEN]');
  const statusLines = await designer.$$eval('.history li', (l) => l.map((x) => x.textContent).filter((t) => t.includes('→')));
  check(statusLines.length === 2 && statusLines[1].includes('Đã xử lý → Chưa xử lý'), 's2-reopen-history-recorded', JSON.stringify(statusLines));
  // So sánh dạng thanh trượt và chồng mờ
  await designer.click('[data-mode=swipe]');
  await designer.waitForSelector('.swipe-handle', { state: 'attached' });
  check(await designer.$eval('.swipe-handle', (h) => !h.hidden && h.style.left === '50%'), 'v2-compare-swipe-handle-at-50');
  const overlayImgs = await designer.$$eval('#stage-right .annotator-image', (l) => l.length);
  check(overlayImgs === 2, 'v2-compare-swipe-overlays-old-version', String(overlayImgs));
  await designer.click('[data-mode=onion]');
  const op = await designer.$eval('.overlay-left', (e) => e.style.opacity);
  check(op === '0.5', 'v2-compare-onion-opacity', op);
  await designer.click('[data-mode=side]');

  const download = designer.waitForEvent('download');
  await designer.evaluate(() => { const a = document.createElement('a'); a.href = document.querySelector('a[href^="/export.php"]').href + '&status=OPEN'; document.body.appendChild(a); a.click(); });
  const file = await download;
  const csvPath = path.join(OUT, 'export.csv');
  await file.saveAs(csvPath);
  const csv = (await import('node:fs')).readFileSync(csvPath, 'utf8');
  check(csv.includes('E2E: bản sửa ở tab 2') && csv.includes('Chưa xử lý') && csv.includes('X (px ảnh gốc)'), 's2-export-has-content-position-status');

  // Lọc trạng thái phía trình duyệt + giao diện tối
  await r.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}`);
  await r.waitForSelector('.ann-list li');
  await r.click('.segmented [data-status=RESOLVED]');
  const notResolved = await r.$$eval('.ann-list li', (l) => l.filter((x) => !x.classList.contains('is-resolved')).length);
  check(notResolved === 0, 'v2-filter-resolved-only', String(notResolved));
  await r.click('[data-theme-toggle]');
  check(await r.evaluate(() => document.documentElement.dataset.theme) === 'dark', 'v2-dark-theme-toggle');
  await r.click('[data-theme-toggle]');
} catch (e) {
  failed++;
  console.log('FAIL e2e: ' + e.message);
} finally {
  check(pageErrors.length === 0, 'no-javascript-errors', pageErrors.join(' | '));
  await browser.close();
}
console.log(`TESTS=${tests} FAILED=${failed}`);
process.exit(failed === 0 ? 0 : 1);
