# Dữ liệu mẫu và đáp án

Nạp bằng `bash lab.sh setup` (hoặc nạp lại sạch bằng `bash lab.sh reset`). Nguồn: `Ma_nguon/database/seed-data.php`,
ảnh trong `Ma_nguon/database/fixtures/` (nhóm tự vẽ bằng `tools/make_fixtures.py`, phát hành CC0).

## Tài khoản (mật khẩu chung `Pinpoint@123`)

| Ký hiệu | Email | Vai trò người dùng | Dự án A – FoodNow | Dự án B – Cổng sinh viên |
|---|---|---|---|---|
| D | designer@pinpoint.test | Người thiết kế | ADMIN (quản lý, người tạo) | ADMIN (quản lý, người tạo) |
| R | reviewer@pinpoint.test | Giảng viên / người duyệt | COMMENT (góp ý) | COMMENT (góp ý) |
| V | viewer@pinpoint.test | Thành viên chỉ xem | VIEW (chỉ xem) | **không thuộc dự án** |
| S | hotro@pinpoint.test | **Bộ phận hỗ trợ** (`is_support`, bản 2.2) | không thuộc dự án | không thuộc dự án |
| Q | admin@pinpoint.test | **Quản trị hệ thống** (`is_admin`, bản 2.3) — xem mọi dự án ở menu Quản trị | không thuộc dự án | không thuộc dự án |

Yêu cầu hỗ trợ mẫu (bản 2.2): #1 của V (Tài khoản, **Đã trả lời** bởi S) và #2 của R (Góp ý tính năng, **Đang chờ**) — đăng nhập S,
mở menu **Xử lý yêu cầu** để trả lời #2.

## Màn hình và phiên bản (2 dự án × 3 màn hình × 2 phiên bản = 12 ảnh)

| Dự án | Màn hình | Kích thước | v1 | v2 (thay đổi so với v1) |
|---|---|---|---|---|
| A | Trang chủ | 1000×500 PNG | nút “Đặt ngay” tâm **(750; 150)** | nút chuyển sang tâm (160; 255), thêm khối “Ưu đãi 20%” |
| A | Thực đơn | 1200×800 PNG | nút “Thêm” 90×34 | nút “+ Thêm vào giỏ” rộng 228 px |
| A | Giỏ hàng | 1200×800 JPEG | nút Thanh toán màu xám | nút màu cam, thêm ô mã giảm giá |
| B | Đăng nhập | 1280×720 PNG | “Quên mật khẩu?” mờ | liên kết đậm, nút dời xuống |
| B | Lịch học | 1280×720 JPEG | chữ môn học 15 px | chữ 17 px |
| B | Hồ sơ | 900×1200 PNG | nút rộng 300 px | nút rộng 740 px |

## 20 góp ý (toạ độ theo pixel ảnh gốc)

Tổng: **11 điểm, 9 vùng chữ nhật; 13 chưa xử lý, 7 đã xử lý**. Mọi góp ý do R tạo; trạng thái do D/R đổi.

