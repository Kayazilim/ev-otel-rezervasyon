<?php

declare(strict_types=1);

namespace App\Seed;

use GdImage;
use RuntimeException;

/**
 * Örnek veri için ilan ve oda fotoğrafı çizer (FR-46 demo verisi).
 * Ders kuralı gereği depoya resim dosyası konmaz: görseller örnek veri yüklenirken GD ile üretilip
 * storage/uploads klasörüne yazılır; ilan sahibinin yüklediği fotoğraflar gibi veridir.
 * Üslup: sade, düz renkli afiş çizimi. Aynı sahne her seferinde aynı görseli üretir.
 */
final class DemoPhotoPainter
{
    public const WIDTH = 1200;
    public const HEIGHT = 750;
    /** Kenar yumuşatma: görsel iki kat büyük çizilip küçültülür. */
    private const SCALE = 2;

    /** Dış mekân sahneleri (gündüz veya akşam çizilebilir). */
    public const OUTDOOR = ['sehir-oteli', 'dag-oteli', 'gol-dag-evi', 'bogaz-daire', 'tarihi-otel', 'konak-otel', 'deniz-villa', 'tas-ev'];
    /** İç mekân sahneleri. */
    public const INDOOR = ['oda-tek', 'oda-cift', 'oda-aile', 'oda-suit', 'salon', 'salon-somine'];

