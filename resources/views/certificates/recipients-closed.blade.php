@extends('layouts.public')

@section('title', 'Daftar Penerima Belum Dibuka')

@section('content')
<div class="mx-auto max-w-lg text-center">
    <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-50 text-amber-600">
        <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
        </svg>
    </span>

    <h1 class="mt-5 text-xl font-bold text-gray-900 sm:text-2xl">Daftar penerima belum dibuka</h1>

    <p class="mt-3 text-sm leading-relaxed text-gray-600">
        Penyelenggara <strong>{{ $event->name }}</strong> belum membuka daftar penerima sertifikat
        kegiatan ini untuk umum. Bisa jadi memang belum diumumkan, atau sengaja tidak ditayangkan.
    </p>

    {{-- Yang paling berguna bagi pemilik sertifikat: ia tidak perlu daftar
         ini sama sekali untuk memeriksa miliknya sendiri. --}}
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 text-left">
        <p class="text-sm font-semibold text-gray-900">Sudah punya sertifikatnya?</p>
        <p class="mt-1.5 text-sm leading-relaxed text-gray-600">
            Anda tidak perlu daftar ini untuk memeriksa keaslian sertifikat Anda sendiri.
            Buka tautan verifikasi pada email sertifikat Anda, atau pindai kode QR yang tercetak
            di dokumennya.
        </p>
    </div>

    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 text-left">
        <p class="text-sm font-semibold text-gray-900">Perlu bantuan?</p>
        <p class="mt-1.5 text-sm leading-relaxed text-gray-600">
            Hubungi tim Media &amp; Strategic Communications di
            <a href="mailto:{{ $kontak }}?subject={{ rawurlencode('Daftar penerima sertifikat: '.$event->name) }}"
               class="font-medium text-blue-600 underline-offset-2 hover:underline">{{ $kontak }}</a>,
            dan sebutkan nama kegiatannya agar lebih cepat ditelusuri.
        </p>
    </div>

    <p class="mt-8 text-sm">
        <a href="{{ route('landing') }}" class="font-medium text-gray-600 underline-offset-2 hover:underline">
            Kembali ke beranda
        </a>
    </p>
</div>
@endsection
