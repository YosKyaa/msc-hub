# MSC Hub — Jakarta Global University
#
# Halaman publik boleh dirayapi. Yang dilarang di bawah ini menuntut login
# atau memuat data milik seseorang, sehingga tidak pantas muncul di hasil
# pencarian siapa pun.

User-agent: *
Allow: /

@foreach ($tertutup as $jalur)
Disallow: {{ $jalur }}
@endforeach

Sitemap: {{ route('sitemap') }}
