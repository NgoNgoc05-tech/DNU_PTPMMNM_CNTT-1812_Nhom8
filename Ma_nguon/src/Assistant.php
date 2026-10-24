<?php

declare(strict_types=1);

/**
 * Trợ lý ảo trả lời câu hỏi về cách dùng Pinpoint (bản 2.1).
 *
 * - Mặc định chạy HOÀN TOÀN TRÊN MÁY: so khớp câu hỏi (bỏ dấu tiếng Việt) với cơ sở tri thức bên dưới,
 *   không gửi dữ liệu ra ngoài, không cần khoá API.
 * - Nếu quản trị đặt biến môi trường PINPOINT_AI_KEY (khoá API Anthropic), câu hỏi được gửi tới mô hình
 *   ngôn ngữ kèm cơ sở tri thức làm ngữ cảnh; lỗi mạng hay hết hạn mức thì tự quay về chế độ trên máy.
 *   Chỉ câu hỏi của người dùng được gửi đi — không gửi nội dung góp ý, ảnh hay dữ liệu dự án.
 */
final class Assistant
{
    /** [chủ đề, từ khoá (không dấu), câu trả lời] */
    public const KB = [
        ['Tạo dự án', ['tao du an', 'du an moi', 'lap du an', 'them du an', 'new project'],
            "Vào trang Dự án, bấm nút “+ Dự án mới”, nhập tên và mô tả rồi bấm Tạo dự án. Người tạo tự động có quyền Quản lý."],
        ['Mời thành viên và cấp quyền', ['moi', 'thanh vien', 'cap quyen', 'phan quyen', 'quyen', 'role', 'chi xem', 'quan ly', 'invite'],
            "Mở dự án → khung Thành viên → nhập email đã đăng ký tài khoản, chọn quyền rồi bấm Mời.\n• Chỉ xem: xem ảnh, góp ý, xuất danh sách.\n• Góp ý: thêm tạo điểm/vùng, trả lời, ghi chú, đánh dấu đã xử lý.\n• Quản lý: thêm tải ảnh, xoá ảnh, quản lý thành viên.\nĐổi quyền bằng ô chọn cạnh tên thành viên; bấm × để xoá khỏi dự án."],
        ['Tải ảnh thiết kế', ['tai anh', 'upload', 'them man hinh', 'man hinh moi', 'png', 'jpeg', 'jpg', 'dinh dang', 'dung luong'],
            "Trong dự án bấm “Thêm màn hình”, đặt tên (ví dụ Trang chủ) và kéo thả ảnh vào khung. Chỉ nhận PNG hoặc JPEG, tối đa 5 MB, mỗi chiều 16–8000 px. Ứng dụng kiểm tra nội dung tệp chứ không chỉ phần đuôi."],
        ['Đặt điểm góp ý', ['dat diem', 'diem', 'ghim', 'pin', 'danh dau', 'tao gop y', 'gop y moi', 'nhan xet'],
            "Mở màn hình, chọn công cụ Điểm (phím C) trên thanh công cụ nổi rồi bấm đúng chỗ cần sửa. Ô soạn hiện ngay cạnh dấu: viết nhận xét, chọn ưu tiên, nhãn, người phụ trách, hạn rồi bấm Lưu góp ý (hoặc Ctrl+Enter). Dấu được đánh số #1, #2… theo từng phiên bản."],
        ['Khoanh vùng chữ nhật', ['vung', 'khoanh', 'chu nhat', 'rect', 'keo chuot'],
            "Chọn công cụ Vùng (phím R), giữ chuột và kéo quanh khu vực cần góp ý, thả chuột rồi viết nhận xét. Vùng quá nhỏ (dưới 2 px ảnh gốc) sẽ không được tạo."],
        ['Toạ độ và phóng to', ['toa do', 'lech', 'phong to', 'thu nho', 'zoom', 'pixel', 'le trong'],
            "Mọi dấu lưu theo pixel của ẢNH GỐC, nên phóng to, thu nhỏ hay đổi kích thước cửa sổ đều không làm lệch. Bấm vào phần lề xám quanh ảnh sẽ bị từ chối. Phóng to bằng + / − / 0 hoặc cuộn chuột; giữ Shift hoặc chọn công cụ Kéo (V) để kéo ảnh."],
        ['Trả lời và sửa nhận xét', ['tra loi', 'reply', 'sua nhan xet', 'chinh sua', 'sua noi dung', 'binh luan'],
            "Bấm vào một dấu để mở chi tiết, gõ vào ô “Trả lời nhận xét này…” rồi bấm Gửi trả lời (Ctrl+Enter). Bạn chỉ sửa được nhận xét của chính mình bằng nút Sửa; bản cũ vẫn được lưu và nhận xét hiện chữ “đã sửa”."],
        ['Nhắc tên và thông báo', ['nhac ten', '@', 'mention', 'thong bao', 'chuong', 'bao tin', 'notification'],
            "Gõ @ trong ô nhận xét hoặc trả lời để chọn tên thành viên. Người được nhắc, người được giao việc, tác giả góp ý khi có trả lời mới hoặc khi góp ý đổi trạng thái sẽ nhận thông báo ở biểu tượng chuông trên đầu trang. Bấm “Đánh dấu đã đọc tất cả” để xoá số đếm."],
        ['Đã xử lý và mở lại', ['da xu ly', 'xu ly', 'mo lai', 'resolve', 'hoan thanh', 'trang thai', 'xac nhan'],
            "Trong chi tiết góp ý bấm nút xanh “Đánh dấu đã xử lý (kiểm tra trên vN)”. Phiên bản dùng để kiểm tra được ghi vào lịch sử. Nếu bản mới vẫn chưa đạt, bấm “Mở lại góp ý” kèm lý do."],
        ['Tải phiên bản mới', ['phien ban moi', 'version', 'ban moi', 'tai lai anh', 'cap nhat anh', 'v2', 'v3'],
            "Người có quyền Quản lý mở màn hình → bấm “Tải phiên bản mới”, kéo thả ảnh đã sửa và ghi “Đã sửa gì?”. Góp ý cũ vẫn thuộc phiên bản cũ, không bị chuyển sang ảnh mới. Sau khi tải, trang So sánh tự mở."],
        ['So sánh hai phiên bản', ['so sanh', 'compare', 'doi chieu', 'thanh truot', 'chong mo', 'canh nhau', 'swipe'],
            "Trang So sánh có 3 chế độ: Cạnh nhau (phím 1), Thanh trượt (phím 2 — kéo để thấy cũ/mới) và Chồng mờ (phím 3). Chọn tab “Góp ý trên v1” rồi bấm từng góp ý để kiểm tra trên bản mới và xác nhận đã xử lý."],
        ['Xoá ảnh tải nhầm', ['xoa anh', 'xoa phien ban', 'tai nham', 'xoa man hinh', 'xoa du an', 'xoa', 'delete', 'go bo'],
            "Người có quyền Quản lý mở màn hình → nút ⚙ (Thêm thao tác):\n• “Xoá phiên bản đang xem” — bỏ ảnh tải nhầm cùng các góp ý trên phiên bản đó (không xoá được nếu chỉ còn 1 phiên bản).\n• “Xoá màn hình” — xoá màn hình và mọi phiên bản.\nXoá dự án: trang dự án → ⚙ → Vùng nguy hiểm, chỉ người tạo dự án, phải gõ lại tên dự án để xác nhận. Thao tác xoá không hoàn tác được."],
        ['Ghi chú xử lý', ['ghi chu', 'note', 'cach sua', 'thong so'],
            "Mỗi góp ý có khối Ghi chú xử lý để ghi cách sửa, thông số, liên kết tài liệu. Bấm “Thêm ghi chú” / “Sửa ghi chú”. Nếu người khác vừa sửa cùng lúc, ứng dụng báo xung đột và vẫn giữ chữ bạn đã gõ."],
        ['Ưu tiên, nhãn, người phụ trách, hạn', ['uu tien', 'nhan', 'phu trach', 'giao viec', 'han', 'deadline', 'qua han', 'ngay'],
            "Trong khối Thuộc tính của góp ý: chọn Ưu tiên (Thấp → Khẩn cấp), Nhãn (Bố cục, Màu sắc…), Người phụ trách và Hạn xử lý (định dạng ngày/tháng/năm, bấm biểu tượng lịch để chọn). Góp ý chưa xử lý mà quá hạn sẽ hiện nhãn đỏ “Quá hạn”."],
        ['Lọc và tìm góp ý', ['loc', 'tim', 'search', 'chua xu ly', 'filter', 'sap xep'],
            "Trong trình xem: ô tìm kiếm (phím /), các nút Tất cả / Chưa xử lý / Đã xử lý và bộ lọc người góp ý, ưu tiên, nhãn, “Chỉ góp ý giao cho tôi”. Tìm trong cả dự án: trang dự án → “Góp ý chưa xử lý”, lọc thêm theo người phụ trách, quá hạn, màn hình."],
        ['Xuất danh sách', ['xuat', 'csv', 'excel', 'export', 'in', 'pdf', 'bao cao'],
            "Trang Tìm góp ý → “Xuất CSV” để tải đúng danh sách đang lọc (22 cột: nội dung, vị trí, người viết, trạng thái, phiên bản, ưu tiên, nhãn, phụ trách, hạn, ghi chú…). Mở bằng Excel hoặc LibreOffice. Nút “In / PDF” để in hoặc lưu PDF."],
        ['Việc của tôi', ['viec cua toi', 'duoc giao', 'inbox', 'cong viec'],
            "Mục “Việc của tôi” trên thanh menu liệt kê mọi góp ý chưa xử lý được giao cho bạn, chia nhóm Quá hạn / Đến hạn trong 3 ngày / Còn lại."],
        ['Xung đột khi sửa', ['xung dot', 'conflict', 'ghi de', 'cung luc', 'dong thoi'],
            "Khi hai người sửa cùng một nhận xét hoặc ghi chú, người lưu sau sẽ thấy thông báo Xung đột kèm nội dung mới nhất, chữ mình gõ vẫn còn. Bấm “Lưu bản của tôi” nếu vẫn muốn thay. Trả lời thì không bao giờ bị mất vì mỗi trả lời là một bản ghi mới."],
        ['Hồ sơ và mật khẩu', ['ho so', 'profile', 'doi ten', 'mat khau', 'password', 'doi mat khau', 'tai khoan'],
            "Bấm ảnh đại diện ở góc phải → Hồ sơ của tôi để đổi họ tên, chức danh và đổi mật khẩu (cần mật khẩu hiện tại). Quên mật khẩu: gửi yêu cầu ở trang Hỗ trợ (mục Tài khoản) để được cấp lại."],
        ['Giao diện tối và phím tắt', ['giao dien toi', 'dark', 'che do toi', 'phim tat', 'shortcut', 'ban phim'],
            "Bấm biểu tượng mặt trăng/mặt trời trên đầu trang để đổi giao diện sáng/tối. Trong trình xem bấm ? để xem phím tắt: C điểm, R vùng, V kéo, ←/→ góp ý trước/sau, M ẩn dấu, / tìm, \\ ẩn bảng góp ý."],
        ['Không có công cụ góp ý', ['khong thay cong cu', 'khong tao duoc', 'chi xem', 'khong co quyen', 'forbidden', 'bi chan'],
            "Nếu không thấy công cụ Điểm/Vùng, bạn đang có quyền Chỉ xem. Nhờ Quản lý dự án đổi quyền của bạn sang Góp ý."],
        ['Cài đặt và chạy', ['cai dat', 'chay', 'lab.sh', 'localhost', 'postgres', 'csdl', 'database', 'wsl', 'ubuntu', 'loi ket noi'],
            "Trong Ubuntu/WSL: cd Ma_nguon → bash lab.sh db-config → bash lab.sh check → bash lab.sh setup → bash lab.sh run, rồi mở http://127.0.0.1:8115. Lỗi kết nối CSDL: bật dịch vụ bằng “sudo service postgresql start” và chạy lại db-config. Chi tiết trong HUONG_DAN.md."],
        ['Tài khoản dữ liệu mẫu', ['tai khoan mau', 'demo', 'dang nhap', 'designer', 'reviewer', 'viewer'],
            "Tài khoản mẫu: designer@pinpoint.test (Quản lý), reviewer@pinpoint.test (Góp ý), viewer@pinpoint.test (Chỉ xem dự án A). Mật khẩu chung Pinpoint@123."],
        ['Liên hệ hỗ trợ', ['ho tro', 'lien he', 'support', 'gap nguoi', 'bao loi', 'loi'],
            "Mở trang Hỗ trợ (menu trên cùng) → “Gửi yêu cầu hỗ trợ”, chọn loại (Hỏi cách dùng, Báo lỗi, Góp ý tính năng, Tài khoản), mô tả rõ và gửi. Bạn theo dõi trạng thái ở mục “Yêu cầu của tôi”."],
    ];

