<?php

namespace App\Services\Certificates\Import;

use App\Enums\ParticipantSource;
use App\Models\CertificateEvent;
use App\Services\Certificates\ParticipantRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Menulis baris hasil pratinjau ke database dengan aturan dedup D8:
 * kunci peserta adalah email, dan seseorang hanya boleh terdaftar sekali
 * per kegiatan (peran apa pun).
 */
class ParticipantImporter
{
    public function __construct(private readonly ParticipantRegistry $participants) {}

    public function import(CertificateEvent $event, ParticipantImportPreview $preview): ParticipantImportResult
    {
        return DB::transaction(function () use ($event, $preview) {
            $created = 0;
            $skipped = 0;
            $warnings = $preview->warnings;

            foreach ($preview->validRows as $row) {
                $participant = $this->participants->findOrCreateByEmail(
                    $row->email,
                    [
                        'name' => $row->name,
                        'institutional_id' => $row->institutionalId,
                        'study_program' => $row->studyProgram,
                        'source' => ParticipantSource::IMPORT->value,
                    ],
                    [
                        'institutional_id' => $row->institutionalId,
                        'study_program' => $row->studyProgram,
                    ],
                );

                if ($participant->name !== $row->name) {
                    $warnings[] = "Baris {$row->line}: nama di file diabaikan, memakai nama master \"{$participant->name}\".";
                }

                if ($event->participations()->where('participant_id', $participant->id)->exists()) {
                    $skipped++;

                    continue;
                }

                $event->participations()->create([
                    'participant_id' => $participant->id,
                    'role' => $row->role->value,
                    'source' => ParticipantSource::IMPORT->value,
                    'certificate_number' => $row->certificateNumber,
                ]);

                $created++;
            }

            return new ParticipantImportResult($created, $skipped, $warnings);
        });
    }
}
