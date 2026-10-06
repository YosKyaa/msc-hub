@extends('layouts.booking')

@section('title', 'Pinjam Alat Multimedia')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-ink">Pinjam Alat Multimedia</h1>
            <p class="text-ink/65 mt-1">Isi form di bawah untuk mengajukan peminjaman alat.</p>
        </div>

        {{-- Info Box --}}
        <div class="bg-sun-soft border border-sun-deep/30 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-sun-ink mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-sun-ink">
                    <p class="font-medium">Jam Operasional: {{ \App\Support\JamOperasional::rentang() }}</p>
                    <p class="mt-1">{{ \App\Support\JamOperasional::catatan() }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('booking.inventory.submit') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Requester Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="requester_name" class="block text-sm font-medium text-ink/75 mb-1">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="requester_name" id="requester_name" 
                        value="{{ old('requester_name', $requester['name']) }}"
                        class="isian"
                        required>
                </div>
                <div>
                    <label class="label-isian">Email</label>
                    <input type="email" value="{{ $requester['email'] }}" 
                        class="w-full px-4 py-2 border border-ink/10 rounded-lg bg-paper-2 text-ink/65" 
                        readonly>
                </div>
            </div>

            <div>
                <label for="unit" class="block text-sm font-medium text-ink/75 mb-1">
                    Unit / Fakultas <span class="text-red-500">*</span>
                </label>
                <select name="unit" id="unit" required
                    class="isian">
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
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="requester_phone" class="block text-sm font-medium text-ink/75 mb-1">
                            No. HP Peminjam
                        </label>
                        <input type="tel" name="requester_phone" id="requester_phone" maxlength="30"
                            value="{{ old('requester_phone') }}"
                            placeholder="08xxxxxxxxxx"
                            class="isian">
                        <p class="mt-1 text-xs text-ink/60">Dicantumkan pada formulir resmi peminjaman.</p>
                        @error('requester_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="supervisor_name" class="block text-sm font-medium text-ink/75 mb-1">
                            Penanggung Jawab (Dosen)
                        </label>
                        <input type="text" name="supervisor_name" id="supervisor_name" maxlength="255"
                            value="{{ old('supervisor_name') }}"
                            placeholder="Nama dosen penanggung jawab"
                            class="isian">
                        <p class="mt-1 text-xs text-ink/60">Dicantumkan pada formulir resmi peminjaman.</p>
                        @error('supervisor_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>


            <div>
                <label for="purpose" class="block text-sm font-medium text-ink/75 mb-1">Keperluan</label>
                <textarea name="purpose" id="purpose" rows="2"
                    placeholder="Jelaskan keperluan peminjaman..."
                    class="isian">{{ old('purpose') }}</textarea>
            </div>

            {{-- Date/Time --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="start_at" class="block text-sm font-medium text-ink/75 mb-1">
                        Waktu Mulai <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="start_at" id="start_at" 
                        value="{{ old('start_at') }}"
                        placeholder="Pilih tanggal dan waktu"
                        class="isian"
                        required
                        readonly>
                </div>
                <div>
                    <label for="end_at" class="block text-sm font-medium text-ink/75 mb-1">
                        Waktu Selesai <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="end_at" id="end_at" 
                        value="{{ old('end_at') }}"
                        placeholder="Pilih tanggal dan waktu"
                        class="isian"
                        required
                        readonly>
                </div>
            </div>

            {{-- Items Selection --}}
            <div>
                <label class="label-isian">
                    Pilih Alat <span class="text-red-500">*</span>
                </label>
                <div class="border border-ink/10 rounded-lg max-h-64 overflow-y-auto">
                    @php
                        $groupedItems = $items->groupBy(fn($item) => $item->category?->getLabel() ?? 'Lainnya');
                    @endphp
                    
                    @foreach($groupedItems as $category => $categoryItems)
                        <div class="border-b border-ink/[.07] last:border-b-0">
                            <div class="bg-paper-2 px-4 py-2 font-medium text-sm text-ink/75">
                                {{ $category }}
                            </div>
                            @foreach($categoryItems as $item)
                                <label class="flex items-center gap-3 px-4 py-3 hover:bg-paper-2 cursor-pointer border-b border-ink/[.06] last:border-b-0">
                                    <input type="checkbox" name="items[]" value="{{ $item->id }}"
                                        {{ in_array($item->id, old('items', [])) ? 'checked' : '' }}
                                        class="kotak-centang">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-ink">{{ $item->name }}</div>
                                        <div class="text-xs text-ink/60">{{ $item->code }}</div>
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
                <p class="text-xs text-ink/60 mt-1">Centang alat yang ingin dipinjam</p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('my.bookings') }}" 
                    class="px-6 py-2 border border-ink/15 rounded-lg text-ink/75 hover:bg-paper-2">
                    Batal
                </a>
                <x-molecules.confirm-submit
                    label="Ajukan Peminjaman"
                    heading="Kirim pengajuan peminjaman alat?"
                    description="Pengajuan akan dikirim ke tim MSC untuk ditinjau. Periksa kembali sebelum melanjutkan."
                    confirm-label="Ya, Ajukan"
                    :summary="[
                        'Nama peminjam' => 'requester_name',
                        'Unit / Fakultas' => 'unit',
                        'Mulai' => 'start_at',
                        'Selesai' => 'end_at',
                        'Alat dipinjam' => 'items[]',
                        'Keperluan' => 'purpose',
                    ]" />
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

@endpush
@endsection
