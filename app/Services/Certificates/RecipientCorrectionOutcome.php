<?php

namespace App\Services\Certificates;

/**
 * Hasil satu koreksi data penerima.
 *
 * Yang perlu diketahui admin bukan sekadar "tersimpan", melainkan ke mana
 * perubahannya merambat: berapa sertifikat ikut diperbarui, dan apakah ada
 * yang penanda pengirimannya dibuka kembali karena alamatnya berganti.
 */
final class RecipientCorrectionOutcome
{
    public function __construct(
        public readonly bool $nameChanged,
        public readonly bool $emailChanged,
        public readonly int $certificatesSynced,
        public readonly bool $deliveryReopened,
    ) {}

    public static function nothing(): self
    {
        return new self(false, false, 0, false);
    }

    public function changed(): bool
    {
        return $this->nameChanged || $this->emailChanged;
    }

    public function title(): string
    {
        return match (true) {
            $this->nameChanged && $this->emailChanged => 'Nama dan email penerima diperbarui',
            $this->emailChanged => 'Email penerima diperbarui',
            $this->nameChanged => 'Nama penerima diperbarui',
            default => 'Tidak ada yang diubah',
        };
    }

    public function body(): string
    {
        if (! $this->changed()) {
            return 'Data penerima sudah sama dengan yang tersimpan.';
        }

        if ($this->certificatesSynced === 0) {
            return 'Belum ada sertifikat terbit untuk orang ini, jadi tidak ada yang perlu disesuaikan.';
        }

        $pesan = "Perubahan disalin ke {$this->certificatesSynced} sertifikat yang sudah terbit.";

        if ($this->deliveryReopened) {
            $pesan .= ' Karena alamatnya berganti, sertifikat yang sebelumnya tercatat terkirim '
                .'ditandai belum terkirim agar bisa dikirim ke alamat yang baru.';
        }

        return $pesan;
    }
}
