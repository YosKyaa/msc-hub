@extends('layouts.booking')

@section('title', 'Ruangan Tidak Tersedia')

@section('content')
<div class="max-w-lg mx-auto text-center">
    <div class="bg-white rounded-xl shadow-sm border p-8">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Ruangan Tidak Tersedia</h1>
        <p class="text-gray-600 mb-6">Maaf, saat ini tidak ada ruangan yang tersedia untuk dibooking.</p>
        <a href="{{ route('my.bookings') }}" class="inline-flex items-center gap-2 text-indigo-600 hover:underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Kembali ke Booking Saya
        </a>
    </div>
</div>
@endsection
