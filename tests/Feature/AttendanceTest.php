<?php

namespace Tests\Feature;

use App\Enums\AttendanceAction;
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

    private function url(CertificateEvent $event, AttendanceAction $action): string
    {
        return route('attendance.show', [
            'action' => $action->value,
            'token' => $event->attendanceToken($action),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submit(CertificateEvent $event, AttendanceAction $action, array $payload = [])
    {
        $target = route('attendance.store', [
            'action' => $action->value,
            'token' => $event->attendanceToken($action),
        ]);

        return $this->from($this->url($event, $action))->post($target, $payload);
    }

    private function checkIn(CertificateEvent $event, string $name = 'Budi Santoso')
    {
        return $this->submit($event, AttendanceAction::CHECK_IN, ['full_name' => $name]);
    }

    private function checkOut(CertificateEvent $event)
    {
        return $this->submit($event, AttendanceAction::CHECK_OUT);
    }

    // ---------------------------------------------------------------- token

    public function test_check_in_and_check_out_have_separate_tokens_and_urls(): void
    {
        $event = $this->event();

        $checkIn = $event->attendanceToken(AttendanceAction::CHECK_IN);
        $checkOut = $event->attendanceToken(AttendanceAction::CHECK_OUT);

        $this->assertNotEmpty($checkIn);
        $this->assertNotEmpty($checkOut);
        $this->assertNotSame($checkIn, $checkOut);
        $this->assertNotSame(
            $this->url($event, AttendanceAction::CHECK_IN),
            $this->url($event, AttendanceAction::CHECK_OUT),
        );
    }

    public function test_a_token_used_on_the_wrong_action_is_not_found(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        // Token check-in dipakai pada path check-out dan sebaliknya.
        $this->get(route('attendance.show', [
            'action' => AttendanceAction::CHECK_OUT->value,
            'token' => $event->attendanceToken(AttendanceAction::CHECK_IN),
        ]))->assertNotFound();

        $this->get(route('attendance.show', [
            'action' => AttendanceAction::CHECK_IN->value,
            'token' => $event->attendanceToken(AttendanceAction::CHECK_OUT),
        ]))->assertNotFound();
    }

    public function test_tokens_are_generated_once_and_never_change(): void
    {
        $event = CertificateEvent::factory()->create();
        $this->assertNull($event->attendanceToken(AttendanceAction::CHECK_IN));

        $event->update(['attendance_enabled' => true]);
        $event->refresh();

        $checkIn = $event->attendanceToken(AttendanceAction::CHECK_IN);
        $checkOut = $event->attendanceToken(AttendanceAction::CHECK_OUT);
        $this->assertNotEmpty($checkIn);
        $this->assertNotEmpty($checkOut);

        $event->update(['checkin_open_at' => now()]);
        $event->refresh();

        $this->assertSame($checkIn, $event->attendanceToken(AttendanceAction::CHECK_IN));
        $this->assertSame($checkOut, $event->attendanceToken(AttendanceAction::CHECK_OUT));
    }

    // ------------------------------------------------------------ check-in

    public function test_check_in_creates_participant_and_participation_from_attendance(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkIn($event, 'Budi Santoso, S.Kom.')
            ->assertRedirect($this->url($event, AttendanceAction::CHECK_IN));

        $participant = Participant::where('email', 'budi@student.jgu.ac.id')->sole();
        $this->assertSame('student', $participant->type);
        $this->assertSame('Budi Santoso, S.Kom.', $participant->name);
        $this->assertSame('Budi Santoso', $participant->google_display_name);

        $participation = CertificateEventParticipant::sole();
        $this->assertSame(ParticipantSource::ATTENDANCE, $participation->source);
        $this->assertNotNull($participation->checked_in_at);
        $this->assertNull($participation->checked_out_at);
    }

    public function test_checking_in_twice_does_not_move_the_recorded_time(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkIn($event);
        $first = CertificateEventParticipant::sole()->checked_in_at;

        $this->travel(5)->minutes();
        $this->checkIn($event)->assertSessionHas('success');

        $this->assertSame(1, CertificateEventParticipant::count());
        $this->assertTrue($first->equalTo(CertificateEventParticipant::sole()->checked_in_at));
    }

    // ----------------------------------------------------------- check-out

    public function test_check_out_is_refused_before_its_window_opens(): void
    {
        $event = CertificateEvent::factory()->withOpenAttendance()->withCheckoutLaterToday()->create();
        $this->loginAs('budi@student.jgu.ac.id');
        $this->checkIn($event);

        $this->checkOut($event)->assertSessionHas('error');

        $this->assertNull(CertificateEventParticipant::sole()->checked_out_at);
    }

    public function test_check_out_is_accepted_once_its_window_opens(): void
    {
        $event = CertificateEvent::factory()->withOpenAttendance()->withCheckoutLaterToday()->create();
        $this->loginAs('budi@student.jgu.ac.id');
        $this->checkIn($event);

        $this->travel(5)->hours();

        $this->checkOut($event)->assertSessionHas('success');

        $this->assertNotNull(CertificateEventParticipant::sole()->checked_out_at);
    }

    public function test_check_out_without_a_check_in_is_refused(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkOut($event)->assertSessionHas('error');

        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_checking_out_twice_does_not_move_the_recorded_time(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');
        $this->checkIn($event);
        $this->checkOut($event);

        $first = CertificateEventParticipant::sole()->checked_out_at;

        $this->travel(5)->minutes();
        $this->checkOut($event)->assertSessionHas('success');

        $this->assertTrue($first->equalTo(CertificateEventParticipant::sole()->checked_out_at));
    }

    // -------------------------------------------------------- nama lengkap

    public function test_a_new_participant_must_supply_a_full_name(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->submit($event, AttendanceAction::CHECK_IN, [])
            ->assertSessionHasErrors('full_name');

        $this->assertSame(0, Participant::count());
        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_a_full_name_with_digits_or_symbols_is_refused(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkIn($event, 'Budi 123')->assertSessionHasErrors('full_name');
        $this->checkIn($event, 'B')->assertSessionHasErrors('full_name');

        $this->assertSame(0, Participant::count());
    }

    public function test_the_full_name_can_only_be_supplied_once(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkIn($event, 'Budi Santoso');

        // Percobaan kedua mengirim nama lain tidak boleh mengubah apa pun.
        $this->checkIn($event, 'Nama Palsu');
        $this->submit($event, AttendanceAction::CHECK_OUT, ['full_name' => 'Nama Palsu Lagi']);

        $this->assertSame('Budi Santoso', Participant::sole()->name);
    }

    public function test_the_name_field_is_only_shown_to_participants_who_are_not_registered_yet(): void
    {
        $event = $this->event();
        $this->loginAs('budi@student.jgu.ac.id');

        $this->get($this->url($event, AttendanceAction::CHECK_IN))
            ->assertOk()
            ->assertSee('Nama lengkap')
            ->assertSee('hanya dapat diisi sekali');

        Participant::factory()->create(['email' => 'budi@student.jgu.ac.id', 'name' => 'Budi Santoso, S.Kom.']);

        $this->get($this->url($event, AttendanceAction::CHECK_IN))
            ->assertOk()
            ->assertSee('Budi Santoso, S.Kom.')
            ->assertSee('Hubungi panitia untuk mengoreksinya')
            ->assertDontSee('hanya dapat diisi sekali');
    }

    public function test_a_name_already_in_the_master_is_never_overwritten(): void
    {
        $event = $this->event();
        Participant::factory()->create([
            'email' => 'budi@student.jgu.ac.id',
            'name' => 'Budi Santoso, S.Kom.',
            'google_display_name' => null,
        ]);

        $this->loginAs('budi@student.jgu.ac.id', 'budi santoso');
        $this->checkIn($event, 'Nama Dari Formulir');

        $participant = Participant::sole();
        $this->assertSame('Budi Santoso, S.Kom.', $participant->name);
        $this->assertSame('budi santoso', $participant->google_display_name);
    }

    // ------------------------------------------------------------- akses

    public function test_check_in_outside_its_window_is_rejected_server_side(): void
    {
        $event = CertificateEvent::factory()->create([
            'attendance_enabled' => true,
            'checkin_open_at' => now()->addDay(),
            'checkin_close_at' => now()->addDays(2),
        ]);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkIn($event)->assertSessionHas('error');

        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_a_closed_check_in_window_is_rejected_server_side(): void
    {
        $event = CertificateEvent::factory()->create([
            'attendance_enabled' => true,
            'checkin_open_at' => now()->subDays(2),
            'checkin_close_at' => now()->subDay(),
        ]);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->checkIn($event)->assertSessionHas('error');

        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_non_jgu_email_cannot_use_the_attendance_route(): void
    {
        $event = $this->event();
        $this->loginAs('orang@gmail.com');

        $this->checkIn($event)->assertSessionHas('error');

        $this->assertSame(0, Participant::count());
        $this->assertSame(0, CertificateEventParticipant::count());
    }

    public function test_disabled_attendance_returns_not_found(): void
    {
        CertificateEvent::factory()->create([
            'attendance_enabled' => false,
            'checkin_token' => 'token-mati',
        ]);
        $this->loginAs('budi@student.jgu.ac.id');

        $this->get(route('attendance.show', ['action' => 'checkin', 'token' => 'token-mati']))->assertNotFound();
        $this->post(route('attendance.store', ['action' => 'checkin', 'token' => 'token-mati']))->assertNotFound();
        $this->get(route('attendance.show', ['action' => 'checkin', 'token' => 'entah']))->assertNotFound();
    }

    public function test_an_unknown_action_segment_is_not_found(): void
    {
        $event = $this->event();

        $this->get('/attend/pulang/'.$event->attendanceToken(AttendanceAction::CHECK_IN))->assertNotFound();
    }

    public function test_guest_is_redirected_to_google_login_with_a_return_url(): void
    {
        $event = $this->event();

        $this->get($this->url($event, AttendanceAction::CHECK_IN))
            ->assertOk()
            ->assertSee('Masuk dengan Google');

        $this->checkIn($event)->assertRedirect(
            route('google.redirect', ['redirect' => $this->url($event, AttendanceAction::CHECK_IN)]),
        );
    }

    // -------------------------------------------------------- kelayakan

    public function test_eligibility_is_set_according_to_each_rule(): void
    {
        // checkin_only: satu kali check-in sudah cukup.
        $event = $this->event(EligibilityRule::CHECKIN_ONLY);
        $this->loginAs('budi@student.jgu.ac.id');
        $this->checkIn($event);
        $this->assertNotNull($event->participations()->sole()->eligible_at);

        // checkin_and_checkout: belum layak sebelum check-out.
        $second = $this->event(EligibilityRule::CHECKIN_AND_CHECKOUT);
        $this->loginAs('siti@student.jgu.ac.id', 'Siti Aminah');
        $this->checkIn($second, 'Siti Aminah');
        $participation = $second->participations()->sole();
        $this->assertNull($participation->eligible_at);

        $this->checkOut($second);
        $this->assertNotNull($participation->fresh()->eligible_at);

        // manual: absensi tidak pernah mengubah flag.
        $third = $this->event(EligibilityRule::MANUAL);
        $this->loginAs('rina@student.jgu.ac.id', 'Rina Wijaya');
        $this->checkIn($third, 'Rina Wijaya');
        $this->checkOut($third);
        $this->assertNull($third->participations()->sole()->eligible_at);
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
        $this->checkIn($event);

        $this->assertSame(1, CertificateEventParticipant::count());
        $this->assertNotNull($participation->fresh()->checked_in_at);
        $this->assertSame('speaker', $participation->fresh()->role);
    }
}
