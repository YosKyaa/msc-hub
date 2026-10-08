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
 */
class ParticipantImportRow
{
    public function __construct(
        public readonly int $line,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ParticipantRole $role,
        public readonly ?string $institutionalId = null,
        public readonly ?string $studyProgram = null,
        public readonly ?string $certificateNumber = null,
        public readonly ?string $sheet = null,
    ) {}

    public function location(): string
    {
        return ParticipantImportPreview::location($this->sheet, $this->line);
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
            'role' => $this->role->value,
            'institutional_id' => $this->institutionalId,
            'study_program' => $this->studyProgram,
            'certificate_number' => $this->certificateNumber,
            'sheet' => $this->sheet,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line: (int) $data['line'],
            name: (string) $data['name'],
            email: filled($data['email'] ?? null) ? (string) $data['email'] : null,
            role: ParticipantRole::from((string) $data['role']),
            institutionalId: $data['institutional_id'] ?? null,
            studyProgram: $data['study_program'] ?? null,
            certificateNumber: $data['certificate_number'] ?? null,
            sheet: $data['sheet'] ?? null,
        );
    }
}
