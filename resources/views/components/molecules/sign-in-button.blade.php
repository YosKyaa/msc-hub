@props([
    'tone' => 'indigo',
    // Versi ringkas untuk bilah atas di layar sempit.
    'compact' => false,
])

{{-- Mengarah ke portal, bukan langsung ke Google: satu pintu masuk untuk
     seluruh sistem, dan pengunjung yang ternyata admin tidak tersesat. --}}
@php
    // Ditulis utuh, bukan dirangkai dari $tone: kelas yang dibentuk saat
    // berjalan hanya selamat selama halaman memakai Tailwind Play CDN.
    $cincin = $tone === 'blue' ? 'focus:ring-blue-300' : 'focus:ring-indigo-300';
@endphp

<a href="{{ route('login.portal', ['redirect' => request()->fullUrl()]) }}"
   {{ $attributes->class([
       'inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2',
       $cincin,
       'px-3 py-1.5 text-xs' => $compact,
       'px-4 py-2 text-sm' => ! $compact,
   ]) }}>
    <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8Z"/>
        <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1 .7-2.3 1.1-4 1.1-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24Z"/>
        <path fill="#FBBC05" d="M5.4 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.4a12 12 0 0 0 0 10.8l4-3.1Z"/>
        <path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.4 6.6l4 3.1C6.3 6.9 8.9 4.8 12 4.8Z"/>
    </svg>
    <span>Masuk</span>
</a>
