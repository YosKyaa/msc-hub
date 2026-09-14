@php
    use App\Filament\Pages\Dokumentasi;
    use App\Support\PanduanPanel;

    $warna = PanduanPanel::warna($isi['warna']);
@endphp

<x-filament-panels::page>
    {{-- Alasan yang sama seperti halaman daftarnya: panel ini tidak memakai
         viteTheme(), jadi kelas utility karangan sendiri tidak pernah ikut
         terkompilasi dan harus ditulis sebagai CSS. --}}
    <style>
        .panduan-langkah { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1rem; }
        .panduan-langkah li { display: flex; gap: 0.75rem; }

        .panduan-nomor {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 600;
        }

        .panduan-utama { font-size: 0.875rem; font-weight: 500; color: rgb(24 24 27); }
        .panduan-detail { margin-top: 0.125rem; font-size: 0.875rem; line-height: 1.6; color: rgb(113 113 122); }

        .panduan-daftar { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.75rem; }
        .panduan-daftar li { display: flex; gap: 0.625rem; font-size: 0.875rem; line-height: 1.6; color: rgb(113 113 122); }
        .panduan-daftar svg { width: 1rem; height: 1rem; flex: 0 0 auto; margin-top: 0.2rem; color: rgb(161 161 170); }

        .panduan-baris {
            display: grid;
            gap: 0.25rem;
            padding: 0.75rem 0;
            border-top: 1px solid rgb(244 244 245);
            font-size: 0.875rem;
        }

        .panduan-baris:first-child { border-top: 0; padding-top: 0; }
        .panduan-istilah { font-weight: 500; color: rgb(24 24 27); }
        .panduan-arti { color: rgb(113 113 122); }

        .panduan-tanya { display: flex; flex-direction: column; gap: 1.25rem; font-size: 0.875rem; }
        .panduan-tanya dt { font-weight: 500; color: rgb(24 24 27); }
        .panduan-tanya dd { margin: 0.25rem 0 0; line-height: 1.6; color: rgb(113 113 122); }

        .panduan-tombol { display: flex; flex-wrap: wrap; gap: 0.75rem; }

        @media (min-width: 640px) {
            .panduan-baris { grid-template-columns: 10rem 1fr; gap: 0.75rem; }
        }

        .dark .panduan-utama,
        .dark .panduan-istilah,
        .dark .panduan-tanya dt { color: #fff; }

        .dark .panduan-detail,
        .dark .panduan-arti,
        .dark .panduan-daftar li,
        .dark .panduan-tanya dd { color: rgb(161 161 170); }

        .dark .panduan-baris { border-color: rgb(39 39 42); }
    </style>

    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        @if (! empty($isi['langkah']))
            <x-filament::section>
                <x-slot name="heading">Langkahnya</x-slot>
                <x-slot name="description">Dari pengajuan sampai selesai.</x-slot>

                <ol class="panduan-langkah">
                    @foreach ($isi['langkah'] as $nomor => $langkah)
                        <li>
                            <span class="panduan-nomor" style="background-color:{{ $warna['latar'] }};color:{{ $warna['teks'] }};">
                                {{ $nomor + 1 }}
                            </span>
                            <span>
                                <span class="panduan-utama" style="display:block;">{{ $langkah[0] }}</span>
                                <span class="panduan-detail" style="display:block;">{{ $langkah[1] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-filament::section>
        @endif

        @if (! empty($isi['peran']))
            <x-filament::section>
                <x-slot name="heading">Pembagian wewenang</x-slot>

                <dl style="margin:0;">
                    @foreach ($isi['peran'] as [$peran, $keterangan])
                        <div class="panduan-baris">
                            <dt class="panduan-istilah">{{ $peran }}</dt>
                            <dd class="panduan-arti" style="margin:0;">{{ $keterangan }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endif

        @if (! empty($isi['tanya']))
            <x-filament::section>
                <x-slot name="heading">Pertanyaan yang sering muncul</x-slot>

                <dl class="panduan-tanya">
                    @foreach ($isi['tanya'] as [$pertanyaan, $jawaban])
                        <div>
                            <dt>{{ $pertanyaan }}</dt>
                            <dd>{{ $jawaban }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endif

        @if (! empty($isi['catatan']))
            <x-filament::section>
                <x-slot name="heading">Yang perlu diingat</x-slot>

                <ul class="panduan-daftar">
                    @foreach ($isi['catatan'] as $catatan)
                        <li>
                            <x-filament::icon icon="heroicon-m-information-circle" />
                            <span>{{ $catatan }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @if (! empty($isi['tautan']))
            <x-filament::section>
                <x-slot name="heading">Buka halamannya</x-slot>

                <div class="panduan-tombol">
                    @foreach ($isi['tautan'] as $route => $label)
                        <x-filament::button tag="a" :href="route($route)" color="gray" outlined>
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <div>
            <x-filament::link :href="Dokumentasi::getUrl()" icon="heroicon-m-arrow-left">
                Kembali ke daftar panduan
            </x-filament::link>
        </div>
    </div>
</x-filament-panels::page>
