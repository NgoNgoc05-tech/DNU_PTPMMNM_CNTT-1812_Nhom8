"""Sinh 12 ảnh thiết kế mẫu (tự vẽ, CC0) cho dữ liệu kiểm thử.

Không bắt buộc chạy: ảnh đã có sẵn trong database/fixtures. Chạy lại bằng
    python3 tools/make_fixtures.py
(cần Pillow). Toạ độ của các nút được ghi trong database/fixtures/README.md và
được dùng làm "đáp án" cho dữ liệu mẫu.
"""
import os
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "database", "fixtures")
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
BOLD = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"


def f(size, bold=False):
    return ImageFont.truetype(BOLD if bold else FONT, size)


def button(d, cx, cy, w, h, text, fill="#e8590c", color="white", size=20):
    d.rounded_rectangle([cx - w / 2, cy - h / 2, cx + w / 2, cy + h / 2], radius=h // 4, fill=fill)
    d.text((cx, cy), text, fill=color, font=f(size, True), anchor="mm")


def header(d, w, title, bg="#102a38", h=60):
    d.rectangle([0, 0, w, h], fill=bg)
    d.text((24, h / 2), title, fill="white", font=f(22, True), anchor="lm")


def lines(d, x, y, widths, gap=22, color="#cfd6dd", h=10):
    for i, lw in enumerate(widths):
        d.rounded_rectangle([x, y + i * gap, x + lw, y + i * gap + h], radius=4, fill=color)


def save(img, name):
    path = os.path.join(OUT, name)
    if name.endswith(".jpg"):
        img.convert("RGB").save(path, quality=88)
    else:
        img.save(path, optimize=True)
    print(name, img.size)


def home(version):
    # Ảnh 1000×500 dùng cho phép thử 1: tâm nút "Đặt ngay" ở v1 là (750;150).
    img = Image.new("RGB", (1000, 500), "#fff8f1")
    d = ImageDraw.Draw(img)
    header(d, 1000, "FoodNow")
    d.text((60, 120), "Món ngon giao nhanh", fill="#1b1f24", font=f(34, True))
    d.text((60, 170), "Giao trong 30 phút tại Hà Nội", fill="#5c6670", font=f(20))
    if version == 1:
        button(d, 750, 150, 200, 64, "Đặt ngay")       # tâm (750;150)
    else:
        button(d, 160, 255, 200, 56, "Đặt ngay")       # v2: nút chuyển sang (160;255)
        d.rounded_rectangle([600, 90, 940, 250], radius=16, fill="#ffe8cc")
        d.text((770, 170), "Ưu đãi 20%", fill="#a33c00", font=f(28, True), anchor="mm")
    # Ba thẻ món
    for i in range(3):
        x = 60 + i * 300
        d.rounded_rectangle([x, 320, x + 260, 470], radius=14, fill="white", outline="#f1d3bd", width=2)
        d.ellipse([x + 20, 345, x + 80, 405], fill=["#ffd8a8", "#b2f2bb", "#a5d8ff"][i])
        d.text((x + 100, 360), ["Phở bò", "Bún chả", "Cơm tấm"][i], fill="#1b1f24", font=f(20, True))
        d.text((x + 100, 392), ["45.000đ", "40.000đ", "50.000đ"][i], fill="#e8590c", font=f(18))
    # Dấu chữ thập mờ đúng tâm nút để kiểm tra bằng mắt
    if version == 1:
        d.line([745, 150, 755, 150], fill="#ffffff", width=2)
        d.line([750, 145, 750, 155], fill="#ffffff", width=2)
    return img


def menu(version):
    img = Image.new("RGB", (1200, 800), "white")
    d = ImageDraw.Draw(img)
    header(d, 1200, "FoodNow · Thực đơn")
    d.text((40, 90), "Thực đơn hôm nay", fill="#1b1f24", font=f(30, True))
    tabs = ["Tất cả", "Món nước", "Cơm", "Đồ uống"]
    for i, t in enumerate(tabs):
        x = 40 + i * 150
        d.rounded_rectangle([x, 145, x + 130, 185], radius=20, fill="#102a38" if i == 0 else "#eef1f4")
        d.text((x + 65, 165), t, fill="white" if i == 0 else "#1b1f24", font=f(16), anchor="mm")
    for r in range(2):
        for c in range(4):
            x, y = 40 + c * 285, 220 + r * 270
            d.rounded_rectangle([x, y, x + 260, y + 245], radius=12, fill="#fafafa", outline="#e3e7eb", width=2)
            d.rectangle([x, y, x + 260, y + 130], fill=["#ffe8cc", "#d3f9d8", "#d0ebff", "#f3d9fa"][c])
            d.text((x + 16, y + 145), f"Món {r * 4 + c + 1}", fill="#1b1f24", font=f(18, True))
            d.text((x + 16, y + 175), "35.000đ", fill="#e8590c", font=f(16))
            if version == 1:
                button(d, x + 205, y + 210, 90, 34, "Thêm", size=14)
            else:
                button(d, x + 130, y + 212, 228, 38, "+ Thêm vào giỏ", size=15)
    return img


def cart(version):
    img = Image.new("RGB", (1200, 800), "#f6f7f9")
    d = ImageDraw.Draw(img)
    header(d, 1200, "FoodNow · Giỏ hàng")
    for i in range(3):
        y = 100 + i * 120
        d.rounded_rectangle([40, y, 760, y + 100], radius=12, fill="white")
        d.rectangle([60, y + 15, 130, y + 85], fill="#ffd8a8")
        d.text((150, y + 25), ["Phở bò", "Bún chả", "Trà đào"][i], fill="#1b1f24", font=f(20, True))
        d.text((150, y + 58), ["45.000đ", "40.000đ", "25.000đ"][i], fill="#5c6670", font=f(16))
        d.text((650, y + 50), "− 1 +", fill="#1b1f24", font=f(20), anchor="mm")
    d.rounded_rectangle([800, 100, 1160, 480], radius=12, fill="white")
    d.text((830, 130), "Tổng cộng", fill="#1b1f24", font=f(22, True))
    d.text((830, 180), "Tạm tính: 110.000đ", fill="#5c6670", font=f(18))
    d.text((830, 215), "Phí giao: 15.000đ", fill="#5c6670", font=f(18))
    d.text((830, 270), "125.000đ", fill="#e8590c", font=f(30, True))
    if version == 1:
        button(d, 980, 420, 300, 56, "Thanh toán", fill="#adb5bd")
    else:
        d.text((830, 320), "Mã giảm giá", fill="#5c6670", font=f(16))
        d.rounded_rectangle([830, 345, 1130, 385], radius=8, outline="#ced4da", width=2, fill="white")
        button(d, 980, 430, 300, 56, "Thanh toán")
    return img


def login(version):
    img = Image.new("RGB", (1280, 720), "#e7f1f6")
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, 560, 720], fill="#1f5f7a")
    d.text((60, 300), "Cổng thông tin", fill="white", font=f(40, True))
    d.text((60, 360), "sinh viên", fill="white", font=f(40, True))
    d.rounded_rectangle([700, 140, 1180, 580], radius=16, fill="white")
    d.text((740, 180), "Đăng nhập", fill="#1b1f24", font=f(28, True))
    for i, label in enumerate(["Mã sinh viên", "Mật khẩu"]):
        y = 250 + i * 95
        d.text((740, y), label, fill="#5c6670", font=f(16))
        d.rounded_rectangle([740, y + 25, 1140, y + 70], radius=8, outline="#ced4da", width=2, fill="white")
    if version == 1:
        button(d, 940, 500, 400, 52, "Đăng nhập", fill="#1f5f7a")
        d.text((740, 445), "Quên mật khẩu?", fill="#adb5bd", font=f(13))
    else:
        d.text((740, 445), "Quên mật khẩu?", fill="#1f5f7a", font=f(16, True))
        button(d, 940, 510, 400, 52, "Đăng nhập", fill="#1f5f7a")
    return img


