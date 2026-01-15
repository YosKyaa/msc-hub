@extends('layouts.booking')

@section('title', 'Booking Ruangan')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Booking Ruangan</h1>
            <p class="text-gray-600 mt-1">Isi form di bawah untuk mengajukan booking ruangan.</p>
        </div>

        {{-- Room Info --}}
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <div class="text-sm text-indigo-800">
                    <p class="font-medium">{{ $room->name }}</p>
                    @if($room->location)
                        <p class="mt-1">Lokasi: {{ $room->location }}</p>
                    @endif
                    @if($room->capacity)
                        <p>Kapasitas: {{ $room->capacity }} orang</p>
                    @endif
                    @if($room->facilities)
                        <p>Fasilitas: {{ $room->facilities }}</p>
                    @endif
                    <p class="mt-2 font-medium">Jam Operasional: {{ substr($room->open_time, 0, 5) }} - {{ substr($room->close_time, 0, 5) }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('booking.room.submit') }}" method="POST" class="space-y-6">
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    <label for="attendees" class="block text-sm font-medium text-gray-700 mb-1">
                        Jumlah Peserta <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="attendees" id="attendees" 
                        value="{{ old('attendees') }}"
                        min="1"
                        max="7"
                        placeholder="Maksimal 7 orang"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required>
                    <p class="text-xs text-gray-500 mt-1">Maksimal 7 orang</p>
                </div>
            </div>

            <div>
                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Keperluan</label>
                <textarea name="purpose" id="purpose" rows="2"
                    placeholder="Jelaskan keperluan booking ruangan..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('purpose') }}</textarea>
            </div>

            {{-- Inventory Items --}}
            @if($inventoryItems->count() > 0)
            <div x-data="{ showInventory: {{ old('inventory_items') ? 'true' : 'false' }} }">
                <div class="flex items-center gap-2 mb-3">
                    <input type="checkbox" id="need_inventory" x-model="showInventory"
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="need_inventory" class="text-sm font-medium text-gray-700">
                        Saya juga ingin meminjam peralatan multimedia
                    </label>
                </div>
                
                <div x-show="showInventory" x-cloak class="bg-gray-50 border rounded-lg p-4 space-y-3">
                    <p class="text-sm text-gray-600 mb-3">Pilih peralatan yang ingin dipinjam:</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-64 overflow-y-auto">
                        @foreach($inventoryItems as $item)
                        <label class="flex items-start gap-3 p-3 bg-white border rounded-lg cursor-pointer hover:border-indigo-300 transition">
                            <input type="checkbox" name="inventory_items[]" value="{{ $item->id }}"
                                {{ in_array($item->id, old('inventory_items', [])) ? 'checked' : '' }}
                                class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">{{ $item->code }} &bull; {{ $item->category?->getLabel() ?? 'Lainnya' }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

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

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('my.bookings') }}" 
                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" 
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Ajukan Booking
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
    // Initialize Flatpickr for datetime inputs
    const startPicker = flatpickr("#start_at", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        minDate: "today",
        minuteIncrement: 30,
        disable: [
            function(date) {
                // Disable weekends (0 = Sunday, 6 = Saturday)
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ],
        locale: {
            firstDayOfWeek: 1 // Start week on Monday
        },
        onChange: function(selectedDates, dateStr, instance) {
            // Set minDate for end_at picker
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
                // Disable weekends
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
    
    // Form submission with SweetAlert confirmation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Booking Ruangan',
            html: `
                <div class="text-left space-y-2">
                    <p class="text-sm text-gray-600">Pastikan semua data sudah benar sebelum submit:</p>
                    <ul class="text-sm text-gray-700 list-disc list-inside space-y-1 mt-3">
                        <li>Unit/Fakultas sudah dipilih</li>
                        <li>Jumlah peserta maksimal 7 orang</li>
                        <li>Waktu booking pada hari Senin - Jumat</li>
                        <li>Maksimal 2x booking per bulan</li>
                    </ul>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4F46E5',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Ya, Submit Booking',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit form
                form.submit();
            }
        });
    });
});
</script>
@endpush
@endsection
