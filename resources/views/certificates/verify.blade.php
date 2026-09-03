@php
    $event = $certificate->event;
    $isValid = $certificate->isValid();
    $verificationUrl = $certificate->verificationUrl();

    $details = [
        'Nomor sertifikat' => $certificate->certificate_number,
        'Nama penerima' => $certificate->recipient_name,
        'Peran' => $certificate->recipient_role_label ?: ucfirst($certificate->recipient_role),
        'Kegiatan' => $event->name,
        'Tanggal kegiatan' => $event->event_date->translatedFormat('d F Y'),
        'Penyelenggara' => $event->organizer,
        'Diterbitkan' => $certificate->issued_at?->translatedFormat('d F Y'),
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>{{ $certificate->recipient_name }} — {{ $event->name }} | Verifikasi Sertifikat MSC Hub</title>
    <meta name="robots" content="noindex">
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">

<header class="border-b bg-white">
    <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-4">
        <img src="{{ asset('img/jgu.png') }}" class="h-9 w-auto" alt="Jakarta Global University">
        <div class="border-l pl-3">
            <p class="text-sm font-semibold leading-tight">MSC Hub</p>
            <p class="text-xs text-slate-500">Verifikasi Sertifikat</p>
        </div>
    </div>
</header>

<main class="mx-auto max-w-5xl px-4 py-8">

    {{-- Pratinjau memakai template, koordinat, dan variabel yang sama dengan
         berkas PDF, sehingga yang dilihat pemeriksa identik dengan yang dicetak. --}}
    <x-molecules.certificate-preview
        :template="$event->template"
        :values="$values"
        :qr-data-uri="$qrDataUri"
        :muted="! $isValid"
        :watermark="$isValid ? null : 'Tidak Berlaku'"
        class="mx-auto max-w-3xl" />

    <div class="mx-auto mt-6 max-w-3xl">
        @if($isValid)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                <p class="flex items-center gap-2 font-semibold">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Sertifikat valid
                </p>
                <p class="mt-1 text-sm">Data sertifikat ini tercatat dan diterbitkan oleh MSC Jakarta Global University.</p>
            </div>
        @else
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900">
                <p class="flex items-center gap-2 font-semibold">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01" stroke-linecap="round"/>
                    </svg>
                    Sertifikat tidak berlaku
                </p>
                <p class="mt-1 text-sm">
                    @if($certificate->revoked_at)
                        Sertifikat ini dicabut pada {{ $certificate->revoked_at->translatedFormat('d F Y') }}.
                    @else
                        Kegiatannya belum dipublikasikan, sehingga sertifikat belum dinyatakan sah.
                    @endif
                </p>
            </div>
        @endif
    </div>

    <div class="mx-auto mt-6 grid max-w-3xl gap-6 lg:max-w-5xl lg:grid-cols-[20rem_1fr]">
        {{-- Aksi --}}
        <div class="space-y-4">
            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diberikan kepada</p>
                <p class="mt-1 text-lg font-bold leading-snug">{{ $certificate->recipient_name }}</p>
                <p class="text-sm text-slate-500">{{ $certificate->recipient_role_label ?: ucfirst($certificate->recipient_role) }}</p>

                @if($isValid)
                    <a href="{{ $certificate->downloadUrl() }}"
                       class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Unduh sertifikat
                    </a>
                @endif

                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $verificationUrl }}').then(() => { this.querySelector('span').textContent = 'Tautan disalin'; })"
                        class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-3 font-semibold text-slate-700 hover:bg-slate-50">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Salin tautan verifikasi</span>
                </button>
            </div>

            <div class="rounded-2xl border bg-white p-5 text-center shadow-sm">
                <img src="{{ $qrDataUri }}" alt="QR verifikasi sertifikat" class="mx-auto size-36">
                <p class="mt-3 text-xs text-slate-500">Pindai untuk membuka halaman verifikasi ini.</p>
            </div>
        </div>

        {{-- Rincian --}}
        <div class="rounded-2xl border bg-white shadow-sm">
            <div class="border-b px-5 py-4">
                <h2 class="font-semibold">Rincian sertifikat</h2>
            </div>
            <dl class="divide-y">
                @foreach($details as $label => $value)
                    <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr]">
                        <dt class="text-sm text-slate-500">{{ $label }}</dt>
                        <dd class="font-medium break-words">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <p class="mx-auto mt-8 max-w-3xl text-center text-xs text-slate-500">
        Keaslian sertifikat dapat diperiksa kapan saja melalui QR pada dokumen atau tautan halaman ini.<br>
        Media &amp; Strategic Communications — Jakarta Global University
    </p>
</main>
</body>
</html>
