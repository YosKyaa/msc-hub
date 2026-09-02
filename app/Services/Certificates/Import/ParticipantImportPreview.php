<?php

namespace App\Services\Certificates\Import;

/**
 * Hasil pembacaan file import sebelum satu baris pun ditulis ke database.
 */
class ParticipantImportPreview
{
    /**
     * @param  array<int, ParticipantImportRow>  $validRows
     * @param  array<int, array{line: int, message: string}>  $problems
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly array $validRows = [],
        public readonly array $problems = [],
        public readonly array $warnings = [],
    ) {}

    public function validCount(): int
    {
        return count($this->validRows);
    }

    public function problemCount(): int
    {
        return count($this->problems);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid_rows' => array_map(fn (ParticipantImportRow $row) => $row->toArray(), $this->validRows),
            'problems' => $this->problems,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            validRows: array_map(
                fn (array $row) => ParticipantImportRow::fromArray($row),
                $data['valid_rows'] ?? [],
            ),
            problems: $data['problems'] ?? [],
            warnings: $data['warnings'] ?? [],
        );
    }
}
