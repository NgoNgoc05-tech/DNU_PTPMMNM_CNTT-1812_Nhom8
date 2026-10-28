# HƯỚNG DẪN CÀI ĐẶT, CHẠY VÀ TRÌNH DIỄN — PINPOINT

Mọi lệnh Bash chạy trong **Ubuntu/WSL** (không chạy trong PowerShell). Đường dẫn tính từ thư mục `Ma_nguon`.

## 1. Công cụ cần có

```bash
sudo apt update
sudo apt install php-cli php-pgsql php-gd php-mbstring git unzip postgresql postgresql-client
php -v                 # cần 8.1 trở lên
php -m | grep -E 'pgsql|gd|fileinfo|mbstring'
psql --version
```

Kiểm thử JavaScript cần **Node.js 18 trở lên**: `node -v`. Ubuntu 24.04 cài bằng `sudo apt install nodejs npm`. Ubuntu 22.04 có Node quá cũ,
hãy cài theo hướng dẫn trên nodejs.org. Ứng dụng web **không** cần Node; Node chỉ dùng cho kiểm thử.

## 2. Tạo CSDL riêng (làm một lần)

```bash
sudo service postgresql start
sudo -u postgres psql
```

```sql
SELECT rolname FROM pg_roles WHERE rolname = 'pinpoint_lab';
SELECT datname FROM pg_database WHERE datname = 'pinpoint_lab';
-- Nếu chưa có:
CREATE ROLE pinpoint_lab LOGIN;
\password pinpoint_lab
CREATE DATABASE pinpoint_lab OWNER pinpoint_lab;
\q
```

Không đưa mật khẩu vào tài liệu hay kho mã. Nếu tên đã được dùng, hãy đặt tên riêng cho nhóm và nhập đúng tên đó ở bước 3.

## 3. Cấu hình, tạo bảng và dữ liệu mẫu

```bash
cd Ma_nguon
bash lab.sh db-config   # Máy chủ 127.0.0.1, cổng 5432, CSDL/tài khoản pinpoint_lab, mật khẩu vừa đặt
bash lab.sh check       # cần thấy MOI_TRUONG=SAN_SANG
bash lab.sh setup       # SCHEMA=OK, SEED=OK | users=3 projects=2 versions=12 annotations=20
```

Cấu hình lưu trong `.local/db.json` (quyền 600, đã nằm trong `.gitignore`). `setup` không ghi đè dữ liệu nếu CSDL đã có tài khoản.
Muốn làm lại dữ liệu demo từ đầu, chạy `bash lab.sh reset` (phải gõ `XOA` để xác nhận).

**Nâng cấp từ bản 1.0:** chạy lại `bash lab.sh setup` để thêm các cột/bảng mới (dữ liệu cũ giữ nguyên). Muốn có đủ ưu tiên, hạn, ghi chú
và cảm xúc mẫu như tài liệu `docs/DU_LIEU_MAU.md` thì chạy `bash lab.sh reset`.

## 4. Chạy ứng dụng

```bash
bash lab.sh run         # http://127.0.0.1:8115 — giữ terminal mở, Ctrl+C để dừng
```

| Tài khoản | Mật khẩu | Quyền |
|---|---|---|
| designer@pinpoint.test | Pinpoint@123 | Quản lý ở cả hai dự án (người thiết kế) |
| reviewer@pinpoint.test | Pinpoint@123 | Góp ý ở cả hai dự án (giảng viên/người duyệt) |
| viewer@pinpoint.test | Pinpoint@123 | Chỉ xem dự án A, không thuộc dự án B |

Muốn hai người dùng cùng lúc, mở hai trình duyệt khác nhau hoặc dùng thêm một cửa sổ ẩn danh.

## 5. Kiểm thử

```bash
bash lab.sh test
```

| Phần | Tệp | Kết quả cần có |
|---|---|---|
| Đơn vị (không cần CSDL) | tests/run.php | `TESTS=56 FAILED=0` |
| JavaScript toạ độ | tests/js/coords.test.mjs | `# pass 6`, `# fail 0` |
| Tích hợp PostgreSQL: 5 phép thử bắt buộc + tính năng 2.0/2.1/2.2 | tests/integration.php | `TESTS=116 FAILED=0` |
| HTTP: quyền ở máy chủ, CSRF, URL chéo dự án, API 2.0/2.1, lời mời, bộ phận hỗ trợ | tests/http.php | `TESTS=43 FAILED=0` |
| Tổng | | `KET_QUA=TAT_CA_DAT` |

