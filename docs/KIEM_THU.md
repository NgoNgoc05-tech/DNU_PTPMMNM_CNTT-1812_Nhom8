# Kiểm thử và sửa lỗi

## 1. Cách chạy

| Lệnh | Bộ kiểm thử | Cần | Số ca |
|---|---|---|---|
| `php tests/run.php` | Đơn vị: quyền, toạ độ, mũi tên/vẽ tay, escape, CSV, giới hạn ảnh, hiển thị dùng chung, trợ lý ảo | PHP | 66 |
| `node --test tests/js/coords.test.mjs tests/js/drawing.test.mjs` | Hàm toạ độ JavaScript; so điểm ảnh “Tô chỗ khác”; rút gọn nét vẽ tay | Node ≥ 18 | 10 |
| `php tests/integration.php` | Năm phép thử bắt buộc + thuộc tính, ghi chú, cảm xúc, nhật ký (2.0) + thông báo, xoá, hồ sơ, hỗ trợ (2.1) + lời mời, bộ phận hỗ trợ (2.2) + mũi tên/vẽ tay, ảnh đính kèm, thông báo tức thời, quên mật khẩu, quản trị (2.3) trên PostgreSQL thật | PostgreSQL | 160 |
| `php tests/http.php` | Qua HTTP: quyền ở máy chủ, CSRF, URL chéo dự án, XSS, tải tệp giả, API bản 2.0, lời mời, trang bộ phận hỗ trợ, ảnh đính kèm, quên mật khẩu, quản trị | PostgreSQL | 59 |
| `node tests/e2e/browser.mjs` | Trình duyệt thật: 2 tình huống, phép thử 1, xung đột, ô soạn nổi, ghi chú, cảm xúc, so sánh trượt/chồng mờ, giao diện tối, lịch hạn xử lý, lời mời, bộ phận hỗ trợ, mũi tên/vẽ tay, dán ảnh chụp, thông báo tức thời, tô chỗ khác, quên mật khẩu, quản trị | Máy chủ đang chạy + Playwright | 68 |

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
| 10 | Trang phép thử toạ độ báo “KHÔNG ĐẠT” trên máy thành viên (lệch 0,4 px) | Thành viên chạy thử trên Windows phóng to 125% | Trang đo tâm dấu bằng `getBoundingClientRect`, giá trị bị làm tròn tới pixel thiết bị; dấu thực ra vẫn đúng (đổi ngược ≈ 749,2; 149,2) | So sánh bằng toạ độ CSS mà trình xem đặt cho dấu (`style.left/top`); vị trí thật trên màn hình vẫn được e2e kiểm tra với sai số ≤ 1 px | e2e `t1-calibration-page-4-cases`, `t1-viewer-*` |
| 11 | Mời thành viên thêm ngay người đó vào dự án, không cần họ đồng ý | Thành viên nhóm dùng thử (bản 2.1) | `members.php` gọi thẳng `INSERT INTO project_members` | Thêm bảng `project_invitations`; mời tạo lời mời PENDING, chỉ khi người nhận bấm Chấp nhận mới thêm vào dự án | `v22-invitee-not-member-yet`, `v22-other-user-cannot-accept`, `http-invite-does-not-add-member`, e2e `v22-invitee-cannot-open-before-accept` |
| 12 | Yêu cầu hỗ trợ đứng mãi ở “Đang chờ”, không ai xử lý trong ứng dụng | Thành viên nhóm dùng thử (bản 2.1) | Chỉ trả lời được bằng `php tools/support.php` | Thêm quyền `users.is_support`, trang `/support_admin.php`, thông báo TICKET / TICKET_REPLY | `v22-new-ticket-notifies-support`, `v22-reply-notifies-user-with-handler`, e2e `v22-user-sees-reply` |
| 13 | Lưu thông báo loại `INVITE_REPLY` báo “value too long for type character varying(10)” | `tests/integration.php` (bản 2.2) | Cột `notifications.kind` là `varchar(10)` | `ALTER COLUMN kind TYPE varchar(20)` trong `schema.sql` (chạy lại được trên CSDL cũ) | Reset rồi chạy lại: 116/116 đạt |
| 14 | Lịch chọn ngày hiện tiếng Anh (October 2026, Su Mo…) | Thành viên dùng Chrome tiếng Anh | Dùng `input type=date` gốc của trình duyệt | Tự vẽ lịch tiếng Việt trong `thread.js`, bỏ ô ngày gốc | e2e `v22-vietnamese-calendar`, `v22-no-native-english-picker` |
| 15 | Nút “Tải phiên bản mới” biến mất khỏi trình xem của Quản lý dự án | e2e (bản 2.3, lỗi `Timeout waiting for [data-open=dlg-version]`) | `templates/header.php` thêm biến `$isAdmin` (quản trị hệ thống) trùng tên với `$isAdmin` (quản lý dự án) của `screen.php`, ghi đè giá trị trước khi phần thân trang dùng tới | Đổi tên biến trong header thành `$isSysAdmin` | e2e 68/68, chạy lại 2 lần liên tiếp đều đạt |
| 16 | Có thể lưu mũi tên không có toạ độ nét (`points` NULL) bằng SQL trực tiếp | `tests/integration.php` (`v23-db-constraint-arrow-needs-points`) | `jsonb_typeof(NULL)` cho NULL, ràng buộc CHECK coi NULL là đạt | Thêm `points IS NOT NULL` vào `annotations_geometry_check` | Reset rồi chạy lại: 160/160 |
| 17 | Bấm “Cấp link đặt lại mật khẩu” xong không thấy link | e2e (bản 2.3) | Yêu cầu chuyển sang “Đã trả lời” nên biến mất khỏi bộ lọc “Đang chờ” đang mở | Sau khi cấp link chuyển sang mục “Tất cả” và cuộn tới đúng yêu cầu | e2e `v23-support-issues-reset-link` |

