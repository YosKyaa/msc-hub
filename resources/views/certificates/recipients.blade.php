@extends('layouts.public')

@section('title', 'Penerima Sertifikat — '.$event->name)

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="text-center">
        <p class="text-sm font-medium text-blue-600">Penerima Sertifikat</p>
        <h1 class="mt-1 text-xl font-bold text-gray-900 sm:text-2xl">{{ $event->name }}</h1>

        <p class="mt-2 text-sm text-gray-500">
            @if ($event->event_date)
                {{ $event->event_date->locale('id')->isoFormat('D MMMM Y') }} &middot;
            @endif
            Diterbitkan oleh {{ $event->issuer?->name ?? 'Media & Strategic Communications, Jakarta Global University' }}
        </p>

        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-blue-50 px-4 py-1.5 text-sm font-medium text-blue-700">
            {{ $total }} penerima
        </p>
    </div>

    {{-- Daftar bisa memuat ratusan nama, jadi pencarian bukan kemewahan. --}}
    <form method="GET" class="mt-6">
        <label for="cari" class="sr-only">Cari nama atau nomor sertifikat</label>
        <div class="flex gap-2">
            <input id="cari" type="search" name="cari" value="{{ $cari }}"
                   placeholder="Cari nama atau nomor sertifikat"
                   class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                Cari
            </button>
        </div>
    </form>

    <div class="mt-6 space-y-2">
        @forelse ($penerima as $item)
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="font-medium text-gray-900">{{ $item->recipient_name }}</p>
                    <p class="mt-0.5 break-all font-mono text-xs text-gray-500">{{ $item->certificate_number }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                        {{ $item->recipient_role_label ?: ucfirst($item->recipient_role) }}
                    </span>
                    <a href="{{ $item->verificationUrl() }}"
                       class="text-sm font-medium text-blue-600 underline-offset-2 hover:underline">
                        Verifikasi
                    </a>
                </div>
            </div>
        @empty
            <p class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500">
                @if ($cari !== '')
                    Tidak ada penerima yang cocok dengan &ldquo;{{ $cari }}&rdquo;.
                    <a href="{{ route('certificates.recipients', $event->slug) }}" class="font-medium text-blue-600 hover:underline">Tampilkan semua</a>.
                @else
                    Belum ada sertifikat yang diterbitkan untuk kegiatan ini.
                @endif
            </p>
        @endforelse
    </div>

    @if ($penerima->hasPages())
        <div class="mt-6">
            {{ $penerima->links() }}
        </div>
    @endif

    <p class="mt-8 text-center text-xs leading-relaxed text-gray-400">
        Daftar ini memuat nama, peran, dan nomor sertifikat sebagaimana tercetak pada dokumennya.
        Keaslian tiap sertifikat dapat diperiksa melalui tautan verifikasinya.
    </p>
</div>
@endsection
