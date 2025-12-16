@extends('layouts.booking')

@section('title', 'Pinjam Alat Multimedia')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Pinjam Alat Multimedia</h1>
            <p class="text-gray-600 mt-1">Isi form di bawah untuk mengajukan peminjaman alat.</p>
        </div>

        {{-- Info Box --}}
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-indigo-800">
                    <p class="font-medium">Jam Operasional: 08:00 - 16:00</p>
                    <p class="mt-1">Peminjaman hanya dapat dilakukan dalam jam operasional. Proses approval memerlukan persetujuan Staff dan Head MSC.</p>
                </div>
            </div>
        </div>

        <form action="{{ route('booking.inventory.submit') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Requester Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="requester_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="requester_name" id="requester_name" 
                        value="{{ old('requester_name', $requester['name']) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" value="{{ $requester['email'] }}" 
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-600" 
                        readonly>
                </div>
            </div>

            <div>
                <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit / Fakultas</label>
                <input type="text" name="unit" id="unit" 
                    value="{{ old('unit') }}"
                    placeholder="Contoh: Fakultas Teknik"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Keperluan</label>
                <textarea name="purpose" id="purpose" rows="2"
                    placeholder="Jelaskan keperluan peminjaman..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('purpose') }}</textarea>
            </div>

            {{-- Date/Time --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="start_at" class="block text-sm font-medium text-gray-700 mb-1">
                        Waktu Mulai <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" name="start_at" id="start_at" 
                        value="{{ old('start_at') }}"
                        min="{{ now()->format('Y-m-d\TH:i') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required>
                </div>
                <div>
                    <label for="end_at" class="block text-sm font-medium text-gray-700 mb-1">
                        Waktu Selesai <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" name="end_at" id="end_at" 
                        value="{{ old('end_at') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required>
                </div>
            </div>

            {{-- Items Selection --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Pilih Alat <span class="text-red-500">*</span>
                </label>
                <div class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto">
                    @php
                        $groupedItems = $items->groupBy(fn($item) => $item->category?->getLabel() ?? 'Lainnya');
                    @endphp
                    
                    @foreach($groupedItems as $category => $categoryItems)
                        <div class="border-b border-gray-100 last:border-b-0">
                            <div class="bg-gray-50 px-4 py-2 font-medium text-sm text-gray-700">
                                {{ $category }}
                            </div>
                            @foreach($categoryItems as $item)
                                <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-b-0">
                                    <input type="checkbox" name="items[]" value="{{ $item->id }}"
                                        {{ in_array($item->id, old('items', [])) ? 'checked' : '' }}
                                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-900">{{ $item->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->code }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full 
                                        {{ $item->condition_status->value === 'good' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ $item->condition_status->getLabel() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-1">Centang alat yang ingin dipinjam</p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('my.bookings') }}" 
                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" 
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Ajukan Peminjaman
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