## 4. Kết quả lần chạy cuối (bản 2.3, 28/10/2026)

Tất cả đều PASS. Danh sách dưới đây sinh từ đầu ra của từng bộ kiểm thử.

### Đơn vị — `tests/run.php` (TESTS=66 FAILED=0)

**Phân quyền (ma trận VIEW / COMMENT / ADMIN)**: `view-can-view`, `view-cannot-comment`, `comment-can-comment`, `comment-cannot-manage`, `admin-can-comment`, `non-member-denied`, `unknown-role-denied`

**Phép thử 1: ảnh 1000×500, điểm mẫu (750; 150)**: `display-500x250-scale`, `display-500x250-x`, `display-500x250-y`, `letterbox-top-margin`, `letterbox-point-y`, `letterbox-back-x`, `letterbox-back-y`, `letterbox-inside`, `click-on-margin-rejected`, `zoom-200-x`, `zoom-200-y`

**Kiểm tra toạ độ ở máy chủ**: `point-valid`, `point-outside-x`, `point-negative`, `point-not-number`, `rect-rounded-2-decimals`, `rect-overflow-right`, `rect-too-small`, `shape-invalid`

**Nội dung nhận xét là dữ liệu, không phải mã**: `html-escaped`, `body-trimmed`, `body-empty`, `body-too-long`, `csv-formula-neutralized`, `csv-normal-text`, `csv-quotes-escaped`, `csv-columns`

**Bản 2.0: hiển thị dùng chung (ảnh đại diện, nhật ký, cột xuất)**: `initials-vietnamese-name`, `initials-single-word`, `avatar-escaped-and-colored`, `event-text-escaped`, `export-22-columns`, `priority-levels`

**Bản 2.1: trợ lý ảo (chạy trên máy, không cần mạng)**: `assistant-fold-vietnamese`, `assistant-delete-image`, `assistant-compare`, `assistant-invite`, `assistant-unknown-question`, `assistant-whole-word-match`, `assistant-question-length`, `mention-name-without-role`

**Bản 2.3: mũi tên và nét vẽ tay (toạ độ ảnh gốc, kiểm tra ở máy chủ)**: `arrow-valid-first-point-is-anchor`, `arrow-needs-exactly-2-points`, `arrow-end-outside-image`, `arrow-too-short`, `draw-valid`, `draw-max-500-points`, `draw-point-must-be-pair`, `draw-points-must-be-list`, `shape-label-arrow`

**Giới hạn ảnh tải lên (chỉ PNG/JPEG, ≤ 5 MB, 16–8000 px)**: `png-accepted`, `jpeg-accepted`, `gif-rejected`, `fake-png-rejected`, `over-5mb-rejected`, `tiny-rejected`, `stored-name-path-traversal`, `non-uploaded-path-rejected`

