<x-filament-panels::page>
    {{-- Ukuran dipasang sebagai style inline, bukan kelas Tailwind: panel ini
         memakai CSS bawaan Filament tanpa viteTheme(), sehingga utility seperti
         w-full atau h-[calc(...)] tidak pernah ikut terbangun dan bingkai jatuh
         ke ukuran default HTML 300x150 px.

         Lembar A4 dipakai untuk diperiksa dan ditandatangani, jadi diberi
         hampir seluruh tinggi layar. --}}
    <iframe
        src="{{ $this->getPreviewUrl() }}"
        title="Formulir peminjaman {{ $this->record->booking_code }}"
        style="display:block;
               width:100%;
               height:calc(100vh - 17rem);
               min-height:720px;
               border:1px solid rgb(228 228 231);
               border-radius:0.75rem;
               background:#fff;"
    ></iframe>

    <p style="margin-top:0.75rem;font-size:0.875rem;line-height:1.25rem;color:rgb(113 113 122);">
        Kolom yang belum terisi di sistem tampil sebagai garis titik-titik agar dapat
        dilengkapi dengan tangan setelah dicetak.
    </p>
</x-filament-panels::page>
