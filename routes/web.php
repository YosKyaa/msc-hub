<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ContentRequestController;
use App\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth for Public Requester
Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');
});

// Content Request (Public)
Route::prefix('request')->name('request.')->group(function () {
    Route::get('/content', [ContentRequestController::class, 'showForm'])->name('content');
    Route::post('/content', [ContentRequestController::class, 'submitForm'])->name('content.submit');
    Route::get('/success', [ContentRequestController::class, 'showSuccess'])->name('success');
    
    Route::get('/status', [ContentRequestController::class, 'showStatus'])->name('status');
    Route::post('/status', [ContentRequestController::class, 'checkStatus'])->name('status.check');
    Route::get('/status/{request_code}', [ContentRequestController::class, 'checkStatus'])->name('status.detail');
    Route::post('/status/{contentRequest}/comment', [ContentRequestController::class, 'addComment'])->name('status.comment');
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
