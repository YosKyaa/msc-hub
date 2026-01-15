<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\InventoryCategory;
use App\Models\InventoryBooking;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PublicBookingController extends Controller
{
    // Middleware check - redirect to Google login if not authenticated
    protected function ensureAuthenticated(Request $request)
    {
        if (!Session::has('requester')) {
            $redirect = $request->fullUrl();
            return redirect()->route('google.redirect', ['redirect' => $redirect]);
        }
        return null;
    }

    protected function getRequester(): ?array
    {
        return Session::get('requester');
    }

    // ========================
    // INVENTORY BOOKING
    // ========================

    public function showInventoryForm(Request $request)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();
        $items = InventoryItem::where('is_active', true)
            ->whereIn('condition_status', ['good', 'minor_issue'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('booking.inventory-form', compact('requester', 'items'));
    }

    public function submitInventoryBooking(Request $request)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();

        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'unit' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'items' => 'required|array|min:1',
            'items.*' => 'exists:inventory_items,id',
        ], [
            'unit.required' => 'Unit/Fakultas harus dipilih.',
            'items.required' => 'Pilih minimal 1 item untuk dipinjam.',
            'items.min' => 'Pilih minimal 1 item untuk dipinjam.',
            'start_at.after' => 'Waktu mulai harus lebih dari sekarang.',
            'end_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        $startAt = new \DateTime($validated['start_at']);
        $endAt = new \DateTime($validated['end_at']);

        // Validate weekday only (Monday-Friday)
        $startDay = (int) $startAt->format('N');
        $endDay = (int) $endAt->format('N');
        if ($startDay > 5 || $endDay > 5) {
            return back()->withInput()->withErrors([
                'time' => 'Peminjaman hanya dapat dilakukan pada hari Senin - Jumat.'
            ]);
        }

        // Validate operating hours (08:00 - 16:00)
        $hourErrors = InventoryBooking::validateOperatingHours($startAt, $endAt);
        if (!empty($hourErrors)) {
            return back()->withInput()->withErrors(['time' => implode(' ', $hourErrors)]);
        }

        // Check maximum bookings per month (2x per month)
        $currentMonth = $startAt->format('Y-m');
        $bookingsThisMonth = InventoryBooking::where('requester_email', $requester['email'])
            ->whereRaw('DATE_FORMAT(start_at, "%Y-%m") = ?', [$currentMonth])
            ->whereIn('status', [
                BookingStatus::PENDING,
                BookingStatus::APPROVED_STAFF,
                BookingStatus::APPROVED_HEAD,
            ])
            ->count();

        if ($bookingsThisMonth >= 2) {
            return back()->withInput()->withErrors([
                'error' => 'Anda sudah melakukan 2x peminjaman pada bulan ini. Maksimal peminjaman per bulan adalah 2x.'
            ]);
        }

        // Check only active items
        $activeItemIds = InventoryItem::whereIn('id', $validated['items'])
            ->where('is_active', true)
            ->whereIn('condition_status', ['good', 'minor_issue'])
            ->pluck('id')
            ->toArray();

        if (empty($activeItemIds)) {
            return back()->withInput()->withErrors(['items' => 'Item yang dipilih tidak tersedia.']);
        }

        // Check for overlapping bookings
        $conflicts = InventoryBooking::checkItemOverlaps($activeItemIds, $startAt, $endAt);
        if (!empty($conflicts)) {
            return back()->withInput()->withErrors([
                'items' => 'Item berikut tidak tersedia pada waktu yang dipilih: ' . implode(', ', $conflicts)
            ]);
        }

        // Create booking in transaction
        try {
            $booking = DB::transaction(function () use ($validated, $requester, $activeItemIds) {
                $booking = InventoryBooking::create([
                    'booking_code' => InventoryBooking::generateBookingCode(),
                    'requester_name' => $validated['requester_name'],
                    'requester_email' => $requester['email'],
                    'requester_google_id' => $requester['google_id'] ?? null,
                    'unit' => $validated['unit'],
                    'purpose' => $validated['purpose'],
                    'start_at' => $validated['start_at'],
                    'end_at' => $validated['end_at'],
                    'status' => BookingStatus::PENDING,
                ]);

                // Attach items
                $booking->items()->attach($activeItemIds);

                return $booking;
            });

            return redirect()->route('booking.success', [
                'type' => 'inventory',
                'code' => $booking->booking_code
            ]);
        } catch (\Exception $e) {
            \Log::error('Inventory booking error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withInput()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    // ========================
    // ROOM BOOKING
    // ========================

    public function showRoomForm(Request $request)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();
        $room = Room::where('is_active', true)->first();

        if (!$room) {
            return view('booking.room-unavailable');
        }

        // Get multimedia items for optional borrowing
        $inventoryItems = InventoryItem::where('is_active', true)
            ->whereIn('category', [
                InventoryCategory::CAMERA,
                InventoryCategory::MICROPHONE,
                InventoryCategory::AUDIO,
                InventoryCategory::LIGHTING,
                InventoryCategory::VIDEO,
                InventoryCategory::PROJECTOR,
            ])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('booking.room-form', compact('requester', 'room', 'inventoryItems'));
    }

    public function submitRoomBooking(Request $request)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();

        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'unit' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'attendees' => 'required|integer|min:1|max:7',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'inventory_items' => 'nullable|array',
            'inventory_items.*' => 'exists:inventory_items,id',
        ], [
            'unit.required' => 'Unit/Fakultas harus dipilih.',
            'attendees.required' => 'Jumlah peserta harus diisi.',
            'attendees.max' => 'Jumlah peserta maksimal 7 orang.',
            'start_at.after' => 'Waktu mulai harus lebih dari sekarang.',
            'end_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        $room = Room::where('is_active', true)->first();
        if (!$room) {
            return back()->withInput()->withErrors(['error' => 'Ruangan tidak tersedia.']);
        }

        $startAt = new \DateTime($validated['start_at']);
        $endAt = new \DateTime($validated['end_at']);

        // Validate weekday only (Monday-Friday)
        $startDay = (int) $startAt->format('N');
        $endDay = (int) $endAt->format('N');
        if ($startDay > 5 || $endDay > 5) {
            return back()->withInput()->withErrors([
                'time' => 'Booking hanya dapat dilakukan pada hari Senin - Jumat.'
            ]);
        }

        // Validate operating hours
        $hourErrors = $room->validateOperatingHours($startAt, $endAt);
        if (!empty($hourErrors)) {
            return back()->withInput()->withErrors(['time' => implode(' ', $hourErrors)]);
        }

        // Check maximum bookings per month (2x per month)
        $currentMonth = $startAt->format('Y-m');
        $bookingsThisMonth = RoomBooking::where('requester_email', $requester['email'])
            ->whereRaw('DATE_FORMAT(start_at, "%Y-%m") = ?', [$currentMonth])
            ->whereIn('status', [
                BookingStatus::PENDING,
                BookingStatus::APPROVED_STAFF,
                BookingStatus::APPROVED_HEAD,
            ])
            ->count();

        if ($bookingsThisMonth >= 2) {
            return back()->withInput()->withErrors([
                'error' => 'Anda sudah melakukan 2x booking pada bulan ini. Maksimal booking per bulan adalah 2x.'
            ]);
        }

        // Check for overlapping bookings
        if ($room->hasOverlappingBookings($startAt, $endAt)) {
            return back()->withInput()->withErrors([
                'time' => 'Ruangan sudah dibooking pada waktu yang dipilih. Silakan pilih waktu lain.'
            ]);
        }

        // Create booking
        try {
            $booking = DB::transaction(function () use ($validated, $requester, $room) {
                $booking = RoomBooking::create([
                    'booking_code' => RoomBooking::generateBookingCode(),
                    'room_id' => $room->id,
                    'requester_name' => $validated['requester_name'],
                    'requester_email' => $requester['email'],
                    'requester_google_id' => $requester['google_id'] ?? null,
                    'unit' => $validated['unit'],
                    'purpose' => $validated['purpose'],
                    'attendees' => $validated['attendees'],
                    'start_at' => $validated['start_at'],
                    'end_at' => $validated['end_at'],
                    'status' => BookingStatus::PENDING,
                ]);

                // Attach inventory items if any
                if (!empty($validated['inventory_items'])) {
                    $itemsData = [];
                    foreach ($validated['inventory_items'] as $itemId) {
                        $itemsData[$itemId] = [
                            'quantity' => 1,
                            'notes' => null
                        ];
                    }
                    $booking->inventoryItems()->sync($itemsData);
                }

                return $booking;
            });

            return redirect()->route('booking.success', [
                'type' => 'room',
                'code' => $booking->booking_code
            ]);
        } catch (\Exception $e) {
            \Log::error('Room booking error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withInput()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    // ========================
    // SUCCESS PAGE
    // ========================

    public function showSuccess(Request $request, string $type, string $code)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();

        if ($type === 'inventory') {
            $booking = InventoryBooking::where('booking_code', $code)
                ->where('requester_email', $requester['email'])
                ->first();
        } else {
            $booking = RoomBooking::where('booking_code', $code)
                ->where('requester_email', $requester['email'])
                ->first();
        }

        if (!$booking) {
            abort(404);
        }

        return view('booking.success', compact('booking', 'type'));
    }

    // ========================
    // MY BOOKINGS
    // ========================

    public function myBookings(Request $request)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();

        $inventoryBookings = InventoryBooking::where('requester_email', $requester['email'])
            ->orderBy('created_at', 'desc')
            ->get();

        $roomBookings = RoomBooking::where('requester_email', $requester['email'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('booking.my-bookings', compact('requester', 'inventoryBookings', 'roomBookings'));
    }

    public function showBookingDetail(Request $request, string $type, string $code)
    {
        if ($redirect = $this->ensureAuthenticated($request)) {
            return $redirect;
        }

        $requester = $this->getRequester();

        if ($type === 'inventory') {
            $booking = InventoryBooking::with('items')
                ->where('booking_code', $code)
                ->where('requester_email', $requester['email'])
                ->first();
        } elseif ($type === 'room') {
            $booking = RoomBooking::with('room')
                ->where('booking_code', $code)
                ->where('requester_email', $requester['email'])
                ->first();
        } else {
            abort(404);
        }

        if (!$booking) {
            abort(404);
        }

        return view('booking.detail', compact('booking', 'type', 'requester'));
    }

    // ========================
    // LOGOUT
    // ========================

    public function logout(Request $request)
    {
        Session::forget('requester');

        return redirect()->route('booking.inventory')
            ->with('success', 'Anda telah logout.');
    }
}
