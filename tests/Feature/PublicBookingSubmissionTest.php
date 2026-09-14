<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pengajuan dari sisi peminjam.
 *
 * Di produksi, tombol kirim menghasilkan 500 padahal datanya tersimpan.
 * Penyebabnya efek samping setelah commit: observer berjalan dengan
 * $afterCommit = true, notifikasi melempar PHP Error, dan controller hanya
 * menangkap Exception sehingga Error lolos menjadi 500. Test di sini menjaga
 * agar kegagalan pemberitahuan tidak pernah lagi menimpa peminjam.
 */
class PublicBookingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);
    }

    private function asRequester(): static
    {
        $this->withSession(['requester' => [
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
        $start = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        return [
            'requester_name' => 'Budi Santoso',
            'requester_phone' => '081234567890',
            'supervisor_name' => 'Dr. Djoko Susilo',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------ pengajuan

    public function test_a_room_booking_is_submitted_and_confirmed(): void
    {
        $this->asRequester()
            ->post(route('booking.room.submit'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $booking = RoomBooking::sole();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame('081234567890', $booking->requester_phone);
        $this->assertSame('Dr. Djoko Susilo', $booking->supervisor_name);
    }

    public function test_the_success_page_renders_after_submitting(): void
    {
        $this->asRequester()->post(route('booking.room.submit'), $this->payload());

        $booking = RoomBooking::sole();

        $this->asRequester()
            ->get(route('booking.success', ['type' => 'room', 'code' => $booking->booking_code]))
            ->assertOk()
            ->assertSee($booking->booking_code);
    }

    /**
     * Inti perbaikannya: pemberitahuan yang meledak setelah commit — termasuk
     * PHP Error seperti kelas Filament yang tidak ada — tidak boleh membuat
     * peminjam melihat 500 atas booking yang sebenarnya berhasil.
     */
    public function test_a_fatal_notification_failure_never_reaches_the_requester(): void
    {
        // Persis kegagalan produksi: pengiriman notifikasi melempar PHP Error,
        // bukan Exception, tepat setelah booking ter-commit.
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

        $response = $this->asRequester()->post(route('booking.room.submit'), $this->payload());

        // Booking tersimpan, dan peminjam tetap diarahkan ke halaman sukses —
        // bukan halaman 500.
        $this->assertSame(1, RoomBooking::count());

        $response->assertRedirect(route('booking.success', [
            'type' => 'room',
            'code' => RoomBooking::sole()->booking_code,
        ]));
        $response->assertSessionHasNoErrors();
    }

    public function test_validation_errors_come_back_to_the_form(): void
    {
        $this->asRequester()
            ->post(route('booking.room.submit'), $this->payload(['unit' => '', 'attendees' => 99]))
            ->assertSessionHasErrors(['unit', 'attendees']);

        $this->assertSame(0, RoomBooking::count());
    }

    // ------------------------------------------------- konfirmasi dan toast

    public function test_the_room_form_asks_for_confirmation_before_sending(): void
    {
        $response = $this->asRequester()->get(route('booking.room'))->assertOk();

        $response->assertSee('Kirim pengajuan booking ruangan?');
        $response->assertSee('Ya, Ajukan');
        // Ringkasan pengajuan ikut ditampilkan sebelum dikirim.
        $response->assertSee('Jumlah peserta');
        // Klik ganda tidak boleh menghasilkan dua booking.
        $response->assertSee('Mengirim', false);
        $response->assertSee('sending = true', false);
    }

    public function test_the_inventory_form_asks_for_confirmation_before_sending(): void
    {
        InventoryItem::create([
            'code' => 'CAM-001',
            'name' => 'Kamera Mirrorless',
            'category' => 'camera',
            'condition_status' => 'good',
            'is_active' => true,
        ]);

        $this->asRequester()
            ->get(route('booking.inventory'))
            ->assertOk()
            ->assertSee('Kirim pengajuan peminjaman alat?')
            ->assertSee('Ya, Ajukan');
    }

    public function test_the_outcome_is_shown_as_a_self_dismissing_toast(): void
    {
        $this->asRequester()->post(route('booking.room.submit'), $this->payload());

        $booking = RoomBooking::sole();

        $response = $this->asRequester()
            ->get(route('booking.success', ['type' => 'room', 'code' => $booking->booking_code]))
            ->assertOk();

        // Toast muncul dan menutup sendiri, sehingga peminjam tahu hasilnya
        // tanpa harus menutupnya manual.
        $response->assertSee('Berhasil');
        $response->assertSee('visible = false', false);
    }
}
