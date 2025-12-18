<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Booking Ruangan - {{ $booking->booking_code }}</title>
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
            border-left: 4px solid #7c3aed;
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
        .room-box {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .room-box h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .room-box p {
            color: #666;
            font-size: 11px;
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
        .status-approved_staff { background: #dbeafe; color: #1e40af; }
        .status-approved_head { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #d1fae5; color: #065f46; }
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
        .notice-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 12px;
            margin-top: 20px;
        }
        .notice-box p {
            font-size: 11px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>JAKARTA GLOBAL UNIVERSITY</h1>
            <h2>Media & Strategic Communications</h2>
            <p style="margin-top: 10px; font-size: 14px; font-weight: bold;">BUKTI BOOKING RUANGAN</p>
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
                    <td>Jumlah Peserta</td>
                    <td>{{ $booking->attendees ?? '-' }} orang</td>
                </tr>
                <tr>
                    <td>Keperluan</td>
                    <td>{{ $booking->purpose ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Informasi Ruangan</div>
            <div class="room-box">
                <h3>{{ $booking->room->name }}</h3>
                <p>{{ $booking->room->location ?? 'Lokasi tidak tersedia' }}</p>
                @if($booking->room->capacity)
                <p>Kapasitas: {{ $booking->room->capacity }} orang</p>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Jadwal Booking</div>
            <table class="info-table">
                <tr>
                    <td>Tanggal</td>
                    <td>{{ $booking->start_at->format('l, d F Y') }}</td>
                </tr>
                <tr>
                    <td>Waktu Mulai</td>
                    <td>{{ $booking->start_at->format('H:i') }} WIB</td>
                </tr>
                <tr>
                    <td>Waktu Selesai</td>
                    <td>{{ $booking->end_at->format('H:i') }} WIB</td>
                </tr>
                <tr>
                    <td>Durasi</td>
                    <td>{{ $booking->start_at->diffForHumans($booking->end_at, true) }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>
                        <span class="status-badge status-{{ strtolower(str_replace('_', '_', $booking->status->value)) }}">
                            {{ $booking->status->getLabel() }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        @if($booking->inventoryItems->count() > 0)
        <div class="section">
            <div class="section-title">Peralatan Multimedia yang Dipinjam</div>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background: #f3f4f6;">
                        <th style="border: 1px solid #e5e7eb; padding: 8px; text-align: left;">Kode</th>
                        <th style="border: 1px solid #e5e7eb; padding: 8px; text-align: left;">Nama Peralatan</th>
                        <th style="border: 1px solid #e5e7eb; padding: 8px; text-align: center;">Jumlah</th>
                        <th style="border: 1px solid #e5e7eb; padding: 8px; text-align: left;">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->inventoryItems as $item)
                    <tr>
                        <td style="border: 1px solid #e5e7eb; padding: 8px;">{{ $item->code }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px;">{{ $item->name }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px; text-align: center;">{{ $item->pivot->quantity }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px;">{{ $item->pivot->notes ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($booking->staff_approved_at || $booking->head_approved_at)
        <div class="section">
            <div class="section-title">Riwayat Approval</div>
            <table class="info-table">
                @if($booking->staff_approved_at)
                <tr>
                    <td>Approval Staff</td>
                    <td>{{ $booking->staffApprover?->name }} - {{ $booking->staff_approved_at->format('d M Y, H:i') }}</td>
                </tr>
                @endif
                @if($booking->head_approved_at)
                <tr>
                    <td>Approval Head</td>
                    <td>{{ $booking->headApprover?->name }} - {{ $booking->head_approved_at->format('d M Y, H:i') }}</td>
                </tr>
                @endif
            </table>
        </div>
        @endif

        <div class="notice-box">
            <p><strong>Ketentuan Penggunaan Ruangan:</strong></p>
            <p>1. Harap datang 15 menit sebelum waktu yang dijadwalkan</p>
            <p>2. Jaga kebersihan dan kerapian ruangan</p>
            <p>3. Matikan AC dan lampu setelah selesai menggunakan</p>
            <p>4. Laporkan jika ada kerusakan fasilitas</p>
        </div>

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