Bộ tích hợp và bộ HTTP tự nạp một bộ dữ liệu riêng (email có hậu tố ngẫu nhiên), rồi tự xoá khi chạy xong. Dữ liệu demo không bị ảnh hưởng.
Nếu thấy `NOT_RUN`, nghĩa là kiểm thử chưa chạy được vì thiếu CSDL hoặc Node. Không coi kết quả đó là đạt.

Kiểm thử trên trình duyệt thật (tuỳ chọn, cũng chạy tự động trên GitHub Actions):

```bash
npm install
npx playwright install chromium
bash lab.sh run          # terminal 1
bash lab.sh e2e          # terminal 2 → TESTS=47 FAILED=0
```

Bài e2e tạo thêm góp ý và một phiên bản ảnh. Chạy xong có thể `bash lab.sh reset` để đưa dữ liệu demo về ban đầu.

## 6. Kịch bản trình diễn

### Tình huống 1 — Tạo dự án → tải ảnh → đánh dấu nút cần sửa → nhận xét → trả lời

1. Đăng nhập **designer** (ở trang đăng nhập bấm thẻ tài khoản mẫu để điền sẵn). Bấm **+ Dự án mới**, đặt tên “Demo vấn đáp”.
2. Trong dự án, ở khung *Thành viên*, gửi lời mời `reviewer@pinpoint.test` với quyền **Góp ý** và `viewer@pinpoint.test` với quyền **Chỉ xem**.
   Hai lời mời nằm ở mục *Đang chờ xác nhận*. Đăng nhập **reviewer** và **viewer**: đầu trang *Dự án* có thẻ lời mời → bấm **Chấp nhận**
   (từ bản 2.2, người được mời phải chấp nhận thì mới vào dự án; Quản lý có thể huỷ lời mời đang chờ).
3. Bấm **Thêm màn hình**, đặt tên “Trang chủ”, kéo thả ảnh `database/fixtures/a-trang-chu-v1.png` vào khung rồi bấm *Tải lên*.
4. Đăng nhập **reviewer** ở cửa sổ ẩn danh và mở màn hình “Trang chủ”.
   - Rê chuột trên ảnh: góc trên phải hiện toạ độ theo **px ảnh gốc**. Chọn **Điểm** (phím `C`) và bấm đúng nút “Đặt ngay” → khoảng (750; 150).
   - **Ô soạn góp ý nổi ngay cạnh dấu**: viết nhận xét, chọn *Ưu tiên: Cao*, *Nhãn: Bố cục*, *Phụ trách: Nguyễn Thu Trang*, đặt hạn; gõ `@Ng…` để nhắc tên. Bấm *Lưu góp ý* (hoặc `Ctrl+Enter`). Dấu **#1** xuất hiện.
   - Chọn **Vùng** (phím `R`), kéo quanh ba thẻ món và nhận xét. Dấu **#2** xuất hiện.
5. Thử bấm vào phần lề xám quanh ảnh: ứng dụng báo “phần lề trống không được tính”.
6. Quay lại cửa sổ **designer** (biểu tượng *Việc của tôi* đã có số mới). Mở dấu #1:
   - Viết trả lời rồi *Gửi trả lời*; thả cảm xúc 👍 vào nhận xét.
   - Bấm **Thêm ghi chú** ở khối *Ghi chú xử lý*, ghi cách sẽ sửa (ví dụ “Chuyển nút sang cột trái, căn lưới 8 px”).
   - Reviewer bấm lại vào dấu sẽ thấy trả lời, ghi chú và **Lịch sử** ghi đủ từng thay đổi.
7. Đăng nhập **viewer**: không có công cụ Điểm/Vùng, thuộc tính chỉ đọc, bấm lên ảnh không tạo được gì.

### Tình huống 2 — Tải bản mới → so sánh → xem góp ý cũ → xác nhận/mở lại → xuất danh sách

1. **designer** mở “Trang chủ”, bấm **Tải phiên bản mới**, kéo thả `a-trang-chu-v2.png` (nút đã chuyển sang trái), ghi “Đã sửa gì?” rồi *Tải lên*.
2. Ứng dụng mở trang **So sánh**: bên trái v1 có các dấu cũ, bên phải v2 **chưa có dấu nào** — góp ý cũ không bị chuyển sang ảnh mới.
   Bấm **Thanh trượt** rồi kéo con trượt để thấy nút “Đặt ngay” đã dời chỗ; bấm **Chồng mờ** để thấy hai bản chồng lên nhau.
