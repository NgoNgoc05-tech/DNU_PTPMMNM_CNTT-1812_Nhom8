<?php

declare(strict_types=1);

/**
 * Phân trang dùng chung (bản 2.5). Đếm tổng ở CSDL trước, rồi chỉ lấy đúng một trang bằng LIMIT/OFFSET,
 * nên danh sách dài (hàng nghìn góp ý, hàng trăm người dùng) vẫn nhanh và trang không bị kéo dài vô tận.
 */
final class Paginator
{
    public const SIZES = [10, 20, 50, 100];

    public readonly int $page;
    public readonly int $pages;

    public function __construct(public readonly int $total, int $page, public readonly int $perPage, public readonly array $sizes = self::SIZES,
        public readonly string $key = 'page')
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = min(max(1, $page), $this->pages);
    }

    /** Đọc ?page=&per= từ yêu cầu; per chỉ nhận các giá trị trong $sizes. */
    public static function fromRequest(int $total, array $get, int $default = 20, array $sizes = self::SIZES, string $key = 'page'): self
    {
        // Danh sách phụ trên cùng trang (ví dụ nhật ký hoạt động) dùng khoá riêng: ?apage= và không có lựa chọn số dòng.
        $per = $key === 'page' ? (int) ($get['per'] ?? $default) : $default;
        return new self($total, (int) ($get[$key] ?? 1), in_array($per, $sizes, true) ? $per : $default, $sizes, $key);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /** Các số trang cần hiện: 1 … 4 5 [6] 7 8 … 20 (0 = dấu …). @return int[] */
    public function window(int $around = 2): array
    {
        $out = [];
        for ($i = 1; $i <= $this->pages; $i++) {
            if ($i === 1 || $i === $this->pages || abs($i - $this->page) <= $around) {
                $out[] = $i;
            } elseif (end($out) !== 0) {
                $out[] = 0;
            }
        }
        return $out;
    }

    /**
     * Thanh phân trang: "Hiển thị 21–40 trên 135 góp ý", ‹ Trước 1 … 3 [4] 5 … 7 Sau ›, chọn số dòng mỗi trang.
     * $query: các tham số lọc hiện tại (giữ nguyên khi chuyển trang).
     */
    public function render(string $path, array $query, string $noun = 'mục', bool $sizes = true, string $anchor = ''): string
    {
        $sizes = $sizes && $this->key === 'page';
        $url = function (int $page, ?int $per = null) use ($path, $query, $anchor): string {
            $q = array_filter($query, fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
            $q[$this->key] = $page;
            if ($this->key === 'page') {
                $q['per'] = $per ?? $this->perPage;
            }
            return e($path . '?' . http_build_query($q) . $anchor);
        };
        $html = '<nav class="pager" aria-label="Phân trang"><span class="pager-info">Hiển thị <strong>' . $this->from() . '–' . $this->to()
            . '</strong> trên <strong>' . $this->total . '</strong> ' . e($noun) . '</span>';
        if ($this->pages > 1) {
            $html .= '<span class="pager-pages">';
            $html .= $this->page > 1
                ? '<a class="pager-btn" rel="prev" href="' . $url($this->page - 1) . '">' . icon('chevron-left', 'sm') . 'Trước</a>'
                : '<span class="pager-btn is-disabled" aria-disabled="true">' . icon('chevron-left', 'sm') . 'Trước</span>';
            foreach ($this->window() as $i) {
                $html .= $i === 0 ? '<span class="pager-gap">…</span>'
                    : ($i === $this->page ? '<span class="pager-num is-current" aria-current="page">' . $i . '</span>'
                        : '<a class="pager-num" href="' . $url($i) . '" aria-label="Trang ' . $i . '">' . $i . '</a>');
            }
            $html .= $this->page < $this->pages
                ? '<a class="pager-btn" rel="next" href="' . $url($this->page + 1) . '">Sau' . icon('chevron-right', 'sm') . '</a>'
                : '<span class="pager-btn is-disabled" aria-disabled="true">Sau' . icon('chevron-right', 'sm') . '</span>';
            $html .= '</span>';
        }
        if ($sizes && $this->total > $this->sizes[0]) {
            $html .= '<span class="pager-size">';
            foreach ($this->sizes as $s) {
                $html .= $s === $this->perPage ? '<span class="is-current" aria-current="true">' . $s . '</span>'
                    : '<a href="' . $url(1, $s) . '" aria-label="' . $s . ' dòng mỗi trang">' . $s . '</a>';
            }
            $html .= '<span class="hint">/ trang</span></span>';
        }
        return $html . '</nav>';
    }
}
