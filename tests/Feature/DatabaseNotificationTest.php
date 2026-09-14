<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ContentType;
use App\Models\User;
use App\Notifications\NewBookingNotification;
use App\Notifications\NewContentRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: aksi pada notifikasi panel memakai Filament\Actions\Action (v4).
 * Class v3 Filament\Notifications\Actions\Action tidak lagi tersedia dan
 * membuat job notifikasi gagal berulang di antrean.
 */
class DatabaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_booking_notification_can_be_stored_in_the_database(): void
    {
        $booking = (object) [
            'booking_code' => 'BK-2026-0001',
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'unit' => 'Fakultas Teknologi',
            'purpose' => 'Dokumentasi acara',
            'start_at' => now(),
            'end_at' => now()->addHours(2),
            'status' => BookingStatus::PENDING,
        ];

        $message = (new NewBookingNotification($booking, 'ROOM'))->toDatabase(User::factory()->create());

        $this->assertIsArray($message);
        $this->assertStringContainsString('BK-2026-0001', $message['body']);
        $this->assertNotEmpty($message['actions']);
    }

    public function test_a_new_content_request_notification_can_be_stored_in_the_database(): void
    {
        $request = (object) [
            'request_code' => 'CR-2026-0001',
            'requester_name' => 'Siti Aminah',
            'requester_email' => 'siti@jgu.ac.id',
            'title' => 'Poster wisuda',
            'content_type' => ContentType::DESIGN_POSTER,
            'deadline' => now()->addWeek(),
            'status' => 'pending',
        ];

        $message = (new NewContentRequestNotification($request))->toDatabase(User::factory()->create());

        $this->assertIsArray($message);
        $this->assertStringContainsString('CR-2026-0001', $message['body']);
        $this->assertNotEmpty($message['actions']);
    }
}
