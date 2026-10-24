<?php

declare(strict_types=1);

/**
 * Nhận và lưu ảnh thiết kế. Chỉ chấp nhận PNG/JPEG thật (kiểm tra nội dung tệp,
 * không tin phần mở rộng hay Content-Type do trình duyệt gửi), giới hạn dung lượng
 * và kích thước. Ảnh lưu NGOÀI thư mục public với tên ngẫu nhiên; chỉ phát qua
 * public/image.php sau khi kiểm tra quyền.
 */
final class ImageStore
{
    public const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    public const MAX_SIDE = 8000;
    public const MIN_SIDE = 16;
    public const TYPES = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg'];

    public static function directory(): string
    {
        $dir = getenv('PINPOINT_STORAGE') ?: __DIR__ . '/../storage/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('STORAGE_UNAVAILABLE');
        }
        return $dir;
    }

    /**
     * Kiểm tra một tệp. $path là đường dẫn tạm, $size là dung lượng (byte).
     * @return array{mime:string,width:int,height:int,byte_size:int,sha256:string,ext:string}
     */
    public static function inspect(string $path, int $size): array
    {
        if ($size <= 0 || !is_file($path)) {
            throw new AppError('IMAGE_MISSING', 422);
        }
        if ($size > self::MAX_BYTES) {
            throw new AppError('IMAGE_TOO_LARGE', 422);
        }
        $info = @getimagesize($path);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new AppError('IMAGE_TYPE_NOT_ALLOWED', 422);
        }
        $mime = self::TYPES[$info[2]];
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($detected !== $mime) {
            throw new AppError('IMAGE_TYPE_NOT_ALLOWED', 422);
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE || $width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new AppError('IMAGE_DIMENSIONS_INVALID', 422);
        }
        return [
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'byte_size' => $size,
            'sha256' => hash_file('sha256', $path),
            'ext' => $mime === 'image/png' ? 'png' : 'jpg',
        ];
    }

    /** Kiểm tra mảng $_FILES['image'] rồi chép vào kho; trả về thông tin ảnh + tên lưu. */
    public static function fromUpload(?array $file): array
    {
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            throw new AppError('IMAGE_MISSING', 422);
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new AppError('IMAGE_TOO_LARGE', 422);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new AppError('IMAGE_MISSING', 422);
        }
        // Chỉ nhận tệp do PHP vừa nhận qua HTTP (không cho chép một đường dẫn bất kỳ trên máy chủ).
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new AppError('IMAGE_MISSING', 422);
        }
        $meta = self::inspect((string) $file['tmp_name'], (int) $file['size']);
        $stored = bin2hex(random_bytes(16)) . '.' . $meta['ext'];
        $target = self::directory() . '/' . $stored;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('STORAGE_WRITE_FAILED');
        }
        $original = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'image.' . $meta['ext'])));
        return $meta + ['stored_name' => $stored, 'original_name' => mb_substr($original, 0, 255)];
    }

    /** Dùng cho dữ liệu mẫu và kiểm thử: chép một tệp có sẵn vào kho. */
    public static function fromFile(string $path): array
    {
        $meta = self::inspect($path, (int) filesize($path));
        $stored = bin2hex(random_bytes(16)) . '.' . $meta['ext'];
        if (!copy($path, self::directory() . '/' . $stored)) {
            throw new RuntimeException('STORAGE_WRITE_FAILED');
        }
        return $meta + ['stored_name' => $stored, 'original_name' => basename($path)];
    }

    public static function path(string $storedName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(png|jpg)$/', $storedName)) {
            throw new AppError('NOT_FOUND', 404);
        }
        return self::directory() . '/' . $storedName;
    }
}
