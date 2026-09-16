@php
    use App\Enums\AttendanceAction;

    $isCheckIn = $action === AttendanceAction::CHECK_IN;

    // Warna ditulis sebagai nilai jadi: dompdf tidak mengenal utility apa pun,
    // dan hanya membaca CSS yang benar-benar ada di dokumen ini.
    $aksen = $isCheckIn ? '#2563EB' : '#059669';

    $opensAt = $event->{$action->openColumn()};
    $closesAt = $event->{$action->closeColumn()};
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QR {{ $action->getLabel() }} — {{ $event->name }}</title>
    <style>
        /* dompdf mengabaikan margin pada @page, jadi jaraknya dipasang
           sebagai padding badan dokumen. */
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 48pt 40pt;
            font-family: helvetica, sans-serif;
            text-align: center;
            color: #111827;
        }

        .logo { height: 46pt; margin-bottom: 24pt; }

        .aksi {
            margin: 0;
            font-size: 30pt;
            font-weight: bold;
            letter-spacing: 6pt;
            text-transform: uppercase;
            color: {{ $aksen }};
        }

        .kegiatan { margin: 14pt 0 0; font-size: 24pt; font-weight: bold; line-height: 1.25; }
        .tanggal { margin: 10pt 0 0; font-size: 13pt; color: #4B5563; }
        .jendela { margin: 6pt 0 0; font-size: 12pt; color: #6B7280; }

        /* QR dibungkus tabel: dompdf tidak dapat diandalkan untuk memusatkan
           elemen blok berlebar tetap. */
        .kotak-qr { margin: 28pt auto; border-collapse: collapse; }

        .kotak-qr td {
            padding: 14pt;
            border: 5pt solid {{ $aksen }};
            border-radius: 12pt;
        }

        .qr { width: 300pt; height: 300pt; }

        .petunjuk { margin: 0; font-size: 13pt; color: #374151; }

        .tautan {
            margin: 10pt 0 0;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 9.5pt;
            color: #6B7280;
            word-wrap: break-word;
        }

        .kaki { margin-top: 34pt; font-size: 9pt; color: #9CA3AF; }
    </style>
</head>
<body>
    @if ($logoDataUri)
        {{-- Logo sebagai data URI, bukan URL: dompdf tidak selalu berhasil
             mengambil berkas lewat jaringan saat mencetak. --}}
        <img class="logo" src="{{ $logoDataUri }}" alt="Jakarta Global University">
    @endif

    <p class="aksi">{{ $action->getLabel() }}</p>
    <h1 class="kegiatan">{{ $event->name }}</h1>
    <p class="tanggal">{{ $event->event_date?->translatedFormat('l, d F Y') }}</p>

    @if ($opensAt || $closesAt)
        <p class="jendela">
            {{ $opensAt ? 'Dibuka '.$opensAt->translatedFormat('H:i') : 'Tanpa batas awal' }}
            &ndash;
            {{ $closesAt ? 'ditutup '.$closesAt->translatedFormat('H:i').' WIB' : 'tanpa batas akhir' }}
        </p>
    @endif

    <table class="kotak-qr">
        <tr>
            <td><img class="qr" src="{{ $qrDataUri }}" alt="QR {{ $action->getLabel() }}"></td>
        </tr>
    </table>

    <p class="petunjuk">Pindai QR di atas, lalu masuk dengan akun Google JGU Anda.</p>
    <p class="tautan">{{ $attendanceUrl }}</p>

    <p class="kaki">Media &amp; Strategic Communications &mdash; Jakarta Global University</p>
</body>
</html>
