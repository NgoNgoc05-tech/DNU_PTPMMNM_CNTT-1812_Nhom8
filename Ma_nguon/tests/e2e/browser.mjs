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

  // Bản 2.1: xoá phiên bản tải nhầm (bản vừa tải ở tình huống 2)
  await designer.goto(`${BASE}/screen.php?id=${item.screen_id}`);
  const pillsBefore = await designer.$$eval('.version-pills a', (l) => l.length);
  await designer.click('summary[aria-label="Thêm thao tác"]');
  await designer.click('[data-open=dlg-del-version]');
  await Promise.all([designer.waitForNavigation(), designer.click('#dlg-del-version button[type=submit]')]);
  const pillsAfter = await designer.$$eval('.version-pills a', (l) => l.length);
  check(pillsAfter === pillsBefore - 1, 'v21-delete-wrong-version', `${pillsBefore}->${pillsAfter}`);

  // Bản 2.2: hạn xử lý nhập theo tháng/ngày/năm + lịch tiếng Việt
  await designer.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}&a=${item.id}`);
  await designer.waitForSelector('.props .date-field input');
  const saved = designer.waitForResponse((res) => res.url().includes('/api.php') && res.request().method() === 'POST');
  await designer.fill('.props .date-field input', '11/05/2030');
  await designer.press('.props .date-field input', 'Enter');
  const dueItem = (await (await saved).json()).item;
  check(dueItem.due_date === '2030-11-05', 'v22-due-date-mm-dd-yyyy', String(dueItem.due_date));
  await designer.waitForSelector('.props .date-field input');
  check(await designer.inputValue('.props .date-field input') === '11/05/2030', 'v22-due-date-shown-mm-dd-yyyy');
  await designer.click('.props .date-field .date-btn');
  await designer.waitForSelector('.cal-pop');
  const calTitle = await designer.textContent('.cal-title');
  const calWd = await designer.$$eval('.cal-wd', (l) => l.map((x) => x.textContent).join(' '));
  check(calTitle === 'Tháng 11, 2030' && calWd === 'T2 T3 T4 T5 T6 T7 CN', 'v22-vietnamese-calendar', `${calTitle} | ${calWd}`);
  check(await designer.$('input[type=date]') === null, 'v22-no-native-english-picker');
  const saved2 = designer.waitForResponse((res) => res.url().includes('/api.php') && res.request().method() === 'POST');
  await designer.click('.cal-day[data-iso="2030-11-20"]');
  const dueItem2 = (await (await saved2).json()).item;
  await designer.waitForFunction(() => document.querySelector('.props .date-field input')?.value === '11/20/2030');
  check(dueItem2.due_date === '2030-11-20' && await designer.$('.cal-pop') === null, 'v22-calendar-pick-saves', String(dueItem2.due_date));
  await designer.screenshot({ path: path.join(OUT, 'v22-due.png') });

  // Bản 2.1: @nhắc tên -> người được nhắc thấy thông báo ở chuông
  await r.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}&a=${item.id}`);
  await r.waitForSelector('textarea[aria-label="Trả lời"]');
  await r.fill('textarea[aria-label="Trả lời"]', 'E2E: @Nguyễn Thu Trang kiểm tra giúp');
  await r.click('button:has-text("Gửi trả lời")');
  await r.waitForSelector('.reply:has-text("E2E: @Nguyễn")');
  await designer.goto(`${BASE}/projects.php`);
  check(!(await designer.$eval('#notif-count', (n) => n.hidden)), 'v21-bell-shows-unread');
  await designer.click('#notif-menu summary');
  await designer.waitForSelector('.notif-item');
  check((await designer.textContent('#notif-items')).includes('nhắc tới bạn'), 'v21-mention-notification-listed');
  await designer.keyboard.press('Escape');

  // Bản 2.1: trợ lý ảo trên trang dự án
  await designer.click('#chat-open');
  await designer.fill('#chat-input', 'Làm sao xoá ảnh tải nhầm?');
  await designer.click('#chat-form button[type=submit]');
  await designer.waitForSelector('.from-bot:has-text("Xoá phiên bản đang xem")');
  check(true, 'v21-assistant-answers');

  // Bản 2.2: lời mời phải được người nhận chấp nhận
  const projName = 'E2E lời mời ' + Date.now();
  await designer.goto(`${BASE}/projects.php`);
  await designer.click('.page-head [data-open=dlg-new-project]');
  await designer.fill('#dlg-new-project #name', projName);
  await Promise.all([designer.waitForNavigation(), designer.click('#dlg-new-project button[type=submit]')]);
  const newProjectUrl = designer.url();
  await designer.fill('#memail', 'viewer@pinpoint.test');
  await designer.selectOption('#mrole', 'COMMENT');
  await designer.fill('input[name=message]', 'Nhờ bạn góp ý giúp');
  await Promise.all([designer.waitForNavigation(), designer.click('form[action="/members.php"].mt button[type=submit]')]);
  check((await designer.textContent('.pending-list')).includes('Phạm Gia Huy'), 'v22-invite-pending-for-manager');
  await viewer.goto(`${BASE}/projects.php`);
  check(!(await viewer.textContent('.grid')).includes(projName), 'v22-invitee-not-member-before-accept');
  const card = viewer.locator(`.invite-card:has-text("${projName}")`);
  check(await card.count() === 1 && (await card.textContent()).includes('Nhờ bạn góp ý giúp'), 'v22-invitee-sees-invitation');
  await viewer.goto(newProjectUrl);
  check((await viewer.textContent('main')).includes('Không tìm thấy'), 'v22-invitee-cannot-open-before-accept');
  await viewer.goto(`${BASE}/projects.php`);
  await Promise.all([viewer.waitForNavigation(), viewer.locator(`.invite-card:has-text("${projName}") button:has-text("Chấp nhận")`).click()]);
  check(viewer.url() === newProjectUrl && (await viewer.textContent('h1')).includes(projName), 'v22-accept-opens-project');
  await designer.reload();
  check((await designer.$$('.pending-list li')).length === 0 && (await designer.textContent('.member-list')).includes('Phạm Gia Huy'), 'v22-accepted-becomes-member');

  // Bản 2.2: yêu cầu hỗ trợ được bộ phận hỗ trợ xử lý
  const subject = 'E2E hỗ trợ ' + Date.now();
  await r.goto(`${BASE}/help.php`);
  await r.selectOption('#tcat', 'BUG');
  await r.fill('#tsub', subject);
  await r.fill('#tmsg', 'Ảnh tải lên bị mờ');
  await Promise.all([r.waitForNavigation(), r.click('#new-ticket button[type=submit]')]);
  check((await r.textContent('#tickets')).includes(subject), 'v22-ticket-created');
  const staff = await login('hotro@pinpoint.test');
  check(!(await staff.$eval('#notif-count', (n) => n.hidden)), 'v22-support-notified');
  await staff.goto(`${BASE}/support_admin.php`);
  const tk = staff.locator(`.ticket-admin:has-text("${subject}")`);
  check(await tk.count() === 1, 'v22-support-desk-lists-ticket');
  await tk.locator('textarea').fill('Chào bạn, hãy tải ảnh PNG gốc độ phân giải cao.');
  await Promise.all([staff.waitForNavigation(), tk.locator('button[type=submit]').click()]);
  await staff.screenshot({ path: path.join(OUT, 'v22-support-desk.png'), fullPage: false });
  await r.goto(`${BASE}/help.php`);
  const mine = r.locator(`#tickets li:has-text("${subject}")`);
  check((await mine.textContent()).includes('Đã trả lời') && (await mine.textContent()).includes('ảnh PNG gốc'), 'v22-user-sees-reply');
  check(!(await r.$eval('#notif-count', (n) => n.hidden)), 'v22-user-notified-of-reply');
  const denied = await viewer.goto(`${BASE}/support_admin.php`);
  check(denied.status() === 403, 'v22-support-desk-forbidden-for-normal-user', String(denied.status()));

  // ================= Bản 2.3 =================
  // Mũi tên và nét vẽ tay trên trình xem (toạ độ theo pixel ảnh gốc)
  await r.goto(`${BASE}/screen.php?id=${item.screen_id}&v=${item.image_version_id}`);
  await r.waitForSelector('.mark-pin');
  await r.click('[data-toggle=hideMarks]');
  const box2 = await r.$eval('.annotator-image', (n) => { const b = n.getBoundingClientRect(); return { x: b.left, y: b.top, w: b.width }; });
  const k2 = box2.w / 1000;
  await r.click('button[data-tool=arrow]');
  await r.mouse.move(box2.x + 100 * k2, box2.y + 400 * k2);
  await r.mouse.down();
  await r.mouse.move(box2.x + 300 * k2, box2.y + 450 * k2, { steps: 8 });
  await r.mouse.up();
  await r.waitForSelector('.composer-pop');
  await r.fill('textarea[aria-label="Nội dung nhận xét"]', 'E2E: mũi tên — dời nút tới đây');
  let resp = r.waitForResponse((res) => res.url().includes('/api.php') && res.request().method() === 'POST');
  await r.click('button:has-text("Lưu góp ý")');
  const arrowItem = (await (await resp).json()).item;
  const ap = arrowItem.points || [];
  check(arrowItem.shape === 'ARROW' && ap.length === 2 && Math.abs(ap[0][0] - 100) <= 1.5 && Math.abs(ap[0][1] - 400) <= 1.5
    && Math.abs(ap[1][0] - 300) <= 1.5 && Math.abs(ap[1][1] - 450) <= 1.5, 'v23-arrow-saved-in-original-pixels', JSON.stringify(ap));
  await r.click('button[data-tool=draw]');
  await r.mouse.move(box2.x + 600 * k2, box2.y + 380 * k2);
  await r.mouse.down();
  for (let i = 1; i <= 12; i++) { await r.mouse.move(box2.x + (600 + i * 25) * k2, box2.y + (380 + (i % 2 ? 30 : -10)) * k2, { steps: 2 }); }
  await r.mouse.up();
  await r.waitForSelector('.composer-pop');
  await r.fill('textarea[aria-label="Nội dung nhận xét"]', 'E2E: vẽ tay — khoanh cụm này');
  resp = r.waitForResponse((res) => res.url().includes('/api.php') && res.request().method() === 'POST');
  await r.click('button:has-text("Lưu góp ý")');
  const drawItem = (await (await resp).json()).item;
  check(drawItem.shape === 'DRAW' && drawItem.points.length >= 3 && drawItem.points.length <= 500, 'v23-freehand-saved', String(drawItem.points && drawItem.points.length));
  await r.click('[data-toggle=hideMarks]');
  await r.waitForSelector(`.mark-stroke[data-stroke="${arrowItem.id}"] path[marker-end]`);
  check(await r.$(`.mark-stroke[data-stroke="${drawItem.id}"] .stroke-line`) !== null, 'v23-strokes-rendered');
  await r.click(`.mark-stroke[data-stroke="${arrowItem.id}"] .stroke-hit`, { force: true });
  await r.waitForSelector('.detail-head, textarea[aria-label="Trả lời"]');
  check((await r.textContent('#panel-body')).includes('Mũi tên ('), 'v23-click-stroke-opens-thread');
  await r.screenshot({ path: path.join(OUT, 'v23-draw.png') });

  // Ảnh đính kèm trong trả lời: chọn tệp + dán ảnh chụp (Ctrl+V)
  await r.setInputFiles('.composer input[type=file]', path.join(FIXTURES, 'a-trang-chu-v2.png'));
  await r.waitForSelector('.attach-chip img');
  await r.evaluate(async () => {
    const c = document.createElement('canvas'); c.width = 40; c.height = 30;
    const g = c.getContext('2d'); g.fillStyle = '#4f46e5'; g.fillRect(0, 0, 40, 30);
    const png = await new Promise((ok) => c.toBlob(ok, 'image/png'));
    const dt = new DataTransfer();
    dt.items.add(new File([png], '', { type: 'image/png' }));
    document.querySelector('textarea[aria-label="Trả lời"]').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  check((await r.$$('.attach-chip')).length === 2, 'v23-paste-screenshot-adds-attachment');
  await r.fill('textarea[aria-label="Trả lời"]', 'E2E: ảnh chụp chỗ cần sửa');
  await r.click('button:has-text("Gửi trả lời")');
  await r.waitForSelector('.reply .c-attach img');
  await r.waitForFunction(() => [...document.querySelectorAll('.reply .c-attach img')].every((i) => i.complete && i.naturalWidth > 0));
  check((await r.$$('.reply .c-attach')).length === 2, 'v23-attachments-shown-in-reply');
  await r.screenshot({ path: path.join(OUT, 'v23-attach.png') });

  // Thông báo tức thời: designer đang mở trang khác vẫn thấy thẻ nổi khi bị @nhắc tên
  await designer.goto(`${BASE}/projects.php`);
  await r.fill('textarea[aria-label="Trả lời"]', 'E2E: @Nguyễn Thu Trang xem mũi tên giúp');
  await r.click('button:has-text("Gửi trả lời")');
  await r.waitForSelector('.reply:has-text("xem mũi tên giúp")');
  await designer.evaluate(() => window.pinpointPoll());
  await designer.waitForSelector('.live-card', { timeout: 10000 });
  check((await designer.textContent('.live-card')).includes('nhắc tới bạn'), 'v23-live-notification-card');
  check(!(await designer.$eval('#notif-count', (n) => n.hidden)), 'v23-bell-updates-without-reload');
  await designer.waitForTimeout(400);
  await designer.screenshot({ path: path.join(OUT, 'v23-live.png') });

  // Tô đỏ chỗ khác nhau giữa v1 và v2
  await designer.goto(`${BASE}/compare.php?screen=${item.screen_id}&mode=diff`);
  await designer.waitForFunction(() => /vùng thay đổi/.test(document.getElementById('diff-summary').textContent), null, { timeout: 15000 });
  const diff = await designer.evaluate(() => { const d = window.pinpointDiff(); return { n: d.regions.length, pct: d.percent, first: d.regions[0] }; });
  check(diff.n >= 2 && diff.pct > 0 && diff.pct < 60, 'v23-diff-finds-changed-regions', JSON.stringify(diff));
  // nút "Đặt ngay" ở v1 tâm (750;150) đã dời đi: phải có vùng thay đổi phủ điểm này
  const covers = await designer.evaluate(() => window.pinpointDiff().regions.some((g) => g.x <= 750 && g.x + g.w >= 750 && g.y <= 150 && g.y + g.h >= 150));
  check(covers, 'v23-diff-covers-moved-button');
  await designer.click('.diff-chip[data-region="1"]');
  check(await designer.evaluate(() => window.pinpointViews.right.state.zoom > 1), 'v23-diff-region-zooms-in');
  await designer.click('.diff-chip.diff-all');
  await designer.screenshot({ path: path.join(OUT, 'v23-diff.png') });

  // Quên mật khẩu → bộ phận hỗ trợ cấp link 30 phút → đặt mật khẩu mới
  const anonCtx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const anon = await anonCtx.newPage();
  anon.on('pageerror', (e) => pageErrors.push(`anon: ${e.message}`));
  await anon.goto(`${BASE}/login.php`);
  await Promise.all([anon.waitForNavigation(), anon.click('a:has-text("Quên mật khẩu?")')]);
  await anon.fill('#email', 'viewer@pinpoint.test');
  await anon.fill('#contact', 'Zalo E2E 0900');
  await Promise.all([anon.waitForNavigation(), anon.click('button[type=submit]')]);
  check(await anon.$('[data-forgot-sent]') !== null, 'v23-forgot-request-accepted');
  const staff2 = await login('hotro@pinpoint.test');
  await staff2.goto(`${BASE}/support_admin.php?status=OPEN`);
  const resetTicket = staff2.locator('.ticket-admin:has-text("Zalo E2E 0900")').first();
  await Promise.all([staff2.waitForNavigation(), resetTicket.locator('button:has-text("Cấp link")').click()]);
  const link = await staff2.inputValue('input.issued-link');
  check(/\/reset_password\.php\?token=[a-f0-9]{48}$/.test(link), 'v23-support-issues-reset-link', link);
  await staff2.screenshot({ path: path.join(OUT, 'v23-reset-link.png') });
  await anon.goto(link);
  await anon.fill('#password', 'Pinpoint@123');
  await anon.fill('#password2', 'Pinpoint@123');
  await Promise.all([anon.waitForNavigation(), anon.click('button[type=submit]')]);
  check(await anon.$('[data-reset-done]') !== null, 'v23-password-reset-via-link');
  await anon.goto(link);
  check(await anon.$('[data-reset-invalid]') !== null, 'v23-reset-link-single-use');

  // Quản trị hệ thống: thống kê, khoá tài khoản → người bị khoá mất phiên ngay → mở khoá
  const adm = await login('admin@pinpoint.test');
  await adm.goto(`${BASE}/admin.php`);
  check((await adm.$$('.stat')).length === 8 && await adm.$('svg.bar-chart rect') !== null, 'v23-admin-overview');
  await adm.screenshot({ path: path.join(OUT, 'v23-admin.png') });
  await adm.goto(`${BASE}/admin.php?tab=users`);
  const vrow = adm.locator('tr[data-user="viewer@pinpoint.test"]');
  await vrow.locator('summary').click();
  await vrow.locator('input[name=reason]').fill('E2E: thử khoá');
  await Promise.all([adm.waitForNavigation(), vrow.locator('button:has-text("Khoá tài khoản")').click()]);
  check(await adm.$('tr.is-locked[data-user="viewer@pinpoint.test"]') !== null, 'v23-admin-locks-user');
  await viewer.goto(`${BASE}/projects.php`);
  check(viewer.url().includes('/login.php'), 'v23-locked-user-logged-out', viewer.url());
  await viewer.fill('#email', 'viewer@pinpoint.test');
  await viewer.fill('#password', 'Pinpoint@123');
  await Promise.all([viewer.waitForNavigation(), viewer.click('button[type=submit]')]);
  check((await viewer.textContent('.flash.error')).includes('bị khoá'), 'v23-locked-login-message');
  await adm.screenshot({ path: path.join(OUT, 'v23-admin-users.png') });
  await vrow.locator('summary').click();
  await Promise.all([adm.waitForNavigation(), vrow.locator('button:has-text("Mở khoá")').click()]);
  check(await adm.$('tr.is-locked[data-user="viewer@pinpoint.test"]') === null, 'v23-admin-unlocks-user');
  await adm.goto(`${BASE}/admin.php?tab=projects`);
  check((await adm.$$('#admin-projects tbody tr')).length >= 2, 'v23-admin-sees-all-projects');
  await anonCtx.close();

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
