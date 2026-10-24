# Chính sách bảo mật

Báo lỗ hổng qua **Security → Report a vulnerability** của kho GitHub (báo riêng tư), không tạo Issue công khai.
Nhóm phản hồi trong 7 ngày. Phạm vi gồm vượt quyền (xem/sửa dữ liệu dự án khác), XSS, CSRF, tải tệp độc hại, SQL injection.

Các biện pháp hiện có: quyền kiểm tra ở máy chủ (`src/Access.php`), truy vấn tham số, escape đầu ra, CSP `script-src 'self'`,
token CSRF cho mọi POST, ảnh lưu ngoài thư mục public và kiểm tra loại theo nội dung, mật khẩu băm, cấu hình CSDL nằm ngoài kho mã (`.local/`).