### JavaScript — `tests/js/coords.test.mjs`, `tests/js/drawing.test.mjs` (pass 10, fail 0)

fitContain, toScreen, toImage (kể cả lề trống), rectFromPoints, zoomAround giữ điểm dưới con trỏ, trường hợp phóng to 200%; bản 2.3: hai ảnh giống nhau không có vùng thay đổi, nút dời chỗ cho đúng hai vùng (chỗ cũ và chỗ mới), bỏ nhiễu lẻ tẻ và khác biệt dưới ngưỡng, rút gọn nét vẽ tay giữ hai đầu.

### Tích hợp PostgreSQL — `tests/integration.php` (TESTS=160 FAILED=0)

**Dữ liệu mẫu có đáp án**: `seed-20-annotations`, `seed-11-points`, `seed-9-rects`, `seed-13-open`, `seed-12-image-versions`, `seed-3-roles`

**Phép thử 1: điểm (750;150) trên ảnh 1000×500 hiển thị 500×250, có lề và phóng to**: `t1-image-1000x500`, `t1-sample-stored-in-original-pixels`, `t1-screen-500x250-zoom1`, `t1-screen-500x400-zoom1`, `t1-screen-800x300-zoom1`, `t1-screen-500x250-zoom2`, `t1-margin-click-rejected-by-server`

**Phép thử 2: lưu rồi mở lại vùng chữ nhật**: `t2-rect-position-size`, `t2-body-literal`, `t2-bound-to-version`, `t2-next-seq-number`, `t2-rect-overflow-rejected`

**Phép thử 3: phiên bản mới có nút chuyển vị trí**: `t3-version-number-3`, `t3-new-version-has-no-old-marks`, `t3-old-marks-stay-on-v1`, `t3-old-mark-keeps-old-coordinates`, `t3-all-versions-listed`, `t3-old-image-file-kept`, `t3-history-still-readable`

**Phép thử 4: quyền và truy cập chéo dự án**: `t4-viewer-cannot-create-or-edit`, `t4-viewer-can-view-A`, `t4-member-A-cannot-read-image-B`, `t4-member-A-cannot-read-comment-B`, `t4-commenter-cannot-manage`, `t4-cannot-edit-others-comment`, `t4-owner-stays-admin`

**Phép thử 5: hai người trả lời, sửa từ bản cũ, đóng rồi mở lại**: `t5-two-concurrent-replies-kept`, `t5-first-edit-saved`, `t5-stale-edit-reports-conflict`, `t5-conflict-returns-current-text`, `t5-no-silent-overwrite`, `t5-edit-history-kept`, `t5-stale-status-reports-conflict`, `t5-reopened`, `t5-close-reopen-history`, `t5-checked-version-same-screen`

**Bản 2.0: thuộc tính, ghi chú xử lý, cảm xúc, nhật ký hoạt động**: `v2-stats-total-open-overdue`, `v2-seed-notes-project-A`, `v2-create-with-meta`, `v2-assignee-must-be-member`, `v2-priority-whitelist`, `v2-due-date-validated`, `v2-note-length-limit`, `v2-note-saved`, `v2-stale-note-conflict`, `v2-status-shares-lock-with-note`, `v2-note-not-overwritten`, `v2-update-clear-assignee-and-due`, `v2-events-logged-in-order`, `v2-two-people-react`, `v2-reaction-toggle-off`, `v2-reaction-whitelist`, `v2-filter-priority`, `v2-filter-overdue`, `v2-search-matches-note`, `v2-filter-unassigned`, `v2-sort-priority`, `v2-my-tasks-overdue`, `v2-project-activity-newest-first`, `v2-resolved-on-version`, `v2-export-new-columns`

**Bản 2.1: thông báo, xoá ảnh tải nhầm, hồ sơ, hỗ trợ khách hàng**: `v21-mention-notifies-designer`, `v21-mention-notifies-viewer-member`, `v21-no-self-notification`, `v21-assign-notifies`, `v21-reply-notifies-author`, `v21-status-notifies-author`, `v21-mark-all-read`, `v21-cannot-mark-others-notification`, `v21-delete-wrong-version-only`, `v21-cannot-delete-last-version`, `v21-delete-screen`, `v21-deleted-screen-gone`, `v21-only-owner-deletes-project`, `v21-delete-project-needs-name`, `v21-project-deleted`, `v21-profile-title`, `v21-password-needs-current`, `v21-password-repeat`, `v21-password-changed`, `v21-ticket-validated`, `v21-ticket-answered-and-private`, `v21-cannot-close-others-ticket`

