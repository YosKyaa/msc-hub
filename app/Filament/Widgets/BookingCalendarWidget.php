<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\ContentRequest;
use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use Filament\Notifications\Notification;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class BookingCalendarWidget extends CalendarWidget
{
    protected static ?int $sort = 1;
    
    protected string|HtmlString|null|bool $heading = 'Jadwal Booking & Request';

    protected int | string | array $columnSpan = 'full';

    protected bool $eventClickEnabled = true;
    
    // Only visible to staff, head, and admin
    public static function canView(): bool
    {
        return true; // DEBUG: Force show
        // $user = auth()->user();
        // return in_array($user->role, ['admin', 'staff', 'head']);
    }

    public function getEvents(FetchInfo $fetchInfo): Collection|Builder|array
    {
        $events = collect();

        // Get room bookings
        $roomBookings = RoomBooking::query()
            ->when($fetchInfo->start, fn($q) => $q->where('start_at', '>=', $fetchInfo->start))
            ->when($fetchInfo->end, fn($q) => $q->where('end_at', '<=', $fetchInfo->end))
            ->with('room')
            ->get();

        foreach ($roomBookings as $booking) {
            $events->push(
                CalendarEvent::make($booking)
                    ->title('🏢 ' . $booking->requester_name . ' (' . $booking->unit . ')')
                    ->start($booking->start_at)
                    ->end($booking->end_at)
                    ->backgroundColor($this->getStatusColor($booking->status))
                    ->extendedProps([
                        'type' => 'room',
                        'id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'requester_name' => $booking->requester_name,
                        'requester_email' => $booking->requester_email,
                        'unit' => $booking->unit,
                        'status' => $booking->status->getLabel(),
                        'room' => $booking->room?->name,
                        'attendees' => $booking->attendees,
                        'purpose' => $booking->purpose ?? '-',
                        'start_at' => $booking->start_at->format('d M Y H:i'),
                        'end_at' => $booking->end_at->format('d M Y H:i'),
                    ])
            );
        }

        // Get inventory bookings
        $inventoryBookings = InventoryBooking::query()
            ->when($fetchInfo->start, fn($q) => $q->where('start_at', '>=', $fetchInfo->start))
            ->when($fetchInfo->end, fn($q) => $q->where('end_at', '<=', $fetchInfo->end))
            ->with('items')
            ->get();

        foreach ($inventoryBookings as $booking) {
            $itemNames = $booking->items->pluck('name')->join(', ');
            
            $events->push(
                CalendarEvent::make($booking)
                    ->title('📦 ' . $booking->requester_name . ' (' . $booking->unit . ')')
                    ->start($booking->start_at)
                    ->end($booking->end_at)
                    ->backgroundColor($this->getStatusColor($booking->status, '#10B981'))
                    ->extendedProps([
                        'type' => 'inventory',
                        'id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'requester_name' => $booking->requester_name,
                        'requester_email' => $booking->requester_email,
                        'unit' => $booking->unit,
                        'status' => $booking->status->getLabel(),
                        'items' => $itemNames ?: '-',
                        'purpose' => $booking->purpose ?? '-',
                        'start_at' => $booking->start_at->format('d M Y H:i'),
                        'end_at' => $booking->end_at->format('d M Y H:i'),
                    ])
            );
        }

        // Get content requests
        $contentRequests = ContentRequest::query()
            ->whereNotNull('deadline')
            ->when($fetchInfo->start, fn($q) => $q->where('deadline', '>=', $fetchInfo->start))
            ->when($fetchInfo->end, fn($q) => $q->where('deadline', '<=', $fetchInfo->end))
            ->get();

        foreach ($contentRequests as $request) {
            $events->push(
                CalendarEvent::make($request)
                    ->title('📝 ' . $request->requester_name . ' - ' . $request->content_type)
                    ->start($request->deadline)
                    ->allDay(true)
                    ->backgroundColor($this->getRequestStatusColor($request->status))
                    ->extendedProps([
                        'type' => 'content_request',
                        'id' => $request->id,
                        'request_code' => $request->request_code,
                        'requester_name' => $request->requester_name,
                        'requester_email' => $request->requester_email,
                        'status' => $request->status->getLabel(),
                        'content_type' => $request->content_type,
                        'description' => $request->description ?? '-',
                        'deadline' => $request->deadline->format('d M Y'),
                    ])
            );
        }

        return $events;
    }

    public function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        $content = '';
        $type = 'unknown';

        if ($event instanceof RoomBooking) {
            $type = 'room';
            $content = $this->buildRoomBookingContent($event);
        } elseif ($event instanceof InventoryBooking) {
            $type = 'inventory';
            $content = $this->buildInventoryBookingContent($event);
        } elseif ($event instanceof ContentRequest) {
            $type = 'content_request';
            $content = $this->buildContentRequestContent($event);
        }
        
        Notification::make()
            ->title($this->getEventTypeLabel($type))
            ->body(new HtmlString($content))
            ->info()
            ->persistent()
            ->send();
    }

    protected function buildRoomBookingContent(RoomBooking $booking): string
    {
        $lines = [];
        $lines[] = "<div class='space-y-2 text-sm'>";
        $lines[] = "<p><strong>Kode:</strong> " . $booking->booking_code . "</p>";
        $lines[] = "<p><strong>Nama:</strong> " . $booking->requester_name . "</p>";
        $lines[] = "<p><strong>Email:</strong> " . $booking->requester_email . "</p>";
        $lines[] = "<p><strong>Unit:</strong> " . $booking->unit . "</p>";
        $lines[] = "<p><strong>Status:</strong> " . $booking->status->getLabel() . "</p>";
        $lines[] = "<p><strong>Ruangan:</strong> " . ($booking->room?->name ?? '-') . "</p>";
        $lines[] = "<p><strong>Peserta:</strong> " . $booking->attendees . " orang</p>";
        $lines[] = "<p><strong>Waktu:</strong> " . $booking->start_at->format('d M Y H:i') . " - " . $booking->end_at->format('d M Y H:i') . "</p>";
        
        if ($booking->purpose) {
            $lines[] = "<p><strong>Keperluan:</strong> " . $booking->purpose . "</p>";
        }
        $lines[] = "</div>";
        return implode('', $lines);
    }

    protected function buildInventoryBookingContent(InventoryBooking $booking): string
    {
        $lines = [];
        $lines[] = "<div class='space-y-2 text-sm'>";
        $lines[] = "<p><strong>Kode:</strong> " . $booking->booking_code . "</p>";
        $lines[] = "<p><strong>Nama:</strong> " . $booking->requester_name . "</p>";
        $lines[] = "<p><strong>Email:</strong> " . $booking->requester_email . "</p>";
        $lines[] = "<p><strong>Unit:</strong> " . $booking->unit . "</p>";
        $lines[] = "<p><strong>Status:</strong> " . $booking->status->getLabel() . "</p>";
        
        $itemNames = $booking->items->pluck('name')->join(', ');
        $lines[] = "<p><strong>Peralatan:</strong> " . ($itemNames ?: '-') . "</p>";
        $lines[] = "<p><strong>Waktu:</strong> " . $booking->start_at->format('d M Y H:i') . " - " . $booking->end_at->format('d M Y H:i') . "</p>";
        
        if ($booking->purpose) {
            $lines[] = "<p><strong>Keperluan:</strong> " . $booking->purpose . "</p>";
        }
        $lines[] = "</div>";
        return implode('', $lines);
    }

    protected function buildContentRequestContent(ContentRequest $request): string
    {
        $lines = [];
        $lines[] = "<div class='space-y-2 text-sm'>";
        $lines[] = "<p><strong>Kode:</strong> " . $request->request_code . "</p>";
        $lines[] = "<p><strong>Nama:</strong> " . $request->requester_name . "</p>";
        $lines[] = "<p><strong>Email:</strong> " . $request->requester_email . "</p>";
        $lines[] = "<p><strong>Status:</strong> " . $request->status->getLabel() . "</p>";
        $lines[] = "<p><strong>Jenis Konten:</strong> " . $request->content_type . "</p>";
        $lines[] = "<p><strong>Deadline:</strong> " . $request->deadline->format('d M Y') . "</p>";
        
        if ($request->description) {
            $lines[] = "<p><strong>Deskripsi:</strong> " . $request->description . "</p>";
        }
        $lines[] = "</div>";
        return implode('', $lines);
    }

    protected function getEventTypeLabel(string $type): string
    {
        return match($type) {
            'room' => '🏢 Detail Booking Ruangan',
            'inventory' => '📦 Detail Peminjaman Alat',
            'content_request' => '📝 Detail Request Konten',
            default => 'Detail Event',
        };
    }

    protected function getStatusColor($status, $defaultColor = '#3B82F6'): string
    {
        return match($status) {
            BookingStatus::PENDING => '#F59E0B', // Orange
            BookingStatus::APPROVED_STAFF => '#3B82F6', // Blue (Info)
            BookingStatus::APPROVED_HEAD => '#10B981', // Green (Success)
            BookingStatus::REJECTED => '#EF4444', // Red
            BookingStatus::CHECKED_OUT => '#8B5CF6', // Purple
            BookingStatus::RETURNED => '#10B981', // Green
            BookingStatus::COMPLETED => '#10B981', // Green
            BookingStatus::CANCELLED => '#6B7280', // Gray
            default => $defaultColor,
        };
    }

    protected function getRequestStatusColor($status): string
    {
        return match($status->value) {
            'pending' => '#F59E0B', // Orange
            'approved' => '#10B981', // Green
            'in_progress' => '#3B82F6', // Blue
            'completed' => '#8B5CF6', // Purple
            'rejected' => '#EF4444', // Red
            default => '#6B7280', // Gray
        };
    }
}
