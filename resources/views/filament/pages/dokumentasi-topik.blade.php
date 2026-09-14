@php
    use App\Filament\Pages\Dokumentasi;
    use App\Support\PanduanPanel;

    $warna = PanduanPanel::warna($isi['warna']);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        @if (! empty($isi['langkah']))
            <x-filament::section>
                <x-slot name="heading">Langkahnya</x-slot>
                <x-slot name="description">Dari pengajuan sampai selesai.</x-slot>

                <ol class="space-y-4">
                    @foreach ($isi['langkah'] as $nomor => $langkah)
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $warna['angka'] }}">
                                {{ $nomor + 1 }}
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $langkah[0] }}</span>
                                <span class="mt-0.5 block text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $langkah[1] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-filament::section>
        @endif

        @if (! empty($isi['peran']))
            <x-filament::section>
                <x-slot name="heading">Pembagian wewenang</x-slot>

                <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    @foreach ($isi['peran'] as [$peran, $keterangan])
                        <div class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[10rem_1fr] sm:gap-3">
                            <dt class="font-medium text-gray-900 dark:text-white">{{ $peran }}</dt>
                            <dd class="text-gray-500 dark:text-gray-400">{{ $keterangan }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endif

        @if (! empty($isi['tanya']))
            <x-filament::section>
                <x-slot name="heading">Pertanyaan yang sering muncul</x-slot>

                <div class="space-y-5 text-sm">
                    @foreach ($isi['tanya'] as [$pertanyaan, $jawaban])
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $pertanyaan }}</p>
                            <p class="mt-1 leading-relaxed text-gray-500 dark:text-gray-400">{{ $jawaban }}</p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if (! empty($isi['catatan']))
            <x-filament::section>
                <x-slot name="heading">Yang perlu diingat</x-slot>

                <ul class="space-y-2.5 text-sm">
                    @foreach ($isi['catatan'] as $catatan)
                        <li class="flex gap-2.5">
                            <x-filament::icon icon="heroicon-m-information-circle" class="mt-0.5 size-4 shrink-0 text-gray-400" />
                            <span class="leading-relaxed text-gray-500 dark:text-gray-400">{{ $catatan }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @if (! empty($isi['tautan']))
            <x-filament::section>
                <x-slot name="heading">Buka halamannya</x-slot>

                <div class="flex flex-wrap gap-3">
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