**Bản 2.2: bộ phận hỗ trợ xử lý yêu cầu**: `v22-support-role`, `v22-new-ticket-notifies-support`, `v22-support-sees-all-open`, `v22-reply-notifies-user-with-handler`, `v22-status-only-change-no-duplicate-notice`, `v22-answer-missing-ticket`

**Bản 2.2: lời mời tham gia dự án phải được chấp nhận**: `v22-invite-created`, `v22-invitee-not-member-yet`, `v22-pending-listed-for-manager`, `v22-invitee-sees-invitation`, `v22-invite-notification`, `v22-reinvite-updates-pending`, `v22-no-duplicate-pending`, `v22-other-user-cannot-accept`, `v22-accept`, `v22-member-after-accept-with-latest-role`, `v22-inviter-notified`, `v22-cannot-answer-twice`, `v22-decline-not-member`, `v22-cancelled-invite-unusable`, `v22-invite-existing-member-updates-role`

**Bản 2.3: mũi tên, vẽ tay, ảnh đính kèm**: `v23-arrow-saved-in-original-pixels`, `v23-draw-saved`, `v23-arrow-outside-rejected-by-server`, `v23-db-constraint-arrow-needs-points`, `v23-export-shape-label`, `v23-attachment-added`, `v23-only-author-attaches`, `v23-attachment-listed-with-reply`, `v23-attachment-belongs-to-project`, `v23-max-4-attachments`, `v23-attachment-files-tracked`

**Bản 2.3: thông báo tức thời**: `v23-poll-returns-only-new`, `v23-poll-nothing-new`

**Bản 2.3: quên mật khẩu**: `v23-forgot-unknown-email-ignored`, `v23-forgot-creates-ticket`, `v23-reset-ticket-has-contact`, `v23-repeat-request-no-duplicate-ticket`, `v23-admin-is-support-staff`, `v23-token-valid`, `v23-token-stored-hashed-only`, `v23-new-link-invalidates-old`, `v23-reset-repeat-must-match`, `v23-reset-password`, `v23-new-password-works`, `v23-link-single-use`, `v23-reset-closes-ticket`, `v23-link-expires-after-30-min`, `v23-malformed-token`

**Bản 2.3: quản trị hệ thống**: `v23-admin-role`, `v23-system-stats`, `v23-daily-14-days`, `v23-search-users`, `v23-cannot-lock-self`, `v23-locked-cannot-login`, `v23-locked-cannot-reset-password`, `v23-filter-locked`, `v23-unlocked-can-login`, `v23-cannot-drop-own-admin`, `v23-grant-support`, `v23-admin-reset-link`, `v23-admin-joins-project-as-manager`, `v23-admin-delete-needs-name`, `v23-admin-deleted-project`, `v23-admin-actions-logged`

**Tìm kiếm và xuất danh sách**: `search-open-only`, `search-by-text`, `search-percent-is-literal`, `filter-by-author`, `export-header`, `export-row-count`

### HTTP — `tests/http.php` (TESTS=59 FAILED=0)

`http-anonymous-api-401`, `http-anonymous-image-denied`, `http-viewer-reads-image-A`, `http-viewer-cannot-read-image-B-by-url`, `http-viewer-cannot-read-comments-B-by-url`, `http-viewer-cannot-open-screen-B`, `http-viewer-cannot-export-B`, `http-viewer-cannot-create`, `http-viewer-cannot-reply`, `http-viewer-cannot-change-status`, `http-missing-csrf-rejected`, `http-comment-stored-literally`, `http-comment-rendered-as-text`, `http-csp-header`, `http-outside-image-422`, `http-export-csv`

**Bản 2.0: thuộc tính, ghi chú, cảm xúc, nhật ký qua HTTP**: `http-viewer-cannot-edit-note`, `http-viewer-cannot-react`, `http-viewer-cannot-read-activity-B`, `http-reviewer-updates-priority-and-note`, `http-stale-update-conflict-409`, `http-reviewer-reacts`, `http-invalid-reaction-422`, `http-activity-newest-first`, `http-reviewer-cannot-rename-project`, `http-inbox-page`

