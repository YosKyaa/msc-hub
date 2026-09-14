@php
    use App\Filament\Pages\DokumentasiTopik;
    use App\Support\PanduanPanel;

    $topik = PanduanPanel::semua();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Cara kerja MSC Hub</x-slot>
            <x-slot name="description">
                Semuanya berawal dari pengajuan warga kampus lewat halaman publik, lalu diproses di panel ini.
            </x-slot>

            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                Ada empat hal yang dikerjakan di sini: <strong>permintaan konten</strong>,
                <strong>peminjaman ruangan dan alat</strong>, <strong>penerbitan sertifikat</strong>, dan
                <strong>arsip media</strong>. Tiga yang pertama selalu menempuh pola yang sama —
                diajukan, ditinjau staf, disetujui Kepala MSC, lalu selesai — dan pemohon dikabari
                lewat email pada tiap perubahan status.
            </p>

            <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                Halaman depan panel menampilkan <strong>Perlu Tindakan Anda</strong>: apa saja yang
                sedang menunggu, lengkap dengan jalan pintas ke sana. Mulailah dari situ.
            </p>
        </x-filament::section>

        {{-- Kartu dulu, rinciannya di halaman masing-masing. Satu halaman
             panjang berisi semua modul memaksa yang dicari digulir dulu. --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($topik as $slug => $item)
                @php $warna = PanduanPanel::warna($item['warna']); @endphp

                <a href="{{ DokumentasiTopik::getUrl(['topik' => $slug]) }}"
                   class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-white/20">
                    <span class="flex size-11 items-center justify-center rounded-lg {{ $warna['kotak'] }}">
                        <x-filament::icon :icon="$item['ikon']" class="size-6" />
                    </span>

                    <span class="mt-4 block font-semibold text-gray-900 dark:text-white">{{ $item['judul'] }}</span>
                    <span class="mt-1 block text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $item['ringkas'] }}</span>

                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400">
                        Baca selengkapnya
                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">Butuh bantuan?</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Hubungi tim Media &amp; Strategic Communications, Jakarta Global University.
                Sebutkan kode pengajuannya — misalnya <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">CR-2026-0001</code>
                atau <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">ROOM-2026-0001</code> — supaya lebih cepat ditelusuri.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
