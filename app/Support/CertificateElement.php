<?php

namespace App\Support;

/**
 * Satu elemen di atas kanvas sertifikat.
 *
 * Sertifikat yang sama digambar tiga kali: di editor, di halaman verifikasi,
 * dan di dalam PDF. Ketiganya dulu menyusun CSS-nya sendiri-sendiri, dan
 * karenanya tidak pernah benar-benar sama — editor meratakan teks ke tengah
 * secara tegak lalu menyembunyikan yang meluber, sementara dua lainnya
 * menempelkan teks ke atas dan membiarkannya meluber. Admin melihat satu baris
 * rapi di editor, penerima menerima dua baris bertumpuk.
 *
 * Semua keputusan tata letak karena itu tinggal di sini. Yang berbeda antar
 * ketiganya hanyalah satuan — PDF memakai piksel kanvas, pratinjau memakai
 * persen dan cqw — jadi satuannya diserahkan kepada pemanggil sementara
 * perataan, tipografi, dan warnanya diputuskan sekali.
 */
final class CertificateElement
{
    public const ALIGNMENTS = ['left', 'center', 'right'];

    public const VERTICAL_ALIGNMENTS = ['top', 'middle', 'bottom'];

    /**
     * Sama di ketiga penggambar; dipakai juga untuk menaksir berapa baris
     * sebuah teks memakan tempat.
     */
    public const LINE_HEIGHT = 1.2;

    public const DEFAULT_ALIGN = 'center';

    /**
     * Editor sejak awal meratakan teks ke tengah secara tegak, jadi setiap
     * template yang sudah ada disusun admin dengan anggapan itu. Menjadikannya
     * bawaan membuat PDF menyusul apa yang mereka lihat saat merancang,
     * bukan sebaliknya.
     */
    public const DEFAULT_VERTICAL_ALIGN = 'middle';

    public const DEFAULT_FONT_FAMILY = 'DejaVu Sans';

    public const DEFAULT_FONT_SIZE = 28;

    public const DEFAULT_FONT_WEIGHT = 400;

    public const DEFAULT_COLOR = '#111827';

    public const DEFAULT_WIDTH = 300;

    public const DEFAULT_HEIGHT = 60;

    private function __construct(private readonly array $raw) {}

    public static function from(array $raw): self
    {
        return new self($raw);
    }

    /**
     * @return list<self>
     */
    public static function collect(?array $elements): array
    {
        return array_values(array_map(
            static fn (array $element): self => new self($element),
            $elements ?? [],
        ));
    }

    // ------------------------------------------------------------- isinya

    public function variable(): string
    {
        return (string) ($this->raw['variable'] ?? '');
    }

    /**
     * QR digambar sebagai gambar, bukan teks, jadi tipografinya tidak berlaku.
     */
    public function isQr(): bool
    {
        return $this->variable() === 'qr_code';
    }

    /**
     * Nilai yang dicetak: dari data sertifikat, atau teks statis yang diketik
     * admin sendiri.
     */
    public function value(array $values): string
    {
        if ($this->isQr()) {
            return '';
        }

        return (string) ($values[$this->variable()] ?? ($this->raw['text'] ?? ''));
    }

    // ------------------------------------------------------------ kotaknya

    public function x(): float
    {
        return (float) ($this->raw['x'] ?? 0);
    }

    public function y(): float
    {
        return (float) ($this->raw['y'] ?? 0);
    }

    public function width(): float
    {
        return (float) ($this->raw['width'] ?? self::DEFAULT_WIDTH);
    }

    public function height(): float
    {
        return (float) ($this->raw['height'] ?? self::DEFAULT_HEIGHT);
    }

    // --------------------------------------------------------- perataannya

    public function align(): string
    {
        $align = (string) ($this->raw['align'] ?? self::DEFAULT_ALIGN);

        return in_array($align, self::ALIGNMENTS, true) ? $align : self::DEFAULT_ALIGN;
    }

    public function verticalAlign(): string
    {
        $align = (string) ($this->raw['valign'] ?? self::DEFAULT_VERTICAL_ALIGN);

        return in_array($align, self::VERTICAL_ALIGNMENTS, true)
            ? $align
            : self::DEFAULT_VERTICAL_ALIGN;
    }

    // -------------------------------------------------------- tipografinya

    public function fontFamily(): string
    {
        return (string) ($this->raw['font_family'] ?? self::DEFAULT_FONT_FAMILY);
    }

    public function fontSize(): int
    {
        return (int) ($this->raw['font_size'] ?? self::DEFAULT_FONT_SIZE);
    }

    public function fontWeight(): int
    {
        return (int) ($this->raw['font_weight'] ?? self::DEFAULT_FONT_WEIGHT);
    }

    public function color(): string
    {
        return (string) ($this->raw['color'] ?? self::DEFAULT_COLOR);
    }

    // ---------------------------------------------------------------- CSS

    /**
     * Kotak elemennya. Satuannya diserahkan pemanggil karena PDF memakai
     * piksel kanvas sedangkan pratinjau memakai persen.
     *
     * `display:table` dipakai — bukan flexbox — karena hanya pasangan
     * table/table-cell yang dipahami dompdf maupun peramban dengan hasil yang
     * sama. Tingginya adalah tinggi terkecil: teks yang lebih panjang
     * melebarkan kotaknya alih-alih terpotong diam-diam.
     */
    public function boxCss(string $left, string $top, string $width, string $height): string
    {
        return "left:{$left};top:{$top};width:{$width};height:{$height};";
    }

    /**
     * Isi kotaknya: perataan mendatar dan tegak sekaligus tipografinya.
     *
     * Tingginya sengaja diulang di sini. Dompdf hanya mematuhi
     * `vertical-align` bila sel itu sendiri yang membawa tingginya; bila hanya
     * kotak luarnya yang punya tinggi, aturannya diterima lalu diabaikan
     * diam-diam dan seluruh teks menempel ke atas. Lihat
     * tests/Feature/CertificateLayoutParityTest.php.
     */
    public function contentCss(string $fontSize, string $height): string
    {
        $dasar = "height:{$height};"
            .'vertical-align:'.$this->verticalAlign().';'
            .'text-align:'.$this->align().';';

        if ($this->isQr()) {
            return $dasar;
        }

        return $dasar
            ."font-family:'".$this->fontFamily()."';"
            ."font-size:{$fontSize};"
            .'font-weight:'.$this->fontWeight().';'
            .'color:'.$this->color().';'
            .'line-height:'.self::LINE_HEIGHT.';';
    }

    /**
     * Bentuk yang sudah dirapikan, untuk disimpan maupun dikirim ke editor.
     */
    public function toArray(): array
    {
        return [
            ...$this->raw,
            'align' => $this->align(),
            'valign' => $this->verticalAlign(),
        ];
    }
}