| STT | Dự án | Màn hình | Bản | Số | Vị trí | Trạng thái cuối | Lịch sử đổi trạng thái | Trả lời | Nội dung |
|---|---|---|---|---|---|---|---|---|---|
| 1 | A | Trang chủ | v1 | #1 | Điểm (750; 150) | Đã xử lý | Đã xử lý (v2) | 1 | Nút “Đặt ngay” nên đưa sang trái, ngay dưới tiêu đề để người dùng thấy trước. |
| 2 | A | Trang chủ | v1 | #2 | Vùng (60; 110) 420×100 | Chưa xử lý | — | 0 | Tiêu đề và dòng mô tả hơi sát nhau, tăng khoảng cách lên khoảng 12 px. |
| 3 | A | Trang chủ | v1 | #3 | Điểm (190; 402) | Chưa xử lý | — | 0 | Giá tiền nên đậm hơn để dễ so sánh giữa các món. |
| 4 | A | Trang chủ | v1 | #4 | Vùng (60; 320) 860×150 | Chưa xử lý | Đã xử lý (v2) → Mở lại (v2) | 2 | Ba thẻ món chưa có nút thêm nhanh vào giỏ. |
| 5 | A | Trang chủ | v2 | #1 | Điểm (160; 255) | Chưa xử lý | — | 0 | Nút đã đúng vị trí mới nhưng chữ hơi nhỏ. |
| 6 | A | Trang chủ | v2 | #2 | Vùng (600; 90) 340×160 | Đã xử lý | Đã xử lý (v2) | 0 | Khối ưu đãi mới: dùng màu cam nhạt hơn để không lấn át nút chính. |
| 7 | A | Thực đơn | v1 | #1 | Vùng (40; 145) 580×40 | Chưa xử lý | — | 0 | Các tab lọc cần trạng thái đang chọn rõ hơn. |
| 8 | A | Thực đơn | v1 | #2 | Điểm (245; 430) | Đã xử lý | Đã xử lý (v2) | 1 | Nút “Thêm” quá nhỏ, khó bấm trên điện thoại. |
| 9 | A | Thực đơn | v1 | #3 | Điểm (56; 365) | Chưa xử lý | — | 0 | Giá chưa thẳng hàng với tên món. |
| 10 | A | Thực đơn | v2 | #1 | Vùng (56; 413) 228×38 | Chưa xử lý | — | 0 | Nút mới ổn. Cân nhắc thêm biểu tượng giỏ hàng. |
| 11 | A | Giỏ hàng | v1 | #1 | Điểm (980; 420) | Đã xử lý | Đã xử lý (v2) | 0 | Nút Thanh toán màu xám trông như đang bị vô hiệu hoá. |
| 12 | A | Giỏ hàng | v1 | #2 | Vùng (40; 100) 720×340 | Chưa xử lý | — | 1 | Danh sách món nên có nút xoá từng món. |
| 13 | A | Giỏ hàng | v1 | #3 | Điểm (650; 150) | Chưa xử lý | — | 0 | Nút tăng/giảm số lượng cần lớn hơn. |
| 14 | A | Giỏ hàng | v2 | #1 | Vùng (830; 345) 300×40 | Chưa xử lý | — | 0 | Ô mã giảm giá thiếu nút “Áp dụng”. |
| 15 | B | Đăng nhập | v1 | #1 | Điểm (800; 452) | Đã xử lý | Đã xử lý (v2) | 0 | Liên kết “Quên mật khẩu?” quá mờ. |
| 16 | B | Đăng nhập | v1 | #2 | Vùng (740; 250) 400×165 | Chưa xử lý | — | 0 | Thiếu dấu * cho hai ô bắt buộc. |
| 17 | B | Đăng nhập | v2 | #1 | Điểm (940; 510) | Chưa xử lý | — | 0 | Nút đăng nhập cần trạng thái “Đang xử lý…”. |
| 18 | B | Lịch học | v1 | #1 | Vùng (125; 135) 175×100 | Đã xử lý | Đã xử lý (v2) | 0 | Chữ trong ô môn học nhỏ, khó đọc. |
| 19 | B | Lịch học | v1 | #2 | Điểm (45; 465) | Chưa xử lý | — | 0 | Nên ghi rõ giờ bắt đầu của từng ca. |
| 20 | B | Hồ sơ | v1 | #1 | Điểm (450; 1060) | Đã xử lý | Đã xử lý (v2) | 0 | Nút “Cập nhật hồ sơ” nên rộng bằng các thẻ thông tin. |

## Thuộc tính bản 2.0 (ưu tiên, nhãn, người phụ trách, hạn, ghi chú)

Hạn được tính **tương đối theo ngày nạp dữ liệu**, nên sau mỗi lần `bash lab.sh reset` luôn có đúng các trường hợp quá hạn để trình diễn.

