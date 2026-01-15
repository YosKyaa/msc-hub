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
                <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">
                    Unit / Fakultas <span class="text-red-500">*</span>
                </label>
                <select name="unit" id="unit" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Pilih Unit/Fakultas</option>
                    <option value="HIMATIF" {{ old('unit') == 'HIMATIF' ? 'selected' : '' }}>HIMATIF</option>
                    <option value="HME" {{ old('unit') == 'HME' ? 'selected' : '' }}>HME</option>
                    <option value="HMS" {{ old('unit') == 'HMS' ? 'selected' : '' }}>HMS</option>
                    <option value="HMTI" {{ old('unit') == 'HMTI' ? 'selected' : '' }}>HMTI</option>
                    <option value="HIMAMEN" {{ old('unit') == 'HIMAMEN' ? 'selected' : '' }}>HIMAMEN</option>
                    <option value="HIMABID" {{ old('unit') == 'HIMABID' ? 'selected' : '' }}>HIMABID</option>
                    <option value="HIMFA" {{ old('unit') == 'HIMFA' ? 'selected' : '' }}>HIMFA</option>
                    <option value="Mahasiswa" {{ old('unit') == 'Mahasiswa' ? 'selected' : '' }}>Mahasiswa</option>
                    <option value="Dosen" {{ old('unit') == 'Dosen' ? 'selected' : '' }}>Dosen</option>
                    <option value="Staff" {{ old('unit') == 'Staff' ? 'selected' : '' }}>Staff</option>
                </select>
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
                    <input type="text" name="start_at" id="start_at" 
                        value="{{ old('start_at') }}"
                        placeholder="Pilih tanggal dan waktu"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required
                        readonly>
                </div>
                <div>
                    <label for="end_at" class="block text-sm font-medium text-gray-700 mb-1">
                        Waktu Selesai <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="end_at" id="end_at" 
                        value="{{ old('end_at') }}"
                        placeholder="Pilih tanggal dan waktu"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required
                        readonly>
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

@push('scripts')
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Flatpickr
    const startPicker = flatpickr("#start_at", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        minDate: "today",
        minuteIncrement: 30,
        disable: [
            function(date) {
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ],
        locale: {
            firstDayOfWeek: 1
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                endPicker.set('minDate', selectedDates[0]);
            }
        }
    });

    const endPicker = flatpickr("#end_at", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        minDate: "today",
        minuteIncrement: 30,
        disable: [
            function(date) {
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ],
        locale: {
            firstDayOfWeek: 1
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    // Form submission with SweetAlert
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Peminjaman Alat',
            html: `
                <div class="text-left space-y-2">
                    <p class="text-sm text-gray-600">Pastikan semua data sudah benar:</p>
                    <ul class="text-sm text-gray-700 list-disc list-inside space-y-1 mt-3">
                        <li>Unit/Fakultas sudah dipilih</li>
                        <li>Alat yang dipinjam sudah dipilih</li>
                        <li>Waktu peminjaman pada hari Senin - Jumat</li>
                        <li>Maksimal 2x peminjaman per bulan</li>
                    </ul>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4F46E5',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Ya, Submit',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                form.submit();
            }
        });
    });
});
</script>
@endpush
@endsection
