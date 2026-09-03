<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Resources\InventoryBookingResource\Pages\ListInventoryBookings;
use App\Models\InventoryBooking;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Booking inventaris memakai pola aksi yang sama dengan booking ruangan:
 * tombol ikon inline, bukan dropdown yang panelnya bisa terpotong layar.
 */
class InventoryBookingPanelTest extends TestCase
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

    private function booking(): InventoryBooking
    {
        $booking = InventoryBooking::create([
            'booking_code' => 'INV-2026-0009',
            'requester_name' => 'Siti Aminah',
            'requester_email' => 'siti@jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Dokumentasi acara',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'status' => BookingStatus::PENDING,
        ]);

        $item = InventoryItem::create([
            'code' => 'MIC-001',
            'name' => 'Microphone Wireless',
            'category' => 'microphone',
            'condition_status' => 'good',
            'is_active' => true,
        ]);
        $booking->items()->attach($item->id);

        return $booking->fresh('items');
    }

    public function test_the_list_row_exposes_its_actions_without_a_dropdown(): void
    {
        $this->actingAs($this->admin());
        $this->booking();

        Livewire::test(ListInventoryBookings::class)
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

        Livewire::test(ListInventoryBookings::class)
            ->callTableAction('staff_approve', $booking);

        $this->assertSame(BookingStatus::APPROVED_STAFF, $booking->fresh()->status);
    }
}