    /** Bỏ dấu tiếng Việt và viết thường để so khớp. */
    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $map = [
            'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ', 'o' => 'òóọỏõôồốộổỗơờớợởỡ',
            'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
        ];
        foreach ($map as $plain => $chars) {
            $s = str_replace(mb_str_split($chars), $plain, $s);
        }
        $s = (string) preg_replace('/[^a-z0-9@]+/', ' ', $s);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** Trả lời bằng cơ sở tri thức trên máy. @return array{answer:string,topic:string,suggestions:list<string>,source:string} */
    public static function local(string $question): array
    {
        $q = ' ' . self::fold($question) . ' ';
        $best = null;
        $bestScore = 0;
        foreach (self::KB as $i => [$topic, $keys]) {
            $score = 0;
            foreach ($keys as $k) {
                if (str_contains($q, ' ' . self::fold($k) . ' ') || ($k === '@' && str_contains($q, '@'))) {
                    $score += 1 + substr_count($k, ' '); // cụm nhiều từ khớp thì được điểm cao hơn
                }
            }
            if (str_contains($q, ' ' . self::fold($topic) . ' ')) {
                $score += 3;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $i;
            }
        }
        if ($best === null) {
            return [
                'answer' => "Mình chưa hiểu rõ câu hỏi. Bạn có thể hỏi về: tạo dự án, mời thành viên, đặt điểm/khoanh vùng, trả lời, ghi chú, "
                    . "đánh dấu đã xử lý, tải phiên bản mới, so sánh, xoá ảnh, xuất CSV, thông báo… Hoặc gửi yêu cầu ở trang Hỗ trợ để được người hỗ trợ trả lời.",
                'topic' => '', 'suggestions' => ['Cách đặt điểm góp ý?', 'Xoá ảnh tải nhầm thế nào?', 'So sánh hai phiên bản'], 'source' => 'local',
            ];
        }
        [$topic, , $answer] = self::KB[$best];
        $related = [];
        foreach ([1, 2, 3] as $d) {
            $j = ($best + $d) % count(self::KB);
            $related[] = self::KB[$j][0];
        }
        return ['answer' => $answer, 'topic' => $topic, 'suggestions' => $related, 'source' => 'local'];
    }

