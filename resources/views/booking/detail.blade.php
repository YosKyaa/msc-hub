@extends('layouts.booking')

@section('title', 'Detail Booking - ' . $booking->booking_code)

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Back Link --}}
    <a href="{{ route('my.bookings') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Kembali ke Booking Saya
    </a>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-indigo-200 text-sm mb-1">Kode Booking</p>
                    <h1 class="text-2xl font-mono font-bold">{{ $booking->booking_code }}</h1>
                </div>
                @php
                    $statusColors = [
                        'pending' => 'bg-yellow-400 text-yellow-900',
                        'approved_staff' => 'bg-blue-400 text-blue-900',
                        'approved_head' => 'bg-green-400 text-green-900',
                        'rejected' => 'bg-red-400 text-red-900',
                        'checked_out' => 'bg-purple-400 text-purple-900',
                        'returned' => 'bg-gray-400 text-gray-900',
                        'completed' => 'bg-gray-400 text-gray-900',
                        'cancelled' => 'bg-gray-400 text-gray-900',
                    ];
                @endphp
                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$booking->status->value] ?? 'bg-gray-400 text-gray-900' }}">
                    {{ $booking->status->getLabel() }}
                </span>
            </div>
        </div>

        <div class="p-6 space-y-6">
            {{-- Type & Time --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Jenis Booking</p>
                    <p class="font-medium">{{ $type === 'inventory' ? 'Peminjaman Alat' : 'Booking Ruangan' }}</p>
                </div>
                @if($type === 'room' && $booking->room)
                    <div>
                        <p class="text-sm text-gray-500">Ruangan</p>
                        <p class="font-medium">{{ $booking->room->name }}</p>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Waktu Mulai</p>
                    <p class="font-medium">{{ $booking->start_at->format('d M Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Waktu Selesai</p>
                    <p class="font-medium">{{ $booking->end_at->format('d M Y H:i') }}</p>
                </div>
            </div>

            {{-- Requester Info --}}
            <div class="border-t pt-4">
                <h3 class="font-semibold text-gray-900 mb-3">Informasi Pemohon</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Nama</p>
                        <p class="font-medium">{{ $booking->requester_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium">{{ $booking->requester_email }}</p>
                    </div>
                    @if($booking->unit)
                        <div>
                            <p class="text-sm text-gray-500">Unit</p>
                            <p class="font-medium">{{ $booking->unit }}</p>
                        </div>
                    @endif
                    @if($type === 'room' && $booking->attendees)
                        <div>
                            <p class="text-sm text-gray-500">Jumlah Peserta</p>
                            <p class="font-medium">{{ $booking->attendees }} orang</p>
                        </div>
                    @endif
                </div>
                @if($booking->purpose)
                    <div class="mt-3">
                        <p class="text-sm text-gray-500">Keperluan</p>
                        <p class="font-medium">{{ $booking->purpose }}</p>
                    </div>
                @endif
            </div>

            {{-- Items (Inventory only) --}}
            @if($type === 'inventory' && $booking->items->count() > 0)
                <div class="border-t pt-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Daftar Alat ({{ $booking->items->count() }} item)</h3>
                    <div class="space-y-2">
                        @foreach($booking->items as $item)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium">{{ $item->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $item->code }}</p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full 
                                    {{ $item->condition_status->value === 'good' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $item->condition_status->getLabel() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Approval Timeline --}}
            <div class="border-t pt-4">
                <h3 class="font-semibold text-gray-900 mb-3">Status Approval</h3>
                <div class="space-y-3">
                    {{-- Submitted --}}
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Diajukan</p>
                            <p class="text-sm text-gray-500">{{ $booking->created_at->format('d M Y H:i') }}</p>
                        </div>
                    </div>

                    {{-- Staff Approval --}}
                    <div class="flex items-start gap-3">
                        @if($booking->staff_approved_at)
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Disetujui Staff MSC</p>
                                <p class="text-sm text-gray-500">{{ $booking->staff_approved_at->format('d M Y H:i') }}</p>
                            </div>
                        @elseif($booking->status->value === 'rejected')
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-red-600">Ditolak</p>
                                @if($booking->reject_reason)
                                    <p class="text-sm text-gray-500">Alasan: {{ $booking->reject_reason }}</p>
                                @endif
                            </div>
                        @elseif($booking->status->value === 'cancelled')
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-500">Dibatalkan</p>
                            </div>
                        @else
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-yellow-600">Menunggu Approval Staff</p>
                            </div>
                        @endif
                    </div>

                    {{-- Head Approval --}}
                    @if($booking->staff_approved_at && !in_array($booking->status->value, ['rejected', 'cancelled']))
                        <div class="flex items-start gap-3">
                            @if($booking->head_approved_at)
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Disetujui Head MSC</p>
                                    <p class="text-sm text-gray-500">{{ $booking->head_approved_at->format('d M Y H:i') }}</p>
                                </div>
                            @else
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-yellow-600">Menunggu Approval Head</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Check-out/Return (Inventory) --}}
                    @if($type === 'inventory' && $booking->head_approved_at)
                        @if($booking->checked_out_at)
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Sudah Diambil</p>
                                    <p class="text-sm text-gray-500">{{ $booking->checked_out_at->format('d M Y H:i') }}</p>
                                </div>
                            </div>
                        @endif

                        @if($booking->returned_at)
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Sudah Dikembalikan</p>
                                    <p class="text-sm text-gray-500">{{ $booking->returned_at->format('d M Y H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Completed (Room) --}}
                    @if($type === 'room' && $booking->completed_at)
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Selesai</p>
                                <p class="text-sm text-gray-500">{{ $booking->completed_at->format('d M Y H:i') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Notes --}}
            @if($booking->checkout_note || $booking->return_note)
                <div class="border-t pt-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Catatan</h3>
                    @if($booking->checkout_note)
                        <div class="bg-gray-50 rounded-lg p-3 mb-2">
                            <p class="text-sm text-gray-500">Catatan Check-out:</p>
                            <p>{{ $booking->checkout_note }}</p>
                        </div>
                    @endif
                    @if($booking->return_note)
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-sm text-gray-500">Catatan Pengembalian:</p>
                            <p>{{ $booking->return_note }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
