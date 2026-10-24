# Hướng dẫn đóng góp

Cảm ơn bạn quan tâm tới Pinpoint. Khi tham gia, bạn đồng ý tuân thủ [Quy tắc ứng xử](CODE_OF_CONDUCT.md).

## Báo lỗi và đề xuất

- Tìm trong **Issues** trước khi tạo mới. Dùng mẫu *Báo lỗi* hoặc *Đề xuất tính năng*.
- Báo lỗi cần có các bước tái hiện, kết quả mong đợi, kết quả thực tế và đầu ra của `bash lab.sh check`.
- Lỗ hổng bảo mật **không** báo công khai, xem [SECURITY.md](SECURITY.md).

## Quy trình

```bash
git clone <kho> pinpoint && cd pinpoint
git switch develop
git switch -c feat/ten-tinh-nang        # hoặc fix/…, docs/…, test/…
# sửa mã, thêm/điều chỉnh kiểm thử
cd Ma_nguon && bash lab.sh test         # phải có KET_QUA=TAT_CA_DAT
git commit -m "feat(viewer): thêm phím tắt khoanh vùng"
git push -u origin feat/ten-tinh-nang   # mở Pull Request vào develop
```

| Nhánh | Mục đích |
|---|---|
| `main` | Bản phát hành ổn định, gắn tag `vX.Y.Z` |
| `develop` | Tích hợp các Pull Request |
| `feat/*`, `fix/*`, `docs/*`, `test/*` | Một thay đổi cho mỗi nhánh |

Mỗi Pull Request cần **CI xanh** và **ít nhất một thành viên khác review**. Người review chạy lại `bash lab.sh test` trên máy mình
và ghi nhận xét theo dòng mã trên GitHub. Tác giả sửa theo nhận xét bằng commit mới; không force-push sau khi đã có review.

## Quy ước commit (Conventional Commits)

`<type>(<phạm vi>): <mô tả ngắn>` với `type` ∈ `feat`, `fix`, `docs`, `test`, `refactor`, `chore`. Ví dụ:
`fix(api): trả 409 khi sửa nhận xét từ bản cũ`, `test(http): thêm ca truy cập ảnh dự án khác`.

## Chuẩn mã

- PHP `declare(strict_types=1)`, thụt lề 4 dấu cách, tên lớp `PascalCase`, hàm `camelCase`. Chú thích tiếng Việt cho phần nghiệp vụ.
- SQL **luôn** dùng `queryParams()` với `$1, $2…`, không ghép chuỗi đầu vào.
- Mọi đầu ra HTML qua `e()`; trong JavaScript chỉ dùng `textContent`, không `innerHTML` với dữ liệu người dùng.
- Mọi thao tác ghi kiểm tra quyền bằng `Access::require()` ở máy chủ và kiểm tra CSRF.
- Không thêm framework hoặc bước build cho giao diện. Thư viện mới phải ghi vào [NGUON_SU_DUNG.md](NGUON_SU_DUNG.md) kèm giấy phép.
- Sửa lỗi phải kèm một ca kiểm thử tái hiện lỗi đó, rồi ghi vào bảng ở `docs/KIEM_THU.md`.

## Phát hành

1. Cập nhật [CHANGELOG.md](CHANGELOG.md) và số phiên bản.
2. Merge `develop` vào `main`, rồi gắn tag `git tag -a v1.0.0 -m "v1.0.0" && git push --tags`.
3. Tạo GitHub Release, đính kèm `pinpoint-v1.0.0.zip` (không có `.local/`, `storage/uploads`, `node_modules/`).
4. Kiểm tra bản phát hành trên một máy sạch theo [HUONG_DAN.md](HUONG_DAN.md).
