<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Catat email yang akhirnya tidak terkirim.
 *
 * Notifikasi yang antre dan gagal setelah seluruh percobaannya habis hanya
 * menjadi satu baris di tabel `failed_jobs`. Tidak ada yang membacanya, dan
 * peminjam yang menunggu kabar tidak pernah menerima apa-apa — sementara di
 * panel pengajuannya tampak sudah diproses.
 *
 * Laravel memanggil `failed()` pada notifikasinya bila metode itu ada (lihat
 * `SendQueuedNotifications::failed()`), jadi di situlah kegagalannya dicatat.
 * Lognya bisa dibaca admin lewat halaman /logs tanpa perlu membuka server.
 *
 * Satu hal yang tidak bisa dicatat dari sini: alamat tujuannya. Laravel hanya
 * meneruskan galatnya, bukan penerimanya. Karena itu konteksnya menyebut kode
 * pengajuan, yang cukup untuk menemukan catatannya beserta alamat email di
 * dalamnya.
 */
trait ReportsDeliveryFailure
{
    /**
     * Penanda yang cukup untuk menemukan pengajuannya kembali.
     *
     * @return array<string, mixed>
     */
    abstract protected function failureContext(): array;

    public function failed(Throwable $exception): void
    {
        Log::error('Email tidak terkirim setelah semua percobaan habis.', [
            'notification' => class_basename(static::class),
            ...$this->failureContext(),
            'error' => Str::limit($exception->getMessage(), 250),
            'exception' => $exception,
        ]);
    }
}
