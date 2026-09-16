@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'canonical' => null,
    'type' => 'website',
    // Halaman milik pengguna — dasbor, riwayat, verifikasi bertoken — tidak
    // pantas muncul di hasil pencarian siapa pun.
    'noindex' => false,
])

@php
    $seo = config('msc.seo');

    $judul = $title ? $title.' — '.$seo['organization']['short_name'] : $seo['site_name'];
    $deskripsi = \Illuminate\Support\Str::limit($description ?: $seo['description'], 300, '');
    $gambar = $image ?: asset($seo['image']);
    $alamat = $canonical ?: url()->current();
@endphp

<title>{{ $judul }}</title>
<meta name="description" content="{{ $deskripsi }}">
<link rel="canonical" href="{{ $alamat }}">

@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large">
@endif

{{-- Pratinjau tautan saat dibagikan di WhatsApp, LinkedIn, dan lainnya.
     Tanpa ini yang muncul hanya alamat mentah tanpa keterangan apa pun. --}}
<meta property="og:site_name" content="{{ $seo['site_name'] }}">
<meta property="og:locale" content="{{ $seo['locale'] }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $judul }}">
<meta property="og:description" content="{{ $deskripsi }}">
<meta property="og:url" content="{{ $alamat }}">
<meta property="og:image" content="{{ $gambar }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $judul }}">
<meta name="twitter:description" content="{{ $deskripsi }}">
<meta name="twitter:image" content="{{ $gambar }}">

{{ $slot ?? '' }}
