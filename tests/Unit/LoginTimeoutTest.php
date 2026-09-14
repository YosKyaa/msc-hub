<?php

namespace Tests\Unit;

use App\Support\LoginTimeout;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Aturan kedaluwarsa login, terlepas dari cara sesinya disimpan.
 */
class LoginTimeoutTest extends TestCase
{
    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-09-06 '.$time);
    }

    public function test_a_recent_and_active_session_stays_valid(): void
    {
        $timeout = new LoginTimeout(idleMinutes: 120, absoluteMinutes: 480);

        $this->assertNull($timeout->expiryReason(
            $this->at('08:00'),
            $this->at('09:30'),
            $this->at('10:00'),
        ));
    }

    public function test_a_session_without_activity_expires(): void
    {
        $timeout = new LoginTimeout(idleMinutes: 120, absoluteMinutes: 480);

        $this->assertSame(LoginTimeout::IDLE, $timeout->expiryReason(
            $this->at('08:00'),
            $this->at('08:30'),
            $this->at('10:31'),
        ));
    }

    /**
     * Inti perlindungannya: pengguna yang terus aktif pun tetap dikeluarkan,
     * sehingga tidak ada login yang hidup selamanya.
     */
    public function test_an_always_active_session_still_hits_the_hard_limit(): void
    {
        $timeout = new LoginTimeout(idleMinutes: 120, absoluteMinutes: 480);

        $this->assertSame(LoginTimeout::ABSOLUTE, $timeout->expiryReason(
            $this->at('08:00'),
            $this->at('15:59'),
            $this->at('16:00'),
        ));
    }

    public function test_the_hard_limit_is_reported_before_inactivity(): void
    {
        $timeout = new LoginTimeout(idleMinutes: 120, absoluteMinutes: 480);

        // Keduanya terlampaui; alasan yang lebih menentukan yang dilaporkan.
        $this->assertSame(LoginTimeout::ABSOLUTE, $timeout->expiryReason(
            $this->at('00:00'),
            $this->at('01:00'),
            $this->at('12:00'),
        ));
    }

    public function test_a_zero_value_disables_that_limit(): void
    {
        $timeout = new LoginTimeout(idleMinutes: 0, absoluteMinutes: 0);

        $this->assertNull($timeout->expiryReason(
            $this->at('00:00'),
            $this->at('00:00'),
            $this->at('23:59'),
        ));
    }

    public function test_each_reason_has_its_own_indonesian_message(): void
    {
        $timeout = new LoginTimeout(idleMinutes: 120, absoluteMinutes: 480);

        $this->assertStringContainsString('tidak ada aktivitas', $timeout->message(LoginTimeout::IDLE));
        $this->assertStringContainsString('batas waktu', $timeout->message(LoginTimeout::ABSOLUTE));
    }
}
