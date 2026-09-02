<?php

namespace App\Services\Certificates;

use App\Models\Participant;

/**
 * Sumber tunggal aturan dedup peserta (keputusan D8).
 *
 * Kunci dedup adalah email yang sudah dinormalkan (lowercase + trim). Nama pada
 * master participant tidak pernah ditimpa oleh jalur otomatis karena admin
 * berhak mengoreksinya menjadi nama formal yang dicetak di sertifikat.
 */
class ParticipantRegistry
{
    public const JGU_STUDENT_DOMAIN = 'student.jgu.ac.id';

    public const JGU_STAFF_DOMAIN = 'jgu.ac.id';

    public static function normaliseEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
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
