<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Peminjaman Inventaris - {{ $booking->booking_code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14px;
            font-weight: normal;
            color: #666;
        }
        .booking-code {
            text-align: center;
            margin: 15px 0;
        }
        .booking-code span {
            background: #f3f4f6;
            padding: 8px 20px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            background: #f3f4f6;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-left: 4px solid #d97706;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .info-table td:first-child {
            width: 35%;
            color: #666;
        }
        .info-table td:last-child {
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }
        .items-table th {
            background: #f3f4f6;
            font-weight: bold;
        }
        .items-table tr:nth-child(even) {
            background: #f9fafb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-checked_out { background: #dbeafe; color: #1e40af; }
        .status-returned { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
        .signature-area {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 10px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>JAKARTA GLOBAL UNIVERSITY</h1>
            <h2>Media & Strategic Communications</h2>
            <p style="margin-top: 10px; font-size: 14px; font-weight: bold;">BUKTI PEMINJAMAN INVENTARIS</p>
        </div>

        <div class="booking-code">
            <span>{{ $booking->booking_code }}</span>
        </div>

        <div class="section">
            <div class="section-title">Informasi Pemohon</div>
            <table class="info-table">
                <tr>
                    <td>Nama Pemohon</td>
                    <td>{{ $booking->requester_name }}</td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{ $booking->requester_email }}</td>
                </tr>
                <tr>
                    <td>Unit/Fakultas</td>
                    <td>{{ $booking->unit ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Tujuan Peminjaman</td>
                    <td>{{ $booking->purpose ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Jadwal Peminjaman</div>
            <table class="info-table">
                <tr>
                    <td>Waktu Mulai</td>
                    <td>{{ $booking->start_at->format('d F Y, H:i') }} WIB</td>
                </tr>
                <tr>
                    <td>Waktu Selesai</td>
                    <td>{{ $booking->end_at->format('d F Y, H:i') }} WIB</td>
                </tr>
                <tr>
                    <td>Durasi</td>
                    <td>{{ $booking->start_at->diffForHumans($booking->end_at, true) }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>
                        <span class="status-badge status-{{ strtolower($booking->status->value) }}">
                            {{ $booking->status->getLabel() }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Daftar Item yang Dipinjam</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th>Kode</th>
                        <th>Nama Item</th>
                        <th>Kategori</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->code }}</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category->getLabel() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($booking->status->value === 'checked_out' || $booking->status->value === 'returned')
        <div class="section">
            <div class="section-title">Informasi Check-out/Return</div>
            <table class="info-table">
                @if($booking->checked_out_at)
                <tr>
                    <td>Waktu Check-out</td>
                    <td>{{ $booking->checked_out_at->format('d F Y, H:i') }} WIB</td>
                </tr>
                <tr>
                    <td>Catatan Check-out</td>
                    <td>{{ $booking->checkout_note ?? '-' }}</td>
                </tr>
                @endif
                @if($booking->returned_at)
                <tr>
                    <td>Waktu Return</td>
                    <td>{{ $booking->returned_at->format('d F Y, H:i') }} WIB</td>
                </tr>
                <tr>
                    <td>Catatan Return</td>
                    <td>{{ $booking->return_note ?? '-' }}</td>
                </tr>
                @endif
            </table>
        </div>
        @endif

        <div class="signature-area">
            <div class="signature-box">
                <p>Pemohon</p>
                <div class="signature-line">{{ $booking->requester_name }}</div>
            </div>
            <div class="signature-box">
                <p>Staff MSC</p>
                <div class="signature-line">{{ $booking->staffApprover?->name ?? '________________' }}</div>
            </div>
            <div class="signature-box">
                <p>Kepala MSC</p>
                <div class="signature-line">{{ $booking->headApprover?->name ?? '________________' }}</div>
            </div>
        </div>

        <div class="footer">
            <p>Dicetak pada: {{ now()->format('d F Y, H:i') }} WIB</p>
            <p>Dokumen ini digenerate secara otomatis oleh sistem MSC Hub.</p>
        </div>
    </div>
</body>
</html>