    private GdImage $im;
    private int $w = 0;
    private int $h = 0;

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    }

    /** @return string JPEG içeriği */
    public function paint(string $scene, bool $dusk = false): string
    {
        $this->w = self::WIDTH * self::SCALE;
        $this->h = self::HEIGHT * self::SCALE;
        $image = imagecreatetruecolor($this->w, $this->h);
        if ($image === false) {
            throw new RuntimeException('Görsel oluşturulamadı.');
        }
        $this->im = $image;
        imagealphablending($this->im, true);

        match ($scene) {
            'sehir-oteli' => $this->cityHotel($dusk, false),
            'dag-oteli' => $this->cityHotel($dusk, true),
            'gol-dag-evi' => $this->lakeChalet($dusk),
            'bogaz-daire' => $this->bosphorusFlat($dusk),
            'tarihi-otel' => $this->historicHotel($dusk),
            'konak-otel' => $this->mansionHotel($dusk),
            'deniz-villa' => $this->seaVilla($dusk),
            'tas-ev' => $this->stoneHouse($dusk),
            'oda-tek', 'oda-cift', 'oda-aile', 'oda-suit' => $this->room(substr($scene, 4)),
            'salon', 'salon-somine' => $this->livingRoom($scene === 'salon-somine'),
            default => throw new RuntimeException('Bilinmeyen sahne: ' . $scene),
        };

        $out = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($out === false) {
            throw new RuntimeException('Görsel oluşturulamadı.');
        }
        imagecopyresampled($out, $this->im, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, $this->w, $this->h);
        ob_start();
        imagejpeg($out, null, 85);

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------ sahneler

    private function cityHotel(bool $dusk, bool $rocky): void
    {
        $this->sky($dusk, $rocky ? ['#B9CCD6', '#EEF1EE'] : ['#A9C7D9', '#EDF1EF']);
        $dusk ? $this->disc(0.80, 0.22, 0.035, '#F6E7C8') : $this->disc(0.82, 0.20, 0.045, '#F7E3B5');
        if ($rocky) {
            // Gümüşhane: sivri, kayalık dağlar
            $this->mountain([[0.0, 0.66], [0.12, 0.30], [0.27, 0.52], [0.42, 0.20], [0.60, 0.50], [0.78, 0.26], [1.0, 0.48], [1.0, 0.66]], '#9AA6A8', $dusk);
            $this->mountain([[0.30, 0.66], [0.42, 0.20], [0.46, 0.30], [0.50, 0.66]], '#B7C0C2', $dusk);
            $this->ridge(0.68, 0.04, 2.1, 1.7, '#7D8B86', $dusk);
        } else {
            $this->ridge(0.52, 0.12, 1.3, 0.4, '#8FAFA3', $dusk);
            $this->ridge(0.60, 0.09, 2.1, 1.7, '#6E9585', $dusk);
        }
        $this->rect(0, 0.78, 1, 1, $dusk ? '#4E5B57' : '#8C9A86');
        $this->rect(0, 0.86, 1, 1, $dusk ? '#3D4643' : '#C9C3B6');
        // Komşu yapılar
        $this->building(0.06, 0.58, 0.20, 0.86, '#D9D2C5', 3, 4, $dusk);
        $this->building(0.74, 0.62, 0.92, 0.86, '#CFC7B8', 3, 3, $dusk);
        // Otel (dağ otelinde daha alçak)
        $top = $rocky ? 0.46 : 0.34;
        $this->building(0.30, $top, 0.66, 0.86, '#EEE9E0', $rocky ? 5 : 6, $rocky ? 5 : 8, $dusk);
        $this->rect(0.30, $top - 0.03, 0.66, $top, '#5C6B66');
        $this->rect(0.44, 0.77, 0.52, 0.86, '#3E4B47');
        $this->rect(0.41, 0.755, 0.55, 0.77, '#1E5B4F');
        foreach ([0.24, 0.70, 0.95] as $x) {
            $this->roundTree($x, 0.86, 0.035, $dusk ? '#33493F' : '#5F8A6A');
        }
    }

    private function lakeChalet(bool $dusk): void
    {
        $this->sky($dusk, ['#9EC1D3', '#E9EFEE']);
        $this->mountain([[0.0, 0.62], [0.18, 0.22], [0.36, 0.58]], '#7F9C93', $dusk);
        $this->mountain([[0.22, 0.62], [0.47, 0.16], [0.74, 0.62]], '#6A8B80', $dusk);
        $this->mountain([[0.55, 0.62], [0.80, 0.26], [1.0, 0.55], [1.0, 0.62]], '#7F9C93', $dusk);
        $this->rect(0, 0.60, 1, 0.64, '#D6E2DF', 70);
        // Göl ve yansıma
        $this->gradient(0, 0.62, 1, 1, $dusk ? '#41536B' : '#5E8FA0', $dusk ? '#22303F' : '#3D6F80');
        foreach ([0.70, 0.76, 0.83, 0.90] as $i => $y) {
            $this->rect(0.10 + $i * 0.13, $y, 0.30 + $i * 0.13, $y + 0.006, '#FFFFFF', 95);
        }
        // Kıyı, çam ormanı ve dağ evi
        $this->poly([[0, 0.66], [0.62, 0.66], [0.70, 0.72], [0, 0.74]], $dusk ? '#2F3F37' : '#4E6B57');
        foreach ([0.04, 0.09, 0.13, 0.52, 0.57, 0.62] as $i => $x) {
            $this->pine($x, 0.68, 0.16 + ($i % 2) * 0.04, $dusk ? '#22332B' : '#3B5A47');
        }
        $this->poly([[0.22, 0.66], [0.33, 0.40], [0.44, 0.66]], '#8A5A3C');
        $this->poly([[0.25, 0.66], [0.33, 0.47], [0.41, 0.66]], '#B07A50');
        $this->rect(0.30, 0.56, 0.36, 0.66, $dusk ? '#F2C46D' : '#2F3A3F');
        $this->rect(0.20, 0.655, 0.46, 0.675, '#6B4630');
    }

    private function bosphorusFlat(bool $dusk): void
    {
        $this->sky($dusk, ['#AFCBDA', '#F0F2EF']);
        // Karşı kıyı ve yamaçtaki evler
        $this->ridge(0.47, 0.04, 1.6, 0.9, '#9AAFA7', $dusk);
        for ($i = 0; $i < 9; $i++) {
            $x = 0.05 + $i * 0.105;
            $this->rect($x, 0.43 + ($i % 3) * 0.008, $x + 0.028, 0.465, $dusk ? '#B9AE9E' : ['#E7DED0', '#D9CFC0', '#EFE8DD'][$i % 3]);
        }
        // Boğaz ve asma köprü (kuleler, tabliye, taşıyıcı kablo)
        $this->gradient(0, 0.52, 1, 0.80, $dusk ? '#3E5470' : '#4F86A0', $dusk ? '#2B3B52' : '#336A82');
        $this->rect(0.10, 0.36, 0.112, 0.53, '#5D6A73');
        $this->rect(0.72, 0.36, 0.732, 0.53, '#5D6A73');
        $this->rect(0.0, 0.502, 1.0, 0.51, '#5D6A73');
        $this->cable(0.106, 0.36, 0.726, 0.36, 0.52, '#5D6A73');
        foreach ([0.60, 0.66, 0.73] as $i => $y) {
            $this->rect(0.20 + $i * 0.2, $y, 0.34 + $i * 0.2, $y + 0.005, '#FFFFFF', 100);
        }
        // Ön planda apartman ve balkonlar
        $this->rect(0, 0.80, 1, 1, $dusk ? '#38403F' : '#BDB6A8');
        $this->building(0.58, 0.30, 0.94, 0.86, '#E9E2D6', 4, 6, $dusk);
        for ($row = 0; $row < 6; $row++) {
            $this->rect(0.57, 0.38 + $row * 0.08, 0.95, 0.39 + $row * 0.08, '#8C8478');
        }
        $this->roundTree(0.50, 0.86, 0.04, $dusk ? '#33493F' : '#6A8F72');
    }

    private function historicHotel(bool $dusk): void
    {
        $this->sky($dusk, ['#B6CEDB', '#F1EFE9']);
        // Cami silueti
        $silhouette = $dusk ? '#4A5266' : '#9AA7B3';
        $this->dome(0.50, 0.48, 0.16, $silhouette);
        $this->dome(0.38, 0.50, 0.08, $silhouette);
        $this->dome(0.62, 0.50, 0.08, $silhouette);
        $this->rect(0.30, 0.48, 0.70, 0.60, $silhouette);
        foreach ([0.26, 0.33, 0.67, 0.74] as $x) {
            $this->minaret($x, 0.60, 0.40, $silhouette);
        }
        // Butik otel cephesi ve sokak
        $this->rect(0, 0.84, 1, 1, $dusk ? '#3D4241' : '#CFC6B5');
        $this->building(0.08, 0.56, 0.42, 0.86, '#E7D9C3', 3, 3, $dusk);
        $this->rect(0.06, 0.54, 0.44, 0.565, '#8B5E4B');
        $this->building(0.46, 0.50, 0.86, 0.86, '#EFE6D6', 4, 4, $dusk);
        $this->rect(0.44, 0.48, 0.88, 0.505, '#7A4E3D');
        $this->rect(0.62, 0.77, 0.70, 0.86, '#3E4B47');
        foreach ([0.04, 0.45, 0.92] as $x) {
            $this->rect($x, 0.70, $x + 0.006, 0.86, '#3F4447');
            $this->disc($x + 0.003, 0.70, 0.010, $dusk ? '#F6D58E' : '#E9E3D6');
        }
    }

    private function mansionHotel(bool $dusk): void
    {
        $this->sky($dusk, ['#A4CBDA', '#F3EFE6']);
        $this->gradient(0, 0.58, 1, 0.70, $dusk ? '#3A5571' : '#3E93A6', $dusk ? '#2B4058' : '#2E7C8E');
        $this->rect(0, 0.70, 1, 1, $dusk ? '#4A4741' : '#D8C9AE');
        // Konak: taş zemin kat, çıkmalı ahşap üst kat, kiremit çatı
        $this->rect(0.22, 0.56, 0.70, 0.88, '#D9C7A8');
        $this->rect(0.18, 0.36, 0.74, 0.58, '#F1E9DA');
        $this->poly([[0.16, 0.37], [0.46, 0.22], [0.76, 0.37]], '#A9573F');
        foreach ([0.24, 0.38, 0.52, 0.64] as $x) {
            $this->rect($x, 0.42, $x + 0.06, 0.53, $dusk ? '#F2C46D' : '#55626A');
            $this->rect($x - 0.005, 0.415, $x + 0.065, 0.425, '#8A5A3C');
        }
        $this->arch(0.42, 0.70, 0.50, 0.88, '#5B463A');
        // Palmiye ve begonviller
        foreach ([0.08, 0.86] as $x) {
            $this->palm($x, 0.88, $dusk);
        }
        foreach ([[0.20, 0.60], [0.23, 0.64], [0.69, 0.62], [0.72, 0.58]] as [$x, $y]) {
            $this->disc($x, $y, 0.018, '#C2457A');
        }
    }

    private function seaVilla(bool $dusk): void
    {
        $this->sky($dusk, ['#8FC3DA', '#F2F4F0']);
        $dusk ? $this->disc(0.20, 0.30, 0.05, '#F3C98B') : $this->disc(0.16, 0.18, 0.04, '#F8E7B9');
        $this->gradient(0, 0.46, 1, 0.70, $dusk ? '#3B5A74' : '#2E9AA8', $dusk ? '#294257' : '#1F7F8E');
        $this->ridge(0.46, 0.035, 2.4, 0.3, '#7F9E98', $dusk, 0.55, 0.95);
        // Teras, havuz, villa
        $this->rect(0, 0.70, 1, 1, $dusk ? '#5A564E' : '#E6DFD2');
        $this->rect(0.08, 0.76, 0.52, 0.86, $dusk ? '#2F6F7F' : '#5BC0CC');
        $this->rect(0.08, 0.755, 0.52, 0.765, '#FFFFFF', 30);
        $this->rect(0.48, 0.42, 0.92, 0.74, '#F6F4EF');
        $this->rect(0.60, 0.30, 0.92, 0.43, '#EFECE6');
        $this->rect(0.46, 0.415, 0.94, 0.43, '#C9C2B6');
        $this->rect(0.52, 0.52, 0.70, 0.70, $dusk ? '#F2C46D' : '#5F7882');
        $this->rect(0.74, 0.52, 0.88, 0.62, $dusk ? '#F2C46D' : '#5F7882');
        foreach ([0.58, 0.63] as $x) {
            $this->rect($x, 0.83, $x + 0.035, 0.84, '#FFFFFF');
            $this->rect($x, 0.84, $x + 0.004, 0.86, '#9A9388');
        }
        $this->roundTree(0.96, 0.74, 0.05, $dusk ? '#3F4D3E' : '#8DA07A');
    }

    private function stoneHouse(bool $dusk): void
    {
        $this->sky($dusk, ['#C2D3DD', '#F5EFE5']);
        // Balonlar
        foreach ([[0.18, 0.16, '#C8553D'], [0.34, 0.10, '#E0A84F'], [0.70, 0.18, '#3F7F8C'], [0.84, 0.08, '#B5654A']] as [$x, $y, $c]) {
            $this->balloon($x, $y, $c);
        }
        // Peri bacaları
        $land = $dusk ? '#9A7660' : '#D9B48F';
        $this->ridge(0.62, 0.05, 1.4, 0.2, $land, $dusk);
        foreach ([[0.06, 0.38], [0.15, 0.30], [0.80, 0.34], [0.90, 0.40]] as [$x, $top]) {
            $this->chimney($x, $top, 0.70, $dusk);
        }
        $this->rect(0, 0.70, 1, 1, $dusk ? '#6E5546' : '#C99D74');
        // Kayaya oyulmuş taş ev
        $this->poly([[0.28, 0.82], [0.30, 0.42], [0.50, 0.30], [0.70, 0.42], [0.72, 0.82]], $dusk ? '#A88A70' : '#E2C9A6');
        $this->arch(0.45, 0.62, 0.55, 0.82, '#6B4E3D');
        foreach ([0.35, 0.60] as $x) {
            $this->arch($x, 0.50, $x + 0.05, 0.58, $dusk ? '#F2C46D' : '#5E5048');
        }
    }

    private function room(string $type): void
    {
        $this->rect(0, 0, 1, 0.72, '#ECE7DE');
        $this->rect(0, 0.72, 1, 1, '#B89A7C');
        $this->rect(0, 0.72, 1, 0.73, '#9C7F64');
        $this->window(0.62, 0.14, 0.90, 0.52);
        $this->rect(0.12, 0.18, 0.26, 0.34, '#FFFFFF');
        $this->rect(0.13, 0.19, 0.25, 0.33, '#A7B9B4');
        $this->poly([[0.13, 0.33], [0.18, 0.25], [0.21, 0.29], [0.25, 0.24], [0.25, 0.33]], '#6E8B7E');
        if ($type === 'tek') {
            $this->bed(0.24, 0.24, false);
        } elseif ($type === 'aile') {
            $this->bed(0.08, 0.36, true);
            $this->bed(0.62, 0.24, false);
        } elseif ($type === 'suit') {
            $this->bed(0.10, 0.40, true);
            $this->sofa(0.60, 0.30);
        } else {
            $this->bed(0.14, 0.44, true);
        }
        $this->rect(0.02, 0.58, 0.08, 0.72, '#8A6A50');
        $this->rect(0.045, 0.44, 0.055, 0.58, '#6B5A4D');
        $this->poly([[0.025, 0.46], [0.05, 0.38], [0.075, 0.46]], '#F2E2C2');
    }

    private function livingRoom(bool $fireplace): void
    {
        $this->rect(0, 0, 1, 0.70, '#EAE4DA');
        $this->rect(0, 0.70, 1, 1, '#A8865F');
        $this->window(0.08, 0.14, 0.34, 0.50);
        if ($fireplace) {
            $this->rect(0.66, 0.30, 0.90, 0.70, '#B9A58C');
            $this->arch(0.71, 0.48, 0.85, 0.70, '#3B3330');
            $this->poly([[0.74, 0.70], [0.78, 0.58], [0.80, 0.64], [0.82, 0.56], [0.84, 0.70]], '#E59A4B');
        } else {
            $this->rect(0.70, 0.20, 0.86, 0.40, '#FFFFFF');
            $this->rect(0.71, 0.21, 0.85, 0.39, '#C9B79A');
        }
        $this->sofa(0.24, 0.42);
        $this->rect(0.30, 0.84, 0.62, 0.92, '#D8CDBD');
        $this->rect(0.38, 0.78, 0.54, 0.80, '#5A4636');
        $this->rect(0.40, 0.80, 0.41, 0.86, '#5A4636');
        $this->rect(0.51, 0.80, 0.52, 0.86, '#5A4636');
        $this->roundTree(0.95, 0.72, 0.03, '#5F8A6A');
        $this->rect(0.93, 0.66, 0.97, 0.72, '#8E7B6A');
    }

    // ------------------------------------------------------------ yapı taşları

    /** @param array{0: string, 1: string} $day gökyüzü (üst, alt) */
    private function sky(bool $dusk, array $day): void
    {
        [$top, $bottom] = $dusk ? ['#3B4A6B', '#E9B98D'] : $day;
        $this->gradient(0, 0, 1, 1, $top, $bottom);
    }

    private function ridge(float $baseY, float $amplitude, float $frequency, float $phase, string $hex, bool $dusk, float $from = 0.0, float $to = 1.0): void
    {
        $points = [[$from, 1.0]];
        for ($i = 0; $i <= 48; $i++) {
            $x = $from + ($to - $from) * $i / 48;
            $y = $baseY - $amplitude * (0.6 * sin($x * $frequency * 3.1 + $phase) + 0.4 * sin($x * $frequency * 7.3 + $phase * 2));
            $points[] = [$x, $y];
        }
        $points[] = [$to, 1.0];
        $this->poly($points, $dusk ? $this->shade($hex, 0.55) : $hex);
    }

    /** @param list<array{0: float, 1: float}> $points */
    private function mountain(array $points, string $hex, bool $dusk): void
    {
        $this->poly($points, $dusk ? $this->shade($hex, 0.55) : $hex);
    }

    private function building(float $x1, float $y1, float $x2, float $y2, string $wall, int $cols, int $rows, bool $dusk): void
    {
        $this->rect($x1, $y1, $x2, $y2, $dusk ? $this->shade($wall, 0.75) : $wall);
        $cw = ($x2 - $x1) / $cols;
        $rh = ($y2 - $y1 - 0.05) / $rows;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $lit = $dusk && (($r * 7 + $c * 3) % 5 !== 0);
                $wx = $x1 + $c * $cw + $cw * 0.25;
                $wy = $y1 + 0.02 + $r * $rh + $rh * 0.2;
                $this->rect($wx, $wy, $wx + $cw * 0.5, $wy + $rh * 0.55, $lit ? '#F2C46D' : ($dusk ? '#2B3238' : '#5A6870'));
            }
        }
    }

    private function window(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->rect($x1 - 0.01, $y1 - 0.015, $x2 + 0.01, $y2 + 0.015, '#FFFFFF');
        $this->gradient($x1, $y1, $x2, $y2, '#A9C7D9', '#E8EFEE');
        $this->poly([[$x1, $y2], [$x1, $y1 + ($y2 - $y1) * 0.6], [$x1 + ($x2 - $x1) * 0.4, $y1 + ($y2 - $y1) * 0.45], [$x2, $y1 + ($y2 - $y1) * 0.65], [$x2, $y2]], '#7E9C8E');
        $this->rect(($x1 + $x2) / 2 - 0.003, $y1, ($x1 + $x2) / 2 + 0.003, $y2, '#FFFFFF');
        $this->rect($x1 - 0.03, $y1 - 0.03, $x1 + 0.01, $y2 + 0.06, '#D7C9B4');
        $this->rect($x2 - 0.01, $y1 - 0.03, $x2 + 0.03, $y2 + 0.06, '#D7C9B4');
    }

    private function bed(float $x, float $width, bool $double): void
    {
        $this->rect($x, 0.40, $x + $width, 0.62, '#7A5B44');
        $this->rect($x, 0.60, $x + $width, 0.80, '#F7F4EE');
        $this->rect($x, 0.68, $x + $width, 0.82, '#1E5B4F');
        $this->rect($x - 0.005, 0.80, $x + $width + 0.005, 0.83, '#6A4D39');
        $pillows = $double ? 2 : 1;
        for ($i = 0; $i < $pillows; $i++) {
            $px = $x + 0.02 + $i * ($width / 2);
            $this->rect($px, 0.56, $px + $width / ($double ? 2.4 : 1.3), 0.62, '#FFFFFF');
        }
    }

    private function sofa(float $x, float $width): void
    {
        $this->rect($x, 0.54, $x + $width, 0.66, '#5E7C73');
        $this->rect($x - 0.02, 0.60, $x + $width + 0.02, 0.76, '#4F6B63');
        $this->rect($x + 0.02, 0.62, $x + $width / 2 - 0.01, 0.70, '#6F8F85');
        $this->rect($x + $width / 2 + 0.01, 0.62, $x + $width - 0.02, 0.70, '#6F8F85');
    }

    private function pine(float $x, float $baseY, float $height, string $hex): void
    {
        $this->rect($x - 0.003, $baseY - 0.02, $x + 0.003, $baseY, '#4B3A2E');
        $this->poly([[$x - $height * 0.22, $baseY - 0.02], [$x, $baseY - $height], [$x + $height * 0.22, $baseY - 0.02]], $hex);
    }

    private function roundTree(float $x, float $baseY, float $radius, string $hex): void
    {
        $this->rect($x - 0.003, $baseY - $radius * 1.4, $x + 0.003, $baseY, '#5B4636');
        $this->disc($x, $baseY - $radius * 1.8, $radius, $hex);
    }

    private function palm(float $x, float $baseY, bool $dusk): void
    {
        $this->poly([[$x - 0.004, $baseY], [$x + 0.004, $baseY], [$x + 0.012, $baseY - 0.34], [$x + 0.006, $baseY - 0.34]], '#7A6047');
        $leaf = $dusk ? '#2F4A3B' : '#4F7F5A';
        $cx = $x + 0.009;
        $cy = $baseY - 0.34;
        foreach ([[-0.09, 0.03], [-0.06, -0.04], [0.0, -0.06], [0.06, -0.04], [0.09, 0.03]] as [$dx, $dy]) {
            $this->poly([[$cx, $cy], [$cx + $dx, $cy + $dy], [$cx + $dx * 0.6, $cy + $dy + 0.02]], $leaf);
        }
    }

    private function dome(float $cx, float $baseY, float $width, string $hex): void
    {
        imagefilledarc($this->im, $this->x($cx), $this->y($baseY), $this->x($width), $this->x($width * 0.8), 180, 360, $this->color($hex), IMG_ARC_PIE);
    }

    private function minaret(float $x, float $baseY, float $topY, string $hex): void
    {
        $this->rect($x - 0.006, $topY, $x + 0.006, $baseY, $hex);
        $this->poly([[$x - 0.008, $topY], [$x, $topY - 0.07], [$x + 0.008, $topY]], $hex);
        $this->rect($x - 0.010, $topY + 0.06, $x + 0.010, $topY + 0.07, $hex);
    }

    private function chimney(float $x, float $topY, float $baseY, bool $dusk): void
    {
        $body = $dusk ? '#A4806A' : '#E4C29C';
        $this->poly([[$x - 0.035, $baseY], [$x - 0.02, $topY + 0.06], [$x, $topY + 0.03], [$x + 0.02, $topY + 0.06], [$x + 0.035, $baseY]], $body);
        $this->poly([[$x - 0.03, $topY + 0.06], [$x, $topY], [$x + 0.03, $topY + 0.06]], $dusk ? '#6E5546' : '#9C7357');
    }

    private function balloon(float $x, float $y, string $hex): void
    {
        $this->ellipse($x, $y, 0.05, 0.11, $hex);
        $this->rect($x - 0.006, $y + 0.075, $x + 0.006, $y + 0.088, '#6B4E3D');
    }

    private function cable(float $x1, float $y1, float $x2, float $y2, float $sagY, string $hex): void
    {
        imagesetthickness($this->im, 2 * self::SCALE);
        $previous = null;
        for ($i = 0; $i <= 40; $i++) {
            $t = $i / 40;
            $x = $x1 + ($x2 - $x1) * $t;
            $y = (1 - $t) ** 2 * $y1 + 2 * (1 - $t) * $t * $sagY + $t ** 2 * $y2;
            if ($previous !== null) {
                imageline($this->im, $this->x($previous[0]), $this->y($previous[1]), $this->x($x), $this->y($y), $this->color($hex));
            }
            $previous = [$x, $y];
        }
        imagesetthickness($this->im, 1);
    }

    private function arch(float $x1, float $y1, float $x2, float $y2, string $hex): void
    {
        $width = $x2 - $x1;
        $this->rect($x1, $y1 + $width * 0.5, $x2, $y2, $hex);
        imagefilledarc($this->im, $this->x($x1 + $width / 2), $this->y($y1 + $width * 0.5), $this->x($width), $this->x($width), 180, 360, $this->color($hex), IMG_ARC_PIE);
    }

    // ------------------------------------------------------------ ilkel çizimler (0–1 arası göreli koordinat)

    private function gradient(float $x1, float $y1, float $x2, float $y2, string $top, string $bottom): void
    {
        [$r1, $g1, $b1] = $this->rgb($top);
        [$r2, $g2, $b2] = $this->rgb($bottom);
        $from = $this->y($y1);
        $to = $this->y($y2);
        $steps = max(1, $to - $from);
        for ($py = $from; $py <= $to; $py++) {
            $t = ($py - $from) / $steps;
            $color = imagecolorallocate($this->im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($this->im, $this->x($x1), $py, $this->x($x2), $py, (int) $color);
        }
    }

    private function rect(float $x1, float $y1, float $x2, float $y2, string $hex, int $alpha = 0): void
    {
        imagefilledrectangle($this->im, $this->x($x1), $this->y($y1), $this->x($x2), $this->y($y2), $this->color($hex, $alpha));
    }

    /** @param list<array{0: float, 1: float}> $points */
    private function poly(array $points, string $hex): void
    {
        $flat = [];
        foreach ($points as [$px, $py]) {
            $flat[] = $this->x($px);
            $flat[] = $this->y($py);
        }
        imagefilledpolygon($this->im, $flat, $this->color($hex));
    }

    private function disc(float $cx, float $cy, float $radius, string $hex): void
    {
        imagefilledellipse($this->im, $this->x($cx), $this->y($cy), $this->x($radius * 2), $this->x($radius * 2), $this->color($hex));
    }

    private function ellipse(float $cx, float $cy, float $width, float $height, string $hex): void
    {
        imagefilledellipse($this->im, $this->x($cx), $this->y($cy), $this->x($width), $this->y($height), $this->color($hex));
    }

    private function x(float $f): int
    {
        return (int) round($f * $this->w);
    }

    private function y(float $f): int
    {
        return (int) round($f * $this->h);
    }

    private function color(string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return (int) imagecolorallocatealpha($this->im, $r, $g, $b, $alpha);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    /** Akşam ışığı için rengi koyulaştırır ve hafifçe maviye çeker. */
    private function shade(string $hex, float $factor): string
    {
        [$r, $g, $b] = $this->rgb($hex);

        return sprintf('#%02X%02X%02X', (int) ($r * $factor), (int) ($g * $factor), (int) min(255, $b * $factor + 18));
    }
}
