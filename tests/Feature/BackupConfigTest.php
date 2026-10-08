<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Tests\TestCase;

/**
 * Cadangan otomatis.
 *
 * Sebelumnya cadangan hanya mysqldump manual sebelum rilis. Tanpa basis data,
 * setiap tautan verifikasi sertifikat yang pernah dibagikan berhenti
 * berfungsi, dan berkas unggahan — desain sertifikat, font, tanda tangan —
 * tidak tersimpan di mana pun selain servernya sendiri.
 *
 * Menjalankan cadangan sungguhan butuh mysqldump, yang tidak tersedia di setiap
 * mesin test, jadi yang dijaga di sini adalah keputusannya: apa yang
 * dicadangkan, ke mana, kapan, dan siapa yang dikabari.
 */
class BackupConfigTest extends TestCase
{
    public function test_the_database_and_the_uploads_are_backed_up(): void
    {
        $this->assertSame(['mysql'], config('backup.backup.source.databases'));
        $this->assertSame([storage_path('app'.DIRECTORY_SEPARATOR.'public')], config('backup.backup.source.files.include'));
    }

    /**
     * Kode sudah ada di git. Mencadangkan seluruh folder proyek — bawaan
     * paketnya — hanya memperbesar arsip tanpa menambah yang bisa dipulihkan.
     */
    public function test_the_code_itself_is_not_backed_up(): void
    {
        $this->assertNotContains(base_path(), config('backup.backup.source.files.include'));
    }

    /**
     * Isi arsip harus berawalan public/..., bukan path absolut server ini,
     * supaya bisa dipulihkan ke server lain.
     */
    public function test_paths_inside_the_archive_are_relative(): void
    {
        $this->assertSame(storage_path('app'), config('backup.backup.source.files.relative_path'));
    }

    public function test_backups_go_to_a_private_disk(): void
    {
        $this->assertContains('backups', config('backup.backup.destination.disks'));
        $this->assertSame('local', config('filesystems.disks.backups.driver'));
        $this->assertSame(storage_path('app/backups'), config('filesystems.disks.backups.root'));
        $this->assertFalse((bool) config('filesystems.disks.backups.serve'), 'Cadangan bisa diunduh lewat web.');
    }

    public function test_database_dumps_are_compressed(): void
    {
        $this->assertSame(\Spatie\DbDumper\Compressors\GzipCompressor::class, config('backup.backup.database_dump_compressor'));
    }

    /**
     * Email harian yang selalu berbunyi "berhasil" cepat diabaikan, termasuk
     * pada hari ia gagal. Yang dikabarkan hanya kegagalan.
     */
    public function test_only_failures_are_emailed(): void
    {
        $kabar = config('backup.notifications.notifications');

        $this->assertSame(['mail'], $kabar[BackupHasFailedNotification::class]);
        $this->assertSame(['mail'], $kabar[UnhealthyBackupWasFoundNotification::class]);
        $this->assertSame([], $kabar[BackupWasSuccessfulNotification::class]);
    }

    public function test_failures_reach_a_real_inbox(): void
    {
        $this->assertStringNotContainsString('example.com', (string) config('backup.notifications.mail.to'));
        $this->assertNotSame('', (string) config('backup.notifications.mail.to'));
    }

    /**
     * Pemeriksa kesehatan memakai nama dan disk yang sama dengan cadangannya;
     * kalau berbeda, ia memeriksa cadangan yang tidak pernah ada lalu diam.
     */
    public function test_the_monitor_watches_the_same_backups(): void
    {
        $pantau = config('backup.monitor_backups.0');

        $this->assertSame(config('backup.backup.name'), $pantau['name']);
        $this->assertSame(config('backup.backup.destination.disks'), $pantau['disks']);
    }

    public function test_backups_run_every_day_and_are_checked(): void
    {
        $perintah = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command.' @ '.$event->expression)
            ->implode("\n");

        $this->assertMatchesRegularExpression('/backup:run.* @ 30 1 \* \* \*/', $perintah);
        $this->assertMatchesRegularExpression('/backup:clean.* @ 0 1 \* \* \*/', $perintah);
        $this->assertMatchesRegularExpression('/backup:monitor.* @ 0 3 \* \* \*/', $perintah);
    }
}
