<?php

use App\Http\Controllers\AnnouncementPublicController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginPortalController;
use App\Http\Controllers\BorrowingFormController;
use App\Http\Controllers\CertificatePublicController;
use App\Http\Controllers\ContentRequestController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\RequesterDashboardController;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

// Landing Page
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/msc-hub', [LandingController::class, 'index'])->name('landing.alias');

// Public Announcements
Route::get('/announcements', [AnnouncementPublicController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{slug}', [AnnouncementPublicController::class, 'show'])->name('announcements.show');

// Satu pintu masuk: pengunjung memilih mahasiswa/pengaju atau admin, lalu
// diteruskan ke cara masuk masing-masing.
Route::get('/masuk', [LoginPortalController::class, 'show'])->name('login.portal');

// Google OAuth for Public Requester (rate limited)
Route::prefix('auth/google')->name('auth.google.')->middleware('throttle:10,1')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');
});

// Google OAuth for Admin (rate limited)
Route::prefix('admin/auth/google')->name('admin.google.')->middleware('throttle:5,1')->group(function () {
    Route::get('/redirect', [\App\Http\Controllers\Auth\AdminGoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [\App\Http\Controllers\Auth\AdminGoogleAuthController::class, 'callback'])->name('callback');
});

// Halaman pertama peminjam setelah masuk: memilih layanan dan melihat
// pengajuannya sendiri.
Route::get('/dashboard', [RequesterDashboardController::class, 'index'])->name('requester.dashboard');

// Content Request (Public)
Route::prefix('request')->name('request.')->group(function () {
    Route::get('/content', [ContentRequestController::class, 'showForm'])->name('content');
    Route::post('/content', [ContentRequestController::class, 'submitForm'])
        ->middleware('throttle:10,1')
        ->name('content.submit');
    Route::get('/success', [ContentRequestController::class, 'showSuccess'])->name('success');

    Route::get('/status', [ContentRequestController::class, 'showStatus'])->name('status');
    Route::post('/status', [ContentRequestController::class, 'checkStatus'])
        ->middleware('throttle:30,1')
        ->name('status.check');
    Route::get('/status/{request_code}', [ContentRequestController::class, 'checkStatus'])->name('status.detail');
    Route::post('/status/{contentRequest}/comment', [ContentRequestController::class, 'addComment'])
        ->middleware('throttle:20,1')
        ->name('status.comment');
});

// Shortcut for Google OAuth
Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');

// Public Booking Portal
Route::prefix('borrow')->group(function () {
    Route::get('/inventory', [PublicBookingController::class, 'showInventoryForm'])->name('booking.inventory');
    Route::post('/inventory', [PublicBookingController::class, 'submitInventoryBooking'])
        ->middleware('throttle:10,1')
        ->name('booking.inventory.submit');
});

Route::prefix('book')->group(function () {
    Route::get('/room', [PublicBookingController::class, 'showRoomForm'])->name('booking.room');
    Route::post('/room', [PublicBookingController::class, 'submitRoomBooking'])
        ->middleware('throttle:10,1')
        ->name('booking.room.submit');
});

Route::get('/booking/success/{type}/{code}', [PublicBookingController::class, 'showSuccess'])->name('booking.success');
Route::get('/my-bookings', [PublicBookingController::class, 'myBookings'])->name('my.bookings');
Route::get('/my-bookings/{type}/{code}', [PublicBookingController::class, 'showBookingDetail'])->name('my.bookings.detail');
Route::post('/booking/logout', [PublicBookingController::class, 'logout'])->name('booking.logout');

// Absensi publik. Check-in dan check-out punya path, token, dan window
// terpisah; token yang tertukar antar aksi otomatis menghasilkan 404.
Route::get('/attend/{action}/{token}', [AttendanceController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('attendance.show');
Route::post('/attend/{action}/{token}', [AttendanceController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('attendance.store');

// Poster QR untuk diproyeksikan di venue (khusus pengelola sertifikat)
Route::get('/admin/attendance/{event}/qr/{action}', [AttendanceController::class, 'poster'])
    ->middleware(['auth', 'permission:certificates.view'])
    ->name('attendance.poster');

// Berkas contoh untuk mengimpor peserta sertifikat. Diunduh dari dalam
// modal impor, jadi hanya berguna bagi yang boleh menambah peserta.
Route::get('/admin/peserta/template-import', function () {
    return Excel::download(
        new App\Exports\ParticipantImportTemplate,
        App\Exports\ParticipantImportTemplate::FILENAME,
    );
})
    ->middleware(['auth', 'permission:certificates.create'])
    ->name('certificates.import-template');

// Formulir resmi peminjaman (FM/JGU/L.89). Bawaannya pratinjau di browser;
// tambahkan ?unduh=1 untuk mengunduh berkasnya.
Route::middleware(['auth'])->prefix('admin/form-peminjaman')->name('borrowing-form.')->group(function () {
    Route::get('/ruangan/{roomBooking}', [BorrowingFormController::class, 'room'])
        ->middleware('permission:room_bookings.view')
        ->name('room');
    Route::get('/inventaris/{inventoryBooking}', [BorrowingFormController::class, 'inventory'])
        ->middleware('permission:inventory_bookings.view')
        ->name('inventory');
});

// Public certificate authenticity and download
Route::get('/verify/certificate/{token}', [CertificatePublicController::class, 'verify'])->name('certificates.verify');
Route::get('/certificates/{token}/download', [CertificatePublicController::class, 'download'])->name('certificates.download');

// Log Viewer — hanya untuk admin yang sudah login ke panel.
Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index'])
    ->middleware(['web', 'auth', 'role:admin'])
    ->name('logs.viewer');
