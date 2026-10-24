# Kiểm thử và sửa lỗi

## 1. Cách chạy

| Lệnh | Bộ kiểm thử | Cần | Số ca |
|---|---|---|---|
| `php tests/run.php` | Đơn vị: quyền, toạ độ, escape, CSV, giới hạn ảnh, hiển thị dùng chung | PHP | 48 |
| `node --test tests/js/coords.test.mjs` | Hàm toạ độ JavaScript dùng trong trình duyệt | Node ≥ 18 | 6 |
| `php tests/integration.php` | Năm phép thử bắt buộc + thuộc tính, ghi chú, cảm xúc, nhật ký (bản 2.0) trên PostgreSQL thật | PostgreSQL | 73 |
| `php tests/http.php` | Qua HTTP: quyền ở máy chủ, CSRF, URL chéo dự án, XSS, tải tệp giả, API bản 2.0 | PostgreSQL | 27 |
| `node tests/e2e/browser.mjs` | Trình duyệt thật: 2 tình huống, phép thử 1, xung đột, ô soạn nổi, ghi chú, cảm xúc, so sánh trượt/chồng mờ, giao diện tối | Máy chủ đang chạy + Playwright | 26 |

`bash lab.sh test` chạy bốn bộ đầu và in `KET_QUA=TAT_CA_DAT` khi tất cả đều đạt. GitHub Actions (`.github/workflows/ci.yml`) chạy cả năm bộ
trên PHP 8.1 và 8.3 với PostgreSQL 16 mỗi lần push hoặc mở Pull Request.

## 2. Đối chiếu với các phép thử bắt buộc của đề tài

| Phép thử | Ca tự động |
|---|---|
| 1. Ảnh 1000×500 hiển thị 500×250; có lề trống; phóng to | `display-500x250-*`, `letterbox-*`, `zoom-200-*`, `click-on-margin-rejected` (đơn vị); 6 ca JS; `t1-*` (tích hợp); `t1-calibration-page-4-cases`, `t1-viewer-*` (e2e, đổi 3 kích thước cửa sổ và phóng to) |
| 2. Lưu rồi mở lại vùng chữ nhật | `t2-rect-position-size`, `t2-body-literal`, `t2-bound-to-version`, `t2-next-seq-number`, `t2-rect-overflow-rejected`; e2e `s1-rect-saved-in-original-pixels` |
| 3. Phiên bản có nút chuyển vị trí | `t3-new-version-has-no-old-marks`, `t3-old-marks-stay-on-v1`, `t3-old-mark-keeps-old-coordinates`, `t3-all-versions-listed`, `t3-old-image-file-kept`, `t3-history-still-readable`; e2e `s2-old-marks-only-on-old-version` |
| 4. Người chỉ xem; truy cập chéo dự án bằng URL | `t4-*`; `http-viewer-cannot-create/reply/change-status`, `http-viewer-cannot-read-image-B-by-url`, `http-viewer-cannot-read-comments-B-by-url`, `http-viewer-cannot-open-screen-B`, `http-viewer-cannot-export-B`; e2e `s1-viewer-*` |
| 5. Hai người trả lời; sửa từ bản cũ; đóng rồi mở lại | `t5-two-concurrent-replies-kept` (hai kết nối, hai giao dịch xen kẽ), `t5-stale-edit-reports-conflict`, `t5-no-silent-overwrite`, `t5-edit-history-kept`, `t5-stale-status-reports-conflict`, `t5-close-reopen-history`; e2e `s1-stale-edit-shows-conflict`, `s2-reopen-history-3-entries` |

## 3. Lỗi phát hiện khi kiểm thử, cách sửa và kiểm tra lại

