@props([
    'template',
    'values' => [],
    'qrDataUri' => null,
    'muted' => false,
    'watermark' => null,
])

@php
    use App\Support\CertificateElement;
    use Illuminate\Support\Facades\Storage;

    $canvasWidth = max(1, (int) $template->canvas_width);
    $canvasHeight = max(1, (int) $template->canvas_height);

    // Semua ukuran dinyatakan relatif terhadap kanvas: posisi memakai persen dan
    // tipografi memakai cqw, sehingga pratinjau tetap sebangun dengan PDF pada
    // lebar layar apa pun tanpa perlu JavaScript.
    $percentX = fn ($value) => round(((float) $value / $canvasWidth) * 100, 4).'%';
    $percentY = fn ($value) => round(((float) $value / $canvasHeight) * 100, 4).'%';
    $containerWidth = fn ($value) => round(((float) $value / $canvasWidth) * 100, 4).'cqw';
@endphp

@once
    @push('styles')
        <style>
            .cert-preview { container-type: inline-size; }

            /* table/table-cell, sama seperti PDF: hanya pasangan inilah yang
               dipahami dompdf dan peramban dengan hasil yang sama. */
            .cert-preview-element { position: absolute; display: table; box-sizing: border-box; }
            .cert-preview-element > div { display: table-cell; }

            @foreach($template->fonts ?? [] as $fontPath)
                @font-face {
                    font-family: "{{ pathinfo($fontPath, PATHINFO_FILENAME) }}";
                    src: url("{{ Storage::disk('public')->url($fontPath) }}") format("truetype");
                    font-display: swap;
                }
            @endforeach
        </style>
    @endpush
@endonce

<div {{ $attributes->class(['cert-preview relative w-full overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200']) }}
     style="aspect-ratio: {{ $canvasWidth }} / {{ $canvasHeight }};">

    <img src="{{ Storage::disk('public')->url($template->background_path) }}"
         alt="Desain sertifikat"
         class="absolute inset-0 h-full w-full {{ $muted ? 'opacity-40 grayscale' : '' }}"
         loading="lazy">

    @foreach(CertificateElement::collect($template->elements) as $element)
        <div class="cert-preview-element {{ $muted ? 'opacity-40' : '' }}"
             style="{{ $element->boxCss(
                 $percentX($element->x()),
                 $percentY($element->y()),
                 $percentX($element->width()),
                 $percentY($element->height()),
             ) }}">
            {{-- Tinggi selnya relatif terhadap kotaknya, bukan kanvas: kotak
                 luarnyalah yang sudah diukur terhadap kanvas. --}}
            <div style="{{ $element->contentCss($containerWidth($element->fontSize()), '100%') }}">
                @if($element->isQr())
                    @if($qrDataUri)
                        <img src="{{ $qrDataUri }}" alt="QR verifikasi" style="width:100%;height:100%;">
                    @endif
                @else
                    {{ $element->value($values) }}
                @endif
            </div>
        </div>
    @endforeach

    @if($watermark)
        <div class="absolute inset-0 flex items-center justify-center">
            <span class="rotate-[-18deg] rounded-lg border-4 border-red-500/70 px-[4cqw] py-[1.5cqw]
                         text-[6cqw] font-black uppercase tracking-[0.2em] text-red-500/70">
                {{ $watermark }}
            </span>
        </div>
    @endif
</div>
