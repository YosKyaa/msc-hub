<?php

use App\Http\Controllers\AnnouncementPublicController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ContentRequestController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

// Landing Page
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/msc-hub', [LandingController::class, 'index'])->name('landing.alias');

// Public Announcements
Route::get('/announcements', [AnnouncementPublicController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{slug}', [AnnouncementPublicController::class, 'show'])->name('announcements.show');

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
