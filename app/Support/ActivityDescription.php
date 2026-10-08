<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Menerjemahkan satu baris jejak audit menjadi kalimat yang bisa dibaca admin.
 *
 * Isi mentahnya nama kelas dan nama kolom basis data — `App\Models\RoomBooking`,
 * `reject_reason` — yang tidak berarti apa-apa bagi orang yang sedang mencari
 * siapa menyetujui sebuah peminjaman.
 */
final class ActivityDescription
{
    /** @var array<string, string> */
    private const SUBJECTS = [
        'Certificate' => 'Sertifikat',
        'CertificateEvent' => 'Kegiatan sertifikat',
        'CertificateTemplate' => 'Template sertifikat',
        'Issuer' => 'Penerbit',
        'Participant' => 'Peserta',
        'CertificateEventParticipant' => 'Keikutsertaan',
        'RoomBooking' => 'Peminjaman ruangan',
        'InventoryBooking' => 'Peminjaman alat',
        'ContentRequest' => 'Pengajuan konten',
        'User' => 'Pengguna',
        'Role' => 'Peran',
        'Announcement' => 'Pengumuman',
        'Twibbon' => 'Twibbon',
    ];

    /** @var array<string, string> */
    private const EVENTS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'peran_diberikan' => 'Peran diberikan',
        'peran_dicabut' => 'Peran dicabut',
        'izin_diberikan' => 'Izin diberikan',
        'izin_dicabut' => 'Izin dicabut',
    ];

    private const MAX_VALUE_LENGTH = 60;

    /**
     * @return array<string, string>
     */
    public static function subjectOptions(): array
    {
        return collect(self::SUBJECTS)
            ->mapWithKeys(fn (string $label, string $kelas) => ['App\\Models\\'.$kelas => $label])
            ->put('Spatie\\Permission\\Models\\Role', 'Peran')
            ->forget('App\\Models\\Role')
            ->all();
    }

    public static function subject(Activity $activity): string
    {
        if ($activity->subject_type === null) {
            return (string) data_get($activity->properties, 'key', 'Pengaturan');
        }

        $label = self::SUBJECTS[class_basename($activity->subject_type)] ?? class_basename($activity->subject_type);

        return $label.' #'.$activity->subject_id;
    }

    public static function event(Activity $activity): string
    {
        return self::EVENTS[(string) $activity->event] ?? Str::headline((string) ($activity->event ?: $activity->description));
    }

    public static function causer(Activity $activity): string
    {
        return (string) ($activity->causer?->getAttribute('name') ?: 'Sistem');
    }

    /**
     * Daftar perubahan: "kolom: lama → baru", atau untuk peran dan izin,
     * nama yang diberikan atau dicabut.
     *
     * @return list<string>
     */
    public static function changes(Activity $activity): array
    {
        $properti = $activity->properties?->toArray() ?? [];

        foreach (['diberikan', 'dicabut'] as $kunci) {
            if (isset($properti[$kunci])) {
                return [ucfirst($kunci).': '.implode(', ', (array) $properti[$kunci])];
            }
        }

        $baru = (array) ($properti['attributes'] ?? []);
        $lama = (array) ($properti['old'] ?? []);

        $baris = [];

        foreach (array_unique([...array_keys($lama), ...array_keys($baru)]) as $kolom) {
            $sebelum = array_key_exists($kolom, $lama) ? self::value($lama[$kolom]) : null;
            $sesudah = array_key_exists($kolom, $baru) ? self::value($baru[$kolom]) : null;

            $baris[] = match (true) {
                $activity->event === 'created' || $sebelum === null => "{$kolom}: {$sesudah}",
                $activity->event === 'deleted' || $sesudah === null => "{$kolom}: {$sebelum}",
                default => "{$kolom}: {$sebelum} → {$sesudah}",
            };
        }

        return $baris;
    }

    private static function value(mixed $nilai): string
    {
        $teks = match (true) {
            $nilai === null, $nilai === '' => 'kosong',
            is_bool($nilai) => $nilai ? 'ya' : 'tidak',
            is_array($nilai) => json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[...]',
            default => strip_tags((string) $nilai),
        };

        return Str::limit($teks, self::MAX_VALUE_LENGTH);
    }
}
