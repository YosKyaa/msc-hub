<?php

namespace App\Enums;

/**
 * Peran seseorang dalam sebuah kegiatan, dicetak pada sertifikat.
 *
 * Nilai enum mengikuti data yang sudah tersimpan di kolom
 * `certificate_event_participants.role` dan `certificates.recipient_role`.
 */
enum ParticipantRole: string
{
    case PARTICIPANT = 'participant';
    case COMMITTEE = 'committee';
    case SPEAKER = 'speaker';
    case MODERATOR = 'moderator';
    case ORGANIZER = 'organizer';
    case JUDGE = 'judge';
    case MENTOR = 'mentor';
    case VOLUNTEER = 'volunteer';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PARTICIPANT => 'Peserta',
            self::COMMITTEE => 'Panitia',
            self::SPEAKER => 'Pembicara',
            self::MODERATOR => 'Moderator',
            self::ORGANIZER => 'Penyelenggara',
            self::JUDGE => 'Juri',
            self::MENTOR => 'Mentor',
            self::VOLUNTEER => 'Relawan',
            self::OTHER => 'Lainnya',
        };
    }

    /**
     * Cocokkan label Bahasa Indonesia dari file import ke nilai enum.
     * Menerima juga nilai mentah (mis. "speaker") agar file lama tetap terbaca.
     */
    public static function fromLabel(string $value): ?self
    {
        $needle = mb_strtolower(trim($value));

        if ($needle === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($needle === $case->value || $needle === mb_strtolower($case->getLabel())) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case) => $options + [$case->value => $case->getLabel()],
            [],
        );
    }

    /**
     * Daftar label yang boleh ditulis di kolom "peran" pada file import.
     *
     * @return array<int, string>
     */
    public static function importableLabels(): array
    {
        return array_map(fn (self $case) => $case->getLabel(), self::cases());
    }
}
