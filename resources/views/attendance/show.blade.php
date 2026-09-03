@extends('layouts.public')

@section('title', $action->getLabel().' '.$event->name)

@php
    use App\Enums\AttendanceAction;

    $isCheckIn = $action === AttendanceAction::CHECK_IN;
    $accent = $isCheckIn ? 'blue' : 'emerald';
    $opensAt = $event->{$action->openColumn()};
    $closesAt = $event->{$action->closeColumn()};
    $checkedIn = $participation?->checked_in_at;
    $checkedOut = $participation?->checked_out_at;
    $alreadyDone = $isCheckIn ? $checkedIn : $checkedOut;
    $needsName = $requester !== null && $isCheckIn && $participant === null;
@endphp

@section('content')
<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
        {{-- Identitas kegiatan dan aksi. Warna sengaja dibedakan agar panitia
             langsung sadar bila QR yang terpasang bukan yang dimaksud. --}}
        <div class="bg-{{ $accent }}-600 text-white px-6 py-5">
            <p class="text-xs uppercase tracking-wide text-{{ $accent }}-100">{{ $action->getLabel() }} Kegiatan</p>
            <h1 class="text-xl font-semibold mt-1 leading-snug">{{ $event->name }}</h1>
            <p class="text-sm text-{{ $accent }}-100 mt-2">{{ $event->event_date->translatedFormat('l, d F Y') }}</p>
            @if($event->organizer)
                <p class="text-sm text-{{ $accent }}-100">{{ $event->organizer }}</p>
            @endif
        </div>

        <div class="px-6 py-6 space-y-5">
            @if($windowState === 'not_started')
                <x-atoms.alert title="{{ $action->getLabel() }} belum dibuka" :dismissible="false">
                    {{ $action->getLabel() }} dibuka pada {{ $opensAt->translatedFormat('l, d F Y H:i') }} WIB.
                </x-atoms.alert>
            @elseif($windowState === 'closed')
                <x-atoms.alert variant="warning" title="{{ $action->getLabel() }} sudah ditutup" :dismissible="false">
                    {{ $action->getLabel() }} ditutup pada {{ $closesAt->translatedFormat('l, d F Y H:i') }} WIB.
                    Hubungi panitia bila Anda hadir tetapi belum tercatat.
                </x-atoms.alert>
            @endif

            @if($opensAt || $closesAt)
                <dl class="text-sm text-gray-600 bg-gray-50 rounded-xl px-4 py-3 space-y-1">
                    @if($opensAt)
                        <div class="flex justify-between gap-4">
                            <dt>{{ $action->getLabel() }} dibuka</dt>
                            <dd class="font-medium text-gray-900 text-right">{{ $opensAt->translatedFormat('d M Y H:i') }} WIB</dd>
                        </div>
                    @endif
                    @if($closesAt)
                        <div class="flex justify-between gap-4">
                            <dt>{{ $action->getLabel() }} ditutup</dt>
                            <dd class="font-medium text-gray-900 text-right">{{ $closesAt->translatedFormat('d M Y H:i') }} WIB</dd>
                        </div>
                    @endif
                </dl>
            @endif

            @if($requester === null)
                <div class="text-center space-y-4">
                    <p class="text-sm text-gray-600">
                        Masuk dengan akun Google JGU Anda untuk mencatat {{ strtolower($action->getLabel()) }}.
                    </p>
                    <a href="{{ $loginUrl }}"
                       class="w-full inline-flex items-center justify-center gap-2 bg-{{ $accent }}-600 hover:bg-{{ $accent }}-700 text-white font-semibold rounded-xl px-6 py-4 text-base">
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
                        <input type="hidden" name="redirect" value="{{ $event->attendanceUrl($action) }}">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-700 underline">
                            Bukan Anda?
                        </button>
                    </form>
                </div>

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

                @if($alreadyDone)
                    <x-atoms.alert variant="success" title="{{ $action->getLabel() }} sudah tercatat" :dismissible="false">
                        {{ $isCheckIn
                            ? 'Kehadiran Anda sudah tercatat. Jangan lupa check-out saat kegiatan selesai.'
                            : 'Terima kasih, kehadiran Anda sudah lengkap.' }}
                    </x-atoms.alert>
                @elseif(! $isCheckIn && ! $checkedIn)
                    <x-atoms.alert variant="warning" title="Anda belum check-in" :dismissible="false">
                        Check-out hanya dapat dicatat setelah Anda check-in. Hubungi panitia bila Anda
                        hadir sejak awal tetapi kehadiran Anda belum terekam.
                    </x-atoms.alert>
                @elseif($windowState === 'open')
                    <form action="{{ route('attendance.store', ['action' => $action->value, 'token' => $event->attendanceToken($action)]) }}"
                          method="POST" class="space-y-4">
                        @csrf

                        @if($needsName)
                            {{-- Nama hanya ditanyakan sekali. Setelah tersimpan, koreksi
                                 menjadi wewenang panitia agar tidak bisa diubah sepihak. --}}
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-900">
                                    Nama lengkap
                                </label>
                                <p class="text-xs text-gray-500 mt-1 mb-2">
                                    Tulis sesuai yang ingin dicetak di sertifikat, termasuk gelar bila ada.
                                    Nama ini hanya dapat diisi sekali.
                                </p>
                                <input type="text" name="full_name" id="full_name" required
                                       maxlength="150"
                                       value="{{ old('full_name', $requester['name'] ?? '') }}"
                                       placeholder="Contoh: Budi Santoso, S.Kom."
                                       class="w-full rounded-xl border-gray-300 px-4 py-3 text-base focus:border-{{ $accent }}-500 focus:ring-{{ $accent }}-500 @error('full_name') border-red-500 @enderror">
                                @error('full_name')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif($isCheckIn && $participant)
                            <div class="rounded-xl border bg-gray-50 px-4 py-3">
                                <p class="text-xs text-gray-500">Nama yang akan dicetak di sertifikat</p>
                                <p class="text-sm font-medium text-gray-900">{{ $participant->name }}</p>
                                <p class="text-xs text-gray-500 mt-1">Nama salah? Hubungi panitia untuk mengoreksinya.</p>
                            </div>
                        @endif

                        <button type="submit"
                                class="w-full bg-{{ $accent }}-600 hover:bg-{{ $accent }}-700 active:bg-{{ $accent }}-800 text-white font-semibold rounded-xl px-6 py-5 text-lg">
                            {{ $action->getLabel() }} Sekarang
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 text-center">{{ $action->getInstruction() }}</p>
                @endif
            @endif
        </div>
    </div>

    <p class="text-center text-xs text-gray-500 mt-4">
        Kehadiran Anda menjadi dasar penerbitan sertifikat kegiatan ini.
    </p>
</div>
@endsection