| # | Lỗi | Phát hiện bởi | Nguyên nhân | Sửa | Kiểm tra lại |
|---|---|---|---|---|---|
| 1 | Không xoá được dự án có góp ý đã xác nhận trên phiên bản khác | `tests/integration.php` (bước dọn dữ liệu) | `status_history.checked_version_id` tham chiếu `image_versions` không có quy tắc xoá, nên CASCADE bị chặn | Đổi thành `REFERENCES image_versions(id) ON DELETE SET NULL` trong `schema.sql` | Chạy lại toàn bộ `bash lab.sh test`: 4 bộ đạt, dữ liệu thử được xoá sạch |
| 2 | Thời gian trong bảng góp ý hiện nguyên chuỗi “2026-10-21 09:05:44.55+07” | Kiểm tra giao diện | `new Date()` không đọc được múi giờ dạng “+07” của PostgreSQL | Đọc trực tiếp ngày/giờ bằng biểu thức chính quy trong `thread.js`; đặt `date_default_timezone_set('Asia/Ho_Chi_Minh')` | e2e `no-javascript-errors`; ảnh chụp trình xem |
| 3 | Lịch sử ghi “Chưa xử lý (kiểm tra trên v1)” cho lần tạo góp ý | Kiểm tra giao diện | Dòng lịch sử đầu tiên được hiển thị như một lần đổi trạng thái | Hiển thị “Tạo góp ý trên vN” khi `from_status` rỗng | `t5-close-reopen-history` |
| 4 | Kiểm thử JS báo lỗi sau khi thêm `package.json` | `bash lab.sh test` | `"type": "module"` làm `coords.js` bị nạp như ES module, nên `module.exports` không tồn tại | Bỏ `"type": "module"`; tệp e2e dùng đuôi `.mjs` | `node --test`: 6/6 đạt |
| 5 | `ImageStore::fromUpload` sẽ chép một đường dẫn bất kỳ nếu tệp không phải tệp vừa tải lên | Review mã (Pull Request) | Nhánh dự phòng `copy()` dành cho dữ liệu mẫu nằm chung trong hàm nhận tệp tải lên | Hàm nhận tải lên chỉ chấp nhận `is_uploaded_file()` + `move_uploaded_file()`; dữ liệu mẫu dùng hàm riêng `fromFile()` | Thêm ca `non-uploaded-path-rejected`; `http-fake-image-upload-rejected` và toàn bộ test chạy lại đạt |
| 6 | `bash lab.sh reset` để lại bảng nhật ký cũ, số sự kiện bị nhân đôi sau khi nạp lại dữ liệu | Truy vấn đếm `annotation_events` sau khi reset (bản 2.0) | `tools/reset.php` chỉ xoá các bảng của bản 1.0; `DROP ... CASCADE` gỡ khoá ngoại nhưng giữ lại bảng mới | Thêm `annotation_events`, `comment_reactions` vào danh sách xoá | Reset rồi đếm lại: mỗi loại sự kiện đúng số của dữ liệu mẫu |
| 7 | Khi mở chi tiết một góp ý, ô tìm kiếm và bộ lọc vẫn hiện | Ảnh chụp giao diện (bản 2.0) | Thuộc tính `hidden` bị quy tắc `display: grid` của `.panel-head` ghi đè | Thêm quy tắc chung `[hidden] { display: none !important; }` | Chụp lại trình xem; e2e `no-javascript-errors` |
| 8 | Chạy e2e lần thứ hai thất bại ở bước khoanh vùng | `bash lab.sh e2e` chạy lặp | Góc trên-trái vùng mới trùng dấu của lần chạy trước nên thao tác kéo chọn nhầm dấu cũ | Bài e2e bật nút “Ẩn mọi dấu” trước khi vẽ (dùng chính tính năng mới) | Chạy e2e hai lần liên tiếp: 26/26 đạt cả hai |
| 9 | Menu tài khoản và menu “Thêm thao tác” bị kéo dãn hết chiều ngang | Ảnh chụp giao diện (bản 2.0) | Hai quy tắc CSS cùng độ ưu tiên đặt cả `left` và `right` | Thêm lớp `menu-right` cho menu căn phải | Chụp lại các trang có menu |

