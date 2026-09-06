@props(['name', 'size' => 'md', 'tone' => 'indigo'])

@php
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
    $sizes = $size === 'sm' ? 'size-8 text-xs' : 'size-10 text-sm';
    $tones = $tone === 'blue'
        ? 'bg-blue-100 text-blue-700 ring-blue-200'
        : 'bg-indigo-100 text-indigo-700 ring-indigo-200';
@endphp

<span role="img" aria-label="Avatar {{ $name }}"
    {{ $attributes->class(["inline-flex shrink-0 items-center justify-center rounded-full font-semibold ring-1", $sizes, $tones]) }}>
    {{ $initial }}
</span>
