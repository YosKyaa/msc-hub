<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
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
