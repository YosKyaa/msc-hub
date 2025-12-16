@extends('layouts.booking')

@section('title', 'Booking Saya')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="mr-6"> <!-- Added margin-right to create spacing -->
                <h1 class="text-2xl font-bold text-gray-900">Booking Saya</h1>
                <p class="text-gray-600">Daftar semua booking yang Anda ajukan.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('booking.inventory') }}"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                    + Pinjam Alat
                </a>
                <a href="{{ route('booking.room') }}"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                    + Booking Ruangan
                </a>
            </div>
        </div>

        {{-- Tabs --}}
        <div x-data="{ tab: 'inventory' }">
            <div class="border-b border-gray-200">
                <nav class="flex gap-8">
                    <button @click="tab = 'inventory'"
                        :class="tab === 'inventory' ? 'border-indigo-600 text-indigo-600' :
                            'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-3 border-b-2 font-medium text-sm transition">
                        Peminjaman Alat
                        @if ($inventoryBookings->count() > 0)
                            <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                                {{ $inventoryBookings->count() }}
                            </span>
                        @endif
                    </button>
                    <button @click="tab = 'room'"
                        :class="tab === 'room' ? 'border-indigo-600 text-indigo-600' :
                            'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-3 border-b-2 font-medium text-sm transition">
                        Booking Ruangan
                        @if ($roomBookings->count() > 0)
                            <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                                {{ $roomBookings->count() }}
                            </span>
                        @endif
                    </button>
                </nav>
            </div>

            {{-- Inventory Bookings --}}
            <div x-show="tab === 'inventory'" class="mt-6">
                @if ($inventoryBookings->isEmpty())
                    <div class="bg-white rounded-xl border p-8 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                            </path>
                        </svg>
                        <p class="text-gray-500 mb-4">Belum ada peminjaman alat.</p>
                        <a href="{{ route('booking.inventory') }}" class="text-indigo-600 hover:underline font-medium">
                            Ajukan Peminjaman
                        </a>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($inventoryBookings as $booking)
                            <a href="{{ route('my.bookings.detail', ['type' => 'inventory', 'code' => $booking->booking_code]) }}"
                                class="block bg-white rounded-xl border p-4 hover:shadow-md transition">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="font-mono font-bold text-indigo-600">{{ $booking->booking_code }}</span>
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                                    'approved_staff' => 'bg-blue-100 text-blue-700',
                                                    'approved_head' => 'bg-green-100 text-green-700',
                                                    'rejected' => 'bg-red-100 text-red-700',
                                                    'checked_out' => 'bg-purple-100 text-purple-700',
                                                    'returned' => 'bg-gray-100 text-gray-700',
                                                    'cancelled' => 'bg-gray-100 text-gray-500',
                                                ];
                                            @endphp
                                            <span
                                                class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ $booking->status->getLabel() }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ $booking->items->count() }} item &middot;
                                            {{ $booking->start_at->format('d M Y H:i') }} -
                                            {{ $booking->end_at->format('H:i') }}
                                        </p>
                                    </div>
                                    <span class="text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Room Bookings --}}
            <div x-show="tab === 'room'" x-cloak class="mt-6">
                @if ($roomBookings->isEmpty())
                    <div class="bg-white rounded-xl border p-8 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <p class="text-gray-500 mb-4">Belum ada booking ruangan.</p>
                        <a href="{{ route('booking.room') }}" class="text-indigo-600 hover:underline font-medium">
                            Booking Ruangan
                        </a>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($roomBookings as $booking)
                            <a href="{{ route('my.bookings.detail', ['type' => 'room', 'code' => $booking->booking_code]) }}"
                                class="block bg-white rounded-xl border p-4 hover:shadow-md transition">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="font-mono font-bold text-indigo-600">{{ $booking->booking_code }}</span>
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                                    'approved_staff' => 'bg-blue-100 text-blue-700',
                                                    'approved_head' => 'bg-green-100 text-green-700',
                                                    'rejected' => 'bg-red-100 text-red-700',
                                                    'completed' => 'bg-gray-100 text-gray-700',
                                                    'cancelled' => 'bg-gray-100 text-gray-500',
                                                ];
                                            @endphp
                                            <span
                                                class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ $booking->status->getLabel() }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ $booking->room->name }} &middot;
                                            {{ $booking->start_at->format('d M Y H:i') }} -
                                            {{ $booking->end_at->format('H:i') }}
                                        </p>
                                    </div>
                                    <span class="text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endpush
