<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email adalah kunci dedup peserta (keputusan D8). Sebelumnya tabel hanya punya
 * unique gabungan (email, institutional_id) yang tidak menghalangi email ganda
 * ketika institutional_id NULL. Migration ini menormalkan email yang ada,
 * menggabungkan duplikat, lalu memasang unique pada kolom email.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->normaliseEmails();
            $this->mergeDuplicateParticipants();
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique('participants_email_institutional_id_unique');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique('participants_email_unique');
            $table->unique(['email', 'institutional_id']);
        });
    }

    private function normaliseEmails(): void
    {
        DB::table('participants')
            ->whereNotNull('email')
            ->orderBy('id')
            ->each(function (object $participant) {
                $normalised = mb_strtolower(trim((string) $participant->email));

                if ($normalised !== $participant->email) {
                    DB::table('participants')
                        ->where('id', $participant->id)
                        ->update(['email' => $normalised ?: null]);
                }
            });
    }

    private function mergeDuplicateParticipants(): void
    {
        $duplicateEmails = DB::table('participants')
            ->select('email')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        foreach ($duplicateEmails as $email) {
            $ids = DB::table('participants')->where('email', $email)->orderBy('id')->pluck('id');
            $keepId = $ids->shift();

            foreach ($ids as $duplicateId) {
                $this->moveParticipations($duplicateId, $keepId);
                DB::table('certificates')->where('participant_id', $duplicateId)->update(['participant_id' => $keepId]);
                DB::table('participants')->where('id', $duplicateId)->delete();
            }
        }
    }

    /**
     * Pindahkan keikutsertaan ke participant yang dipertahankan. Bila kombinasi
     * (kegiatan, peran) sudah dimiliki record tujuan, baris duplikat dibuang dan
     * sertifikatnya dialihkan ke keikutsertaan yang dipertahankan.
     */
    private function moveParticipations(int $duplicateId, int $keepId): void
    {
        $participations = DB::table('certificate_event_participants')
            ->where('participant_id', $duplicateId)
            ->get();

        foreach ($participations as $participation) {
            $existingId = DB::table('certificate_event_participants')
                ->where('participant_id', $keepId)
                ->where('certificate_event_id', $participation->certificate_event_id)
                ->where('role', $participation->role)
                ->value('id');

            if ($existingId === null) {
                DB::table('certificate_event_participants')
                    ->where('id', $participation->id)
                    ->update(['participant_id' => $keepId]);

                continue;
            }

            DB::table('certificates')
                ->where('event_participant_id', $participation->id)
                ->update(['event_participant_id' => $existingId]);

            DB::table('certificate_event_participants')->where('id', $participation->id)->delete();
        }
    }
};
