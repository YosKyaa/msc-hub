<?php

namespace App\Support;

use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi formulir resmi FM/JGU/L.89 — "Form Peminjaman Ruangan / Fasilitas
 * Multimedia JGU" — untuk satu booking.
 *
 * Formulir kampus melayani peminjaman ruangan maupun fasilitas dengan lembar
 * yang sama, jadi kedua jenis booking dinormalkan ke bentuk ini dan dicetak
 * oleh satu template.
 */
class BorrowingFormData
{
    public const FORM_CODE = 'FM/JGU/L.89';

    /**
     * @param  Collection<int, array{name: string, detail: string|null}>  $facilities
     */
    public function __construct(
        public readonly string $bookingCode,
        public readonly ?string $supervisorName,
        public readonly ?string $activity,
        public readonly ?string $unit,
        public readonly ?string $attendees,
        public readonly string $requesterName,
        public readonly ?string $requesterPhone,
        public readonly Carbon $startAt,
        public readonly Carbon $endAt,
        public readonly Collection $facilities,
        public readonly ?string $staffApprover,
        public readonly ?string $headApprover,
    ) {}

    public static function fromRoomBooking(RoomBooking $booking): self
    {
        $booking->loadMissing(['room', 'inventoryItems', 'staffApprover', 'headApprover']);

        // Ruangan dicatat sebagai baris pertama fasilitas, sesuai formulir yang
        // menggabungkan peminjaman ruangan dan alat dalam satu daftar.
        $facilities = collect([[
            'name' => 'Ruangan: '.$booking->room?->name,
            'detail' => $booking->room?->location,
        ]]);

        foreach ($booking->inventoryItems as $item) {
            $facilities->push([
                'name' => $item->name.' ('.$item->code.')',
                'detail' => trim(($item->pivot->quantity ?? 1).' unit'.($item->pivot->notes ? ' — '.$item->pivot->notes : '')),
            ]);
        }

        return new self(
            bookingCode: $booking->booking_code,
            supervisorName: $booking->supervisor_name,
            activity: $booking->purpose,
            unit: $booking->unit,
            attendees: $booking->attendees ? $booking->attendees.' orang' : null,
            requesterName: $booking->requester_name,
            requesterPhone: $booking->requester_phone,
            startAt: $booking->start_at,
            endAt: $booking->end_at,
            facilities: $facilities,
            staffApprover: $booking->staffApprover?->name,
            headApprover: $booking->headApprover?->name,
        );
    }

    public static function fromInventoryBooking(InventoryBooking $booking): self
    {
        $booking->loadMissing(['items', 'staffApprover', 'headApprover']);

        $facilities = $booking->items->map(fn ($item) => [
            'name' => $item->name.' ('.$item->code.')',
            'detail' => $item->category?->getLabel(),
        ]);

        return new self(
            bookingCode: $booking->booking_code,
            supervisorName: $booking->supervisor_name,
            activity: $booking->purpose,
            unit: $booking->unit,
            attendees: null,
            requesterName: $booking->requester_name,
            requesterPhone: $booking->requester_phone,
            startAt: $booking->start_at,
            endAt: $booking->end_at,
            facilities: $facilities,
            staffApprover: $booking->staffApprover?->name,
            headApprover: $booking->headApprover?->name,
        );
    }

    /**
     * "Senin, 6 September 2026" — bila peminjaman melintasi hari, kedua
     * tanggalnya ditulis agar tidak menyesatkan petugas.
     *
     * Locale ditetapkan eksplisit: ini dokumen resmi berbahasa Indonesia,
     * tidak boleh ikut berubah bila bahasa antarmuka diganti.
     */
    public function borrowedOn(): string
    {
        $start = $this->startAt->locale('id')->translatedFormat('l, d F Y');

        return $this->startAt->isSameDay($this->endAt)
            ? $start
            : $start.' – '.$this->endAt->locale('id')->translatedFormat('l, d F Y');
    }

    public function timeRange(): string
    {
        return $this->startAt->format('H:i').' – '.$this->endAt->format('H:i').' WIB';
    }

    public function fileName(): string
    {
        return 'form-peminjaman-'.$this->bookingCode.'.pdf';
    }
}
