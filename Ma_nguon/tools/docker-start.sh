#!/bin/sh
# Khởi động trong Docker (Render/Railway...): tạo bảng, nạp dữ liệu mẫu rồi chạy máy chủ PHP.
# Ổ đĩa của gói miễn phí bị xoá mỗi lần khởi động lại, nên mặc định nạp lại dữ liệu demo
# (PINPOINT_RESET_ON_START=1) để ảnh và CSDL luôn khớp nhau. Đặt =0 nếu đã gắn ổ đĩa lưu trữ bền vững.
set -e
cd /app
mkdir -p storage/uploads /tmp/pinpoint-sessions
if [ "${PINPOINT_RESET_ON_START:-1}" = "1" ]; then
  echo XOA | php tools/reset.php
else
  php tools/setup.php
fi
exec php -d session.save_path=/tmp/pinpoint-sessions -d session.name=PINPOINT \
  -d upload_max_filesize=6M -d post_max_size=8M -d display_errors=0 \
  -S 0.0.0.0:"${PORT:-10000}" -t public
