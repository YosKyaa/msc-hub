@php
    use App\Support\BorrowingFormData;
    use App\Support\PdfLetterhead;

    // Formulir resmi menyediakan sebelas baris fasilitas; barisan sisa tetap
    // dicetak sebagai garis titik-titik agar dapat ditambah dengan tangan.
    $rows = 11;
    $facilities = $form->facilities->take($rows);
    $blankRows = max(0, $rows - $facilities->count());

    $logo = PdfLetterhead::logoDataUri();
    $footer = PdfLetterhead::footerDataUri();

    $line = fn (?string $value) => filled($value) ? e($value) : '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Peminjaman Ruangan / Fasilitas — {{ $form->bookingCode }}</title>
    <style>
        @page { margin: 26px 40px 92px; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            line-height: 1.45;
            color: #000;
        }

        .form-code { font-size: 9px; margin-bottom: 6px; }

        .head { width: 100%; margin-bottom: 4px; }
        .head td { vertical-align: middle; }
        .head .logo { width: 130px; }
        .head .logo img { width: 120px; }

        h1 {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 10px 0 14px;
        }

        /* ------------------------------------------------------ isian atas */
        .fields { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .fields td { padding: 3px 0; vertical-align: bottom; font-size: 9.5px; }
        .fields .label { width: 24%; }
        .fields .value {
            width: 26%;
            border-bottom: 1px dotted #000;
            padding-left: 4px;
            font-weight: bold;
        }
        .fields .gap { width: 4%; }

        /* -------------------------------------------------- daftar fasilitas */
        .facilities { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .facilities .caption { width: 24%; vertical-align: top; padding-top: 2px; }
        .facilities .list { vertical-align: top; }
        .facility {
            border-bottom: 1px dotted #000;
            padding: 2px 4px 1px;
            min-height: 13px;
        }
        .facility .name { font-weight: bold; }
        .facility .detail { color: #333; }
        /* Baris sisa hanya menyediakan garis isian, tanpa karakter yang ikut
           tersalin saat teks PDF disorot. */
        .facility.blank { height: 13px; }

        /* --------------------------------------------------- tanda tangan */
        .signatures { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .signatures th, .signatures td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            font-size: 8.5px;
        }
        .signatures th { font-weight: bold; }
        .signatures .role { height: 46px; font-weight: normal; vertical-align: top; }
        .signatures .space { height: 58px; vertical-align: bottom; }
        .signatures .name { font-weight: bold; font-size: 9px; }

        .notice { margin-top: 10px; font-size: 9px; font-weight: bold; text-align: center; }
        .legend { margin-top: 8px; font-size: 8.5px; }
        .legend p { margin-bottom: 1px; }

        /* -------------------------------------------------------- kaki surat */
        .letterfoot { position: fixed; bottom: -74px; left: 0; right: 0; }
        .letterfoot img { width: 100%; }
        .meta { position: fixed; bottom: -86px; left: 0; right: 0; font-size: 7px; color: #666; }
    </style>
</head>
<body>

<div class="form-code">{{ BorrowingFormData::FORM_CODE }}</div>

<table class="head">
    <tr>
        @if($logo)
            <td class="logo"><img src="{{ $logo }}" alt="Jakarta Global University"></td>
        @endif
        <td></td>
    </tr>
</table>

<h1>Form Peminjaman Ruangan / Fasilitas Multimedia JGU</h1>

<table class="fields">
    <tr>
        <td class="label">Penanggung Jawab (Dosen)*</td>
        <td class="value">{!! $line($form->supervisorName) !!}</td>
        <td class="gap"></td>
        <td class="label">Acara / Kegiatan*</td>
        <td class="value">{!! $line($form->activity) !!}</td>
    </tr>
    <tr>
        <td class="label">Nama Fakultas / Organisasi*</td>
        <td class="value">{!! $line($form->unit) !!}</td>
        <td class="gap"></td>
        <td class="label">Jumlah Peserta*</td>
        <td class="value">{!! $line($form->attendees) !!}</td>
    </tr>
    <tr>
        <td class="label">Nama Peminjam*</td>
        <td class="value">{!! $line($form->requesterName) !!}</td>
        <td class="gap"></td>
        <td class="label">Hari dan Tgl Peminjaman*</td>
        <td class="value">{!! $line($form->borrowedOn()) !!}</td>
    </tr>
    <tr>
        <td class="label">No. HP Peminjam*</td>
        <td class="value">{!! $line($form->requesterPhone) !!}</td>
        <td class="gap"></td>
        <td class="label">Waktu*</td>
        <td class="value">{!! $line($form->timeRange()) !!}</td>
    </tr>
</table>

<table class="facilities">
    <tr>
        <td class="caption">Daftar Fasilitas yang Dipinjam&nbsp;:</td>
        <td class="list">
            @foreach($facilities as $facility)
                <div class="facility">
                    <span class="name">{{ $facility['name'] }}</span>
                    @if($facility['detail'])
                        <span class="detail">— {{ $facility['detail'] }}</span>
                    @endif
                </div>
            @endforeach

            @for($i = 0; $i < $blankRows; $i++)
                <div class="facility blank">&nbsp;</div>
            @endfor
        </td>
    </tr>
</table>

<table class="signatures">
    <tr>
        <th colspan="3">Rekomendasi</th>
        <th>Peminjam**</th>
    </tr>
    <tr>
        <td class="role">Kepala<br>Media &amp; Strategic<br>Communication</td>
        <td class="role">Staff<br>Media &amp; Strategic<br>Communication</td>
        <td class="role">Penanggung Jawab<br>Kegiatan<br>(Dosen)</td>
        <td class="role">&nbsp;</td>
    </tr>
    <tr>
        <td class="space"></td>
        <td class="space"></td>
        <td class="space"></td>
        <td class="space"></td>
    </tr>
    <tr>
        <td class="name">{{ $form->headApprover ?: '(………………………)' }}</td>
        <td class="name">{{ $form->staffApprover ?: '(………………………)' }}</td>
        <td class="name">{{ $form->supervisorName ?: '(………………………)' }}</td>
        <td class="name">{{ $form->requesterName }}</td>
    </tr>
</table>

<p class="notice">Form ini hanya berlaku untuk 1 (Satu) acara dalam 1 (Satu) hari</p>

<div class="legend">
    <p><strong>Keterangan:</strong></p>
    <p>* wajib diisi oleh Peminjam</p>
    <p>** isikan jabatan atau posisi peminjam dalam acara di kolom bawah Peminjam</p>
</div>

<div class="meta">{{ $form->bookingCode }} · dicetak {{ now()->locale('id')->translatedFormat('d F Y, H:i') }} WIB</div>

@if($footer)
    <div class="letterfoot"><img src="{{ $footer }}" alt=""></div>
@endif

</body>
</html>
