<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verifikasi Sertifikat - MSC Hub</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-50 text-slate-900"><main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-12">
<div class="w-full rounded-2xl border bg-white p-8 shadow-sm">
    <div class="mb-6 flex items-center gap-3"><img src="{{ asset('img/jgu.png') }}" class="h-10" alt="JGU"><div><p class="font-bold">MSC Hub</p><p class="text-xs text-slate-500">Verifikasi Sertifikat</p></div></div>
    @if($certificate->isValid())
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800"><p class="font-semibold">Sertifikat valid</p><p class="mt-1 text-sm">Data sertifikat tercatat dan diterbitkan oleh MSC JGU.</p></div>
    @else
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><p class="font-semibold">Sertifikat tidak berlaku</p><p class="mt-1 text-sm">Sertifikat belum dipublikasikan atau telah dicabut.</p></div>
    @endif
    <dl class="divide-y rounded-xl border">
        @foreach(['Nomor sertifikat' => $certificate->certificate_number, 'Nama penerima' => $certificate->recipient_name, 'Peran' => $certificate->recipient_role_label ?: ucfirst($certificate->recipient_role), 'Kegiatan' => $certificate->event->name, 'Tanggal kegiatan' => $certificate->event->event_date->translatedFormat('d F Y'), 'Penyelenggara' => $certificate->event->organizer] as $label => $value)
        <div class="grid gap-1 p-4 sm:grid-cols-2"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="font-medium sm:text-right">{{ $value ?: '—' }}</dd></div>
        @endforeach
    </dl>
    @if($certificate->isValid())<a href="{{ route('certificates.download', $certificate->verification_token) }}" class="mt-6 block rounded-lg bg-blue-600 px-4 py-3 text-center font-semibold text-white hover:bg-blue-700">Unduh sertifikat</a>@endif
</div></main></body></html>
