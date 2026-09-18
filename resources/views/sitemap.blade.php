{{--
    Deklarasi XML tidak bisa ditulis apa adanya: PHP membaca `<?` sebagai
    pembuka tag, jadi ia harus keluar lewat echo. Penutupnya pun sengaja
    dipenggal menjadi '?' . '>' — beberapa pengurai (termasuk yang dipakai
    editor) mencari `?>` tanpa memperhatikan bahwa ia berada di dalam string,
    lalu menganggap mode PHP berakhir di situ dan salah membaca seluruh sisa
    berkas ini.
--}}
<?php echo '<?xml version="1.0" encoding="UTF-8"?'.'>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($halaman as $item)
    <url>
        <loc>{{ $item['loc'] }}</loc>
        <lastmod>{{ $item['lastmod'] ?? $sekarang }}</lastmod>
        <changefreq>{{ $item['changefreq'] }}</changefreq>
        <priority>{{ $item['priority'] }}</priority>
    </url>
@endforeach
</urlset>
