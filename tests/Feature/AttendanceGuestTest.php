<?php

namespace Tests\Feature;

use App\Enums\AttendanceAction;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Support\AttendanceGuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Absensi untuk peserta di luar JGU.
 *
 * Kegiatan yang terbuka untuk umum dulu tetap menolak akun Gmail dengan
 * "Hanya email @jgu.ac.id atau @student.jgu.ac.id yang diperbolehkan",
 * sehingga peserta dari luar kampus tidak bisa absen dan tidak mendapat
 * sertifikat. Kini bisa, per kegiatan — tanpa membuka portal pengajuan dan
 * peminjaman, yang tetap khusus sivitas JGU.
 */
class AttendanceGuestTest extends TestCase
{
    use RefreshDatabase;

    private function googleReturns(string $email, string $name = 'Rina Wulandari'): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('987654321');
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('with')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    private function event(bool $tamu): CertificateEvent
    {
        return CertificateEvent::factory()->withOpenAttendance()->create(['attendance_guests_allowed' => $tamu]);
    }

    private function url(CertificateEvent $event): string
    {
        return $event->attendanceUrl(AttendanceAction::CHECK_IN);
    }

    private function loginThroughGoogle(CertificateEvent $event, string $email)
    {
        $this->googleReturns($email);
        $this->get(route('google.redirect', ['redirect' => $this->url($event)]));

        return $this->get(route('auth.google.callback', ['code' => 'kode-uji']));
    }

    private function checkIn(CertificateEvent $event)
    {
        return $this->from($this->url($event))->post(
            route('attendance.store', ['action' => 'checkin', 'token' => $event->attendanceToken(AttendanceAction::CHECK_IN)]),
            ['full_name' => 'Rina Wulandari'],
        );
    }

    /**
     * Yang diminta: kegiatan terbuka untuk umum menerima akun Gmail.
     */
    public function test_a_gmail_account_can_check_in_to_an_event_open_to_guests(): void
    {
        $event = $this->event(tamu: true);

        $this->loginThroughGoogle($event, 'rina.wulandari@gmail.com')
            ->assertRedirect($this->url($event))
            ->assertSessionHas('success');

        $this->checkIn($event)->assertSessionHas('success');

        $peserta = Participant::where('email', 'rina.wulandari@gmail.com')->sole();
        $this->assertSame('guest', $peserta->type);
        $this->assertNotNull(CertificateEventParticipant::where('participant_id', $peserta->id)->value('checked_in_at'));
    }

    /**
     * Bawaannya tetap khusus email JGU.
     */
    public function test_an_event_closed_to_guests_still_refuses_gmail(): void
    {
        $event = $this->event(tamu: false);

        $this->loginThroughGoogle($event, 'rina.wulandari@gmail.com')
            ->assertSessionHas('error', 'Hanya email @jgu.ac.id atau @student.jgu.ac.id yang diperbolehkan.');

        $this->assertNull(AttendanceGuest::current());
        $this->assertSame(0, Participant::count());
    }

    /**
     * Akun tamu hanya untuk absen. Seandainya ia masuk ke sesi pemohon,
     * siapa pun dengan Gmail bisa mengajukan peminjaman ruangan.
     */
    public function test_a_guest_never_reaches_the_borrowing_portal(): void
    {
        $event = $this->event(tamu: true);
        $this->loginThroughGoogle($event, 'rina.wulandari@gmail.com');

        $this->assertNull(session('requester'));
        $this->assertNotNull(AttendanceGuest::current());

        $this->get(route('booking.room'))->assertDontSee('rina.wulandari@gmail.com');
    }

    /**
     * Login lewat halaman lain tetap ditolak untuk Gmail, walau ada kegiatan
     * yang menerima tamu.
     */
    public function test_gmail_is_refused_anywhere_but_the_guest_event_page(): void
    {
        $this->event(tamu: true);
        $this->googleReturns('rina.wulandari@gmail.com');

        $this->get(route('google.redirect', ['redirect' => '/borrow/inventory']));

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))->assertSessionHas('error');
        $this->assertNull(AttendanceGuest::current());
    }

    /**
     * Sesi tamu dari kegiatan terbuka tidak berlaku di kegiatan lain yang
     * khusus JGU.
     */
    public function test_a_guest_session_does_not_carry_over_to_a_jgu_only_event(): void
    {
        $terbuka = $this->event(tamu: true);
        $khusus = $this->event(tamu: false);

        $this->loginThroughGoogle($terbuka, 'rina.wulandari@gmail.com');

        $this->get($this->url($khusus))->assertSee('Masuk dengan Google');
        $this->checkIn($khusus);

        $this->assertSame(0, CertificateEventParticipant::where('certificate_event_id', $khusus->id)->count());
    }

    public function test_the_page_tells_guests_any_google_account_works(): void
    {
        $this->get($this->url($this->event(tamu: true)))->assertSee('akun Google apa pun bisa dipakai');
        $this->get($this->url($this->event(tamu: false)))->assertSee('Gunakan email @jgu.ac.id atau @student.jgu.ac.id.');
    }

    public function test_logging_out_ends_the_guest_session(): void
    {
        $event = $this->event(tamu: true);
        $this->loginThroughGoogle($event, 'rina.wulandari@gmail.com');

        $this->post(route('auth.google.logout'), ['redirect' => $this->url($event)]);

        $this->assertNull(AttendanceGuest::current());
    }
}
