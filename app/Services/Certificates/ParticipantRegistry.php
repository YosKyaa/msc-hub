<?php

namespace App\Services\Certificates;

use App\Models\CertificateEvent;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sumber tunggal aturan dedup peserta (keputusan D8).
 *
 * Kunci dedup adalah email yang sudah dinormalkan (lowercase + trim). Nama pada
 * master participant tidak pernah ditimpa oleh jalur otomatis karena admin
 * berhak mengoreksinya menjadi nama formal yang dicetak di sertifikat.
 *
 * Peserta tanpa email tidak punya kunci itu, jadi dikenali dengan cara yang
 * jauh lebih sempit; lihat findOrCreateWithoutEmail().
 */
class ParticipantRegistry
{
    public const JGU_STUDENT_DOMAIN = 'student.jgu.ac.id';

    public const JGU_STAFF_DOMAIN = 'jgu.ac.id';

    /**
     * Salah ketik Gmail yang lazim. Didaftar satu per satu, bukan diukur
     * kemiripannya: mail.com, email.com, dan ymail.com sama-sama berselisih
     * satu huruf dari gmail.com, padahal ketiganya alamat sungguhan.
     */
    private const COMMON_TYPOS = [
        'gmail.co' => 'gmail.com',
        'gmail.con' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmail.om' => 'gmail.com',
        'gmail.comm' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gnail.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com',
    ];

