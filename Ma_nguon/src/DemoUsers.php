<?php

declare(strict_types=1);

/**
 * Bản 2.6 — tạo người dùng ảo để thử trang quản trị với danh sách đủ dài (phân trang, tìm, lọc, khoá hàng loạt).
 * Tên ghép từ họ / tên đệm / tên phổ biến của Việt Nam; email dạng ho.ten.NNN@demo.pinpoint.test nên không đụng tài khoản thật.
 * Dùng bộ sinh số cố định (mt_srand) nên chạy trên máy nào cũng ra cùng một danh sách; chạy lại không tạo trùng.
 * Một phần người dùng được: thêm vào các dự án mẫu, gán ngày tạo / lần đăng nhập rải trong 120 ngày, khoá, cấp quyền hỗ trợ.
 */
final class DemoUsers
{
    public const DOMAIN = 'demo.pinpoint.test';

    private const HO = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý', 'Đinh', 'Trịnh'];
    private const DEM_NAM = ['Văn', 'Minh', 'Đức', 'Quang', 'Hữu', 'Thành', 'Gia', 'Anh', 'Tuấn', 'Công'];
    private const DEM_NU = ['Thị', 'Thu', 'Ngọc', 'Thanh', 'Bảo', 'Phương', 'Khánh', 'Mai', 'Hồng', 'Diệu'];
    private const TEN_NAM = ['An', 'Bình', 'Cường', 'Dũng', 'Hải', 'Hiếu', 'Hùng', 'Khoa', 'Long', 'Nam', 'Phúc', 'Quân', 'Sơn', 'Tâm', 'Thắng', 'Trung', 'Tú', 'Vinh'];
    private const TEN_NU = ['Anh', 'Chi', 'Dung', 'Giang', 'Hà', 'Hạnh', 'Hương', 'Lan', 'Linh', 'Ly', 'My', 'Ngân', 'Nhung', 'Oanh', 'Quỳnh', 'Thảo', 'Trang', 'Vy', 'Yến'];
    private const CHUC_DANH = ['Thiết kế UI', 'Thiết kế UX', 'Lập trình Front-end', 'Lập trình Back-end', 'Kiểm thử', 'Quản lý dự án',
        'Sinh viên', 'Giảng viên', 'Chuyên viên nội dung', 'Phân tích nghiệp vụ', ''];
    private const LY_DO_KHOA = ['Đăng nhập sai nhiều lần', 'Đã nghỉ việc', 'Tài khoản trùng', 'Theo yêu cầu của người dùng', 'Vi phạm quy định nhóm'];

    /** Bỏ dấu tiếng Việt để làm phần tên của email. */
    public static function slug(string $s): string
    {
        // Bảng tự viết, không cần extension intl (máy WSL của sinh viên thường không cài).
        $map = ['a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ', 'o' => 'òóọỏõôồốộổỗơờớợởỡ',
            'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ'];
        $s = mb_strtolower(trim($s));
        foreach ($map as $to => $chars) {
            $s = str_replace(mb_str_split($chars), $to, $s);
        }
        return trim((string) preg_replace('/[^a-z0-9]+/', '.', $s), '.');
    }

    /**
     * Sinh $n người dùng ảo (không ghi CSDL) — tách riêng để kiểm thử đơn vị.
     * @return list<array{name:string,email:string,title:string}>
     */
    public static function generate(int $n = 100): array
    {
        mt_srand(2026);
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $female = mt_rand(0, 1) === 1;
            $ho = self::HO[mt_rand(0, count(self::HO) - 1)];
            $dem = $female ? self::DEM_NU[mt_rand(0, count(self::DEM_NU) - 1)] : self::DEM_NAM[mt_rand(0, count(self::DEM_NAM) - 1)];
            $ten = $female ? self::TEN_NU[mt_rand(0, count(self::TEN_NU) - 1)] : self::TEN_NAM[mt_rand(0, count(self::TEN_NAM) - 1)];
            $out[] = [
                'name' => "$ho $dem $ten",
                'email' => self::slug("$ten.$ho") . '.' . sprintf('%03d', $i) . '@' . self::DOMAIN,
                'title' => self::CHUC_DANH[mt_rand(0, count(self::CHUC_DANH) - 1)],
            ];
        }
        mt_srand();
        return $out;
    }

    /** Ghi vào CSDL. @return array{created:int,skipped:int,locked:int,support:int,members:int} */
    public static function run(PgSql\Connection $c, int $n = 100, string $password = 'Pinpoint@123'): array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT); // băm một lần cho cả 100 tài khoản cho nhanh
        $projects = array_map(fn ($r) => (int) $r['id'], fetchAll($c, 'SELECT id FROM projects ORDER BY id'));
        $owner = fetchOne($c, 'SELECT id FROM users WHERE is_admin ORDER BY id LIMIT 1');
        $stat = ['created' => 0, 'skipped' => 0, 'locked' => 0, 'support' => 0, 'members' => 0];
        mt_srand(4242);
        queryParams($c, 'BEGIN');
        try {
            foreach (self::generate($n) as $i => $p) {
                if (fetchOne($c, 'SELECT 1 FROM users WHERE email = $1', [$p['email']]) !== null) {
                    $stat['skipped']++;
                    continue;
                }
                $createdDays = mt_rand(1, 120);
                $loginRoll = mt_rand(1, 10); // 1–2: chưa đăng nhập; 3–4: lâu rồi (>30 ngày); còn lại: gần đây
                $lastLogin = $loginRoll <= 2 ? null : ($loginRoll <= 4 ? mt_rand(31, $createdDays + 31) : mt_rand(0, min(29, $createdDays)));
                $locked = $i % 14 === 6;
                $row = fetchOne($c,
                    "INSERT INTO users (email, name, password_hash, title, created_at, last_login_at, is_support, locked_at, locked_reason)
                     VALUES ($1, $2, $3, $4, now() - ($5::int * interval '1 day') - ($6::int * interval '1 minute'),
                             CASE WHEN $7::int IS NULL THEN NULL ELSE now() - ($7::int * interval '1 day') - ($6::int * interval '1 minute') END,
                             $8, CASE WHEN $9 THEN now() - interval '2 days' END, $10) RETURNING id",
                    [$p['email'], $p['name'], $hash, $p['title'], $createdDays, mt_rand(0, 1439), $lastLogin,
                        $i % 25 === 3 ? 't' : 'f', $locked ? 't' : 'f', $locked ? self::LY_DO_KHOA[$i % count(self::LY_DO_KHOA)] : '']);
                $stat['created']++;
                $stat['locked'] += $locked ? 1 : 0;
                $stat['support'] += $i % 25 === 3 ? 1 : 0;
                // Khoảng 40% người dùng tham gia một dự án mẫu với quyền Góp ý hoặc Chỉ xem.
                if ($projects !== [] && mt_rand(1, 10) <= 4) {
                    queryParams($c, 'INSERT INTO project_members (project_id, user_id, role, added_by) VALUES ($1, $2, $3, $4) ON CONFLICT DO NOTHING',
                        [$projects[mt_rand(0, count($projects) - 1)], (int) $row['id'], mt_rand(0, 2) === 0 ? 'VIEW' : 'COMMENT', $owner['id'] ?? null]);
                    $stat['members']++;
                }
            }
            queryParams($c, 'COMMIT');
        } catch (Throwable $e) {
            queryParams($c, 'ROLLBACK');
            throw $e;
        } finally {
            mt_srand();
        }
        return $stat;
    }
}