**Bản 2.1: xoá, thông báo, trợ lý ảo, hỗ trợ qua HTTP**: `http-reviewer-cannot-delete-version`, `http-outsider-cannot-delete-screen-B`, `http-delete-needs-csrf`, `http-notifications-api`, `http-mark-all-read`, `http-chat-assistant`, `http-chat-requires-login`, `http-support-ticket-escaped`, `http-profile-page`

**Bản 2.2: lời mời và bộ phận hỗ trợ qua HTTP**: `http-invite-does-not-add-member`, `http-other-user-cannot-accept-invite`, `http-accept-needs-csrf`, `http-invitee-accepts`, `http-support-desk-forbidden`, `http-support-desk-lists-escaped`, `http-support-reply-visible-to-user`, `http-fake-image-upload-rejected`

**Bản 2.3: vẽ, ảnh đính kèm, thông báo tức thời, quên mật khẩu, quản trị qua HTTP**: `http-create-arrow`, `http-draw-one-point-rejected`, `http-upload-attachment`, `http-fake-attachment-rejected`, `http-cannot-attach-to-others-comment`, `http-member-reads-attachment`, `http-outsider-cannot-read-attachment`, `http-notif-poll`, `http-forgot-same-answer-for-any-email`, `http-reset-bad-token`, `http-reset-password-via-link`, `http-admin-forbidden-for-user`, `http-admin-lists-users`, `http-locked-user-session-ends`, `http-admin-action-needs-csrf`, `http-admin-projects-tab`, `http-fake-image-upload-rejected`

**Tải tệp giả**: `http-fake-image-upload-rejected`

### Trình duyệt — `tests/e2e/browser.mjs` (TESTS=68 FAILED=0)

`t1-calibration-page-4-cases`, `t1-viewer-1440x900`, `t1-viewer-1100x650`, `t1-viewer-900x1000`, `t1-viewer-zoomed`, `s1-margin-click-ignored`, `v2-popover-composer-next-to-mark`, `s1-rect-saved-in-original-pixels`, `v2-create-with-priority-and-label`, `s1-comment-shown-as-text`, `v2-processing-note-saved`, `v2-reaction-on-comment`, `s1-reviewer-sees-designer-reply`, `s1-viewer-has-no-tools`, `s1-viewer-click-creates-nothing`, `s1-stale-edit-shows-conflict`, `s2-old-marks-only-on-old-version`, `s2-resolve-records-checked-version`, `s2-reopen-history-recorded`, `v2-compare-swipe-handle-at-50`, `v2-compare-swipe-overlays-old-version`, `v2-compare-onion-opacity`, `s2-export-has-content-position-status`, `v21-delete-wrong-version`, `v22-due-date-mm-dd-yyyy`, `v22-due-date-shown-mm-dd-yyyy`, `v22-vietnamese-calendar`, `v22-no-native-english-picker`, `v22-calendar-pick-saves`, `v21-bell-shows-unread`, `v21-mention-notification-listed`, `v21-assistant-answers`, `v22-invite-pending-for-manager`, `v22-invitee-not-member-before-accept`, `v22-invitee-sees-invitation`, `v22-invitee-cannot-open-before-accept`, `v22-accept-opens-project`, `v22-accepted-becomes-member`, `v22-ticket-created`, `v22-support-notified`, `v22-support-desk-lists-ticket`, `v22-user-sees-reply`, `v22-user-notified-of-reply`, `v22-support-desk-forbidden-for-normal-user`, `v23-arrow-saved-in-original-pixels`, `v23-freehand-saved`, `v23-strokes-rendered`, `v23-click-stroke-opens-thread`, `v23-paste-screenshot-adds-attachment`, `v23-attachments-shown-in-reply`, `v23-live-notification-card`, `v23-bell-updates-without-reload`, `v23-diff-finds-changed-regions`, `v23-diff-covers-moved-button`, `v23-diff-region-zooms-in`, `v23-forgot-request-accepted`, `v23-support-issues-reset-link`, `v23-password-reset-via-link`, `v23-reset-link-single-use`, `v23-admin-overview`, `v23-admin-locks-user`, `v23-locked-user-logged-out`, `v23-locked-login-message`, `v23-admin-unlocks-user`, `v23-admin-sees-all-projects`, `v2-filter-resolved-only`, `v2-dark-theme-toggle`, `no-javascript-errors`

