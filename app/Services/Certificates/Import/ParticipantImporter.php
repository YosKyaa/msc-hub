<?php

namespace App\Services\Certificates\Import;

use App\Enums\ParticipantSource;
use App\Models\CertificateEvent;
use App\Models\Participant;
use App\Services\Certificates\ParticipantRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Menulis baris hasil pratinjau ke database dengan aturan dedup D8:
 * kunci peserta adalah email, dan seseorang hanya boleh terdaftar sekali
 * per kegiatan (peran apa pun). Peserta tanpa email dikenali dengan aturan
 * yang lebih sempit di ParticipantRegistry::findOrCreateWithoutEmail().
 */
class ParticipantImporter
{
    public function __construct(private readonly ParticipantRegistry $participants) {}

    public function import(CertificateEvent $event, ParticipantImportPreview $preview): ParticipantImportResult
    {
        return DB::transaction(function () use ($event, $preview) {
            $created = 0;
            $skipped = 0;

            foreach ($preview->validRows as $row) {
                $participant = $this->participantFor($event, $row);

                // Nama master yang berbeda dari berkas sudah diperingatkan sejak
                // pratinjau (ParticipantImportParser), dan peringatan pratinjau
                // ikut terbawa ke hasil ini.

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

            return new ParticipantImportResult($created, $skipped, $preview->warnings);
        });
    }

    private function participantFor(CertificateEvent $event, ParticipantImportRow $row): Participant
    {
        $baru = [
            'name' => $row->name,
            'institutional_id' => $row->institutionalId,
            'study_program' => $row->studyProgram,
            'source' => ParticipantSource::IMPORT->value,
        ];

        if ($row->email === null) {
            return $this->participants->findOrCreateWithoutEmail($event, $row->name, $row->institutionalId, $baru);
        }

        return $this->participants->findOrCreateByEmail(
            $row->email,
            $baru,
            [
                'institutional_id' => $row->institutionalId,
                'study_program' => $row->studyProgram,
            ],
        );
    }
}
