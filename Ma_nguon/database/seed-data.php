<?php

declare(strict_types=1);

/*
 * DỮ LIỆU MẪU CÓ ĐÁP ÁN (dùng cho bash lab.sh seed và tests/integration.php).
 *
 * 2 dự án · 3 tài khoản có quyền khác nhau · 3 màn hình/dự án · 2 phiên bản/màn hình
 * · 20 góp ý (11 điểm, 9 vùng chữ nhật; 13 chưa xử lý, 7 đã xử lý).
 * Toạ độ tính theo PIXEL ẢNH GỐC và trùng với vị trí nút/khối trong ảnh
 * (xem tools/make_fixtures.py). Ảnh "Trang chủ" 1000×500 có điểm mẫu (750; 150) đúng tâm nút "Đặt ngay".
 * Mật khẩu chung của tài khoản mẫu: Pinpoint@123 (chỉ dùng trên máy học tập).
 */
return [
    'password' => 'Pinpoint@123',
    'users' => [
        'D' => ['email' => 'designer@pinpoint.test', 'name' => 'Nguyễn Thu Trang (Thiết kế)'],
        'R' => ['email' => 'reviewer@pinpoint.test', 'name' => 'Lê Minh Đức (Giảng viên)'],
        'V' => ['email' => 'viewer@pinpoint.test', 'name' => 'Phạm Gia Huy (Thành viên)'],
    ],
    'projects' => [
        'A' => [
            'name' => 'FoodNow – Ứng dụng đặt món',
            'description' => 'Thiết kế giao diện ứng dụng đặt món ăn trực tuyến. Hạn góp ý: thứ Sáu.',
            'owner' => 'D',
            'members' => ['R' => 'COMMENT', 'V' => 'VIEW'],
            'screens' => [
                'Trang chủ' => ['a-trang-chu-v1.png', 'a-trang-chu-v2.png'],
                'Thực đơn' => ['a-thuc-don-v1.png', 'a-thuc-don-v2.png'],
                'Giỏ hàng' => ['a-gio-hang-v1.jpg', 'a-gio-hang-v2.jpg'],
            ],
        ],
        'B' => [
            'name' => 'Cổng thông tin sinh viên',
            'description' => 'Thiết kế lại cổng thông tin: đăng nhập, lịch học, hồ sơ.',
            'owner' => 'D',
            // Người chỉ xem V KHÔNG thuộc dự án B (phục vụ phép thử 4: không lấy được ảnh/nhận xét của B bằng URL).
            'members' => ['R' => 'COMMENT'],
            'screens' => [
                'Đăng nhập' => ['b-dang-nhap-v1.png', 'b-dang-nhap-v2.png'],
                'Lịch học' => ['b-lich-hoc-v1.jpg', 'b-lich-hoc-v2.jpg'],
                'Hồ sơ' => ['b-ho-so-v1.png', 'b-ho-so-v2.png'],
            ],
        ],
    ],
    // [dự án, màn hình, phiên bản, kiểu, x, y, w, h, tác giả, nội dung, trả lời[], lịch sử trạng thái[]]
    // lịch sử: [trạng thái mới, người đổi, phiên bản kiểm tra, ghi chú]
    // thuộc tính (bản 2.0): p = ưu tiên, c = nhãn, to = người phụ trách, due = hạn (số ngày so với hôm nạp dữ liệu;
    // số âm = đã quá hạn), note = ghi chú xử lý, react = cảm xúc trên nhận xét gốc.
    'annotations' => [
        ['A', 'Trang chủ', 1, 'POINT', 750, 150, null, null, 'R', 'Nút “Đặt ngay” nên đưa sang trái, ngay dưới tiêu đề để người dùng thấy trước.',
            [['D', 'Em sẽ chuyển nút sang trái ở bản sau.']], [['RESOLVED', 'D', 2, 'Đã chuyển nút sang trái ở v2.']],
            ['p'=>'HIGH','c'=>'LAYOUT','to'=>'D','due'=>-3,'note'=>'Chuyển nút sang cột trái, căn theo lưới 8 px; giữ màu cam thương hiệu.','react'=>[['D','LIKE']]]],
        ['A', 'Trang chủ', 1, 'RECT', 60, 110, 420, 100, 'R', 'Tiêu đề và dòng mô tả hơi sát nhau, tăng khoảng cách lên khoảng 12 px.', [], [],
            ['p'=>'MEDIUM','c'=>'TYPO','to'=>'D','due'=>3]],
        ['A', 'Trang chủ', 1, 'POINT', 190, 402, null, null, 'R', 'Giá tiền nên đậm hơn để dễ so sánh giữa các món.', [], [],
            ['p'=>'LOW','c'=>'TYPO']],
        ['A', 'Trang chủ', 1, 'RECT', 60, 320, 860, 150, 'R', 'Ba thẻ món chưa có nút thêm nhanh vào giỏ.',
            [['D', 'Phần này để màn Thực đơn xử lý được không ạ?'], ['R', 'Trang chủ vẫn cần nút thêm nhanh.']],
            [['RESOLVED', 'D', 2, 'Đã xem lại trên v2.'], ['OPEN', 'R', 2, 'v2 vẫn chưa có nút thêm nhanh.']],
            ['p'=>'HIGH','c'=>'UX','to'=>'D','due'=>-1,'note'=>'Thống nhất với màn Thực đơn: dùng chung thành phần “Thêm nhanh” (nút + nhỏ ở góc thẻ).','react'=>[['D','EYES']]]],
        ['A', 'Trang chủ', 2, 'POINT', 160, 255, null, null, 'R', 'Nút đã đúng vị trí mới nhưng chữ hơi nhỏ.', [], [],
            ['p'=>'MEDIUM','c'=>'TYPO','to'=>'D','due'=>5]],
        ['A', 'Trang chủ', 2, 'RECT', 600, 90, 340, 160, 'R', 'Khối ưu đãi mới: dùng màu cam nhạt hơn để không lấn át nút chính.',
            [], [['RESOLVED', 'D', 2, 'Đã giảm độ đậm màu nền.']],
            ['p'=>'MEDIUM','c'=>'COLOR','to'=>'D']],
        ['A', 'Thực đơn', 1, 'RECT', 40, 145, 580, 40, 'R', 'Các tab lọc cần trạng thái đang chọn rõ hơn.', [], [],
            ['p'=>'MEDIUM','c'=>'UX','to'=>'D','due'=>2]],
        ['A', 'Thực đơn', 1, 'POINT', 245, 430, null, null, 'R', 'Nút “Thêm” quá nhỏ, khó bấm trên điện thoại.',
            [['D', 'Đã đổi thành nút rộng “+ Thêm vào giỏ”.']], [['RESOLVED', 'D', 2, 'Kiểm tra trên v2: nút rộng 228 px.']],
            ['p'=>'URGENT','c'=>'A11Y','to'=>'D','note'=>'Vùng chạm tối thiểu 44×44 px (WCAG 2.5.5). Đã kiểm tra trên điện thoại 5,5 inch.','react'=>[['R','PARTY']]]],
        ['A', 'Thực đơn', 1, 'POINT', 56, 365, null, null, 'R', 'Giá chưa thẳng hàng với tên món.', [], [],
            ['p'=>'LOW','c'=>'LAYOUT']],
        ['A', 'Thực đơn', 2, 'RECT', 56, 413, 228, 38, 'R', 'Nút mới ổn. Cân nhắc thêm biểu tượng giỏ hàng.', [], [],
            ['p'=>'LOW','c'=>'CONTENT']],
        ['A', 'Giỏ hàng', 1, 'POINT', 980, 420, null, null, 'R', 'Nút Thanh toán màu xám trông như đang bị vô hiệu hoá.',
            [], [['RESOLVED', 'D', 2, 'Đổi sang màu cam ở v2.']],
            ['p'=>'HIGH','c'=>'COLOR','to'=>'D']],
        ['A', 'Giỏ hàng', 1, 'RECT', 40, 100, 720, 340, 'R', 'Danh sách món nên có nút xoá từng món.', [['D', 'Sẽ thêm biểu tượng thùng rác.']], [],
            ['p'=>'MEDIUM','c'=>'UX','to'=>'D','due'=>4]],
        ['A', 'Giỏ hàng', 1, 'POINT', 650, 150, null, null, 'R', 'Nút tăng/giảm số lượng cần lớn hơn.', [], [],
            ['p'=>'HIGH','c'=>'A11Y','to'=>'D','due'=>-2,'note'=>'Đề xuất nút 40 px, có viền để phân biệt với nền.']],
        ['A', 'Giỏ hàng', 2, 'RECT', 830, 345, 300, 40, 'R', 'Ô mã giảm giá thiếu nút “Áp dụng”.', [], [],
            ['p'=>'MEDIUM','c'=>'UX','to'=>'D']],
        ['B', 'Đăng nhập', 1, 'POINT', 800, 452, null, null, 'R', 'Liên kết “Quên mật khẩu?” quá mờ.',
            [], [['RESOLVED', 'D', 2, 'Đổi màu chữ và in đậm ở v2.']],
            ['p'=>'MEDIUM','c'=>'A11Y','to'=>'D']],
        ['B', 'Đăng nhập', 1, 'RECT', 740, 250, 400, 165, 'R', 'Thiếu dấu * cho hai ô bắt buộc.', [], [],
            ['p'=>'HIGH','c'=>'CONTENT','to'=>'D','due'=>1]],
        ['B', 'Đăng nhập', 2, 'POINT', 940, 510, null, null, 'R', 'Nút đăng nhập cần trạng thái “Đang xử lý…”.', [], [],
            ['p'=>'MEDIUM','c'=>'UX','to'=>'D','due'=>6]],
        ['B', 'Lịch học', 1, 'RECT', 125, 135, 175, 100, 'R', 'Chữ trong ô môn học nhỏ, khó đọc.',
            [], [['RESOLVED', 'D', 2, 'Tăng cỡ chữ lên 17 px ở v2.']],
            ['p'=>'HIGH','c'=>'TYPO','to'=>'D']],
        ['B', 'Lịch học', 1, 'POINT', 45, 465, null, null, 'R', 'Nên ghi rõ giờ bắt đầu của từng ca.', [], [],
            ['p'=>'LOW','c'=>'CONTENT']],
        ['B', 'Hồ sơ', 1, 'POINT', 450, 1060, null, null, 'R', 'Nút “Cập nhật hồ sơ” nên rộng bằng các thẻ thông tin.',
            [], [['RESOLVED', 'D', 2, 'Đã kéo rộng nút ở v2.']],
            ['p'=>'MEDIUM','c'=>'LAYOUT','to'=>'D']],
    ],
];
