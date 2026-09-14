<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\ContentRequestResource;
use App\Filament\Resources\InventoryBookingResource;
use App\Filament\Resources\RoomBookingResource;
use App\Models\CertificateEventParticipant;
use App\Models\ContentRequest;
use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use App\Support\CertificateStage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Apa yang menunggu dikerjakan hari ini.
 *
 * Dasbor sebelumnya hanya menampilkan berapa banyak arsip yang tersimpan —
 * angka yang menyenangkan tetapi tidak menyuruh siapa pun berbuat apa. Yang
 * dibutuhkan staf saat membuka panel adalah daftar pekerjaan yang tertahan
 * pada dirinya, beserta jalan pintas ke sana.
 */
class PendingActionsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    protected ?string $heading = 'Perlu Tindakan Anda';

    protected ?string $description = 'Pengajuan yang menunggu diproses. Klik untuk membukanya.';

    /** Status peminjaman yang masih menunggu keputusan. */
    private const MENUNGGU = [BookingStatus::PENDING, BookingStatus::APPROVED_STAFF];

    protected function getStats(): array
    {
        return [
            $this->stat(
                'Booking Ruangan',
                RoomBooking::whereIn('status', self::MENUNGGU)->count(),
                'menunggu persetujuan',
                'heroicon-m-building-office-2',
                RoomBookingResource::getUrl(),
            ),

            $this->stat(
                'Peminjaman Alat',
                InventoryBooking::whereIn('status', self::MENUNGGU)->count(),
                'menunggu persetujuan',
                'heroicon-m-camera',
                InventoryBookingResource::getUrl(),
            ),

            $this->stat(
                'Permintaan Konten',
                ContentRequest::whereIn('status', ['incoming', 'assigned', 'in_progress', 'waiting_head_approval'])->count(),
                'sedang berjalan',
                'heroicon-m-pencil-square',
                ContentRequestResource::getUrl(),
            ),

            $this->stat(
                'Sertifikat Siap Terbit',
                $this->certificates(CertificateStage::READY),
                'peserta berhak, belum diterbitkan',
                'heroicon-m-academic-cap',
                CertificateEventResource::getUrl(),
            ),

            $this->stat(
                'Sertifikat Belum Dikirim',
                $this->certificates(CertificateStage::ISSUED),
                'sudah terbit, email belum dikirim',
                'heroicon-m-paper-airplane',
                CertificateEventResource::getUrl(),
            ),
        ];
    }

    /**
     * Angka nol dibuat tenang, sisanya menonjol — supaya mata langsung
     * tertuju pada yang benar-benar menunggu.
     */
    private function stat(string $label, int $jumlah, string $keterangan, string $ikon, string $url): Stat
    {
        return Stat::make($label, $jumlah)
            ->description($jumlah === 0 ? 'Tidak ada yang menunggu' : $keterangan)
            ->descriptionIcon($ikon)
            ->color($jumlah === 0 ? 'gray' : 'warning')
            ->url($url);
    }

    /**
     * Aturan tahapnya dipinjam dari CertificateStage, sehingga angka di
     * dasbor tidak bisa berbeda dari yang tertulis di halaman kegiatan.
     */
    private function certificates(CertificateStage $stage): int
    {
        return $stage->constrain(
            CertificateEventParticipant::query()
                ->whereHas('event', fn (Builder $event) => $event->where('status', 'published')),
        )->count();
    }

    /**
     * Mengikuti izin yang sama dengan resource-nya, bukan sekadar "siapa pun
     * yang berhasil masuk panel".
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('room_bookings.view') ?? false;
    }
}