3. Trong danh sách *Góp ý trên v1*, chọn #1, ghi chú “Đã chuyển nút sang trái” rồi bấm **Đánh dấu đã xử lý (kiểm tra trên v2)**.
4. **reviewer** mở trang so sánh, chọn #2 rồi bấm **Mở lại góp ý** kèm lý do. *Lịch sử* ghi: tạo trên v1 → Chưa xử lý → Đã xử lý (kiểm tra trên v2) → mở lại.
5. Về trang dự án: các thẻ thống kê (chưa xử lý, quá hạn, ưu tiên cao…) và thanh tiến độ đã cập nhật. Bấm **Góp ý chưa xử lý** để lọc theo
   trạng thái, người góp ý, ưu tiên, nhãn, người phụ trách, quá hạn. Bấm **Xuất CSV** (22 cột: nội dung, vị trí, người viết, trạng thái,
   phiên bản, ưu tiên, nhãn, phụ trách, hạn, ghi chú, phiên bản kiểm tra sau sửa…) hoặc **In / PDF**.

### Tình huống 3 (bản 2.1, 2.2) — Thông báo, xoá ảnh tải nhầm, hạn xử lý, hỗ trợ

1. **reviewer** mở một góp ý, trả lời có `@Nguyễn Thu Trang`. Ở cửa sổ **designer**, biểu tượng 🔔 hiện số đỏ; bấm vào để xem
   “Lê Minh Đức nhắc tới bạn”, bấm thông báo để mở đúng góp ý. Giao việc cho ai, người đó cũng nhận thông báo.
2. **designer** tải nhầm một ảnh làm phiên bản mới → bấm ⚙ trên thanh công cụ → **Xoá phiên bản đang xem** → xác nhận. Phiên bản và
   góp ý trên nó bị xoá, các phiên bản khác giữ nguyên.
3. Ở ô **Hạn xử lý** gõ `11/05/2026` theo dạng **tháng/ngày/năm** (= ngày 5 tháng 11) hoặc bấm biểu tượng lịch để mở lịch tiếng Việt
   (Tháng 11, 2026 · T2…CN · *Hôm nay* · *Xoá hạn*; dùng phím mũi tên để chọn ngày).
4. Bấm **Hỏi trợ lý** ở góc phải dưới, hỏi “làm sao so sánh hai phiên bản?”. Vào **Hỗ trợ** để xem câu hỏi thường gặp và gửi yêu cầu.
5. **Ai xử lý yêu cầu hỗ trợ?** (bản 2.2) Đăng nhập tài khoản **bộ phận hỗ trợ** `hotro@pinpoint.test` / `Pinpoint@123`: chuông 🔔 báo
   có yêu cầu mới, menu có thêm **Xử lý yêu cầu** (`/support_admin.php`). Viết câu trả lời, chọn trạng thái rồi bấm *Lưu và gửi trả lời*.
   Người gửi nhận thông báo và thấy câu trả lời ở mục *Yêu cầu của tôi* (trạng thái *Đang chờ* → *Đã trả lời*).
   Cấp quyền hỗ trợ cho tài khoản khác: `php tools/support.php grant email@...` (thu hồi: `revoke`). Vẫn trả lời được bằng dòng lệnh:
   `php tools/support.php list`, `php tools/support.php reply <số> "nội dung"`.
6. Bấm ảnh đại diện → **Hồ sơ của tôi** để đổi tên, chức danh, mật khẩu.

**Trợ lý ảo dùng AI (không bắt buộc):** mặc định trợ lý trả lời bằng cơ sở tri thức chạy trên máy (không cần mạng). Muốn dùng mô hình
AI, đặt biến môi trường `PINPOINT_AI_KEY` (khoá API Anthropic) trước khi `bash lab.sh run`, ví dụ `PINPOINT_AI_KEY=... bash lab.sh run`;
trên Render thêm biến này ở mục Environment Variables. Chỉ câu hỏi của người dùng được gửi đi, không gửi dữ liệu dự án.

### Mẹo khi trình diễn

