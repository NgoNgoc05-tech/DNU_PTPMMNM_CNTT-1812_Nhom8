<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/AppError.php';
require_once __DIR__ . '/Input.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Access.php';
require_once __DIR__ . '/Coordinates.php';
require_once __DIR__ . '/ImageStore.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ProjectRepository.php';
require_once __DIR__ . '/ScreenRepository.php';
require_once __DIR__ . '/AnnotationRepository.php';
require_once __DIR__ . '/Export.php';
require_once __DIR__ . '/Seeder.php';

/** Thông báo tiếng Việt cho các mã lỗi nghiệp vụ. */
function errorMessage(string $code): string
{
    return [
        'NOT_FOUND' => 'Không tìm thấy hoặc bạn không thuộc dự án này.',
        'FORBIDDEN' => 'Bạn không có quyền thực hiện thao tác này.',
        'CONFLICT' => 'Nội dung đã được người khác thay đổi. Hãy tải lại để xem bản mới nhất trước khi sửa.',
        'DUPLICATE' => 'Tên này đã tồn tại.',
        'BODY_INVALID' => 'Nội dung nhận xét phải từ 1 đến 2000 ký tự.',
        'NAME_INVALID' => 'Tên không hợp lệ (1–120 ký tự).',
        'POSITION_OUTSIDE_IMAGE' => 'Vị trí nằm ngoài ảnh. Hãy chọn bên trong vùng ảnh.',
        'RECT_TOO_SMALL' => 'Vùng chọn quá nhỏ.',
        'SHAPE_INVALID' => 'Kiểu dấu không hợp lệ.',
        'IMAGE_MISSING' => 'Chưa chọn ảnh.',
        'IMAGE_TOO_LARGE' => 'Ảnh vượt quá 5 MB.',
        'IMAGE_TYPE_NOT_ALLOWED' => 'Chỉ chấp nhận ảnh PNG hoặc JPEG.',
        'IMAGE_DIMENSIONS_INVALID' => 'Kích thước ảnh phải từ 16 đến 8000 px mỗi chiều.',
        'USER_NOT_FOUND' => 'Chưa có tài khoản dùng email này. Người được mời cần đăng ký trước.',
        'ROLE_INVALID' => 'Quyền không hợp lệ.',
        'OWNER_MUST_STAY_ADMIN' => 'Người tạo dự án luôn giữ quyền Quản lý.',
        'STATUS_INVALID' => 'Trạng thái không hợp lệ.',
        'STATUS_UNCHANGED' => 'Góp ý đã ở trạng thái này.',
        'CHECKED_VERSION_INVALID' => 'Phiên bản kiểm tra phải thuộc cùng màn hình.',
        'EMAIL_INVALID' => 'Email không hợp lệ.',
        'PASSWORD_TOO_SHORT' => 'Mật khẩu cần ít nhất 8 ký tự.',
        'PRIORITY_INVALID' => 'Mức ưu tiên không hợp lệ.',
        'CATEGORY_INVALID' => 'Nhãn phân loại không hợp lệ.',
        'ASSIGNEE_NOT_MEMBER' => 'Người phụ trách phải là thành viên của dự án.',
        'DUE_DATE_INVALID' => 'Hạn xử lý không hợp lệ (định dạng năm-tháng-ngày).',
        'NOTE_TOO_LONG' => 'Ghi chú tối đa 4000 ký tự.',
        'REACTION_INVALID' => 'Cảm xúc không hợp lệ.',
    ][$code] ?? 'Đã có lỗi xảy ra. Vui lòng thử lại.';
}

/** Gắn header bảo mật cho mọi trang. */
function securityHeaders(): void
{
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
