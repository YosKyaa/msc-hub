@props([
    'title',
    'code',
    'status' => null,
    'statusTone' => 'neutral',
    'meta' => [],
    'signatories' => [],
    'terms' => [],
])

@php
    // Logo disematkan sebagai data URI karena Dompdf tidak selalu dapat
    // menembus path publik. Versi kecilnya di-cache oleh PdfLetterhead.
    $logo = \App\Support\PdfLetterhead::logoDataUri();

    $tones = [
        'success' => ['#ecfdf5', '#a7f3d0', '#047857'],
        'warning' => ['#fffbeb', '#fde68a', '#b45309'],
        'danger' => ['#fef2f2', '#fecaca', '#b91c1c'],
        'info' => ['#eff6ff', '#bfdbfe', '#1d4ed8'],
        'neutral' => ['#f8fafc', '#e2e8f0', '#475569'],
    ];
    [$toneBg, $toneBorder, $toneText] = $tones[$statusTone] ?? $tones['neutral'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $code }}</title>
    <style>
        @page { margin: 28px 34px 56px; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            line-height: 1.55;
            color: #1e293b;
        }

        /* ---------------------------------------------------------- kop surat */
        .letterhead { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; }
        .letterhead td { vertical-align: middle; }
        .letterhead .logo { width: 62px; }
        .letterhead .logo img { width: 54px; }
        .letterhead .institution { font-size: 14px; font-weight: bold; letter-spacing: .04em; color: #0f172a; }
        .letterhead .unit { font-size: 10px; color: #475569; letter-spacing: .06em; text-transform: uppercase; }
        .letterhead .address { font-size: 8.5px; color: #94a3b8; margin-top: 2px; }

        /* ------------------------------------------------------ judul dokumen */
        .doc-title { text-align: center; margin: 18px 0 4px; }
        .doc-title h1 { font-size: 13px; font-weight: bold; letter-spacing: .1em; text-transform: uppercase; color: #0f172a; }
        .doc-title .code { font-size: 11px; color: #475569; letter-spacing: .16em; margin-top: 3px; }

        .status-bar { margin: 12px 0 18px; text-align: center; }
        .status-badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 12px;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: .08em;
            text-transform: uppercase;
            background: {{ $toneBg }};
            border: 1px solid {{ $toneBorder }};
            color: {{ $toneText }};
        }

        /* --------------------------------------------------------------- meta */
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td {
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 9px;
            width: 25%;
        }
        .meta .label { color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; display: block; }
        .meta .value { color: #0f172a; font-weight: bold; font-size: 10px; }

        /* ------------------------------------------------------------ section */
        .section { margin-bottom: 16px; }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-table td.key { width: 30%; color: #64748b; }
        .info-table td.val { color: #0f172a; font-weight: bold; }

        /* -------------------------------------------------------- tabel data */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #475569;
            text-align: left;
        }
        .data-table td { border: 1px solid #e2e8f0; padding: 6px 8px; }
        .data-table .num { text-align: center; width: 34px; }
        .data-table .qty { text-align: center; width: 60px; }
        .data-table tfoot td { background: #f8fafc; font-weight: bold; }

        .empty-note { color: #94a3b8; font-style: italic; }

        /* ---------------------------------------------------------- ketentuan */
        .terms { border: 1px solid #e2e8f0; border-left: 3px solid #0f172a; padding: 10px 12px; }
        .terms .terms-title { font-weight: bold; font-size: 9.5px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .terms ol { margin-left: 14px; font-size: 9.5px; color: #475569; }

        /* ------------------------------------------------------ tanda tangan */
        .signatures { width: 100%; margin-top: 24px; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 6px; }
        .signatures .role { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .signatures .space { height: 52px; }
        .signatures .name { border-top: 1px solid #0f172a; padding-top: 4px; font-weight: bold; font-size: 9.5px; }
        .signatures .caption { font-size: 8px; color: #94a3b8; }

        /* ------------------------------------------------------------- footer */
        .footer {
            position: fixed;
            bottom: -34px;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8px;
            color: #94a3b8;
        }
        .footer td { width: 50%; }
        .footer .right { text-align: right; }
    </style>
</head>
<body>

<table class="letterhead">
    <tr>
        @if($logo)
            <td class="logo"><img src="{{ $logo }}" alt="Jakarta Global University"></td>
        @endif
        <td>
            <div class="institution">JAKARTA GLOBAL UNIVERSITY</div>
            <div class="unit">Media &amp; Strategic Communications</div>
            <div class="address">Jl. Boulevard Grand Depok City, Depok, Jawa Barat</div>
        </td>
    </tr>
</table>

<div class="doc-title">
    <h1>{{ $title }}</h1>
    <div class="code">{{ $code }}</div>
</div>

@if($status)
    <div class="status-bar"><span class="status-badge">{{ $status }}</span></div>
@endif

@if($meta !== [])
    <table class="meta">
        <tr>
            @foreach($meta as $label => $value)
                <td>
                    <span class="label">{{ $label }}</span>
                    <span class="value">{{ $value ?: '—' }}</span>
                </td>
            @endforeach
        </tr>
    </table>
@endif

{{ $slot }}

@if($terms !== [])
    <div class="section">
        <div class="terms">
            <div class="terms-title">Ketentuan</div>
            <ol>
                @foreach($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ol>
        </div>
    </div>
@endif

@if($signatories !== [])
    <table class="signatures">
        <tr>
            @foreach($signatories as $role => $signatory)
                <td>
                    <div class="role">{{ $role }}</div>
                    <div class="space"></div>
                    <div class="name">{{ $signatory['name'] ?: '(   ...................   )' }}</div>
                    <div class="caption">{{ $signatory['caption'] ?? '' }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endif

<table class="footer">
    <tr>
        <td>{{ $code }} &middot; dicetak {{ now()->translatedFormat('d F Y, H:i') }} WIB</td>
        <td class="right">Dokumen resmi MSC Hub — Jakarta Global University</td>
    </tr>
</table>

</body>
</html>
