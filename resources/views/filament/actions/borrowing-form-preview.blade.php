{{-- Pratinjau formulir resmi sebelum diunduh, memakai penampil PDF bawaan browser. --}}
<div class="space-y-3">
    <iframe src="{{ $previewUrl }}"
            class="h-[70vh] w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"
            title="Pratinjau formulir peminjaman"></iframe>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Periksa isian sebelum dicetak. Kolom yang belum terisi di sistem tampil sebagai
        garis titik-titik agar dapat dilengkapi dengan tangan.
    </p>
</div>
