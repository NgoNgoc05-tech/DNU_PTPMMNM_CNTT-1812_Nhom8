<?php

declare(strict_types=1);

/**
 * YÊU CẦU KỸ THUẬT 1 — toạ độ theo ẢNH GỐC.
 *
 * Trình duyệt đổi vị trí chuột trên màn hình sang pixel của ảnh gốc (xem
 * public/assets/js/coords.js) rồi gửi lên. Máy chủ kiểm tra lại: điểm/vùng phải
 * nằm trong ảnh gốc của ĐÚNG phiên bản đó, nên phần lề trống quanh ảnh không thể
 * lọt vào toạ độ. Các hàm ở đây là hàm thuần, kiểm thử trong tests/run.php.
 */
final class Coordinates
{
    public const MIN_RECT = 2.0; // vùng nhỏ hơn 2 px coi như bấm nhầm

    /** @return array{shape:string,x:float,y:float,w:?float,h:?float} */
    public static function validate(string $shape, mixed $x, mixed $y, mixed $w, mixed $h, int $imageWidth, int $imageHeight): array
    {
        $x = self::number($x);
        $y = self::number($y);
        if ($x === null || $y === null || $x < 0 || $y < 0 || $x > $imageWidth || $y > $imageHeight) {
            throw new AppError('POSITION_OUTSIDE_IMAGE', 422);
        }
        if ($shape === 'POINT') {
            return ['shape' => 'POINT', 'x' => round($x, 2), 'y' => round($y, 2), 'w' => null, 'h' => null];
        }
        if ($shape !== 'RECT') {
            throw new AppError('SHAPE_INVALID', 422);
        }
        $w = self::number($w);
        $h = self::number($h);
        if ($w === null || $h === null || $w < self::MIN_RECT || $h < self::MIN_RECT) {
            throw new AppError('RECT_TOO_SMALL', 422);
        }
        // Cho phép sai số làm tròn 0,01 px ở mép ảnh.
        if ($x + $w > $imageWidth + 0.01 || $y + $h > $imageHeight + 0.01) {
            throw new AppError('POSITION_OUTSIDE_IMAGE', 422);
        }
        return ['shape' => 'RECT', 'x' => round($x, 2), 'y' => round($y, 2), 'w' => round($w, 2), 'h' => round($h, 2)];
    }

    /**
     * Toạ độ màn hình của một điểm ảnh khi ảnh được vẽ trong khung có lề (letterbox).
     * Dùng cho kiểm thử đối chiếu với coords.js: cùng công thức ở hai phía.
     *
     * @param array{left:float,top:float,scale:float} $content vùng nội dung ảnh trên màn hình
     * @return array{0:float,1:float}
     */
    public static function toScreen(float $x, float $y, array $content): array
    {
        return [$content['left'] + $x * $content['scale'], $content['top'] + $y * $content['scale']];
    }

    /** @return array{0:float,1:float,2:bool} [x, y, inside] trong pixel ảnh gốc */
    public static function toImage(float $screenX, float $screenY, array $content, int $imageWidth, int $imageHeight): array
    {
        $x = ($screenX - $content['left']) / $content['scale'];
        $y = ($screenY - $content['top']) / $content['scale'];
        $inside = $x >= 0 && $y >= 0 && $x <= $imageWidth && $y <= $imageHeight;
        return [$x, $y, $inside];
    }

    /** Khung "contain": ảnh vừa khít trong khung, phần thừa là lề trống chia đều hai bên. */
    public static function fitContain(float $boxWidth, float $boxHeight, int $imageWidth, int $imageHeight, float $zoom = 1.0): array
    {
        $scale = min($boxWidth / $imageWidth, $boxHeight / $imageHeight) * $zoom;
        $width = $imageWidth * $scale;
        $height = $imageHeight * $scale;
        return [
            'left' => ($boxWidth - $width) / 2,
            'top' => ($boxHeight - $height) / 2,
            'width' => $width,
            'height' => $height,
            'scale' => $scale,
        ];
    }

    private static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }
}
