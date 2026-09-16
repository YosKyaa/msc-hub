<?php

namespace App\Services\Certificates;

use App\Enums\CertificateNumberReset;
use App\Models\CertificateEvent;
use App\Support\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pola penomoran sertifikat beserta cara menerjemahkannya menjadi nomor jadi.
 *
 * Pola ditulis dengan token dalam kurung kurawal, mengikuti kebiasaan penomoran
 * dokumen resmi kampus, contoh:
 *
 *     {nomor:4}/CERT/MSC-JGU/{bulan_romawi}/{tahun}
 *     -> 0057/CERT/MSC-JGU/IX/2026
 */
class CertificateNumberFormat
{
    public const SETTING_PATTERN = 'certificates.number_pattern';

    public const SETTING_RESET = 'certificates.number_reset';

    public const SETTING_UNIT_CODE = 'certificates.number_unit_code';

    public const DEFAULT_PATTERN = '{nomor:4}/CERT/{kode_unit}/{bulan_romawi}/{tahun}';

    public const DEFAULT_UNIT_CODE = 'MSC-JGU';

    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function __construct(
        public readonly string $pattern,
        public readonly CertificateNumberReset $reset,
        public readonly string $unitCode,
        /** Nomor urut pertama setiap kali urutan dimulai ulang. */
        public readonly int $numberStart = 1,
    ) {}

    /**
     * Pola default kampus, berlaku untuk seluruh sertifikat.
     */
    public static function default(): self
    {
        return new self(
            pattern: (string) AppSetting::get(self::SETTING_PATTERN, self::DEFAULT_PATTERN),
            reset: CertificateNumberReset::tryFrom((string) AppSetting::get(self::SETTING_RESET, CertificateNumberReset::YEARLY->value))
                ?? CertificateNumberReset::YEARLY,
            unitCode: (string) AppSetting::get(self::SETTING_UNIT_CODE, self::DEFAULT_UNIT_CODE),
        );
    }

    /**
     * Pola yang berlaku untuk sebuah kegiatan, dari yang paling khusus:
     * pola kegiatan, lalu pola penerbitnya, lalu pola default sistem.
     */
    public static function for(CertificateEvent $event): self
    {
        $default = static::default();
        $issuer = $event->resolvedIssuer();

        return new self(
            pattern: $event->certificate_number_format
                ?: ($issuer?->number_pattern ?: $default->pattern),
            reset: $issuer?->number_reset ?? $default->reset,
            unitCode: $issuer?->code ?: $default->unitCode,
            numberStart: $issuer?->startingNumber() ?? $default->numberStart,
        );
    }

    /**
     * Daftar token beserta keterangannya, dipakai sebagai bantuan di panel.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            '{nomor}' => 'Nomor urut, contoh 57. Tambahkan panjang digit dengan {nomor:4} menjadi 0057.',
            '{tahun}' => 'Tahun terbit empat digit, contoh 2026.',
            '{tahun_pendek}' => 'Tahun terbit dua digit, contoh 26.',
            '{bulan}' => 'Bulan terbit dua digit, contoh 09.',
            '{bulan_romawi}' => 'Bulan terbit angka Romawi, contoh IX.',
            '{tanggal}' => 'Tanggal terbit dua digit, contoh 06.',
            '{kode_kegiatan}' => 'Kode kegiatan yang diisi pada kegiatan bersangkutan.',
            '{kode_unit}' => 'Kode unit penerbit dari pengaturan, contoh MSC-JGU.',
        ];
    }

    /**
     * Kunci penghitung nomor urut. Menentukan kapan urutan kembali ke 1.
     *
     * Selalu diawali penerbit, sehingga sertifikat mitra tidak menggerus
     * urutan nomor JGU dan sebaliknya.
     */
    public function sequenceScope(CertificateEvent $event, Carbon $moment): string
    {
        $period = match ($this->reset) {
            CertificateNumberReset::YEARLY => 'year-'.$moment->format('Y'),
            CertificateNumberReset::MONTHLY => 'month-'.$moment->format('Y-m'),
            CertificateNumberReset::PER_EVENT => 'event-'.$event->id,
            CertificateNumberReset::NEVER => 'global',
        };

        return 'issuer-'.($event->resolvedIssuer()?->id ?? 0).':'.$period;
    }

    /**
     * Terjemahkan pola menjadi nomor jadi.
     */
    public function render(CertificateEvent $event, int $sequence, ?Carbon $moment = null): string
    {
        $moment ??= now();

        $replacements = [
            '{tahun}' => $moment->format('Y'),
            '{tahun_pendek}' => $moment->format('y'),
            '{bulan}' => $moment->format('m'),
            '{bulan_romawi}' => self::ROMAN_MONTHS[(int) $moment->format('n')],
            '{tanggal}' => $moment->format('d'),
            '{kode_kegiatan}' => $this->eventCode($event),
            '{kode_unit}' => $this->unitCode,
        ];

        // {nomor} dan {nomor:n} diproses lebih dulu karena membawa argumen.
        $rendered = preg_replace_callback(
            '/\{nomor(?::(\d+))?\}/',
            fn (array $match) => str_pad((string) $sequence, (int) ($match[1] ?? 1), '0', STR_PAD_LEFT),
            $this->pattern,
        );

        return strtr((string) $rendered, $replacements);
    }

    /**
     * Contoh hasil untuk ditampilkan di panel, memakai nomor urut 57.
     */
    public function preview(?CertificateEvent $event = null): string
    {
        return $this->render($event ?? new CertificateEvent(['name' => 'Contoh Kegiatan']), 57);
    }

    private function eventCode(CertificateEvent $event): string
    {
        if (filled($event->certificate_code)) {
            return $event->certificate_code;
        }

        // Cadangan agar token tidak pernah menghasilkan potongan kosong.
        return Str::upper(Str::slug((string) $event->name, ''))
            ?: 'KEGIATAN';
    }
}
