@props([
    'variant' => 'default',
    'title' => null,
    'dismissible' => true,
])

@php
    $styles = match ($variant) {
        'success' => [
            'container' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
            'icon' => 'text-emerald-600',
        ],
        'destructive' => [
            'container' => 'border-red-200 bg-red-50 text-red-950',
            'icon' => 'text-red-600',
        ],
        'warning' => [
            'container' => 'border-amber-200 bg-amber-50 text-amber-950',
            'icon' => 'text-amber-600',
        ],
        default => [
            'container' => 'border-slate-200 bg-white text-slate-950',
            'icon' => 'text-slate-600',
        ],
    };
@endphp

<div
    role="{{ $variant === 'destructive' ? 'alert' : 'status' }}"
    aria-live="{{ $variant === 'destructive' ? 'assertive' : 'polite' }}"
    {{ $attributes->class(['relative w-full rounded-lg border px-4 py-3 shadow-sm', $styles['container']]) }}
    @if($dismissible) x-data="{ visible: true }" x-show="visible" x-transition @endif
>
    <div class="flex items-start gap-3">
        <div class="mt-0.5 shrink-0 {{ $styles['icon'] }}" aria-hidden="true">
            @if($variant === 'success')
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
            @elseif($variant === 'destructive')
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
            @else
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            @if($title)
                <h2 class="mb-1 text-sm font-semibold leading-none">{{ $title }}</h2>
            @endif
            <div class="text-sm leading-relaxed [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">
                {{ $slot }}
            </div>
        </div>

        @if($dismissible)
            <button type="button" @click="visible = false" class="-m-1 rounded-md p-1 opacity-60 transition hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current" aria-label="Tutup notifikasi">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 6-12 12M6 6l12 12"/></svg>
            </button>
        @endif
    </div>
</div>
