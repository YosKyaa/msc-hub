<?php

namespace Tests\Feature;

use App\Enums\EligibilityRule;
use App\Enums\ParticipantSource;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $email, string $name = 'Budi Santoso'): void
    {
        $this->withSession(['requester' => [
            'google_id' => '1234567890',
            'name' => $name,
            'email' => $email,
            'type' => str_contains($email, 'student') ? 'student' : 'lecturer',
        ]]);
    }

    private function event(EligibilityRule $rule = EligibilityRule::CHECKIN_ONLY): CertificateEvent
    {
        return CertificateEvent::factory()->withOpenAttendance($rule)->create();
    }

    public function test_check_in_creates_participant_and_participation_from_attendance(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->post(route('attendance.store', $event->attendance_token))
            ->assertRedirect(route('attendance.show', $event->attendance_token));

        $participant = Participant::where('email', 'budi@student.jgu.ac.id')->first();
        $this->assertNotNull($participant);
        $this->assertSame('student', $participant->type);
        $this->assertSame('Budi Santoso', $participant->google_display_name);

        $participation = CertificateEventParticipant::first();
        $this->assertSame(ParticipantSource::ATTENDANCE, $participation->source);
        $this->assertNotNull($participation->checked_in_at);
        $this->assertNull($participation->checked_out_at);
    }

    public function test_second_scan_records_check_out_and_third_scan_is_a_no_op(): void
    {
        $event = $this->event(EligibilityRule::CHECKIN_AND_CHECKOUT);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->post(route('attendance.store', $event->attendance_token));
        $this->post(route('attendance.store', $event->attendance_token));

        $participation = CertificateEventParticipant::sole();
        $checkedOutAt = $participation->checked_out_at;
        $this->assertNotNull($checkedOutAt);

        $this->post(route('attendance.store', $event->attendance_token));

        $this->assertSame(1, CertificateEventParticipant::count());
        $this->assertTrue($checkedOutAt->equalTo($participation->fresh()->checked_out_at));
    }

    public function test_attendance_outside_the_window_is_rejected_server_side(): void
    {
        $event = CertificateEvent::factory()->create([
            'attendance_enabled' => true,
            'attendance_open_at' => now()->addDay(),
            'attendance_close_at' => now()->addDays(2),
        ]);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->from(route('attendance.show', $event->attendance_token))
            ->post(route('attendance.store', $event->attendance_token))
            ->assertRedirect(route('attendance.show', $event->attendance_token))
            ->assertSessionHas('error');

        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_closed_window_is_rejected_server_side(): void
    {
        $event = CertificateEvent::factory()->create([
            'attendance_enabled' => true,
            'attendance_open_at' => now()->subDays(2),
            'attendance_close_at' => now()->subDay(),
        ]);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->post(route('attendance.store', $event->attendance_token))->assertSessionHas('error');

        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_non_jgu_email_cannot_use_the_attendance_route(): void
    {
        $event = $this->event();
        $this->loginAs('orang@gmail.com');

        $this->post(route('attendance.store', $event->attendance_token))->assertSessionHas('error');

        $this->assertSame(0, Participant::count());
        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_disabled_attendance_returns_not_found(): void
    {
        CertificateEvent::factory()->create(['attendance_enabled' => false, 'attendance_token' => 'token-mati']);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->get(route('attendance.show', 'token-mati'))->assertNotFound();
        $this->post(route('attendance.store', 'token-mati'))->assertNotFound();
        $this->get(route('attendance.show', 'token-tidak-dikenal'))->assertNotFound();

        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_eligibility_is_set_according_to_each_rule(): void
    {
        // checkin_only: satu kali check-in sudah cukup.
        $event = $this->event(EligibilityRule::CHECKIN_ONLY);
        $this->loginAs('budi@student.jgu.ac.id');
        $this->post(route('attendance.store', $event->attendance_token));
        $this->assertNotNull(CertificateEventParticipant::sole()->eligible_at);

        // checkin_and_checkout: belum eligible sebelum check-out.
        $second = $this->event(EligibilityRule::CHECKIN_AND_CHECKOUT);
        $this->loginAs('siti@student.jgu.ac.id', 'Siti Aminah');
        $this->post(route('attendance.store', $second->attendance_token));
        $participation = $second->participations()->sole();
        $this->assertNull($participation->eligible_at);

        $this->post(route('attendance.store', $second->attendance_token));
        $this->assertNotNull($participation->fresh()->eligible_at);

        // manual: absensi tidak pernah mengubah flag.
        $third = $this->event(EligibilityRule::MANUAL);
        $this->loginAs('rina@student.jgu.ac.id', 'Rina Wijaya');
        $this->post(route('attendance.store', $third->attendance_token));
        $this->assertNull($third->participations()->sole()->eligible_at);
    }

    public function test_admin_corrected_name_is_never_overwritten_by_a_later_check_in(): void
    {
        $event = $this->event(EligibilityRule::CHECKIN_AND_CHECKOUT);
        Participant::factory()->create([
            'email' => 'budi@student.jgu.ac.id',
            'name' => 'Budi Santoso, S.Kom.',
            'google_display_name' => null,
        ]);

        $this->loginAs('budi@student.jgu.ac.id', 'budi santoso');
        $this->post(route('attendance.store', $event->attendance_token));
        $this->post(route('attendance.store', $event->attendance_token));

        $participant = Participant::sole();
        $this->assertSame('Budi Santoso, S.Kom.', $participant->name);
        $this->assertSame('budi santoso', $participant->google_display_name);
    }

    public function test_existing_registration_is_reused_instead_of_creating_a_duplicate(): void
    {
        $event = $this->event();
        $participant = Participant::factory()->create(['email' => 'nara@jgu.ac.id']);
        $participation = CertificateEventParticipant::factory()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => $participant->id,
            'role' => 'speaker',
            'source' => ParticipantSource::IMPORT->value,
        ]);

        $this->loginAs('nara@jgu.ac.id', 'Nara Putri');
        $this->post(route('attendance.store', $event->attendance_token));

        $this->assertSame(1, CertificateEventParticipant::count());
        $this->assertNotNull($participation->fresh()->checked_in_at);
        $this->assertSame('speaker', $participation->fresh()->role);
    }

    public function test_guest_is_redirected_to_google_login_with_a_return_url(): void
    {
        $event = $this->event();

        $this->get(route('attendance.show', $event->attendance_token))
            ->assertOk()
            ->assertSee('Masuk dengan Google');

        $this->post(route('attendance.store', $event->attendance_token))
            ->assertRedirect(route('google.redirect', ['redirect' => route('attendance.show', $event->attendance_token)]));
    }

    public function test_attendance_token_is_generated_once_when_the_feature_is_enabled(): void
    {
        $event = CertificateEvent::factory()->create();
        $this->assertNull($event->attendance_token);

        $event->update(['attendance_enabled' => true]);
        $token = $event->fresh()->attendance_token;
        $this->assertNotEmpty($token);

        $event->update(['attendance_open_at' => now()]);
        $this->assertSame($token, $event->fresh()->attendance_token);
    }
}
