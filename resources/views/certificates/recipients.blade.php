@extends('layouts.public')

@section('title', 'Penerima Sertifikat — '.$event->name)

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="text-center">
        <p class="text-sm font-medium text-sun-ink">Penerima Sertifikat</p>
        <h1 class="mt-1 text-xl font-bold text-ink sm:text-2xl">{{ $event->name }}</h1>

        <p class="mt-2 text-sm text-ink/60">
            @if ($event->event_date)
                {{ $event->event_date->locale('id')->isoFormat('D MMMM Y') }} &middot;
            @endif
            Diterbitkan oleh {{ $event->issuer?->name ?? 'Media & Strategic Communications, Jakarta Global University' }}
        </p>

        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-sun-soft px-4 py-1.5 text-sm font-medium text-sun-ink">
            {{ $total }} penerima
        </p>
    </div>

    {{-- Daftar bisa memuat ratusan nama, jadi pencarian bukan kemewahan. --}}
    <form method="GET" class="mt-6">
        <label for="cari" class="sr-only">Cari nama atau nomor sertifikat</label>
        <div class="flex gap-2">
            <input id="cari" type="search" name="cari" value="{{ $cari }}"
                   placeholder="Cari nama atau nomor sertifikat"
                   class="w-full rounded-lg border border-ink/15 px-4 py-2.5 text-sm focus:border-sun-deep focus:outline-none focus:ring-1 focus:ring-sun-deep/50">
            <button type="submit" class="shrink-0 rounded-lg bg-sun px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-sun-deep">
                Cari
            </button>
        </div>
    </form>

    <div class="mt-6 space-y-2">
        @forelse ($penerima as $item)
            <div class="flex flex-col gap-3 rounded-xl border border-ink/10 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="font-medium text-ink">{{ $item->recipient_name }}</p>
                    <p class="mt-0.5 break-all font-mono text-xs text-ink/60">{{ $item->certificate_number }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <span class="rounded-full bg-paper-3 px-2.5 py-0.5 text-xs font-medium text-ink/75">
                        {{ $item->recipient_role_label ?: ucfirst($item->recipient_role) }}
                    </span>
                    <a href="{{ $item->verificationUrl() }}"
                       class="text-sm font-medium text-sun-ink underline-offset-2 hover:underline">
                        Verifikasi
                    </a>
                </div>
            </div>
        @empty
            <p class="rounded-xl bg-paper-2 p-6 text-center text-sm text-ink/60">
                @if ($cari !== '')
                    Tidak ada penerima yang cocok dengan &ldquo;{{ $cari }}&rdquo;.
                    <a href="{{ route('certificates.recipients', $event->slug) }}" class="font-medium text-sun-ink hover:underline">Tampilkan semua</a>.
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

    <p class="mt-8 text-center text-xs leading-relaxed text-ink/45">
        Daftar ini memuat nama, peran, dan nomor sertifikat sebagaimana tercetak pada dokumennya.
        Keaslian tiap sertifikat dapat diperiksa melalui tautan verifikasinya.
    </p>
</div>
@endsection
