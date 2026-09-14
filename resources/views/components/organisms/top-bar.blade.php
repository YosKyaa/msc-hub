@props([
    'tone' => 'indigo',
])

@php
    $requester = session('requester');
@endphp

{{-- Hanya untuk layar sempit: di layar lebar navigasinya sudah menetap di
     sisi kiri, jadi bilah ini tidak perlu ada. --}}
<header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <button type="button" @click="menuOpen = true"
            :aria-expanded="menuOpen" aria-controls="menu-utama" aria-label="Buka menu"
            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            <span class="text-sm font-medium">Menu</span>
        </button>

        <a href="{{ route('landing') }}" class="flex min-w-0 items-center gap-2">
            <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-7 w-auto">
            <span class="truncate text-sm font-semibold text-gray-900">MSC Hub</span>
        </a>

        @if ($requester)
            <x-atoms.avatar :name="$requester['name']" size="sm" :tone="$tone" />
        @else
            <x-molecules.sign-in-button :tone="$tone" compact />
        @endif
    </div>
</header>
