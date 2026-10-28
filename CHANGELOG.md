# Changelog

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/); đánh số theo [Semantic Versioning](https://semver.org/lang/vi/).

## [Unreleased]

### Dự kiến
- Gửi email mời thành viên chưa có tài khoản (hiện lời mời chỉ hiện trong ứng dụng).
- Phân trang trang Tìm góp ý khi dự án có hàng nghìn góp ý.
- Thông báo thời gian thực khi có trả lời mới.

## [2.3.0] - 2026-10-28

### Thêm
- **Công cụ vẽ**: **Mũi tên** (phím `A`, kéo từ đuôi tới đầu) và **Vẽ tay** (phím `D`, tối đa 500 điểm, tự rút gọn nét) bên cạnh Điểm và Vùng.
  Toạ độ vẫn theo pixel ảnh gốc, máy chủ kiểm tra mọi điểm nằm trong ảnh (`Coordinates::validateStroke`), CSDL lưu `annotations.points` (jsonb)
  với ràng buộc CHECK theo từng kiểu dấu. Bấm vào nét để mở góp ý; danh sách, Tìm góp ý và CSV hiện đúng kiểu dấu.
- **Ảnh đính kèm trong trả lời**: nút 📎 Ảnh, kéo thả, hoặc chụp màn hình rồi **Ctrl+V** trong ô trả lời (PNG/JPEG ≤ 5 MB, tối đa 4 ảnh).
  Ảnh lưu ngoài thư mục public, phát qua `/attachment.php` sau khi kiểm tra người xem thuộc dự án; chỉ tác giả được đính kèm.
- **Tô chỗ khác** (trang So sánh, phím `4`): ứng dụng tự so từng điểm ảnh giữa hai phiên bản, tô đỏ và đánh số các vùng thay đổi,
  báo % diện tích thay đổi; bấm số để phóng tới vùng; chỉnh Độ nhạy, bật/tắt làm mờ phần giống nhau. Chạy hoàn toàn trên trình duyệt (`diff.js`).
- **Thông báo tức thời**: chuông tự cập nhật mỗi 8 giây (dừng khi tab ẩn), hiện thẻ nổi “… nhắc tới bạn” ở góc màn hình;
  trình xem tự tải lại góp ý khi có trả lời mới trên đúng phiên bản đang mở (`/api.php?action=notif_poll`).
- **Quên mật khẩu**: liên kết “Quên mật khẩu?” ở trang đăng nhập. Link đặt lại `/reset_password.php?token=…` dùng một lần, hết hạn sau
  **30 phút**, CSDL chỉ lưu SHA-256 của mã. Có `PINPOINT_MAIL_FROM` thì gửi thư; không có thì tạo yêu cầu hỗ trợ “Quên mật khẩu” để
  bộ phận hỗ trợ / quản trị viên bấm **Cấp link** và gửi cho người dùng. Trang luôn trả lời giống nhau (không lộ email nào đã đăng ký).
- **Quản trị hệ thống** (`/admin.php`, tài khoản mẫu `admin@pinpoint.test` / `Pinpoint@123`): thống kê toàn hệ thống và biểu đồ 14 ngày;
  quản lý người dùng (tìm, lọc, **khoá / mở khoá** — người bị khoá mất phiên ngay, cấp/bỏ quyền quản trị và bộ phận hỗ trợ, cấp link đặt lại mật khẩu);
  quản lý mọi dự án (mở để can thiệp với quyền Quản lý, xoá dự án); **nhật ký quản trị** ghi mọi thao tác. Quản trị viên cũng vào được trang Xử lý yêu cầu.
- `php tools/admin.php list | grant | revoke | unlock | reset-link <email>`.

### Thay đổi
- `bash lab.sh setup` trên CSDL đã có dữ liệu tự tạo `admin@pinpoint.test` nếu chưa có quản trị viên (hãy đổi mật khẩu, hoặc dùng `tools/admin.php grant`).
- Trang đăng nhập có thêm thẻ tài khoản mẫu Bộ phận hỗ trợ và Quản trị hệ thống.

### Bảo mật
- Tài khoản bị khoá không đăng nhập, không lấy được link đặt lại mật khẩu; phiên đang mở bị huỷ ở yêu cầu tiếp theo.
- Không tự khoá / tự bỏ quyền quản trị của chính mình; hệ thống luôn giữ ít nhất một quản trị viên.

## [2.2.0] - 2026-10-24

### Thêm
- **Lời mời phải được chấp nhận**: Quản lý dự án gửi lời mời (kèm lời nhắn); người được mời nhận thông báo, thấy lời mời ở đầu
  trang Dự án và bấm **Chấp nhận** hoặc **Từ chối**. Trước khi chấp nhận, người đó chưa là thành viên và không mở được dự án.
  Quản lý thấy mục “Đang chờ xác nhận” và có thể huỷ lời mời; người mời nhận thông báo khi lời mời được trả lời.
- **Bộ phận hỗ trợ**: tài khoản có quyền hỗ trợ (mẫu `hotro@pinpoint.test` / `Pinpoint@123`) có thêm menu **Xử lý yêu cầu**
  (`/support_admin.php`): xem mọi yêu cầu, lọc theo trạng thái, trả lời và đổi trạng thái. Nhân viên hỗ trợ nhận thông báo khi có
  yêu cầu mới; người gửi nhận thông báo khi có câu trả lời và thấy tên người trả lời.
- **Lịch chọn ngày tiếng Việt** của ứng dụng (Tháng 10, 2026 · T2…CN · Hôm nay · Xoá hạn), dùng được bằng bàn phím,
  thay cho lịch gốc tiếng Anh của trình duyệt.
- `php tools/support.php grant <email>` / `revoke <email>` để cấp hoặc thu hồi quyền hỗ trợ.

### Thay đổi
- **Hạn xử lý theo dạng tháng/ngày/năm (mm/dd/yyyy)** ở ô nhập, danh sách góp ý, trang Việc của tôi, Tìm góp ý, nhật ký hoạt động
  và tệp CSV (cột “Hạn xử lý (tháng/ngày/năm)”). Máy chủ vẫn lưu dạng `yyyy-mm-dd`.
- `bash lab.sh setup` trên CSDL đã có dữ liệu sẽ tạo tài khoản bộ phận hỗ trợ nếu chưa có (không đụng tới dữ liệu khác).

### Sửa
- Mời thành viên trước đây thêm ngay người được mời vào dự án mà không cần họ đồng ý.
- Yêu cầu hỗ trợ không có ai xử lý trong ứng dụng (chỉ trả lời được bằng dòng lệnh).

## [2.1.0] - 2026-10-24

### Thêm
- **Thông báo** (chuông trên đầu trang, trang `/notifications.php`): khi được @nhắc tên, được giao việc, có trả lời mới trong góp ý
  của mình hoặc mình phụ trách, góp ý của mình đổi trạng thái. Đánh dấu đã đọc từng cái hoặc tất cả.
- **Xoá ảnh tải nhầm**: xoá phiên bản đang xem (cùng góp ý của phiên bản đó), xoá màn hình, xoá dự án (chỉ người tạo, gõ lại tên để xác nhận).
- **Hồ sơ của tôi** (`/profile.php`): đổi họ tên, chức danh, đổi mật khẩu; thống kê hoạt động; dự án và quyền; thông báo gần đây.
- **Hỗ trợ khách hàng** (`/help.php`): câu hỏi thường gặp, gửi yêu cầu hỗ trợ, theo dõi “Yêu cầu của tôi”; bộ phận hỗ trợ trả lời bằng `php tools/support.php`.
- **Trợ lý ảo** (khung chat nổi + trang Hỗ trợ): trả lời câu hỏi về cách dùng ứng dụng bằng cơ sở tri thức chạy trên máy;
  tuỳ chọn dùng mô hình AI khi đặt biến môi trường `PINPOINT_AI_KEY`.
- Ô **hạn xử lý theo dạng ngày/tháng/năm** (dd/mm/yyyy), gõ trực tiếp hoặc chọn trên lịch, không phụ thuộc ngôn ngữ trình duyệt.
- `Dockerfile` và hướng dẫn triển khai lên Render.

### Thay đổi
- Bỏ mục “Phép thử toạ độ” khỏi thanh menu (trang vẫn ở `/calibration.php`, có liên kết trong trang Hỗ trợ).
- Menu tài khoản: Hồ sơ của tôi, Thông báo, Hỗ trợ khách hàng (bỏ hai mục trùng với thanh menu).

### Sửa
- Trang phép thử toạ độ báo “KHÔNG ĐẠT” khi Windows/Chrome phóng to 110–150% do đo bằng vị trí đã làm tròn tới pixel thiết bị;
  nay so sánh bằng toạ độ CSS mà trình xem đặt cho dấu.

## [2.0.0] - 2026-10-24

### Thêm
- **Thuộc tính góp ý:** mức ưu tiên (Thấp → Khẩn cấp), nhãn phân loại (Bố cục, Màu sắc, Chữ & phông, Nội dung, Trải nghiệm, Trợ năng),
  người phụ trách (phải là thành viên dự án), hạn xử lý và cảnh báo quá hạn.
- **Ghi chú xử lý** cho từng góp ý (≤ 4000 ký tự), có khoá lạc quan và báo xung đột giữ nguyên chữ đang gõ.
- **Cảm xúc** 👍 ❤️ 🎉 👀 trên nhận xét và trả lời; **@nhắc tên** thành viên có gợi ý.
- **Nhật ký hoạt động** (`annotation_events`) ghi mọi thay đổi; dòng thời gian trong từng góp ý, tab *Hoạt động*, trang dự án.
- Trình xem mới: thanh công cụ nổi, **ô soạn góp ý nổi cạnh dấu**, thẻ xem nhanh khi rê chuột, toạ độ con trỏ theo px ảnh gốc,
  ẩn dấu / ẩn đã xử lý, chuyển màn hình, nút phiên bản, phím tắt, thu gọn bảng góp ý.
- Bộ lọc trình xem: tìm nội dung/ghi chú/#số, trạng thái có đếm, người góp ý, ưu tiên, nhãn, “giao cho tôi”, sắp xếp.
- So sánh phiên bản thêm chế độ **thanh trượt** và **chồng mờ**.
- Trang dự án dạng bảng tổng quan: thẻ thống kê, tiến độ, ảnh đại diện thành viên, hoạt động gần đây; sửa thông tin dự án, đổi tên màn hình.
- Trang **Việc của tôi** (`/inbox.php`); tìm góp ý thêm lọc ưu tiên/nhãn/phụ trách/quá hạn, sắp xếp, in/PDF.
- Xuất CSV thêm 8 cột: ưu tiên, nhãn, người phụ trách, hạn, quá hạn, ghi chú xử lý, phiên bản kiểm tra sau sửa, bản mới nhất.
- Giao diện mới (hệ thiết kế, biểu tượng SVG, ảnh đại diện chữ viết tắt), **giao diện tối**, trang đăng nhập/đăng ký mới, hộp thoại tải ảnh kéo thả.
- Kiểm thử: đơn vị 48, tích hợp 73, HTTP 27, trình duyệt 26.

### Sửa
- `tools/reset.php` xoá cả bảng mới; quy tắc `[hidden]` chung; menu thả xuống căn phải; e2e chạy lặp ổn định.

## [1.0.0] - 2026-10-21

### Thêm
- Dự án, mời thành viên theo email, quyền VIEW / COMMENT / ADMIN kiểm tra ở máy chủ.
- Màn hình thiết kế, tải ảnh PNG/JPEG (≤ 5 MB, 16–8000 px), phiên bản v1, v2… giữ nguyên ảnh và góp ý cũ.
- Dấu điểm và vùng chữ nhật, đánh số theo phiên bản, toạ độ lưu theo pixel ảnh gốc.
- Nhận xét, trả lời, sửa nội dung của mình (lưu bản cũ), đã xử lý / mở lại có lịch sử và phiên bản kiểm tra.
- Lọc theo trạng thái và người góp ý; tìm góp ý trong dự án; xuất CSV.
- So sánh hai phiên bản đặt cạnh nhau, đồng bộ phóng to/kéo.
- Khoá lạc quan báo xung đột khi sửa từ bản cũ.
- Trang phép thử toạ độ `/calibration.php`.
- Dữ liệu mẫu có đáp án: 2 dự án, 3 tài khoản, 12 ảnh, 20 góp ý.
- Kiểm thử đơn vị, JS, tích hợp PostgreSQL, HTTP, trình duyệt; GitHub Actions trên PHP 8.1/8.3 + PostgreSQL 16.

### Sửa
- `status_history.checked_version_id` dùng `ON DELETE SET NULL` để xoá được dự án.
- Định dạng thời gian có múi giờ “+07” trong bảng góp ý.
- Chỉ nhận tệp tải lên thật trong `ImageStore::fromUpload`.

[Unreleased]: https://github.com/<tai-khoan>/pinpoint/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/<tai-khoan>/pinpoint/releases/tag/v1.0.0
