@props([
    'route',
    'pattern',
    'label',
    // Satu kalimat yang menjelaskan isinya. Nama menu saja belum tentu
    // dimengerti orang yang baru pertama membuka portal ini.
    'description' => null,
    'icon' => null,
    // Dipertahankan demi pemanggil lama; warnanya kini satu untuk seluruh
    // portal, mengikuti sistem desain beranda.
    'tone' => null,
])

@php
    $aktif = request()->routeIs($pattern);
@endphp

<a href="{{ route($route) }}"
   @if ($aktif) aria-current="page" @endif
   class="flex items-start gap-3 rounded-xl px-3 py-2.5 transition {{ $aktif ? 'bg-sun-soft text-sun-ink ring-1 ring-sun-deep/30' : 'text-ink/75 hover:bg-paper-2' }}">
    @if ($icon)
        <svg class="mt-0.5 size-5 shrink-0 {{ $aktif ? 'text-sun-ink' : 'text-ink/40' }}"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
        </svg>
    @endif

    <span class="min-w-0">
        <span class="block text-sm font-medium">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-xs leading-snug {{ $aktif ? 'text-sun-ink/80' : 'text-ink/55' }}">
                {{ $description }}
            </span>
        @endif
    </span>
</a>
