# Quản lý dự án trên GitHub

Gợi ý cách tổ chức kho mã để đáp ứng tiêu chí *tài liệu, issue và review* và *đóng góp cá nhân*. Nhóm tự tạo trên kho GitHub của mình.

## Milestones

| Milestone | Nội dung | Hạn gợi ý |
|---|---|---|
| M1 – Nền tảng | CSDL, đăng nhập, dự án, thành viên, tải ảnh | Tuần 2 |
| M2 – Góp ý | Trình xem, điểm/vùng, nhận xét, trả lời, trạng thái | Tuần 4 |
| M3 – Phiên bản | Tải bản mới, so sánh, lịch sử, tìm và xuất | Tuần 5 |
| v1.0.0 – Bàn giao | Kiểm thử, CI, tài liệu, báo cáo, bản phát hành | Tuần 6 |

## Issue gợi ý (mỗi issue một nhánh, một Pull Request)

| # | Tiêu đề | Nhãn | Milestone | Phụ trách | Người review |
|---|---|---|---|---|---|
| 1 | Thiết kế CSDL theo thực thể đề tài (`schema.sql`) | `database` | M1 | TV1 | TV3 |
| 2 | Đăng nhập, đăng ký, phiên, CSRF | `security` | M1 | TV1 | TV2 |
| 3 | Dự án, mời thành viên, quyền VIEW/COMMENT/ADMIN | `feature` | M1 | TV1 | TV3 |
| 4 | Tải ảnh PNG/JPEG an toàn, đặt tên màn hình | `feature` `security` | M1 | TV1 | TV2 |
| 5 | Hàm toạ độ màn hình ↔ ảnh gốc + kiểm thử JS | `feature` `test` | M2 | TV2 | TV1 |
| 6 | Trình xem: zoom, kéo, đặt điểm, khoanh vùng | `feature` | M2 | TV2 | TV3 |
| 7 | Nhận xét, trả lời, sửa của mình, trạng thái | `feature` | M2 | TV3 | TV2 |
| 8 | Khoá lạc quan: báo xung đột khi sửa từ bản cũ | `feature` `concurrency` | M2 | TV3 | TV1 |
| 9 | Tải phiên bản mới, so sánh hai bản cạnh nhau | `feature` | M3 | TV2 | TV1 |
| 10 | Lịch sử trạng thái, tìm góp ý, xuất CSV | `feature` | M3 | TV3 | TV2 |
| 11 | Dữ liệu mẫu có đáp án (2 dự án, 20 góp ý) | `test` `data` | M3 | TV1 | TV3 |
| 12 | Kiểm thử tích hợp 5 phép thử + kiểm thử HTTP | `test` | v1.0.0 | TV3 | TV1 |
| 13 | Kiểm thử trình duyệt + GitHub Actions | `test` `ci` | v1.0.0 | TV2 | TV3 |
| 14 | README, HUONG_DAN, NGUON_SU_DUNG, báo cáo | `docs` | v1.0.0 | Cả nhóm | Cả nhóm |
| 15 | Lỗi: không xoá được dự án có lịch sử kiểm tra (FK) | `bug` | v1.0.0 | TV3 | TV1 |

## Review

- Bật *Branch protection* cho `main` và `develop`: bắt buộc Pull Request, 1 lượt duyệt và CI đạt.
- Người review phải chạy lại `bash lab.sh test` trên máy mình, rồi ghi ít nhất một nhận xét theo dòng mã: lỗi, câu hỏi hoặc đề xuất.
- Mỗi thành viên commit bằng tài khoản GitHub của chính mình. Lịch sử commit và lượt review là bằng chứng cho phần *đóng góp cá nhân*.

## Milestone v2.0 — Hoàn thiện theo góp ý của giảng viên (“chức năng còn sơ sài, chưa phù hợp người dùng”)

| Issue | Nội dung | Phụ trách gợi ý | Nhãn |
|---|---|---|---|
| #16 | Thuộc tính góp ý: ưu tiên, nhãn, người phụ trách, hạn + migration `ADD COLUMN IF NOT EXISTS` | Thành viên 1 | `database`, `enhancement` |
| #17 | Ghi chú xử lý có khoá lạc quan, nhật ký hoạt động `annotation_events` | Thành viên 3 | `backend`, `concurrency` |
| #18 | Cảm xúc và @nhắc tên trong trao đổi | Thành viên 3 | `frontend` |
| #19 | Trình xem mới: thanh công cụ nổi, ô soạn nổi cạnh dấu, thẻ xem nhanh, phím tắt | Thành viên 2 | `frontend`, `ux` |
| #20 | So sánh thanh trượt / chồng mờ | Thành viên 2 | `frontend` |
| #21 | Bảng tổng quan dự án, Việc của tôi, lọc nâng cao, CSV 22 cột | Thành viên 1 | `feature` |
| #22 | Hệ thiết kế mới + giao diện tối | Thành viên 2 | `design` |
| #23 | Kiểm thử bản 2.0 (tích hợp, HTTP, e2e) và cập nhật tài liệu | Thành viên 3 | `test`, `docs` |

Quy trình giữ như milestone v1.0: mỗi issue một nhánh `feature/...`, Pull Request có người review, CI phải xanh trước khi gộp.