- Bấm biểu tượng 🌙 ở góc trên để chuyển giao diện tối; bấm biểu tượng bàn phím (hoặc phím `?`) để xem phím tắt.
- Nút *Ẩn góp ý đã xử lý* và *Ẩn mọi dấu* (phím `M`) trên thanh công cụ nổi giúp xem lại ảnh gốc.
- `←` / `→` chuyển nhanh giữa các góp ý; `/` để tìm.

### Trình diễn nhanh các phép thử

| Phép thử | Cách xem trực tiếp |
|---|---|
| 1. Toạ độ | Mở `/calibration.php` (trang **Hỗ trợ** → *Phép thử toạ độ*). Bốn khung (500×250, có lề, lề trái/phải, phóng to 200%) đều hiện **ĐẠT**. Ở trình xem, phóng to, thu nhỏ và đổi kích thước cửa sổ: dấu #1 luôn ở tâm nút |
| 2. Vùng | Tạo một vùng, tải lại trang (F5), mở lại: vị trí, kích thước và nội dung giữ nguyên. Cột *Vị trí* trong trang Tìm góp ý cũng hiển thị đúng |
| 3. Phiên bản | Bấm nút **v1 / v2** trên thanh công cụ: v1 vẫn có đủ dấu, v2 chỉ có dấu của v2 |
| 4. Quyền | Đăng nhập viewer, dán URL `/screen.php?id=4` (màn hình của dự án B) hoặc `/image.php?id=7` → “Không tìm thấy” |
| 5. Đồng thời | Mở cùng một góp ý ở hai tab và bấm *Sửa* (nhận xét) hoặc *Sửa ghi chú* ở cả hai. Lưu ở tab 2 trước, rồi lưu ở tab 1: ứng dụng báo **Xung đột**, hiện nội dung mới nhất và vẫn giữ chữ bạn đã gõ |

## 7. Đưa lên mạng bằng Render (không bắt buộc)

GitHub Pages chỉ phát tệp tĩnh, không chạy được PHP/PostgreSQL. Muốn có đường link online, dùng dịch vụ hỗ trợ Docker như Render.
Kho mã đã có sẵn `Dockerfile` và `Ma_nguon/tools/docker-start.sh`.

1. Trên Render chọn **New → Postgres**, gói **Free**, tạo xong mở trang CSDL và copy **Internal Database URL**.
2. **New → Web Service**, chọn repo GitHub của nhóm. **Language: Docker**, **Branch: main**, **Instance Type: Free**.
3. Mục **Environment Variables** thêm `DATABASE_URL` = địa chỉ vừa copy. Bấm **Deploy web service**.
4. Khi log hiện `SEED=OK` và `Development Server ... started`, mở địa chỉ `https://<ten-dich-vu>.onrender.com`.

Lưu ý: gói miễn phí tự ngủ khi không có truy cập (lần mở đầu chờ khoảng 1 phút) và ổ đĩa bị xoá khi khởi động lại, nên mỗi lần
khởi động ứng dụng **nạp lại dữ liệu demo** (`PINPOINT_RESET_ON_START=1`). Chính sách gói miễn phí của Render có thể thay đổi;
kiểm tra trang giá của Render trước khi dùng. Khi trình diễn trên lớp vẫn nên chạy bản trên máy (`bash lab.sh run`).

## 8. Lỗi thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| `THIEU_PHP_pgsql` / `THIEU_PHP_gd` | Cài đúng phiên bản: `sudo apt install php8.3-pgsql php8.3-gd` (đổi số theo `php -v`) |
| `CHUA_SAN_SANG_CSDL` | Bật dịch vụ `sudo service postgresql start`; chạy lại `bash lab.sh db-config` |
| `SETUP_FAILED` | Kiểm tra `SELECT current_database()` trong `bash lab.sh sql`; đúng CSDL riêng chưa |
| Tải ảnh báo “Chỉ chấp nhận PNG hoặc JPEG” | Ứng dụng kiểm tra nội dung tệp, không chỉ phần đuôi. Ảnh WebP/GIF/SVG bị từ chối |
| Ảnh lớn bị từ chối | Giới hạn 5 MB và 16–8000 px mỗi chiều; `lab.sh run` đã đặt `upload_max_filesize=6M` |
| `NOT_RUN` khi test | Thiếu CSDL hoặc Node. Sửa môi trường rồi chạy lại; không sửa test để bỏ qua |
