<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\InventoryBooking;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Support\BorrowingFormData;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Formulir resmi kampus FM/JGU/L.89 dicetak dari data booking.
 *
 * Isinya diperiksa lewat HTML yang dirender; rutenya diuji terpisah untuk
 * memastikan bawaannya pratinjau di browser, bukan unduhan langsung.
 */
class BorrowingFormTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function roomBooking(array $attributes = [], int $itemCount = 2): RoomBooking
    {
        $room = Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);

        $booking = RoomBooking::create([
            'booking_code' => 'ROOM-2026-0011',
            'room_id' => $room->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'requester_phone' => '081234567890',
            'supervisor_name' => 'Dr. Djoko Susilo',
            'unit' => 'HIMATIF',
            'purpose' => 'Seminar Nasional Teknologi',
            'attendees' => 7,
            'start_at' => now()->setDate(2026, 9, 10)->setTime(9, 0),
            'end_at' => now()->setDate(2026, 9, 10)->setTime(12, 30),
            'status' => BookingStatus::APPROVED_HEAD,
            ...$attributes,
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

    private function html(RoomBooking $booking): string
    {
        return view('pdf.borrowing-form', [
            'form' => BorrowingFormData::fromRoomBooking($booking),
        ])->render();
    }

    // ------------------------------------------------------------- isi form

    public function test_the_form_reproduces_the_official_layout(): void
    {
        $html = $this->html($this->roomBooking());

        $this->assertStringContainsString('FM/JGU/L.89', $html);
        $this->assertStringContainsString('Form Peminjaman Ruangan / Fasilitas Multimedia JGU', $html);

        foreach ([
            'Penanggung Jawab (Dosen)*', 'Acara / Kegiatan*',
            'Nama Fakultas / Organisasi*', 'Jumlah Peserta*',
            'Nama Peminjam*', 'Hari dan Tgl Peminjaman*',
            'No. HP Peminjam*', 'Waktu*',
            'Daftar Fasilitas yang Dipinjam',
            'Rekomendasi', 'Peminjam**',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "Label \"{$label}\" hilang dari formulir.");
        }

        $this->assertStringContainsString('Form ini hanya berlaku untuk 1 (Satu) acara dalam 1 (Satu) hari', $html);
        $this->assertStringContainsString('wajib diisi oleh Peminjam', $html);
        $this->assertStringContainsString('isikan jabatan atau posisi peminjam', $html);
    }

    public function test_the_form_is_filled_from_the_booking(): void
    {
        $html = $this->html($this->roomBooking());

        $this->assertStringContainsString('Dr. Djoko Susilo', $html);
        $this->assertStringContainsString('Seminar Nasional Teknologi', $html);
        $this->assertStringContainsString('HIMATIF', $html);
        $this->assertStringContainsString('7 orang', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('081234567890', $html);
        $this->assertStringContainsString('09:00 – 12:30 WIB', $html);
        $this->assertStringContainsString('September 2026', $html);
    }

    public function test_the_facility_list_covers_the_room_and_every_item(): void
    {
        $booking = $this->roomBooking();
        $html = $this->html($booking);

        $this->assertStringContainsString('Ruangan: Ruang Multimedia MSC', $html);

        foreach ($booking->inventoryItems as $item) {
            $this->assertStringContainsString($item->name, $html);
            $this->assertStringContainsString($item->code, $html);
        }
    }

    public function test_missing_fields_are_printed_as_blank_lines(): void
    {
        $booking = $this->roomBooking(['supervisor_name' => null, 'requester_phone' => null]);
        $html = $this->html($booking);

        // Formulir tetap lengkap labelnya, dan nama penandatangan dosen kosong
        // ditampilkan sebagai ruang isian tangan.
        $this->assertStringContainsString('Penanggung Jawab (Dosen)*', $html);
        $this->assertStringContainsString('(………………………)', $html);
    }

    public function test_pending_approvals_leave_the_signature_lines_open(): void
    {
        $booking = $this->roomBooking(['status' => BookingStatus::PENDING]);
        $html = $this->html($booking);

        // Dua kolom rekomendasi MSC belum bernama sampai disetujui; kolom
        // dosen tetap terisi karena datanya sudah ada pada booking.
        $this->assertSame(2, substr_count($html, '(………………………)'));
        $this->assertStringContainsString('Dr. Djoko Susilo', $html);
    }

    // --------------------------------------------------------------- rute

    public function test_the_route_previews_in_the_browser_by_default(): void
    {
        $booking = $this->roomBooking();

        $response = $this->actingAs($this->staff())
            ->get(route('borrowing-form.room', $booking))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('inline', $response->headers->get('content-disposition'));
    }

    public function test_the_route_downloads_only_when_asked(): void
    {
        $booking = $this->roomBooking();

        $response = $this->actingAs($this->staff())
            ->get(route('borrowing-form.room', ['roomBooking' => $booking, 'unduh' => 1]))
            ->assertOk();

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringContainsString('ROOM-2026-0011', $disposition);
    }

    public function test_the_form_is_closed_to_guests(): void
    {
        $booking = $this->roomBooking();

        $this->get(route('borrowing-form.room', $booking))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_inventory_booking_uses_the_same_form(): void
    {
        $booking = InventoryBooking::create([
            'booking_code' => 'INV-2026-0011',
            'requester_name' => 'Siti Aminah',
            'requester_email' => 'siti@jgu.ac.id',
            'requester_phone' => '081200000000',
            'supervisor_name' => 'Dr. Rina Kartika',
            'unit' => 'HIMATIF',
            'purpose' => 'Dokumentasi wisuda',
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

        $html = view('pdf.borrowing-form', [
            'form' => BorrowingFormData::fromInventoryBooking($booking->fresh('items')),
        ])->render();

        $this->assertStringContainsString('FM/JGU/L.89', $html);
        $this->assertStringContainsString('INV-2026-0011', $html);
        $this->assertStringContainsString('Microphone Wireless', $html);
        $this->assertStringContainsString('Dr. Rina Kartika', $html);
    }

    public function test_the_pdf_is_actually_generated_with_the_letterhead(): void
    {
        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.borrowing-form', ['form' => BorrowingFormData::fromRoomBooking($this->roomBooking())])
            ->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        // Logo dan pita kaki surat ikut tertanam.
        $this->assertStringContainsString('/Image', $pdf);
    }
}
