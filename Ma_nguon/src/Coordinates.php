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
    public const MIN_STROKE = 8.0; // mũi tên / nét vẽ ngắn hơn 8 px ảnh gốc coi như bấm nhầm
    public const MAX_DRAW_POINTS = 500;
    public const SHAPES = ['POINT' => 'Điểm', 'RECT' => 'Vùng', 'ARROW' => 'Mũi tên', 'DRAW' => 'Vẽ tay'];

    /**
     * Mũi tên (ARROW, đúng 2 điểm: đuôi → đầu) và nét vẽ tay (DRAW, 2–500 điểm), bản 2.3.
     * Mọi điểm theo pixel ảnh gốc và phải nằm trong ảnh; (x, y) là điểm đầu tiên, nơi đặt số thứ tự.
     * @return array{shape:string,x:float,y:float,w:null,h:null,points:list<array{0:float,1:float}>}
     */
    public static function validateStroke(string $shape, mixed $points, int $imageWidth, int $imageHeight): array
    {
        if (!is_array($points) || !array_is_list($points)) {
            throw new AppError('SHAPE_INVALID', 422);
        }
        $n = count($points);
        if (($shape === 'ARROW' && $n !== 2) || ($shape === 'DRAW' && ($n < 2 || $n > self::MAX_DRAW_POINTS))) {
            throw new AppError('SHAPE_INVALID', 422);
        }
        $out = [];
        $length = 0.0;
        foreach ($points as $pt) {
            $px = is_array($pt) && array_is_list($pt) && count($pt) === 2 ? self::number($pt[0]) : null;
            $py = is_array($pt) && array_is_list($pt) && count($pt) === 2 ? self::number($pt[1]) : null;
            if ($px === null || $py === null || $px < 0 || $py < 0 || $px > $imageWidth || $py > $imageHeight) {
                throw new AppError('POSITION_OUTSIDE_IMAGE', 422);
            }
            $cur = [round($px, 1), round($py, 1)];
            if ($out !== []) {
                $prev = $out[count($out) - 1];
                $length += hypot($cur[0] - $prev[0], $cur[1] - $prev[1]);
            }
            $out[] = $cur;
        }
        if ($length < self::MIN_STROKE) {
            throw new AppError('STROKE_TOO_SHORT', 422);
        }
        return ['shape' => $shape, 'x' => $out[0][0], 'y' => $out[0][1], 'w' => null, 'h' => null, 'points' => $out];
    }

    /** @return array{shape:string,x:float,y:float,w:?float,h:?float} */
    public static function validate(string $shape, mixed $x, mixed $y, mixed $w, mixed $h, int $imageWidth, int $imageHeight, mixed $points = null): array
    {
        if ($shape === 'ARROW' || $shape === 'DRAW') {
            return self::validateStroke($shape, $points, $imageWidth, $imageHeight);
        }
        $x = self::number($x);
        $y = self::number($y);
        if ($x === null || $y === null || $x < 0 || $y < 0 || $x > $imageWidth || $y > $imageHeight) {
            throw new AppError('POSITION_OUTSIDE_IMAGE', 422);
        }
        if ($shape === 'POINT') {
            return ['shape' => 'POINT', 'x' => round($x, 2), 'y' => round($y, 2), 'w' => null, 'h' => null, 'points' => null];
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
        return ['shape' => 'RECT', 'x' => round($x, 2), 'y' => round($y, 2), 'w' => round($w, 2), 'h' => round($h, 2), 'points' => null];
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
