<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bukti booking dicetak dari template bersama `x-pdf.document`.
 *
 * Isi dokumen diperiksa lewat HTML yang dirender — deterministik dan mudah
 * dibaca — sedangkan pembuatan PDF-nya diuji terpisah agar galat Blade atau
 * Dompdf tetap tertangkap.
 */
class BookingPdfTest extends TestCase
{
    use RefreshDatabase;

    private function roomBooking(int $itemCount = 2): RoomBooking
    {
        $room = Room::create([
            'name' => 'Ruang Studio MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 12,
            'is_active' => true,
        ]);

        $booking = RoomBooking::create([
            'booking_code' => 'ROOM-2026-0007',
            'room_id' => $room->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi kepanitiaan',
            'attendees' => 6,
            'start_at' => now()->setTime(9, 0),
            'end_at' => now()->setTime(11, 30),
            'status' => BookingStatus::APPROVED_HEAD,
        ]);

        for ($i = 1; $i <= $itemCount; $i++) {
            $item = InventoryItem::create([
                'code' => "CAM-00{$i}",
                'name' => "Kamera Mirrorless {$i}",
                'category' => 'camera',
                'condition_status' => 'good',
                'is_active' => true,
            ]);

            $booking->inventoryItems()->attach($item->id, ['quantity' => $i + 1, 'notes' => "Catatan {$i}"]);
        }

        return $booking->fresh(['room', 'inventoryItems']);
    }

    private function html(RoomBooking $booking): string
    {
        return view('pdf.room-booking', ['booking' => $booking])->render();
    }

    public function test_the_room_booking_document_lists_every_borrowed_item(): void
    {
        $booking = $this->roomBooking();
        $html = $this->html($booking);

        $this->assertStringContainsString('Peralatan Multimedia yang Dipinjam', $html);

        foreach ($booking->inventoryItems as $item) {
            $this->assertStringContainsString($item->code, $html);
            $this->assertStringContainsString($item->name, $html);
            $this->assertStringContainsString($item->pivot->notes, $html);
        }

        // Rekap: 2 jenis, total kuantitas 2 + 3 = 5 unit.
        $this->assertStringContainsString('Total 2 jenis peralatan', $html);
        $this->assertMatchesRegularExpression('/<td class="qty">5<\/td>/', $html);
    }

    public function test_the_room_booking_document_says_so_when_nothing_was_borrowed(): void
    {
        $html = $this->html($this->roomBooking(itemCount: 0));

        // Bagian tetap dicetak agar petugas tahu bedanya "tidak meminjam"
        // dengan "data belum terisi".
        $this->assertStringContainsString('Peralatan Multimedia yang Dipinjam', $html);
        $this->assertStringContainsString('tidak meminjam peralatan apa pun', $html);
    }

    public function test_the_room_booking_document_carries_the_letterhead_terms_and_signatures(): void
    {
        $booking = $this->roomBooking();
        $booking->update([
            'staff_approved_at' => now(),
            'staff_approved_by' => User::factory()->create(['name' => 'Chika Staff'])->id,
        ]);

        $html = $this->html($booking->fresh(['room', 'inventoryItems', 'staffApprover']));

        $this->assertStringContainsString('JAKARTA GLOBAL UNIVERSITY', $html);
        $this->assertStringContainsString('Media &amp; Strategic Communications', $html);
        $this->assertStringContainsString('Bukti Booking Ruangan', $html);
        $this->assertStringContainsString('ROOM-2026-0007', $html);
        $this->assertStringContainsString('Ruang Studio MSC', $html);
        $this->assertStringContainsString('Ketentuan', $html);
        $this->assertStringContainsString('Kepala MSC', $html);
        $this->assertStringContainsString('Chika Staff', $html);
        // Logo kop surat disematkan sebagai data URI.
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_the_room_booking_document_shows_the_schedule_and_duration(): void
    {
        $html = $this->html($this->roomBooking());

        $this->assertStringContainsString('09:00', $html);
        $this->assertStringContainsString('11:30', $html);
        $this->assertStringContainsString('2.5 jam', $html);
    }

    public function test_the_room_booking_pdf_is_actually_generated(): void
    {
        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.room-booking', ['booking' => $this->roomBooking()])
            ->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(20_000, strlen($pdf));
        // Logo kop surat ikut tertanam.
        $this->assertStringContainsString('/Image', $pdf);
    }

    public function test_the_inventory_booking_document_shares_the_same_letterhead(): void
    {
        $booking = \App\Models\InventoryBooking::create([
            'booking_code' => 'INV-2026-0003',
            'requester_name' => 'Siti Aminah',
            'requester_email' => 'siti@jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Dokumentasi acara',
            'start_at' => now(),
            'end_at' => now()->addDay(),
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

        $html = view('pdf.inventory-booking', ['booking' => $booking->fresh('items')])->render();

        $this->assertStringContainsString('JAKARTA GLOBAL UNIVERSITY', $html);
        $this->assertStringContainsString('Bukti Peminjaman Inventaris', $html);
        $this->assertStringContainsString('INV-2026-0003', $html);
        $this->assertStringContainsString('Microphone Wireless', $html);
        $this->assertStringContainsString('MIC-001', $html);
    }
}
