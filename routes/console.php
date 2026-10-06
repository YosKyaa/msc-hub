<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Antrean dikerjakan lewat penjadwal
|--------------------------------------------------------------------------
|
| Email sertifikat dan pemberitahuan peminjaman dititipkan ke antrean, dan
| antrean tidak mengerjakan dirinya sendiri. Cara bakunya adalah pekerja yang
| hidup terus di bawah Supervisor; tetapi bila yang tersedia di server hanya
| cron, tidak ada satu pun pekerjaan yang pernah tersentuh — `schedule:run`
| berjalan tiap menit dan tidak menemukan apa pun untuk dikerjakan.
|
| Tugas di bawah menutup keadaan itu: tiap menit cron memanggil pekerja yang
| mengosongkan antrean lalu berhenti sendiri.
|
|   --stop-when-empty  berhenti begitu antreannya habis, bukan menunggu
|                      pekerjaan berikutnya sampai cron memanggil lagi
|   --max-time=50      berhenti sebelum cron menit berikutnya datang
|   withoutOverlapping antrean panjang tidak memanggil pekerja kedua di
|                      atas yang pertama
|
| Bila Supervisor tersedia, pakai itu: pekerjaannya diambil seketika alih-alih
| menunggu pergantian menit. Tugas ini aman dibiarkan hidup berdampingan —
| yang datang belakangan tidak menemukan apa-apa lalu berhenti.
*/
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('pekerja-antrean');

/*
| Pekerjaan yang gagal menumpuk tanpa batas dan membuat tabelnya sulit
| dibaca justru ketika sedang ditelusuri. Yang lebih tua dari sebulan dibuang.
*/
Schedule::command('queue:prune-failed --hours=720')
    ->weekly()
    ->name('bersihkan-antrean-gagal');
