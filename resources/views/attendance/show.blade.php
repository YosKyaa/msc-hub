@extends('layouts.public')

@section('title', 'Absensi '.$event->name)

@section('content')
<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
        {{-- Identitas kegiatan --}}
        <div class="bg-blue-600 text-white px-6 py-5">
            <p class="text-xs uppercase tracking-wide text-blue-100">Absensi Kegiatan</p>
            <h1 class="text-xl font-semibold mt-1 leading-snug">{{ $event->name }}</h1>
            <p class="text-sm text-blue-100 mt-2">{{ $event->event_date->translatedFormat('l, d F Y') }}</p>
            @if($event->organizer)
                <p class="text-sm text-blue-100">{{ $event->organizer }}</p>
            @endif
        </div>

        <div class="px-6 py-6 space-y-5">
            @if($windowState === 'not_started')
                <x-atoms.alert title="Absensi belum dibuka" :dismissible="false">
                    Absensi dibuka pada {{ $event->attendance_open_at->translatedFormat('l, d F Y H:i') }} WIB.
                </x-atoms.alert>
            @elseif($windowState === 'closed')
                <x-atoms.alert variant="warning" title="Absensi sudah ditutup" :dismissible="false">
                    Absensi ditutup pada {{ $event->attendance_close_at->translatedFormat('l, d F Y H:i') }} WIB.
                    Hubungi panitia bila Anda hadir tetapi belum tercatat.
                </x-atoms.alert>
            @endif

            @if($event->attendance_open_at || $event->attendance_close_at)
                <dl class="text-sm text-gray-600 bg-gray-50 rounded-xl px-4 py-3 space-y-1">
                    @if($event->attendance_open_at)
                        <div class="flex justify-between gap-4">
                            <dt>Absensi dibuka</dt>
                            <dd class="font-medium text-gray-900 text-right">{{ $event->attendance_open_at->translatedFormat('d M Y H:i') }} WIB</dd>
                        </div>
                    @endif
                    @if($event->attendance_close_at)
                        <div class="flex justify-between gap-4">
                            <dt>Absensi ditutup</dt>
                            <dd class="font-medium text-gray-900 text-right">{{ $event->attendance_close_at->translatedFormat('d M Y H:i') }} WIB</dd>
                        </div>
                    @endif
                </dl>
            @endif

            @if($requester === null)
                <div class="text-center space-y-4">
                    <p class="text-sm text-gray-600">
                        Masuk dengan akun Google JGU Anda untuk mencatat kehadiran.
                    </p>
                    <a href="{{ $loginUrl }}"
                       class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl px-6 py-4 text-base">
                        Masuk dengan Google
                    </a>
                    <p class="text-xs text-gray-500">
                        Gunakan email @jgu.ac.id atau @student.jgu.ac.id.
                    </p>
                </div>
            @else
                <div class="rounded-xl border bg-gray-50 px-4 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">Anda login sebagai</p>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $requester['name'] ?? $requester['email'] }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $requester['email'] }}</p>
                    </div>
                    <form action="{{ route('auth.google.logout') }}" method="POST" class="shrink-0">
                        @csrf
                        <input type="hidden" name="redirect" value="{{ route('attendance.show', $event->attendance_token) }}">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-700 underline">
                            Bukan Anda?
                        </button>
                    </form>
                </div>

                @php
                    $checkedIn = $participation?->checked_in_at;
                    $checkedOut = $participation?->checked_out_at;
                @endphp

                @if($checkedIn || $checkedOut)
                    <dl class="text-sm rounded-xl border px-4 py-3 space-y-1">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Check-in</dt>
                            <dd class="font-medium text-gray-900">{{ $checkedIn ? $checkedIn->translatedFormat('d M Y H:i').' WIB' : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Check-out</dt>
                            <dd class="font-medium text-gray-900">{{ $checkedOut ? $checkedOut->translatedFormat('d M Y H:i').' WIB' : '—' }}</dd>
                        </div>
                    </dl>
                @endif

                @if($windowState === 'open')
                    @if($checkedIn && $checkedOut)
                        <x-atoms.alert variant="success" title="Kehadiran tercatat" :dismissible="false">
                            Check-in dan check-out Anda sudah lengkap. Tidak ada tindakan lain yang diperlukan.
                        </x-atoms.alert>
                    @else
                        <form action="{{ route('attendance.store', $event->attendance_token) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold rounded-xl px-6 py-5 text-lg">
                                {{ $checkedIn ? 'Check-out Sekarang' : 'Check-in Sekarang' }}
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 text-center">
                            {{ $checkedIn
                                ? 'Tekan saat Anda meninggalkan kegiatan.'
                                : 'Tekan saat Anda tiba di lokasi kegiatan.' }}
                        </p>
                    @endif
                @endif
            @endif
        </div>
    </div>

    <p class="text-center text-xs text-gray-500 mt-4">
        Kehadiran Anda menjadi dasar penerbitan sertifikat kegiatan ini.
    </p>
</div>
@endsection
