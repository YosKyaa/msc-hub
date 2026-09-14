@props([
    'requester',
    // Kedua portal memakai route keluar yang sama; sebelumnya masing-masing
    // punya sendiri dan tujuan setelah keluar jadi berbeda-beda.
    'logoutRoute' => 'auth.google.logout',
    'tone' => 'indigo',
    // Arah membukanya. Di kaki sidebar menu harus naik: membuka ke bawah dari
    // sana berarti jatuh di luar layar, dan tombol keluarnya tidak pernah
    // terlihat sama sekali.
    'placement' => 'bottom',
])

@php
    $naik = $placement === 'top';

    $posisi = $naik
        ? 'bottom-full mb-2 left-0 right-0'
        : 'top-full mt-2 right-0 w-72';

    $asal = $naik ? 'origin-bottom' : 'origin-top-right';
@endphp

<div class="relative" x-data="{ accountMenuOpen: false }" @keydown.escape.window="accountMenuOpen = false">
    <button type="button" @click="accountMenuOpen = ! accountMenuOpen" :aria-expanded="accountMenuOpen"
        class="flex w-full max-w-full items-center gap-2 rounded-lg p-1.5 text-left transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
        aria-haspopup="menu" aria-label="Buka menu akun">
        <x-atoms.avatar :name="$requester['name']" size="sm" :tone="$tone" />
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-gray-800">{{ $requester['name'] }}</span>
            <span class="block text-xs text-gray-500">Akun saya</span>
        </span>
        <svg class="size-4 shrink-0 text-gray-400 transition" :class="accountMenuOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <div x-show="accountMenuOpen" x-cloak x-transition @click.outside="accountMenuOpen = false"
        class="absolute z-40 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg {{ $posisi }} {{ $asal }}" role="menu">
        <div class="flex items-center gap-3 border-b border-gray-100 p-4">
            <x-atoms.avatar :name="$requester['name']" :tone="$tone" />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900">{{ $requester['name'] }}</p>
                <p class="truncate text-xs text-gray-500">{{ $requester['email'] }}</p>
            </div>
        </div>

        <form action="{{ route($logoutRoute) }}" method="POST" class="p-2">
            @csrf
            <input type="hidden" name="redirect" value="{{ route('landing') }}">
            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50" role="menuitem">
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg>
                Keluar dari akun
            </button>
        </form>
    </div>
</div>