    /**
     * Trả lời câu hỏi. $history là các lượt trước [{role:user|assistant, text}] (tối đa 6 lượt được dùng).
     */
    public static function answer(string $question, array $history = []): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > 500) {
            throw new AppError('CHAT_INVALID', 422);
        }
        $key = getenv('PINPOINT_AI_KEY');
        if (is_string($key) && $key !== '') {
            $ai = self::remote($key, $question, $history);
            if ($ai !== null) {
                return ['answer' => $ai, 'topic' => '', 'suggestions' => [], 'source' => 'ai'];
            }
        }
        return self::local($question);
    }

    private static function remote(string $key, string $question, array $history): ?string
    {
        $kb = implode("\n", array_map(fn ($r) => '## ' . $r[0] . "\n" . $r[2], self::KB));
        $messages = [];
        foreach (array_slice($history, -6) as $h) {
            if (isset($h['role'], $h['text']) && in_array($h['role'], ['user', 'assistant'], true) && is_string($h['text'])) {
                $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['text'], 0, 1000)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $question];
        while ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }
        $payload = [
            'model' => getenv('PINPOINT_AI_MODEL') ?: 'claude-haiku-5-5',
            'max_tokens' => 600,
            'system' => "Bạn là trợ lý hỗ trợ khách hàng của ứng dụng Pinpoint (góp ý trực tiếp trên ảnh thiết kế giao diện). "
                . "Chỉ trả lời câu hỏi về cách dùng ứng dụng, bằng tiếng Việt, ngắn gọn, dựa trên tài liệu dưới đây. "
                . "Nếu câu hỏi nằm ngoài phạm vi hoặc tài liệu không có, nói rõ và gợi ý gửi yêu cầu ở trang Hỗ trợ.\n\n" . $kb,
            'messages' => $messages,
        ];
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 20, 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\nx-api-key: $key\r\nanthropic-version: 2023-06-01\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]]);
        $raw = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);
        if ($raw === false) {
            error_log('Pinpoint assistant: AI request failed');
            return null;
        }
        $data = json_decode($raw, true);
        $text = $data['content'][0]['text'] ?? null;
        if (!is_string($text) || $text === '') {
            error_log('Pinpoint assistant: AI error ' . (is_array($data) ? ($data['error']['type'] ?? 'unknown') : 'invalid'));
            return null;
        }
        return trim($text);
    }
}
