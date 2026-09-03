@props([
    'template',
    'values' => [],
    'qrDataUri' => null,
    'muted' => false,
    'watermark' => null,
])

@php
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

    @foreach($template->elements ?? [] as $element)
        @php
            $variable = $element['variable'] ?? '';
            $value = $variable === 'qr_code' ? null : ($values[$variable] ?? ($element['text'] ?? ''));
        @endphp

        <div class="absolute {{ $muted ? 'opacity-40' : '' }}"
             style="left:{{ $percentX($element['x'] ?? 0) }};
                    top:{{ $percentY($element['y'] ?? 0) }};
                    width:{{ $percentX($element['width'] ?? 300) }};
                    height:{{ $percentY($element['height'] ?? 60) }};
                    text-align:{{ $element['align'] ?? 'center' }};
                    font-family:'{{ $element['font_family'] ?? 'DejaVu Sans' }}', ui-sans-serif, system-ui, sans-serif;
                    font-size:{{ $containerWidth($element['font_size'] ?? 28) }};
                    font-weight:{{ $element['font_weight'] ?? 400 }};
                    color:{{ $element['color'] ?? '#111827' }};
                    line-height:1.2;">
            @if($variable === 'qr_code')
                @if($qrDataUri)
                    <img src="{{ $qrDataUri }}" alt="QR verifikasi" class="h-full w-full">
                @endif
            @else
                {{ $value }}
            @endif
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