## 4. Kết quả lần chạy cuối (bản 2.0, 24/10/2026)

Tất cả đều PASS. Danh sách dưới đây sinh từ đầu ra của từng bộ kiểm thử.

### Đơn vị — `tests/run.php` (TESTS=48 FAILED=0)

**Phân quyền (ma trận VIEW / COMMENT / ADMIN)**: `view-can-view`, `view-cannot-comment`, `comment-can-comment`, `comment-cannot-manage`, `admin-can-comment`, `non-member-denied`, `unknown-role-denied`

**Phép thử 1: ảnh 1000×500, điểm mẫu (750; 150)**: `display-500x250-scale`, `display-500x250-x`, `display-500x250-y`, `letterbox-top-margin`, `letterbox-point-y`, `letterbox-back-x`, `letterbox-back-y`, `letterbox-inside`, `click-on-margin-rejected`, `zoom-200-x`, `zoom-200-y`

**Kiểm tra toạ độ ở máy chủ**: `point-valid`, `point-outside-x`, `point-negative`, `point-not-number`, `rect-rounded-2-decimals`, `rect-overflow-right`, `rect-too-small`, `shape-invalid`

**Nội dung nhận xét là dữ liệu, không phải mã**: `html-escaped`, `body-trimmed`, `body-empty`, `body-too-long`, `csv-formula-neutralized`, `csv-normal-text`, `csv-quotes-escaped`, `csv-columns`

**Bản 2.0: hiển thị dùng chung (ảnh đại diện, nhật ký, cột xuất)**: `initials-vietnamese-name`, `initials-single-word`, `avatar-escaped-and-colored`, `event-text-escaped`, `export-22-columns`, `priority-levels`

**Giới hạn ảnh tải lên (chỉ PNG/JPEG, ≤ 5 MB, 16–8000 px)**: `png-accepted`, `jpeg-accepted`, `gif-rejected`, `fake-png-rejected`, `over-5mb-rejected`, `tiny-rejected`, `stored-name-path-traversal`, `non-uploaded-path-rejected`

### JavaScript — `tests/js/coords.test.mjs` (pass 6, fail 0)

fitContain, toScreen, toImage (kể cả lề trống), rectFromPoints, zoomAround giữ điểm dưới con trỏ, trường hợp phóng to 200%.

### Tích hợp PostgreSQL — `tests/integration.php` (TESTS=73 FAILED=0)

**Dữ liệu mẫu có đáp án**: `seed-20-annotations`, `seed-11-points`, `seed-9-rects`, `seed-13-open`, `seed-12-image-versions`, `seed-3-roles`

**Phép thử 1: điểm (750;150) trên ảnh 1000×500 hiển thị 500×250, có lề và phóng to**: `t1-image-1000x500`, `t1-sample-stored-in-original-pixels`, `t1-screen-500x250-zoom1`, `t1-screen-500x400-zoom1`, `t1-screen-800x300-zoom1`, `t1-screen-500x250-zoom2`, `t1-margin-click-rejected-by-server`

**Phép thử 2: lưu rồi mở lại vùng chữ nhật**: `t2-rect-position-size`, `t2-body-literal`, `t2-bound-to-version`, `t2-next-seq-number`, `t2-rect-overflow-rejected`

**Phép thử 3: phiên bản mới có nút chuyển vị trí**: `t3-version-number-3`, `t3-new-version-has-no-old-marks`, `t3-old-marks-stay-on-v1`, `t3-old-mark-keeps-old-coordinates`, `t3-all-versions-listed`, `t3-old-image-file-kept`, `t3-history-still-readable`

**Phép thử 4: quyền và truy cập chéo dự án**: `t4-viewer-cannot-create-or-edit`, `t4-viewer-can-view-A`, `t4-member-A-cannot-read-image-B`, `t4-member-A-cannot-read-comment-B`, `t4-commenter-cannot-manage`, `t4-cannot-edit-others-comment`, `t4-owner-stays-admin`