def schedule(version):
    img = Image.new("RGB", (1280, 720), "white")
    d = ImageDraw.Draw(img)
    header(d, 1280, "Cổng sinh viên · Lịch học", bg="#1f5f7a")
    days = ["Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7"]
    for i, day in enumerate(days):
        x = 120 + i * 190
        d.text((x + 90, 100), day, fill="#1b1f24", font=f(18, True), anchor="mm")
    for r in range(5):
        y = 130 + r * 110
        d.text((30, y + 50), f"Ca {r + 1}", fill="#5c6670", font=f(16))
        d.line([110, y, 1260, y], fill="#e3e7eb", width=2)
    colors = ["#d0ebff", "#ffe8cc", "#d3f9d8", "#f3d9fa"]
    blocks = [(0, 0, "Mã nguồn mở"), (1, 1, "Cơ sở dữ liệu"), (2, 0, "Mạng máy tính"), (3, 2, "Kiểm thử PM"), (4, 3, "Tiếng Anh")]
    for col, row, name in blocks:
        x, y = 125 + col * 190, 135 + row * 110
        d.rounded_rectangle([x, y, x + 175, y + 100], radius=10, fill=colors[(col + row) % 4])
        d.text((x + 12, y + 14), name, fill="#1b1f24", font=f(15 if version == 1 else 17, True))
        d.text((x + 12, y + 44), "Phòng A2-30" + str(col), fill="#5c6670", font=f(13))
    return img


