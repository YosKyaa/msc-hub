# Catatan Perubahan Timezone Aplikasi

**Tanggal berlaku (cutoff): 3 September 2026.**

`config/app.php` sebelumnya memakai `UTC`. Mulai cutoff di atas nilainya menjadi
`Asia/Jakarta` (dapat ditimpa lewat `APP_TIMEZONE` di `.env`).

## Alasan

Seluruh logika window absensi kegiatan (Fase 1 sistem sertifikat otomatis)
membandingkan `now()` dengan `attendance_open_at` / `attendance_close_at` yang
diisi admin dalam waktu Jakarta. Menyimpan konfigurasi dalam UTC membuat window
meleset tujuh jam.

## Dampak pada data lama

Laravel menulis timestamp memakai timezone aplikasi. Artinya:

- Baris yang dibuat **sebelum** cutoff tersimpan sebagai waktu UTC.
- Baris yang dibuat **setelah** cutoff tersimpan sebagai waktu WIB.

Instalasi ini belum berjalan penuh di produksi, sehingga **tidak dilakukan
migrasi data**. Konsekuensinya: timestamp booking dan content request yang
dibuat sebelum cutoff akan tampil tujuh jam lebih awal dari waktu sebenarnya.
Bila di kemudian hari dibutuhkan koreksi, jalankan `UPDATE ... SET kolom =
DATE_ADD(kolom, INTERVAL 7 HOUR)` pada baris dengan `created_at < '2026-09-03'`
untuk tabel berikut, dan catat hasilnya di dokumen ini:

- `content_requests`, `content_request_comments`
- `inventory_bookings`, `room_bookings`, `inventory_logs`
- `announcements`, `notifications`, `activity_log`

## Kode yang bergantung pada timezone (sudah diaudit)

| Lokasi | Bentuk | Status setelah perubahan |
|---|---|---|
| `app/Http/Controllers/PublicBookingController.php` | `after:now`, `DATE_FORMAT(start_at, '%Y-%m')` vs `now()->format('Y-m')` | Konsisten — pembanding dan data baru sama-sama WIB |
| `app/Filament/Resources/RoomBookingResource*` | kuota bulanan `DATE_FORMAT` | Sama seperti di atas |
| `app/Filament/Resources/InventoryBookingResource/Pages/CreateInventoryBooking.php` | kuota bulanan | Sama seperti di atas |
| `app/Filament/Resources/ContentRequestResource.php` | `whereMonth('created_at', now()->month)` | Sama seperti di atas |
| `app/Filament/Resources/RoomBookingResource.php` | `whereDate('start_at', today())` | Sama seperti di atas |
| `app/Models/CertificateEvent.php` | window absensi | Dirancang untuk WIB |
| Email & Blade | label `WIB` pada tampilan tanggal | Kini benar-benar WIB |
