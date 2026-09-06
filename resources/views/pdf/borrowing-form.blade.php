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
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Peminjaman Ruangan / Fasilitas — {{ $form->bookingCode }}</title>
    <style>
        /* Dompdf mengabaikan marjin pada @page, jadi marjin cetak dipasang
           sebagai padding body. Kertas dibiarkan tanpa marjin supaya pita kaki
           surat dapat menempel penuh ke tepi bawah seperti lembar aslinya. */
        @page { margin: 0; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000;
            padding: 30pt 40pt 82pt;
        }

        .form-code { font-size: 9pt; }

        .logo { width: 96pt; margin: 6pt 0 2pt; }

        h1 {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 4pt 0 10pt;
        }

        /* ------------------------------------------------------ isian atas */
        .fields { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
        .fields td { padding: 4pt 0 2pt; vertical-align: bottom; }
        .fields .label { width: 26%; }
        .fields .value {
            width: 22%;
            border-bottom: 1px dotted #000;
            padding-left: 5pt;
            font-weight: bold;
        }
        .fields .gap { width: 2%; }

        /* -------------------------------------------------- daftar fasilitas */
        .facilities { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
        .facilities .caption { width: 26%; vertical-align: top; padding-top: 3pt; }
        .facilities .list { vertical-align: top; }

        /* Tinggi baris dipatok agar sebelas garis isian selalu muat dalam satu
           halaman, sekaligus mengisi lembar seperti formulir aslinya. */
        .facility {
            height: 19pt;
            border-bottom: 1px dotted #000;
            padding: 3pt 5pt 0;
        }
        .facility .name { font-weight: bold; }

        /* --------------------------------------------------- tanda tangan */
        .signatures { width: 100%; border-collapse: collapse; }
        .signatures th, .signatures td {
            border: 1px solid #000;
            padding: 4pt 5pt;
            text-align: center;
            font-size: 10pt;
        }
        .signatures .role { height: 30pt; font-weight: normal; vertical-align: top; }
        .signatures .space { height: 40pt; }
        .signatures .name { font-weight: bold; }

        .notice { margin-top: 8pt; font-weight: bold; text-align: center; }

        .legend { margin-top: 6pt; font-size: 9.5pt; }
        .legend p { margin-bottom: 1pt; }

        /* -------------------------------------------------------- kaki surat */
        .letterfoot { position: fixed; bottom: 0; left: 0; width: 100%; }
        .letterfoot img { width: 100%; }
        .meta { position: fixed; bottom: 70pt; left: 40pt; font-size: 7pt; color: #555; }
    </style>
</head>
<body>

<div class="form-code">{{ BorrowingFormData::FORM_CODE }}</div>

@if($logo)
    <img class="logo" src="{{ $logo }}" alt="Jakarta Global University">
@endif

<h1>Form Peminjaman Ruangan / Fasilitas Multimedia JGU</h1>

<table class="fields">
    <tr>
        <td class="label">Penanggung Jawab (Dosen)*</td>
        <td class="value">{{ $form->supervisorName }}</td>
        <td class="gap"></td>
        <td class="label">Acara / Kegiatan*</td>
        <td class="value">{{ $form->activity }}</td>
    </tr>
    <tr>
        <td class="label">Nama Fakultas / Organisasi*</td>
        <td class="value">{{ $form->unit }}</td>
        <td class="gap"></td>
        <td class="label">Jumlah Peserta*</td>
        <td class="value">{{ $form->attendees }}</td>
    </tr>
    <tr>
        <td class="label">Nama Peminjam*</td>
        <td class="value">{{ $form->requesterName }}</td>
        <td class="gap"></td>
        <td class="label">Hari dan Tgl Peminjaman*</td>
        <td class="value">{{ $form->borrowedOn() }}</td>
    </tr>
    <tr>
        <td class="label">No. HP Peminjam*</td>
        <td class="value">{{ $form->requesterPhone }}</td>
        <td class="gap"></td>
        <td class="label">Waktu*</td>
        <td class="value">{{ $form->timeRange() }}</td>
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
                        <span>— {{ $facility['detail'] }}</span>
                    @endif
                </div>
            @endforeach

            @for($i = 0; $i < $blankRows; $i++)
                <div class="facility">&nbsp;</div>
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
