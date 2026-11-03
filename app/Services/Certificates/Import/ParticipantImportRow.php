<?php

namespace App\Services\Certificates\Import;

use App\Enums\ParticipantRole;

/**
 * Satu baris file import yang sudah lolos validasi dan siap ditulis.
 *
 * `line` adalah nomor baris asli di Excel (header = baris 1) sehingga pesan
 * yang dilihat admin cocok dengan yang terlihat di aplikasi spreadsheet.
 * `sheet` diisi nama lembarnya bila berkas memuat lebih dari satu daftar,
 * misalnya lembar Panitia dan lembar Peserta.
 *
 * `email` boleh kosong: peserta yang belum punya alamat tetap mendapat
 * sertifikat, hanya tidak dikirimi lewat email.
 *
 * Kolom peran yang berisi jabatan, misalnya "Ketua Pelaksana", disimpan
 * sebagai `roleLabel`, dan `roleSource` mencatat dari mana kategorinya
 * disimpulkan. `role` yang kosong berarti kategorinya mengikuti Kategori
 * default yang dipilih saat import; pilihan itu sengaja tidak ikut disimpan
 * di sini, supaya pratinjau di cache tetap berlaku bila pilihannya diganti.
 */
class ParticipantImportRow
{
    /** Kategori diambil dari nama lembar, misalnya lembar "Panitia". */
    public const ROLE_FROM_SHEET = 'sheet';

    /** Kategori diambil dari kata peran di dalam jabatannya, misalnya "Juri Tamu". */
    public const ROLE_FROM_LABEL = 'label';

    public function __construct(
        public readonly int $line,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?ParticipantRole $role,
        public readonly ?string $institutionalId = null,
        public readonly ?string $studyProgram = null,
        public readonly ?string $certificateNumber = null,
        public readonly ?string $sheet = null,
        public readonly ?string $roleLabel = null,
        public readonly ?string $roleSource = null,
    ) {}

    public function location(): string
    {
        return ParticipantImportPreview::location($this->sheet, $this->line);
    }

    public function roleFor(ParticipantRole $default): ParticipantRole
    {
        return $this->role ?? $default;
    }

    /**
     * Peran seperti yang dicek admin di pratinjau: "Peserta", atau untuk
     * jabatan "Koordinator Peserta (Panitia — dari nama lembar)".
     */
    public function describeRole(ParticipantRole $default): string
    {
        $kategori = $this->roleFor($default)->getLabel();

        if ($this->roleLabel === null) {
            return $kategori;
        }

        $asal = match ($this->roleSource) {
            self::ROLE_FROM_SHEET => 'dari nama lembar',
            self::ROLE_FROM_LABEL => 'dari kata peran di jabatannya',
            default => 'Kategori default',
        };

        return "{$this->roleLabel} ({$kategori} — {$asal})";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'institutional_id' => $this->institutionalId,
            'study_program' => $this->studyProgram,
            'certificate_number' => $this->certificateNumber,
            'sheet' => $this->sheet,
            'role_label' => $this->roleLabel,
            'role_source' => $this->roleSource,
        ];
    }

    /**
     * Pratinjau yang tersimpan sebelum jabatan dikenal tidak memuat
     * `role_label`; isinya tetap terbaca sebagai baris tanpa jabatan.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line: (int) $data['line'],
            name: (string) $data['name'],
            email: filled($data['email'] ?? null) ? (string) $data['email'] : null,
            role: filled($data['role'] ?? null) ? ParticipantRole::from((string) $data['role']) : null,
            institutionalId: $data['institutional_id'] ?? null,
            studyProgram: $data['study_program'] ?? null,
            certificateNumber: $data['certificate_number'] ?? null,
            sheet: $data['sheet'] ?? null,
            roleLabel: filled($data['role_label'] ?? null) ? (string) $data['role_label'] : null,
            roleSource: $data['role_source'] ?? null,
        );
    }
}
