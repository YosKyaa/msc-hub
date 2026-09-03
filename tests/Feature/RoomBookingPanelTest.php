<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookingResource;
use App\Filament\Resources\RoomBookingResource\Pages\ListRoomBookings;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Peralatan yang dipinjam bersama ruangan harus terlihat di panel.
 *
 * Sebelumnya data pivot tersimpan dengan benar tetapi hanya muncul sebagai
 * angka di kolom tabel, sehingga petugas tidak tahu alat apa yang harus
 * disiapkan tanpa membuka halaman ubah.
 */
class RoomBookingPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function booking(int $itemCount = 2, BookingStatus $status = BookingStatus::PENDING): RoomBooking
    {
        $room = Room::create([
            'name' => 'Ruang Studio MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 12,
            'is_active' => true,
        ]);

        $booking = RoomBooking::create([
            'booking_code' => 'ROOM-2026-0009',
            'room_id' => $room->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => $status,
        ]);

        for ($i = 1; $i <= $itemCount; $i++) {
            $item = InventoryItem::create([
                'code' => "CAM-00{$i}",
                'name' => "Kamera Mirrorless {$i}",
                'category' => 'camera',
                'condition_status' => 'good',
                'is_active' => true,
            ]);

            $booking->inventoryItems()->attach($item->id, ['quantity' => 2, 'notes' => "Lensa kit {$i}"]);
        }

        return $booking->fresh(['room', 'inventoryItems']);
    }

    public function test_the_detail_page_lists_the_borrowed_equipment(): void
    {
        $this->actingAs($this->admin());
        $booking = $this->booking();

        $response = $this->get(RoomBookingResource::getUrl('view', ['record' => $booking]))->assertOk();

        $response->assertSee('Peralatan Multimedia yang Dipinjam');

        foreach ($booking->inventoryItems as $item) {
            $response->assertSee($item->code);
            $response->assertSee($item->name);
            $response->assertSee($item->pivot->notes);
        }

        // Ringkasan: 2 jenis, masing-masing 2 unit.
        $response->assertSee('4 unit dari 2 jenis peralatan.');
    }

    public function test_the_detail_page_says_so_when_nothing_was_borrowed(): void
    {
        $this->actingAs($this->admin());
        $booking = $this->booking(itemCount: 0);

        $this->get(RoomBookingResource::getUrl('view', ['record' => $booking]))
            ->assertOk()
            ->assertSee('Peralatan Multimedia yang Dipinjam')
            ->assertSee('tidak meminjam peralatan apa pun');
    }

    public function test_the_list_row_exposes_its_quick_actions_without_a_dropdown(): void
    {
        $this->actingAs($this->admin());
        $this->booking();

        Livewire::test(ListRoomBookings::class)
            ->assertSuccessful()
            ->assertTableActionExists('view')
            ->assertTableActionExists('export_pdf')
            ->assertTableActionExists('staff_approve')
            ->assertTableActionExists('reject');
    }

    public function test_approving_from_the_list_moves_the_booking_forward(): void
    {
        $this->actingAs($this->admin());
        $booking = $this->booking();

        Livewire::test(ListRoomBookings::class)
            ->callTableAction('staff_approve', $booking);

        $this->assertSame(BookingStatus::APPROVED_STAFF, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->staff_approved_at);
    }

    public function test_the_pdf_download_is_reachable_from_the_list(): void
    {
        $this->actingAs($this->admin());
        $booking = $this->booking();

        $response = RoomBookingResource::downloadPdf($booking);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            $booking->booking_code,
            $response->headers->get('content-disposition'),
        );
    }
}
