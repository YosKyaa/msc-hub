@extends('layouts.public')

@section('title', 'Ringkasan')

@section('content')
<div class="space-y-8">
    {{-- Sapaan --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">
                Halo, {{ \Illuminate\Support\Str::before($requester['name'], ' ') }}
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Halaman ini merangkum semua pengajuan Anda ke tim Media &amp; Strategic Communications.
            </p>
            <p class="mt-0.5 break-words text-xs text-gray-400">{{ $requester['email'] }}</p>
        </div>
        <span class="self-start rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
            {{ \App\Support\RequesterSession::accountLabel($requester) }}
        </span>
    </div>

    {{-- Pilihan layanan: inilah alasan utama halaman ini ada. --}}
    <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Mau mengajukan apa?</h2>

        @php
            $layanan = [
                [
                    'route' => 'request.content',
                    'label' => 'Ajukan Konten',
                    'desc' => 'Foto, video, desain, atau publikasi media sosial.',
                    'tone' => 'blue',
                    'icon' => 'M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586Z',
                ],
                [
                    'route' => 'booking.room',
                    'label' => 'Booking Ruangan',
                    'desc' => 'Pinjam studio atau ruang rapat MSC.',
                    'tone' => 'indigo',
                    'icon' => 'M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4',
                ],
                [
                    'route' => 'booking.inventory',
                    'label' => 'Pinjam Alat',
                    'desc' => 'Kamera, lighting, audio, dan perlengkapan lain.',
                    'tone' => 'emerald',
                    'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                ],
            ];

            // Ditulis utuh, bukan dirangkai dari $tone: kelas yang dibentuk
            // saat berjalan hanya selamat selama halaman memakai Play CDN.
            $tepi = [
                'blue' => 'hover:border-blue-300 focus:ring-blue-400',
                'indigo' => 'hover:border-indigo-300 focus:ring-indigo-400',
                'emerald' => 'hover:border-emerald-300 focus:ring-emerald-400',
            ];

            $ikon = [
                'blue' => 'bg-blue-50 text-blue-600',
                'indigo' => 'bg-indigo-50 text-indigo-600',
                'emerald' => 'bg-emerald-50 text-emerald-600',
            ];
        @endphp

        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($layanan as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 {{ $tepi[$item['tone']] }}">
                    <span class="flex size-11 items-center justify-center rounded-xl {{ $ikon[$item['tone']] }}">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                    </span>
                    <span class="mt-4 block font-semibold text-gray-900">{{ $item['label'] }}</span>
                    <span class="mt-1 block text-sm text-gray-500">{{ $item['desc'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Orang yang baru pertama mengajukan tidak tahu apa yang terjadi
         sesudah tombol kirim ditekan. Tiga langkah ini menjawabnya. --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-5">
        <h2 class="font-semibold text-gray-900">Bagaimana prosesnya?</h2>

        <ol class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['1', 'Anda mengajukan', 'Isi formulir dan kirim. Anda langsung menerima kode pengajuan.'],
                ['2', 'Tim MSC meninjau', 'Staf memeriksa, lalu Kepala MSC menyetujui. Anda dikabari lewat email.'],
                ['3', 'Selesai', 'Statusnya berubah di halaman ini dan pengajuan siap dijalankan.'],
            ] as [$nomor, $judul, $isi])
                <li class="flex gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-600">
                        {{ $nomor }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-900">{{ $judul }}</span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">{{ $isi }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Pengajuan sendiri --}}
    <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-900">Pengajuan Konten</h2>
                <a href="{{ route('request.status') }}" class="shrink-0 text-sm font-medium text-blue-600 hover:underline">
                    Lihat semua ({{ $totals['konten'] }})
                </a>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($contentRequests as $item)
                    <a href="{{ route('request.status.detail', $item->request_code) }}"
                       class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 p-3 transition hover:border-blue-200 hover:bg-blue-50/40">
                        <span class="min-w-0">
                            <span class="block break-all font-mono text-sm font-semibold text-blue-700">{{ $item->request_code }}</span>
                            <span class="mt-0.5 block truncate text-sm text-gray-600">{{ $item->content_type->getLabel() }}</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                            {{ $item->status->getLabel() }}
                        </span>
                    </a>
                @empty
                    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">
                        Belum ada pengajuan konten.
                        <a href="{{ route('request.content') }}" class="font-medium text-blue-600 hover:underline">Ajukan sekarang</a>.
                    </p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-900">Peminjaman</h2>
                <a href="{{ route('my.bookings') }}" class="shrink-0 text-sm font-medium text-indigo-600 hover:underline">
                    Lihat semua ({{ $totals['ruangan'] + $totals['alat'] }})
                </a>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($roomBookings as $item)
                    <a href="{{ route('my.bookings.detail', ['type' => 'room', 'code' => $item->booking_code]) }}"
                       class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/40">
                        <span class="min-w-0">
                            <span class="block break-all font-mono text-sm font-semibold text-indigo-700">{{ $item->booking_code }}</span>
                            <span class="mt-0.5 block truncate text-sm text-gray-600">
                                {{ $item->room?->name ?? 'Ruangan' }} &middot; {{ $item->start_at->format('d M Y, H:i') }}
                            </span>
                        </span>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                            {{ $item->status->getLabel() }}
                        </span>
                    </a>
                @empty
                @endforelse

                @forelse ($inventoryBookings as $item)
                    <a href="{{ route('my.bookings.detail', ['type' => 'inventory', 'code' => $item->booking_code]) }}"
                       class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/40">
                        <span class="min-w-0">
                            <span class="block break-all font-mono text-sm font-semibold text-indigo-700">{{ $item->booking_code }}</span>
                            <span class="mt-0.5 block truncate text-sm text-gray-600">
                                {{ $item->items->count() }} alat &middot; {{ $item->start_at->format('d M Y, H:i') }}
                            </span>
                        </span>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                            {{ $item->status->getLabel() }}
                        </span>
                    </a>
                @empty
                @endforelse

                @if ($roomBookings->isEmpty() && $inventoryBookings->isEmpty())
                    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">
                        Belum ada peminjaman.
                        <a href="{{ route('booking.room') }}" class="font-medium text-indigo-600 hover:underline">Booking ruangan</a>
                        atau
                        <a href="{{ route('booking.inventory') }}" class="font-medium text-indigo-600 hover:underline">pinjam alat</a>.
                    </p>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
