<x-filament-panels::page>
    {{-- Lembar A4 ditampilkan sebesar mungkin: dokumen ini dipakai untuk
         diperiksa dan ditandatangani, bukan sekadar dilirik. --}}
    <iframe src="{{ $this->getPreviewUrl() }}"
            class="h-[calc(100vh-16rem)] min-h-[640px] w-full rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10"
            title="Formulir peminjaman {{ $this->record->booking_code }}"></iframe>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Kolom yang belum terisi di sistem tampil sebagai garis titik-titik agar dapat
        dilengkapi dengan tangan setelah dicetak.
    </p>
</x-filament-panels::page>
