@php
    use App\Filament\Pages\DokumentasiTopik;
    use App\Support\PanduanPanel;

    $topik = PanduanPanel::semua();
@endphp

<x-filament-panels::page>
    {{-- Gaya ditulis di sini, bukan lewat utility Tailwind: panel ini tidak
         memakai viteTheme(), sehingga hanya kelas yang sudah dipakai Filament
         sendiri yang ikut terkompilasi. Kelas karangan sendiri — grid kartu,
         bayangan, sudut membulat — tidak akan pernah berlaku. --}}
    <style>
        .panduan-grid {
            display: grid;
            gap: 1rem;
            /* Menyesuaikan lebar layar tanpa media query. */
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr));
        }

        .panduan-kartu {
            display: flex;
            flex-direction: column;
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid rgb(228 228 231);
            background-color: #fff;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .panduan-kartu:hover {
            border-color: rgb(161 161 170);
            box-shadow: 0 4px 12px rgb(0 0 0 / 0.08);
            transform: translateY(-2px);
        }

        .panduan-ikon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.625rem;
        }

        .panduan-ikon svg { width: 1.5rem; height: 1.5rem; }

        .panduan-judul {
            margin-top: 1rem;
            font-weight: 600;
            color: rgb(24 24 27);
        }

        .panduan-ringkas {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: rgb(113 113 122);
        }

        .panduan-lanjut {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: auto;
            padding-top: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgb(217 119 6);
        }

        .panduan-lanjut svg { width: 1rem; height: 1rem; }

        .dark .panduan-kartu {
            border-color: rgb(63 63 70);
            background-color: rgb(24 24 27);
            box-shadow: none;
        }

        .dark .panduan-kartu:hover { border-color: rgb(113 113 122); }
        .dark .panduan-judul { color: #fff; }
        .dark .panduan-ringkas { color: rgb(161 161 170); }
        .dark .panduan-lanjut { color: rgb(251 191 36); }
    </style>

    <div class="fi-section-content-ctn" style="display:flex;flex-direction:column;gap:1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Cara kerja MSC Hub</x-slot>
            <x-slot name="description">
                Semuanya berawal dari pengajuan warga kampus lewat halaman publik, lalu diproses di panel ini.
            </x-slot>

            <p style="font-size:0.875rem;line-height:1.6;color:rgb(82 82 91);">
                Ada empat hal yang dikerjakan di sini: <strong>permintaan konten</strong>,
                <strong>peminjaman ruangan dan alat</strong>, <strong>penerbitan sertifikat</strong>, dan
                <strong>arsip media</strong>. Tiga yang pertama selalu menempuh pola yang sama —
                diajukan, ditinjau staf, disetujui Kepala MSC, lalu selesai — dan pemohon dikabari
                lewat email pada tiap perubahan status.
            </p>

            <p style="margin-top:0.75rem;font-size:0.875rem;line-height:1.6;color:rgb(82 82 91);">
                Halaman depan panel menampilkan <strong>Perlu Tindakan Anda</strong>: apa saja yang
                sedang menunggu, lengkap dengan jalan pintas ke sana. Mulailah dari situ.
            </p>
        </x-filament::section>

        {{-- Kartu dulu, rinciannya di halaman masing-masing. Satu halaman
             panjang berisi semua modul memaksa yang dicari digulir dulu. --}}
        <div class="panduan-grid">
            @foreach ($topik as $slug => $item)
                @php $warna = PanduanPanel::warna($item['warna']); @endphp

                <a href="{{ DokumentasiTopik::getUrl(['topik' => $slug]) }}" class="panduan-kartu">
                    <span class="panduan-ikon" style="background-color:{{ $warna['latar'] }};color:{{ $warna['teks'] }};">
                        <x-filament::icon :icon="$item['ikon']" />
                    </span>

                    <span class="panduan-judul">{{ $item['judul'] }}</span>
                    <span class="panduan-ringkas">{{ $item['ringkas'] }}</span>

                    <span class="panduan-lanjut">
                        Baca selengkapnya
                        <x-filament::icon icon="heroicon-m-arrow-right" />
                    </span>
                </a>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">Butuh bantuan?</x-slot>

            <p style="font-size:0.875rem;color:rgb(82 82 91);">
                Hubungi tim Media &amp; Strategic Communications, Jakarta Global University.
                Sebutkan kode pengajuannya — misalnya <code>CR-2026-0001</code>
                atau <code>ROOM-2026-0001</code> — supaya lebih cepat ditelusuri.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
