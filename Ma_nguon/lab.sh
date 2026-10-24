#!/usr/bin/env bash
# Pinpoint — góp ý trực tiếp trên ảnh thiết kế. Dùng cho học tập cục bộ trên Ubuntu/WSL.
# Script không cài gói và không xoá dữ liệu (trừ lệnh reset, có hỏi xác nhận).
set -u
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)" || exit 2
cd "$root" || exit 2
port="${PORT:-8115}"
usage() {
  printf 'Cách dùng: bash lab.sh check | db-config | setup | run | test | e2e | sql | reset\n'
  printf '  check     kiểm tra PHP, Git, PostgreSQL, kết nối CSDL\n'
  printf '  db-config lưu thông tin kết nối vào .local/db.json (không đưa lên Git)\n'
  printf '  setup     tạo bảng và nạp dữ liệu mẫu (bỏ qua nếu đã có dữ liệu)\n'
  printf '  run       chạy web tại http://127.0.0.1:%s (Ctrl+C để dừng)\n' "$port"
  printf '  test      chạy kiểm thử đơn vị, JS, tích hợp PostgreSQL và HTTP\n'
  printf '  e2e       kiểm thử trên trình duyệt thật (cần Node + Playwright, xem README)\n'
  printf '  sql       mở psql vào CSDL đã cấu hình\n'
  printf '  reset     XOÁ dữ liệu Pinpoint rồi nạp lại mẫu (hỏi xác nhận)\n'
}
need_php() {
  command -v php >/dev/null 2>&1 || { echo 'THIEU_PHP: sudo apt install php-cli'; return 2; }
  php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || { echo 'CAN_PHP_8_1_TRO_LEN'; return 2; }
  for ext in pgsql gd fileinfo mbstring; do
    php -r "exit(extension_loaded('$ext') ? 0 : 1);" || { echo "THIEU_PHP_$ext: sudo apt install php-$ext (cài đúng phiên bản php -v)"; return 2; }
  done
}
check_env() {
  need_php || return 2
  command -v git >/dev/null 2>&1 || { echo 'THIEU_GIT'; return 2; }
  command -v psql >/dev/null 2>&1 || { echo 'THIEU_PSQL: sudo apt install postgresql-client'; return 2; }
  php tools/db-check.php || return 2
  printf 'MOI_TRUONG=SAN_SANG | Pinpoint\n'
}
case "${1:-help}" in
  check) check_env ;;
  db-config) need_php || exit 2; php tools/db-config.php ;;
  setup) check_env || exit 2; php tools/setup.php ;;
  run)
    check_env || exit 2
    echo "THU_MUC=$root"
    echo "DIA_CHI=http://127.0.0.1:$port"
    echo 'Giữ terminal này mở. Nhấn Ctrl+C để dừng.'
    mkdir -p .local/sessions storage/uploads || exit 2
    php -d session.name=PINPOINT -d session.save_path="$root/.local/sessions" \
      -d upload_max_filesize=6M -d post_max_size=8M \
      -S "127.0.0.1:$port" -t public ;;
  test)
    need_php || exit 2
    status=0
    echo '== Kiểm thử đơn vị (không cần CSDL)'
    php tests/run.php || status=1
    echo '== Kiểm thử JavaScript toạ độ'
    if command -v node >/dev/null 2>&1; then
      node --test tests/js/coords.test.mjs || status=1
    else
      echo 'NOT_RUN: chưa có Node.js (sudo apt install nodejs). Không tính là đạt.'; status=1
    fi
    echo '== Kiểm thử tích hợp PostgreSQL (5 phép thử bắt buộc; tự dọn dữ liệu thử)'
    php tests/integration.php || status=1
    echo '== Kiểm thử qua HTTP: quyền ở máy chủ, CSRF, truy cập chéo dự án bằng URL'
    php tests/http.php || status=1
    [[ $status == 0 ]] && echo 'KET_QUA=TAT_CA_DAT' || echo 'KET_QUA=CO_LOI'
    exit $status ;;
  e2e)
    command -v node >/dev/null 2>&1 || { echo 'NOT_RUN: cần Node.js'; exit 2; }
    [[ -d node_modules/playwright ]] || { echo 'NOT_RUN: chạy npm install trước (cài Playwright)'; exit 2; }
    node tests/e2e/browser.mjs ;;
  sql) need_php || exit 2; php tools/sql.php ;;
  reset) check_env || exit 2; php tools/reset.php ;;
  help|-h|--help) usage ;;
  *) usage; exit 2 ;;
esac