| STT | Màn hình (bản, số) | Ưu tiên | Nhãn | Phụ trách | Hạn | Ghi chú xử lý / cảm xúc |
|---|---|---|---|---|---|---|
| 1 | A Trang chủ v1 #1 | Cao | Bố cục | D | hôm nay −3 (đã xử lý nên không tính quá hạn) | ghi chú “Chuyển nút sang cột trái…”; D 👍 |
| 2 | A Trang chủ v1 #2 | Trung bình | Chữ & phông | D | +3 | |
| 3 | A Trang chủ v1 #3 | Thấp | Chữ & phông | — | — | |
| 4 | A Trang chủ v1 #4 | Cao | Trải nghiệm | D | **−1 → quá hạn** | ghi chú “Thống nhất với màn Thực đơn…”; D 👀 |
| 5 | A Trang chủ v2 #1 | Trung bình | Chữ & phông | D | +5 | |
| 6 | A Trang chủ v2 #2 | Trung bình | Màu sắc | D | — | |
| 7 | A Thực đơn v1 #1 | Trung bình | Trải nghiệm | D | +2 | |
| 8 | A Thực đơn v1 #2 | **Khẩn cấp** | Trợ năng | D | — | ghi chú “Vùng chạm tối thiểu 44×44 px…”; R 🎉 |
| 9 | A Thực đơn v1 #3 | Thấp | Bố cục | — | — | |
| 10 | A Thực đơn v2 #1 | Thấp | Nội dung | — | — | |
| 11 | A Giỏ hàng v1 #1 | Cao | Màu sắc | D | — | |
| 12 | A Giỏ hàng v1 #2 | Trung bình | Trải nghiệm | D | +4 | |
| 13 | A Giỏ hàng v1 #3 | Cao | Trợ năng | D | **−2 → quá hạn** | ghi chú “Đề xuất nút 40 px…” |
| 14 | A Giỏ hàng v2 #1 | Trung bình | Trải nghiệm | D | — | |
| 15 | B Đăng nhập v1 #1 | Trung bình | Trợ năng | D | — | |
| 16 | B Đăng nhập v1 #2 | Cao | Nội dung | D | +1 | |
| 17 | B Đăng nhập v2 #1 | Trung bình | Trải nghiệm | D | +6 | |
| 18 | B Lịch học v1 #1 | Cao | Chữ & phông | D | — | |
| 19 | B Lịch học v1 #2 | Thấp | Nội dung | — | — | |
| 20 | B Hồ sơ v1 #1 | Trung bình | Bố cục | D | — | |

Đáp án nhanh dự án A: 14 góp ý, 10 chưa xử lý, **2 quá hạn**, 2 ưu tiên cao/khẩn đang mở, 3 chưa giao người; “Việc của tôi” của D có 9 mục.

## Đáp án cho các phép thử bắt buộc

| Phép thử | Dữ liệu | Kết quả đúng | Kiểm thử tự động |
|---|---|---|---|
| 1. Ảnh 1000×500 hiển thị 500×250 | A · Trang chủ · v1 · #1 = (750; 150) | Dấu ở (375; 75) so với góc ảnh. Khung 500×400 (lề trên/dưới 75 px): (375; 150). Khung 800×300 (lề trái 100 px): (550; 90). Phóng to 200%: (500; 25). Bấm vào lề → bị từ chối | `tests/run.php`, `tests/js/coords.test.mjs`, `tests/integration.php` (t1-*), `tests/e2e/browser.mjs`, trang `/calibration.php` |
| 2. Lưu rồi mở lại vùng | Vùng (123.45; 67.8) 200×100.25 trên A · Trang chủ · v1 | Đọc lại bằng kết nối khác: đúng toạ độ, kích thước, nội dung (kể cả `<b>` và dấu nháy), số thứ tự #5 | `tests/integration.php` (t2-*) |
| 3. Phiên bản có nút chuyển vị trí | Tải v3 cho A · Trang chủ | v3 không có dấu nào; 5 dấu vẫn ở v1 với toạ độ cũ; danh sách v1–v3 và lịch sử vẫn đọc được; tệp ảnh cũ còn | `tests/integration.php` (t3-*), e2e `s2-*` |
| 4. Quyền | V (VIEW ở A, không thuộc B) | V không tạo/sửa/trả lời/đổi trạng thái (403); V mở ảnh, nhận xét, màn hình, CSV của B bằng URL → 404 | `tests/integration.php` (t4-*), `tests/http.php` |
| 5. Đồng thời và lịch sử | Góp ý A · Trang chủ · v1 · #2 | Hai người trả lời cùng lúc → đủ 2 trả lời; sửa từ bản cũ → CONFLICT, không ghi đè; đóng rồi mở lại → lịch sử: Tạo (v1) → Đã xử lý (v3) → Mở lại (v3) | `tests/integration.php` (t5-*), e2e `s1-stale-edit-shows-conflict` |
