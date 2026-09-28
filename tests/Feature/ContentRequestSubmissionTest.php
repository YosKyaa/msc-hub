<?php

namespace Tests\Feature;

use App\Models\ContentRequest;
use App\Models\User;
use App\Notifications\ContentRequestStatusUpdated;
use App\Notifications\ContentRequestSubmitted;
use App\Notifications\NewContentRequestNotification;
use App\Support\RequesterSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pengajuan permintaan konten, dari tombol kirim sampai emailnya.
 *
 * Satu dari tiga pintu masuk publik, dan satu-satunya yang belum pernah diuji
 * sama sekali. Ia memicu dua email sekaligus — tanda terima bagi pengaju dan
 * kabar bagi tim MSC — jadi kegagalan di sini berarti dua pihak sama-sama
 * tidak tahu ada pekerjaan masuk.
 */
class ContentRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function asRequester(): static
    {
        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]]);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'requester_name' => 'Budi Santoso',
            'requester_type' => 'student',
            'unit' => 'HIMATIF',
            'phone' => '08123456789',
            'content_type' => 'design_poster',
            'purpose' => 'Publikasi seminar nasional',
            'deadline' => now()->addWeek()->toDateString(),
            ...$overrides,
        ];
    }

    // ------------------------------------------------------- pengajuannya

    public function test_a_request_is_saved_and_the_requester_is_sent_to_the_receipt(): void
    {
        Notification::fake();

        $response = $this->asRequester()->post(route('request.content.submit'), $this->payload());

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, ContentRequest::count());

        $request = ContentRequest::sole();

        $this->assertSame('budi@student.jgu.ac.id', $request->requester_email);
        $response->assertRedirect(route('request.success', ['code' => $request->request_code]));
        $response->assertSessionHasNoErrors();
    }

    public function test_someone_who_is_not_signed_in_is_sent_back_to_the_form(): void
    {
        $this->post(route('request.content.submit'), $this->payload())
            ->assertRedirect(route('request.content'));

        $this->assertSame(0, ContentRequest::count());
    }

    public function test_validation_errors_come_back_to_the_form(): void
    {
        $this->asRequester()
            ->post(route('request.content.submit'), $this->payload([
                'requester_name' => '',
                'deadline' => now()->subWeek()->toDateString(),
            ]))
            ->assertSessionHasErrors(['requester_name', 'deadline']);

        $this->assertSame(0, ContentRequest::count());
    }

    /**
     * Alamat email diambil dari sesi Google, bukan dari isian: pengaju tidak
     * boleh bisa mengatasnamakan orang lain.
     */
    public function test_the_email_comes_from_the_session_not_the_form(): void
    {
        Notification::fake();

        $this->asRequester()->post(route('request.content.submit'), $this->payload([
            'requester_email' => 'orang.lain@jgu.ac.id',
        ]));

        $this->assertSame('budi@student.jgu.ac.id', ContentRequest::sole()->requester_email);
    }

    // ----------------------------------------------------------- emailnya

    public function test_both_the_requester_and_the_team_are_told(): void
    {
        $this->seed(RoleSeeder::class);
        $staf = User::factory()->create(['email' => 'media@jgu.ac.id']);
        $staf->assignRole('staff_msc');

        Notification::fake();

        $this->asRequester()->post(route('request.content.submit'), $this->payload());

        // Tanda terima bagi pengajunya.
        Notification::assertSentOnDemand(ContentRequestSubmitted::class);

        // Kabar bagi tim yang harus mengerjakannya.
        Notification::assertSentTo($staf, NewContentRequestNotification::class);
    }

    /**
     * Perubahan status memberitahu pengajunya, karena dialah yang menunggu.
     */
    public function test_a_status_change_reaches_the_requester(): void
    {
        $request = ContentRequest::factory()->create([
            'requester_email' => 'budi@student.jgu.ac.id',
        ]);

        // Dipalsukan setelah dibuat: pembuatannya sendiri sudah mengirim dua
        // email, dan yang sedang diuji adalah perubahan statusnya.
        Notification::fake();

        $request->update(['status' => 'approved']);

        Notification::assertSentOnDemand(ContentRequestStatusUpdated::class);
    }

    /**
     * Status yang tidak menarik bagi pengaju tidak perlu dikabarkan; email
     * yang tidak berarti membuat yang berarti ikut diabaikan.
     */
    public function test_an_internal_status_change_stays_internal(): void
    {
        $request = ContentRequest::factory()->create([
            'requester_email' => 'budi@student.jgu.ac.id',
        ]);

        Notification::fake();

        $request->update(['status' => 'in_progress']);

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------- ketahanannya

    /**
     * Sama seperti peminjaman: pemberitahuan yang meledak — termasuk PHP Error,
     * bukan Exception — tidak boleh membuat pengaju melihat 500 atas permintaan
     * yang sebenarnya sudah tersimpan.
     */
    public function test_a_fatal_notification_failure_never_reaches_the_requester(): void
    {
        $this->app->bind(NotificationDispatcher::class, fn () => new class implements NotificationDispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new \Error('Kelas notifikasi tidak ditemukan');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \Error('Kelas notifikasi tidak ditemukan');
            }
        });

        $response = $this->asRequester()->post(route('request.content.submit'), $this->payload());

        $this->assertSame(1, ContentRequest::count());

        $response->assertRedirect(route('request.success', [
            'code' => ContentRequest::sole()->request_code,
        ]));
        $response->assertSessionHasNoErrors();
    }
}