**Phép thử 5: hai người trả lời, sửa từ bản cũ, đóng rồi mở lại**: `t5-two-concurrent-replies-kept`, `t5-first-edit-saved`, `t5-stale-edit-reports-conflict`, `t5-conflict-returns-current-text`, `t5-no-silent-overwrite`, `t5-edit-history-kept`, `t5-stale-status-reports-conflict`, `t5-reopened`, `t5-close-reopen-history`, `t5-checked-version-same-screen`

**Bản 2.0: thuộc tính, ghi chú xử lý, cảm xúc, nhật ký hoạt động**: `v2-stats-total-open-overdue`, `v2-seed-notes-project-A`, `v2-create-with-meta`, `v2-assignee-must-be-member`, `v2-priority-whitelist`, `v2-due-date-validated`, `v2-note-length-limit`, `v2-note-saved`, `v2-stale-note-conflict`, `v2-status-shares-lock-with-note`, `v2-note-not-overwritten`, `v2-update-clear-assignee-and-due`, `v2-events-logged-in-order`, `v2-two-people-react`, `v2-reaction-toggle-off`, `v2-reaction-whitelist`, `v2-filter-priority`, `v2-filter-overdue`, `v2-search-matches-note`, `v2-filter-unassigned`, `v2-sort-priority`, `v2-my-tasks-overdue`, `v2-project-activity-newest-first`, `v2-resolved-on-version`, `v2-export-new-columns`

**Tìm kiếm và xuất danh sách**: `search-open-only`, `search-by-text`, `search-percent-is-literal`, `filter-by-author`, `export-header`, `export-row-count`

### HTTP — `tests/http.php` (TESTS=27 FAILED=0)

`http-anonymous-api-401`, `http-anonymous-image-denied`, `http-viewer-reads-image-A`, `http-viewer-cannot-read-image-B-by-url`, `http-viewer-cannot-read-comments-B-by-url`, `http-viewer-cannot-open-screen-B`, `http-viewer-cannot-export-B`, `http-viewer-cannot-create`, `http-viewer-cannot-reply`, `http-viewer-cannot-change-status`, `http-missing-csrf-rejected`, `http-comment-stored-literally`, `http-comment-rendered-as-text`, `http-csp-header`, `http-outside-image-422`, `http-export-csv`

**Bản 2.0: thuộc tính, ghi chú, cảm xúc, nhật ký qua HTTP**: `http-viewer-cannot-edit-note`, `http-viewer-cannot-react`, `http-viewer-cannot-read-activity-B`, `http-reviewer-updates-priority-and-note`, `http-stale-update-conflict-409`, `http-reviewer-reacts`, `http-invalid-reaction-422`, `http-activity-newest-first`, `http-reviewer-cannot-rename-project`, `http-inbox-page`, `http-fake-image-upload-rejected`

### Trình duyệt — `tests/e2e/browser.mjs` (TESTS=26 FAILED=0)

`t1-calibration-page-4-cases`, `t1-viewer-1440x900`, `t1-viewer-1100x650`, `t1-viewer-900x1000`, `t1-viewer-zoomed`, `s1-margin-click-ignored`, `v2-popover-composer-next-to-mark`, `s1-rect-saved-in-original-pixels`, `v2-create-with-priority-and-label`, `s1-comment-shown-as-text`, `v2-processing-note-saved`, `v2-reaction-on-comment`, `s1-reviewer-sees-designer-reply`, `s1-viewer-has-no-tools`, `s1-viewer-click-creates-nothing`, `s1-stale-edit-shows-conflict`, `s2-old-marks-only-on-old-version`, `s2-resolve-records-checked-version`, `s2-reopen-history-recorded`, `v2-compare-swipe-handle-at-50`, `v2-compare-swipe-overlays-old-version`, `v2-compare-onion-opacity`, `s2-export-has-content-position-status`, `v2-filter-resolved-only`, `v2-dark-theme-toggle`, `no-javascript-errors`

