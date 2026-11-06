<?php

declare(strict_types=1);

namespace App\Core;

use App\Model\ReservationStatus;
use App\Support\Money;
use DateTimeImmutable;
use RuntimeException;

/**
 * Görünüm katmanı: yalnızca HTML üretir ve çıktıyı kaçışlar (e() = htmlspecialchars).
 * Hesap yapmaz, veri çekmez (tasarim.md bölüm 1).
 */
final class View
{
    /** @var array<string, mixed> tüm görünümlere giden ortak veriler */
    private array $shared = [];

    public function __construct(private readonly string $directory, private readonly string $basePath = '')
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->partial($layout, ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Görünüm bulunamadı: ' . $template);
        }
        $vars = $data + $this->shared;
        $vars['view'] = $this;

        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        })($file, $vars);
    }

    /**
     * Uygulama içi adres (alt klasörde çalışırken de doğru olur).
     *
     * @param array<string, mixed> $query
     */
    public function url(string $path, array $query = []): string
    {
        $query = array_filter($query, fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);

        return $this->basePath . $path . ($query === [] ? '' : '?' . http_build_query($query));
    }

    public function csrfField(): string
    {
        $token = $this->shared['csrfToken'] ?? '';

        return '<input type="hidden" name="_csrf" value="' . e($token) . '">';
    }

    public function money(int $kurus): string
    {
        return e(Money::format($kurus));
    }

    public function date(?DateTimeImmutable $date, string $format = 'd.m.Y'): string
    {
        return $date === null ? '-' : e($date->format($format));
    }

    /** @param array<string, string> $errors */
    public function invalid(array $errors, string $field): string
    {
        return isset($errors[$field]) ? ' is-invalid' : '';
    }

    /** @param array<string, string> $errors */
    public function error(array $errors, string $field): string
    {
        return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
    }

    public function statusBadge(ReservationStatus $status): string
    {
        return '<span class="badge text-bg-' . e($status->badge()) . '">' . e($status->label()) . '</span>';
    }

    public function stars(?float $rating, int $count = 0): string
    {
        if ($rating === null) {
            return '<span class="text-body-secondary small">Henüz puan yok</span>';
        }

        return '<span class="text-warning">★</span> <strong>' . e(number_format($rating, 1, ',', '')) . '</strong>'
            . ($count > 0 ? ' <span class="text-body-secondary small">(' . $count . ' yorum)</span>' : '');
    }

    public function selected(mixed $a, mixed $b): string
    {
        return (string) $a === (string) $b ? ' selected' : '';
    }

    public function checked(bool $condition): string
    {
        return $condition ? ' checked' : '';
    }
}
