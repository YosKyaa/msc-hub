@extends('layouts.booking')

@section('title', 'Booking Berhasil')

@section('content')
<div class="max-w-lg mx-auto text-center">
    <div class="bg-white rounded-xl shadow-sm border p-8">
        {{-- Success Icon --}}
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Booking Berhasil Diajukan!</h1>
        <p class="text-gray-600 mb-6">Permintaan booking Anda telah diterima dan akan diproses oleh tim MSC.</p>

        {{-- Booking Code --}}
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <p class="text-sm text-gray-500 mb-1">Kode Booking</p>
            <p class="text-2xl font-mono font-bold text-indigo-600">{{ $booking->booking_code }}</p>
        </div>

        {{-- Details --}}
        <div class="text-left space-y-3 mb-6 border-t pt-4">
            @if($type === 'inventory')
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Jenis</span>
                    <span class="font-medium">Peminjaman Alat</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Jumlah Item</span>
                    <span class="font-medium">{{ $booking->items->count() }} item</span>
                </div>
            @else
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Jenis</span>
                    <span class="font-medium">Booking Ruangan</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Ruangan</span>
                    <span class="font-medium">{{ $booking->room->name }}</span>
                </div>
            @endif
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Waktu Mulai</span>
                <span class="font-medium">{{ $booking->start_at->format('d M Y H:i') }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Waktu Selesai</span>
                <span class="font-medium">{{ $booking->end_at->format('d M Y H:i') }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Status</span>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">
                    Menunggu Approval
                </span>
            </div>
        </div>

        {{-- Info --}}
        <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-6 text-left">
            <p class="text-sm text-blue-800">
                <strong>Proses Selanjutnya:</strong><br>
                1. Staff MSC akan mereview permintaan Anda<br>
                2. Setelah disetujui staff, Head MSC akan memberikan approval final<br>
                3. Anda dapat mengecek status booking di halaman "Booking Saya"
            </p>
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('my.bookings.detail', ['type' => $type, 'code' => $booking->booking_code]) }}" 
                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                Lihat Detail
            </a>
            <a href="{{ route('my.bookings') }}" 
                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Semua Booking Saya
            </a>
        </div>
    </div>
</div>
@endsection
