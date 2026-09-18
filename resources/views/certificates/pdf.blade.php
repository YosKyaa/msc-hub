{{--
    Latar sengaja digambar sebagai <img>, bukan CSS `background`.
    Dompdf gagal mem-parsing `url('data:...')` bertanda kutip tunggal dan
    membuang SELURUH aturan setelahnya, termasuk `.element { position:absolute }`,
    sehingga elemen menumpuk di kiri atas dan desain latar tidak tergambar.
    Lihat tests/Feature/CertificatePdfTest.php.

    Perataan dan tipografinya diputuskan App\Support\CertificateElement, sama
    persis dengan editor dan halaman verifikasi. Lihat
    tests/Feature/CertificateLayoutParityTest.php.
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
{{-- table/table-cell, bukan flexbox: hanya pasangan inilah yang dipahami
     dompdf dan peramban dengan hasil yang sama. --}}
.element { position:absolute; display:table; box-sizing:border-box; }
.element-content { display:table-cell; }
</style></head><body>
<div class="canvas">
@if ($backgroundDataUri)
    {{-- Latar sebagai <img>, bukan CSS: dompdf membuang seluruh aturan gaya
         setelah url('data:...') berkutip tunggal. --}}
    <img class="background" src="{{ $backgroundDataUri }}" alt="">
@endif
@foreach(\App\Support\CertificateElement::collect($template->elements) as $element)
    <div class="element" style="{{ $element->boxCss(
        $element->x().'px',
        $element->y().'px',
        $element->width().'px',
        $element->height().'px',
    ) }}">
        <div class="element-content" style="{{ $element->contentCss($element->fontSize().'px', $element->height().'px') }}">
            @if($element->isQr())
                <img src="{{ $qrDataUri }}" alt="QR verifikasi" style="width:{{ $element->width() }}px;height:{{ $element->height() }}px;">
            @else
                {{ $element->value($values) }}
            @endif
        </div>
    </div>
@endforeach
</div>
</body></html>
