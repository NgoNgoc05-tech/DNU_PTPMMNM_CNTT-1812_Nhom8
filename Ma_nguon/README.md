# Pinpoint — mã nguồn

Ứng dụng PHP thuần + PostgreSQL, chạy giống các bài Lab của học phần. Hướng dẫn đầy đủ: [../HUONG_DAN.md](../HUONG_DAN.md).

```bash
bash lab.sh db-config   # lưu kết nối CSDL riêng vào .local/db.json
bash lab.sh check       # MOI_TRUONG=SAN_SANG
bash lab.sh setup       # tạo bảng + dữ liệu mẫu (2 dự án, 3 tài khoản, 12 ảnh, 20 góp ý)
bash lab.sh run         # http://127.0.0.1:8115
bash lab.sh test        # KET_QUA=TAT_CA_DAT
```

Tài khoản mẫu: `designer@pinpoint.test`, `reviewer@pinpoint.test`, `viewer@pinpoint.test`, mật khẩu chung `Pinpoint@123`.

Không đưa `.local/`, `storage/uploads/` hoặc mật khẩu vào kho mã và gói nộp.
