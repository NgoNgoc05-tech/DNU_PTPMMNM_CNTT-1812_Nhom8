-- Pinpoint: góp ý trực tiếp trên ảnh thiết kế.
-- Chạy trong CSDL riêng của nhóm (không dùng CSDL production).
-- Toạ độ annotation lưu theo PIXEL CỦA ẢNH GỐC (không phải pixel màn hình),
-- nên không phụ thuộc kích thước hiển thị, mức phóng to hay phần lề trống.

CREATE TABLE IF NOT EXISTS users (
    id            bigserial PRIMARY KEY,
    email         varchar(200) NOT NULL UNIQUE CHECK (email = lower(trim(email))),
    name          varchar(120) NOT NULL CHECK (length(trim(name)) > 0),
    password_hash varchar(255) NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS projects (
    id          bigserial PRIMARY KEY,
    name        varchar(150) NOT NULL CHECK (length(trim(name)) > 0),
    description text NOT NULL DEFAULT '',
    created_by  bigint NOT NULL REFERENCES users(id),
    created_at  timestamptz NOT NULL DEFAULT now()
);

-- Bảng trung gian N-N giữa users và projects, kèm quyền.
-- VIEW: chỉ xem; COMMENT: xem + góp ý/trả lời/đổi trạng thái; ADMIN: quản lý dự án.
CREATE TABLE IF NOT EXISTS project_members (
    project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id    bigint NOT NULL REFERENCES users(id),
    role       varchar(10) NOT NULL CHECK (role IN ('VIEW', 'COMMENT', 'ADMIN')),
    added_by   bigint REFERENCES users(id),
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (project_id, user_id)
);

-- Màn hình thiết kế (Trang chủ, Giỏ hàng...) thuộc một dự án.
CREATE TABLE IF NOT EXISTS screens (
    id         bigserial PRIMARY KEY,
    project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name       varchar(120) NOT NULL CHECK (length(trim(name)) > 0),
    created_by bigint NOT NULL REFERENCES users(id),
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (project_id, name)
);

-- Mỗi lần tải ảnh tạo một phiên bản mới; phiên bản cũ không bị ghi đè.
CREATE TABLE IF NOT EXISTS image_versions (
    id            bigserial PRIMARY KEY,
    screen_id     bigint NOT NULL REFERENCES screens(id) ON DELETE CASCADE,
    version_no    integer NOT NULL CHECK (version_no > 0),
    stored_name   varchar(80) NOT NULL UNIQUE,
    original_name varchar(255) NOT NULL,
    mime_type     varchar(20) NOT NULL CHECK (mime_type IN ('image/png', 'image/jpeg')),
    width         integer NOT NULL CHECK (width > 0),
    height        integer NOT NULL CHECK (height > 0),
    byte_size     integer NOT NULL CHECK (byte_size > 0),
    sha256        char(64) NOT NULL,
    note          varchar(255) NOT NULL DEFAULT '',
    uploaded_by   bigint NOT NULL REFERENCES users(id),
    created_at    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (screen_id, version_no)
);

-- Dấu vị trí: điểm (POINT) hoặc vùng chữ nhật (RECT), gắn CỐ ĐỊNH với một phiên bản ảnh.
CREATE TABLE IF NOT EXISTS annotations (
    id               bigserial PRIMARY KEY,
    image_version_id bigint NOT NULL REFERENCES image_versions(id) ON DELETE CASCADE,
    seq_no           integer NOT NULL CHECK (seq_no > 0),
    shape            varchar(5) NOT NULL CHECK (shape IN ('POINT', 'RECT')),
    x                numeric(10, 2) NOT NULL CHECK (x >= 0),
    y                numeric(10, 2) NOT NULL CHECK (y >= 0),
    w                numeric(10, 2),
    h                numeric(10, 2),
    status           varchar(10) NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN', 'RESOLVED')),
    created_by       bigint NOT NULL REFERENCES users(id),
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now(),
    lock_version     integer NOT NULL DEFAULT 1,
    UNIQUE (image_version_id, seq_no),
    CHECK ((shape = 'POINT' AND w IS NULL AND h IS NULL)
        OR (shape = 'RECT' AND w > 0 AND h > 0))
);

-- Nhận xét gốc (parent_id IS NULL) và các trả lời (parent_id trỏ tới nhận xét gốc).
CREATE TABLE IF NOT EXISTS comments (
    id            bigserial PRIMARY KEY,
    annotation_id bigint NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    parent_id     bigint REFERENCES comments(id) ON DELETE CASCADE,
    author_id     bigint NOT NULL REFERENCES users(id),
    body          text NOT NULL CHECK (length(trim(body)) BETWEEN 1 AND 2000),
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    lock_version  integer NOT NULL DEFAULT 1
);
CREATE UNIQUE INDEX IF NOT EXISTS comments_one_root ON comments (annotation_id) WHERE parent_id IS NULL;

-- Lịch sử sửa nội dung nhận xét (giữ bản cũ, không mất dữ liệu).
CREATE TABLE IF NOT EXISTS comment_revisions (
    id         bigserial PRIMARY KEY,
    comment_id bigint NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    old_body   text NOT NULL,
    edited_by  bigint NOT NULL REFERENCES users(id),
    edited_at  timestamptz NOT NULL DEFAULT now()
);

-- Lịch sử trạng thái OPEN <-> RESOLVED. checked_version_id ghi nhận phiên bản ảnh
-- đã được dùng để kiểm tra sau khi sửa (ví dụ: góp ý trên v1, xác nhận trên v2).
CREATE TABLE IF NOT EXISTS status_history (
    id                 bigserial PRIMARY KEY,
    annotation_id      bigint NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    from_status        varchar(10) CHECK (from_status IN ('OPEN', 'RESOLVED')),
    to_status          varchar(10) NOT NULL CHECK (to_status IN ('OPEN', 'RESOLVED')),
    changed_by         bigint NOT NULL REFERENCES users(id),
    checked_version_id bigint REFERENCES image_versions(id) ON DELETE SET NULL,
    note               varchar(500) NOT NULL DEFAULT '',
    created_at         timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS annotations_version_status ON annotations (image_version_id, status);
CREATE INDEX IF NOT EXISTS comments_annotation ON comments (annotation_id, id);
CREATE INDEX IF NOT EXISTS status_history_annotation ON status_history (annotation_id, id);

-- ===================== Phiên bản 2.0: thuộc tính góp ý, ghi chú, cảm xúc, nhật ký =====================
-- Dùng ADD COLUMN IF NOT EXISTS để CSDL tạo từ bản 1.0 nâng cấp được bằng `bash lab.sh setup`.

-- Mức ưu tiên, nhãn phân loại, người phụ trách, hạn xử lý và GHI CHÚ XỬ LÝ của từng góp ý.
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS priority varchar(6) NOT NULL DEFAULT 'MEDIUM'
    CHECK (priority IN ('LOW', 'MEDIUM', 'HIGH', 'URGENT'));
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS category varchar(8) NOT NULL DEFAULT 'OTHER'
    CHECK (category IN ('LAYOUT', 'COLOR', 'TYPO', 'CONTENT', 'UX', 'A11Y', 'OTHER'));
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS assignee_id bigint REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS due_date date;
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS note text NOT NULL DEFAULT '' CHECK (length(note) <= 4000);
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS note_updated_by bigint REFERENCES users(id);
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS note_updated_at timestamptz;

-- Thả cảm xúc vào nhận xét/trả lời (mỗi người tối đa một lần cho mỗi loại).
CREATE TABLE IF NOT EXISTS comment_reactions (
    comment_id bigint NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    user_id    bigint NOT NULL REFERENCES users(id),
    kind       varchar(6) NOT NULL CHECK (kind IN ('LIKE', 'HEART', 'PARTY', 'EYES')),
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (comment_id, user_id, kind)
);

-- Nhật ký hoạt động của góp ý: mọi thay đổi (tạo, trả lời, sửa, trạng thái, ưu tiên,
-- nhãn, người phụ trách, hạn, ghi chú) đều được ghi lại, kèm giá trị cũ và mới.
CREATE TABLE IF NOT EXISTS annotation_events (
    id            bigserial PRIMARY KEY,
    annotation_id bigint NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    actor_id      bigint NOT NULL REFERENCES users(id),
    kind          varchar(10) NOT NULL CHECK (kind IN ('CREATED', 'REPLIED', 'EDITED', 'STATUS', 'PRIORITY',
                                                       'CATEGORY', 'ASSIGNEE', 'DUE', 'NOTE')),
    old_value     varchar(500) NOT NULL DEFAULT '',
    new_value     varchar(500) NOT NULL DEFAULT '',
    detail        varchar(500) NOT NULL DEFAULT '',
    created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS annotation_events_annotation ON annotation_events (annotation_id, id);
CREATE INDEX IF NOT EXISTS annotations_assignee ON annotations (assignee_id) WHERE assignee_id IS NOT NULL;

-- ===================== Phiên bản 2.1: thông báo, hỗ trợ khách hàng, hồ sơ =====================

-- Thông báo cho người dùng: được @nhắc tên, được giao việc, có trả lời mới, góp ý của mình đổi trạng thái.
CREATE TABLE IF NOT EXISTS notifications (
    id            bigserial PRIMARY KEY,
    user_id       bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    actor_id      bigint REFERENCES users(id) ON DELETE SET NULL,
    kind          varchar(10) NOT NULL CHECK (kind IN ('MENTION', 'ASSIGNED', 'REPLY', 'STATUS')),
    annotation_id bigint REFERENCES annotations(id) ON DELETE CASCADE,
    body          varchar(300) NOT NULL DEFAULT '',
    created_at    timestamptz NOT NULL DEFAULT now(),
    read_at       timestamptz
);
CREATE INDEX IF NOT EXISTS notifications_user_unread ON notifications (user_id, read_at, id DESC);

-- Yêu cầu hỗ trợ khách hàng gửi từ trang Hỗ trợ.
CREATE TABLE IF NOT EXISTS support_tickets (
    id         bigserial PRIMARY KEY,
    user_id    bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category   varchar(10) NOT NULL DEFAULT 'QUESTION' CHECK (category IN ('QUESTION', 'BUG', 'IDEA', 'ACCOUNT')),
    subject    varchar(150) NOT NULL CHECK (length(trim(subject)) > 0),
    message    text NOT NULL CHECK (length(trim(message)) BETWEEN 1 AND 4000),
    status     varchar(10) NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN', 'ANSWERED', 'CLOSED')),
    reply      text NOT NULL DEFAULT '',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- Hồ sơ: chức danh / giới thiệu ngắn hiển thị cho thành viên cùng dự án.
ALTER TABLE users ADD COLUMN IF NOT EXISTS title varchar(120) NOT NULL DEFAULT '';

-- ===================== Phiên bản 2.2: lời mời tham gia dự án cần người được mời xác nhận =====================
CREATE TABLE IF NOT EXISTS project_invitations (
    id           bigserial PRIMARY KEY,
    project_id   bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id      bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role         varchar(10) NOT NULL CHECK (role IN ('VIEW', 'COMMENT', 'ADMIN')),
    invited_by   bigint REFERENCES users(id) ON DELETE SET NULL,
    status       varchar(10) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'ACCEPTED', 'DECLINED', 'CANCELLED')),
    message      varchar(300) NOT NULL DEFAULT '',
    created_at   timestamptz NOT NULL DEFAULT now(),
    responded_at timestamptz
);
-- Mỗi người chỉ có tối đa một lời mời đang chờ cho mỗi dự án.
CREATE UNIQUE INDEX IF NOT EXISTS project_invitations_one_pending ON project_invitations (project_id, user_id) WHERE status = 'PENDING';

-- Thông báo về lời mời: gắn với dự án thay vì góp ý.
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS project_id bigint REFERENCES projects(id) ON DELETE CASCADE;
-- Loại thông báo mới dài hơn 10 ký tự (INVITE_REPLY, TICKET_REPLY).
ALTER TABLE notifications ALTER COLUMN kind TYPE varchar(20);

-- Bộ phận hỗ trợ: tài khoản có is_support = true xem và trả lời mọi yêu cầu hỗ trợ (không cần thuộc dự án nào).
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_support boolean NOT NULL DEFAULT false;
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS handled_by bigint REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS ticket_id bigint REFERENCES support_tickets(id) ON DELETE CASCADE;
ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_kind_check;
ALTER TABLE notifications ADD CONSTRAINT notifications_kind_check
    CHECK (kind IN ('MENTION', 'ASSIGNED', 'REPLY', 'STATUS', 'INVITE', 'INVITE_REPLY', 'TICKET', 'TICKET_REPLY'));

-- =====================================================================
-- Bản 2.3: mũi tên / vẽ tay, ảnh đính kèm trả lời, quên mật khẩu, quản trị hệ thống
-- =====================================================================

-- Mũi tên (ARROW: [[x1,y1],[x2,y2]]) và nét vẽ tay (DRAW: tối đa 500 điểm), theo pixel ảnh gốc.
ALTER TABLE annotations ADD COLUMN IF NOT EXISTS points jsonb;
ALTER TABLE annotations DROP CONSTRAINT IF EXISTS annotations_shape_check;
ALTER TABLE annotations DROP CONSTRAINT IF EXISTS annotations_check;
ALTER TABLE annotations DROP CONSTRAINT IF EXISTS annotations_geometry_check;
ALTER TABLE annotations ADD CONSTRAINT annotations_shape_check CHECK (shape IN ('POINT', 'RECT', 'ARROW', 'DRAW'));
ALTER TABLE annotations ADD CONSTRAINT annotations_geometry_check CHECK (
       (shape = 'POINT' AND w IS NULL AND h IS NULL AND points IS NULL)
    OR (shape = 'RECT' AND w > 0 AND h > 0 AND points IS NULL)
    OR (shape IN ('ARROW', 'DRAW') AND w IS NULL AND h IS NULL AND points IS NOT NULL AND jsonb_typeof(points) = 'array'));

-- Ảnh đính kèm trong nhận xét / trả lời (ảnh chụp màn hình), lưu như ảnh thiết kế: tên tệp ngẫu nhiên, kiểm tra quyền khi phát.
CREATE TABLE IF NOT EXISTS comment_attachments (
    id            bigserial PRIMARY KEY,
    comment_id    bigint NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    stored_name   varchar(80) NOT NULL UNIQUE,
    original_name varchar(255) NOT NULL,
    mime_type     varchar(20) NOT NULL CHECK (mime_type IN ('image/png', 'image/jpeg')),
    width         integer NOT NULL CHECK (width > 0),
    height        integer NOT NULL CHECK (height > 0),
    byte_size     integer NOT NULL CHECK (byte_size > 0),
    uploaded_by   bigint NOT NULL REFERENCES users(id),
    created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS comment_attachments_comment ON comment_attachments (comment_id);

-- Quản trị hệ thống và khoá tài khoản.
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin boolean NOT NULL DEFAULT false;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_at timestamptz;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_reason varchar(300) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at timestamptz;

-- Đặt lại mật khẩu: chỉ lưu SHA-256 của mã; mã dùng một lần, hết hạn sau 30 phút.
CREATE TABLE IF NOT EXISTS password_resets (
    id          bigserial PRIMARY KEY,
    user_id     bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  char(64) NOT NULL UNIQUE,
    created_by  bigint REFERENCES users(id) ON DELETE SET NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    expires_at  timestamptz NOT NULL,
    used_at     timestamptz
);
CREATE INDEX IF NOT EXISTS password_resets_user ON password_resets (user_id);
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS is_password_reset boolean NOT NULL DEFAULT false;

-- Nhật ký thao tác quản trị (ai khoá ai, xoá dự án nào...).
CREATE TABLE IF NOT EXISTS admin_log (
    id         bigserial PRIMARY KEY,
    admin_id   bigint REFERENCES users(id) ON DELETE SET NULL,
    action     varchar(30) NOT NULL,
    target     varchar(200) NOT NULL DEFAULT '',
    detail     varchar(300) NOT NULL DEFAULT '',
    created_at timestamptz NOT NULL DEFAULT now()
);
