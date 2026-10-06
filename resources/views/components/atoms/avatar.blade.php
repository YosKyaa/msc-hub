@props([
    'name',
    'size' => 'md',
    // Dipertahankan demi pemanggil lama; warnanya kini satu untuk seluruh
    // portal, mengikuti sistem desain beranda.
    'tone' => null,
])

@php
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
    $sizes = $size === 'sm' ? 'size-8 text-xs' : 'size-10 text-sm';
@endphp

<span role="img" aria-label="Avatar {{ $name }}"
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full bg-sun-tint font-semibold text-sun-ink ring-1 ring-sun-deep/30',
        $sizes,
    ]) }}>
    {{ $initial }}
</span>