def profile(version):
    img = Image.new("RGB", (900, 1200), "#f6f7f9")
    d = ImageDraw.Draw(img)
    header(d, 900, "Hồ sơ sinh viên", bg="#1f5f7a")
    d.ellipse([360, 110, 540, 290], fill="#a5d8ff")
    d.text((450, 330), "Nguyễn Văn A", fill="#1b1f24", font=f(30, True), anchor="mm")
    d.text((450, 370), "MSSV 1771020000 · CNTT 17-08", fill="#5c6670", font=f(18), anchor="mm")
    for i, label in enumerate(["Ngày sinh", "Email", "Điện thoại", "Địa chỉ", "Khoa"]):
        y = 440 + i * 110
        d.rounded_rectangle([80, y, 820, y + 90], radius=12, fill="white")
        d.text((110, y + 20), label, fill="#5c6670", font=f(16))
        lines(d, 110, y + 52, [300 + 50 * (i % 3)], h=14)
    button(d, 450, 1060 if version == 1 else 1090, 300 if version == 1 else 740, 60, "Cập nhật hồ sơ", fill="#1f5f7a")
    return img


def calibration():
    # Ảnh mẫu tĩnh cho trang phép thử: đánh dấu rõ điểm (750;150) trên lưới 50 px.
    img = Image.new("RGB", (1000, 500), "white")
    d = ImageDraw.Draw(img)
    for x in range(0, 1001, 50):
        d.line([x, 0, x, 500], fill="#e9ecef" if x % 250 else "#adb5bd", width=1)
    for y in range(0, 501, 50):
        d.line([0, y, 1000, y], fill="#e9ecef" if y % 250 else "#adb5bd", width=1)
    button(d, 750, 150, 200, 64, "Đặt ngay")
    d.line([740, 150, 760, 150], fill="white", width=2)
    d.line([750, 140, 750, 160], fill="white", width=2)
    d.rectangle([0, 0, 999, 499], outline="#102a38", width=3)
    d.text((12, 470), "1000 × 500 px — điểm mẫu (750; 150)", fill="#102a38", font=f(16, True))
    return img


if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    for v in (1, 2):
        save(home(v), f"a-trang-chu-v{v}.png")
        save(menu(v), f"a-thuc-don-v{v}.png")
        save(cart(v), f"a-gio-hang-v{v}.jpg")
        save(login(v), f"b-dang-nhap-v{v}.png")
        save(schedule(v), f"b-lich-hoc-v{v}.jpg")
        save(profile(v), f"b-ho-so-v{v}.png")
    calib = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "public", "assets", "calibration-1000x500.png")
    calibration().save(calib, optimize=True)
    print("calibration", calib)
