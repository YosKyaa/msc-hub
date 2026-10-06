<?php

namespace App\Support;

/**
 * Apakah email yang dikirim benar-benar meninggalkan server.
 *
 * Laravel menganggap pengiriman berhasil selama pengantarnya tidak melempar
 * galat. Pengantar `log` dan `array` tidak pernah melempar apa pun: keduanya
 * menerima surat lalu membuangnya. Sistem mencatat "terkirim", panel
 * menampilkan "Email terkirim", dan penerimanya tidak pernah menerima apa pun.
 *
 * Itu bukan kemungkinan teoretis — itu keadaan bawaan Laravel saat
 * MAIL_MAILER belum disetel, dan paling sering terbawa ke server karena
 * .env produksi disalin dari berkas contoh.
 */
class MailHealth
{
    /**
     * Pengantar yang menerima surat tanpa pernah mengirimkannya.
     */
    private const PURA_PURA = ['log', 'array', 'null'];

    public static function mailer(): string
    {
        return (string) config('mail.default');
    }

    /**
     * Apakah pengantarnya sungguh mengirim ke luar.
     */
    public static function delivers(): bool
    {
        return ! in_array(self::mailer(), self::PURA_PURA, true);
    }

    /**
     * Peringatan siap tampil, atau null bila pengirimannya memang berjalan.
     */
    public static function warning(): ?string
    {
        if (self::delivers()) {
            return self::alamatPengirimPincang();
        }

        return 'Email tidak akan sampai ke siapa pun: MAIL_MAILER di server masih "'
            .self::mailer().'", yang hanya menuliskan surat ke berkas log dan membuangnya. '
            .'Sistem tetap mencatatnya sebagai terkirim. Setel MAIL_MAILER=smtp beserta '
            .'MAIL_HOST, MAIL_USERNAME, dan MAIL_PASSWORD di .env server, lalu jalankan '
            .'`php artisan config:cache`.';
    }

    /**
     * Alamat pengirim yang masih memakai contoh bawaan.
     *
     * Penyedia SMTP menolak surat dari domain yang tidak dikenalnya, dan
     * penolakannya sering baru terlihat berjam-jam kemudian di kotak
     * pengirim.
     */
    private static function alamatPengirimPincang(): ?string
    {
        $dari = (string) config('mail.from.address');

        if (blank($dari) || str_contains($dari, 'example.com') || $dari === 'hello@example.com') {
            return 'Alamat pengirim masih memakai contoh bawaan ('.($dari ?: 'kosong').'). '
                .'Penyedia SMTP umumnya menolak surat dari domain yang tidak dikenalnya. '
                .'Setel MAIL_FROM_ADDRESS di .env server.';
        }

        return null;
    }

    /**
     * Ringkasan untuk ditampilkan di panel.
     *
     * @return array{mailer: string, mengirim: bool, dari: string, peringatan: ?string}
     */
    public static function summary(): array
    {
        return [
            'mailer' => self::mailer(),
            'mengirim' => self::delivers(),
            'dari' => (string) config('mail.from.address'),
            'peringatan' => self::warning(),
        ];
    }
}