    /**
     * Bentuk baku sebuah alamat: huruf kecil, tanpa spasi di ujungnya.
     *
     * Spasi yang dibuang termasuk yang tak terlihat. Alamat yang disalin dari
     * halaman web, Google Sheets, atau WhatsApp kerap membawa spasi tak putus
     * (NBSP) atau karakter lebar-nol. trim() tidak mengenali keduanya, sehingga
     * alamat yang tampak benar tersimpan berbeda dari aslinya: dedup gagal
     * mengenali orang yang sama, dan emailnya bisa tidak terkirim.
     */
    public static function normaliseEmail(?string $email): string
    {
        $email = (string) $email;
        $email = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $email) ?? $email;
        $email = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $email) ?? $email;

        // Sisa penulisan yang tidak pernah bagian dari sebuah alamat: koma
        // atau titik koma dari daftar yang dipisah-pisah, "mailto:" dari
        // tautan yang disalin, dan kurung sudut dari "Nama <alamat>". Alamat
        // yang sah tidak bisa diawali atau diakhiri tanda-tanda ini, jadi
        // membuangnya tidak mengubah alamat siapa pun. Tanda kutip sengaja
        // tidak ikut: "john doe"@contoh.com adalah alamat yang sah.
        $email = preg_replace('/^mailto:/i', '', $email) ?? $email;
        $email = trim($email, " \t<>,;.");

        return mb_strtolower($email);
    }

    public static function domainOf(?string $email): string
    {
        $email = static::normaliseEmail($email);
        $position = strrpos($email, '@');

        return $position === false ? '' : substr($email, $position + 1);
    }

    public static function isJguDomain(?string $email): bool
    {
        return in_array(
            static::domainOf($email),
            [self::JGU_STUDENT_DOMAIN, self::JGU_STAFF_DOMAIN],
            true,
        );
    }

    /**
     * Domain yang kemungkinan dimaksud, bila domain alamat ini tampak salah
     * ketik; null bila tidak.
     *
     * Domain JGU diukur kemiripannya: student.jgu.ic.id atau studen.jgu.ac.id
     * berselisih satu huruf dan pasti keliru, karena tidak ada domain seperti
     * itu. Batasnya sengaja satu huruf. Pada dua huruf, student.ugj.ac.id
     * milik kampus lain ikut dicurigai.
     */
    public static function suggestDomain(?string $email): ?string
    {
        $domain = static::domainOf($email);

        if ($domain === '') {
            return null;
        }

        if (isset(self::COMMON_TYPOS[$domain])) {
            return self::COMMON_TYPOS[$domain];
        }

        foreach ([self::JGU_STUDENT_DOMAIN, self::JGU_STAFF_DOMAIN] as $resmi) {
            if ($domain !== $resmi && levenshtein($domain, $resmi) === 1) {
                return $resmi;
            }
        }

        return null;
    }

    /**
     * Tipe peserta ditebak dari domain email; email di luar JGU dianggap tamu.
     */
    public static function typeFromEmail(?string $email): string
    {
        return match (static::domainOf($email)) {
            self::JGU_STUDENT_DOMAIN => 'student',
            self::JGU_STAFF_DOMAIN => 'lecturer',
            default => 'guest',
        };
    }

    /**
     * Ambil participant berdasarkan email, atau buat baru bila belum ada.
     *
     * @param  array<string, mixed>  $attributes  Nilai untuk record yang baru dibuat.
     * @param  array<string, mixed>  $fillIfEmpty  Nilai yang hanya diisi bila kolomnya masih kosong.
     */
    public function findOrCreateByEmail(string $email, array $attributes, array $fillIfEmpty = []): Participant
    {
        $email = static::normaliseEmail($email);

        $participant = Participant::firstOrCreate(
            ['email' => $email],
            [...$attributes, 'type' => $attributes['type'] ?? static::typeFromEmail($email)],
        );

        if (! $participant->wasRecentlyCreated) {
            $this->backfill($participant, $fillIfEmpty);
        }

        return $participant;
    }

    /**
     * Ambil atau buat peserta yang tidak punya alamat email.
     *
     * Tanpa email tidak ada kunci dedup, jadi orangnya hanya dikenali di dalam
     * kegiatan yang sama: nama persis sama, dan NIM/NIP sama atau sama-sama
     * kosong. Itu cukup untuk membuat impor ulang berkas yang sama tidak
     * menggandakan siapa pun. Lintas kegiatan sengaja tidak dicocokkan —
     * dua orang bernama Muhammad Rizki bukan orang yang sama, dan menyatukan
     * mereka berarti sertifikat yang satu tercetak atas data yang lain.
     *
     * @param  array<string, mixed>  $attributes  Nilai untuk record yang baru dibuat.
     */
    public function findOrCreateWithoutEmail(
        CertificateEvent $event,
        string $name,
        ?string $institutionalId,
        array $attributes = [],
    ): Participant {
        $existing = Participant::query()
            ->whereNull('email')
            ->where('name', $name)
            ->when(
                filled($institutionalId),
                fn (Builder $query) => $query->where('institutional_id', $institutionalId),
                fn (Builder $query) => $query->whereNull('institutional_id'),
            )
            ->whereHas('participations', fn (Builder $query) => $query->where('certificate_event_id', $event->getKey()))
            ->first();

        return $existing ?? Participant::create([
            ...$attributes,
            'name' => $name,
            'email' => null,
            'institutional_id' => $institutionalId ?: null,
            'type' => $attributes['type'] ?? static::typeFromEmail(null),
        ]);
    }

    /**
     * Apakah alamat ini sudah dipakai peserta lain?
     *
     * Email adalah kunci dedup peserta dan unik di basis data. Koreksi email
     * diperiksa lewat sini sebelum disimpan, supaya bentrokan ditolak dengan
     * pesan yang menyebutkan sebabnya alih-alih galat dari batas unik itu.
     */
    public function emailTakenByAnother(Participant $participant, ?string $email): bool
    {
        $email = static::normaliseEmail($email);

        if ($email === '') {
            return false;
        }

        return Participant::query()
            ->where('email', $email)
            ->whereKeyNot($participant->getKey())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function backfill(Participant $participant, array $values): void
    {
        $missing = array_filter(
            $values,
            fn (mixed $value, string $column) => filled($value) && blank($participant->{$column}),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing !== []) {
            $participant->forceFill($missing)->save();
        }
    }
}
