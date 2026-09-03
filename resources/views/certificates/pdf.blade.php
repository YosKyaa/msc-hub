{{--
    Latar sengaja digambar sebagai <img>, bukan CSS `background`.
    Dompdf gagal mem-parsing `url('data:...')` bertanda kutip tunggal dan
    membuang SELURUH aturan setelahnya, termasuk `.element { position:absolute }`,
    sehingga elemen menumpuk di kiri atas dan desain latar tidak tergambar.
    Lihat tests/Feature/CertificatePdfTest.php.
--}}
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
@page { margin: 0; }
html, body { margin:0; padding:0; width:{{ $template->canvas_width }}px; height:{{ $template->canvas_height }}px; overflow:hidden; }
@foreach($template->fonts ?? [] as $fontPath)
@font-face { font-family:"{{ pathinfo($fontPath, PATHINFO_FILENAME) }}"; src:url("file:///{{ str_replace('\\', '/', \Illuminate\Support\Facades\Storage::disk('public')->path($fontPath)) }}") format("truetype"); font-weight:normal; }
@endforeach
.canvas { position:relative; width:{{ $template->canvas_width }}px; height:{{ $template->canvas_height }}px; }
.background { position:absolute; left:0; top:0; width:{{ $template->canvas_width }}px; height:{{ $template->canvas_height }}px; }
.element { position:absolute; box-sizing:border-box; white-space:normal; }
</style></head><body>
<div class="canvas">
<img class="background" src="{{ $backgroundDataUri }}" alt="">
@foreach($template->elements ?? [] as $element)
    @php
        $variable = $element['variable'] ?? '';
        $value = $variable === 'qr_code' ? null : ($values[$variable] ?? ($element['text'] ?? ''));
    @endphp
    <div class="element" style="left:{{ $element['x'] ?? 0 }}px;top:{{ $element['y'] ?? 0 }}px;width:{{ $element['width'] ?? 300 }}px;height:{{ $element['height'] ?? 60 }}px;text-align:{{ $element['align'] ?? 'center' }};font-family:'{{ $element['font_family'] ?? 'DejaVu Sans' }}';font-size:{{ $element['font_size'] ?? 28 }}px;font-weight:{{ $element['font_weight'] ?? 400 }};color:{{ $element['color'] ?? '#111827' }};line-height:1.2;">
        @if($variable === 'qr_code')
            <img src="{{ $qrDataUri }}" alt="QR verifikasi" style="width:100%;height:100%;">
        @else
            {{ $value }}
        @endif
    </div>
@endforeach
</div>
</body></html>
