{{--
    Gaya tambahan untuk panel.

    Panel ini tidak memakai tema Vite, jadi kelas Tailwind yang tidak dipakai
    Filament sendiri tidak ikut terkompilasi. Gaya yang dibutuhkan ditulis di
    sini, dimuat di <head> setiap halaman panel.
--}}
<style>
    /* Peserta tanpa email: tetap terdaftar dan tetap mendapat sertifikat,
       tetapi tidak bisa dikirimi. Barisnya dikuningkan supaya admin tahu
       siapa yang emailnya masih perlu dilengkapi. */
    .fi-ta-row.msc-tanpa-email > td { background-color: rgb(254 249 195); }
    .fi-ta-row.msc-tanpa-email:hover > td { background-color: rgb(254 240 138); }
    .fi-ta-row.msc-tanpa-email > td:first-child { box-shadow: inset 4px 0 0 rgb(234 179 8); }
    .dark .fi-ta-row.msc-tanpa-email > td { background-color: rgb(113 63 18 / .35); }
    .dark .fi-ta-row.msc-tanpa-email:hover > td { background-color: rgb(113 63 18 / .5); }
</style>
