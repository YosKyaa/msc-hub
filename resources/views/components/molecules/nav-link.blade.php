@props([
    'route',
    'pattern',
    'label',
    // Satu kalimat yang menjelaskan isinya. Nama menu saja belum tentu
    // dimengerti orang yang baru pertama membuka portal ini.
    'description' => null,
    'icon' => null,
    'tone' => 'indigo',
])

@php
    $aktif = request()->routeIs($pattern);

    // Ditulis utuh, bukan dirangkai dari $tone: kelas yang dibentuk saat
    // berjalan hanya selamat selama halaman memakai Tailwind Play CDN.
    $sorot = $tone === 'blue'
        ? 'bg-blue-50 text-blue-700'
        : 'bg-indigo-50 text-indigo-700';

    $ikonAktif = $tone === 'blue' ? 'text-blue-600' : 'text-indigo-600';
@endphp

<a href="{{ route($route) }}"
   @if ($aktif) aria-current="page" @endif
   class="flex items-start gap-3 rounded-xl px-3 py-2.5 transition {{ $aktif ? $sorot : 'text-gray-700 hover:bg-gray-50' }}">
    @if ($icon)
        <svg class="mt-0.5 size-5 shrink-0 {{ $aktif ? $ikonAktif : 'text-gray-400' }}"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
        </svg>
    @endif

    <span class="min-w-0">
        <span class="block text-sm font-medium">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-xs leading-snug {{ $aktif ? 'text-gray-600' : 'text-gray-500' }}">
                {{ $description }}
            </span>
        @endif
    </span>
</a>
