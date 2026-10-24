# Nguồn sử dụng và giấy phép

Mã nguồn Pinpoint phát hành theo **MIT** (tệp [LICENSE](LICENSE)). Bảng dưới liệt kê mọi phần mềm, thư viện và tài nguyên bên ngoài.
Tất cả đều có giấy phép tương thích với MIT.

| Thành phần | Dùng để | Phiên bản | Giấy phép | Có trong gói phát hành? |
|---|---|---|---|---|
| PHP + phần mở rộng pgsql, gd, fileinfo, mbstring | Chạy ứng dụng | ≥ 8.1 | PHP License 3.01 | Không (người dùng tự cài) |
| PostgreSQL | Cơ sở dữ liệu | ≥ 14 (thử trên 16) | PostgreSQL License | Không |
| Node.js | Chạy kiểm thử JS và e2e | ≥ 18 | MIT | Không |
| Playwright (npm `playwright`) | Kiểm thử trình duyệt | 1.56.1 | Apache-2.0 | Không (cài bằng `npm install`) |
| Pillow (Python) | Chỉ để sinh lại ảnh mẫu (`tools/make_fixtures.py`) | ≥ 10 | MIT-CMU (HPND) | Không |
| Phông DejaVu Sans | Chữ trong ảnh mẫu tự vẽ | 2.37 | Bitstream Vera / Public domain | Chữ đã vẽ thành ảnh |
| Cấu trúc `lab.sh`, `tools/db-config.php`, `db-check.php`, `sql.php`, `tests/helper.php` | Khung chạy và kiểm thử theo Lab01–Lab05 | 2026 | MIT — © FIT4111 Teaching Examples Contributors ([FIT-DNU/Open-Source-Software-Development](https://github.com/FIT-DNU/Open-Source-Software-Development)) | Có (đã chỉnh sửa, giữ ghi công) |
| Contributor Covenant | Quy tắc ứng xử | 2.1 | CC BY 4.0 | Có |
| 12 ảnh thiết kế mẫu + ảnh phép thử | Dữ liệu kiểm thử | — | CC0 (nhóm tự vẽ) | Có |
| GitHub Actions: `actions/checkout`, `actions/setup-node`, `actions/upload-artifact`, `shivammathur/setup-php` | CI | v4 / v2 | MIT | Không |

Ứng dụng **không** dùng framework PHP, thư viện JavaScript hay CDN nào khi chạy. Toàn bộ HTML, CSS và JS trong `public/` do nhóm viết.
Có thể kiểm tra lại giấy phép gói npm bằng `npm ls --all` hoặc `npx license-checker --summary`.
