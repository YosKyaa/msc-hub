@php
    use App\Enums\BookingStatus;

    $tone = match ($booking->status) {
        BookingStatus::APPROVED_HEAD, BookingStatus::COMPLETED => 'success',
        BookingStatus::APPROVED_STAFF => 'info',
        BookingStatus::PENDING => 'warning',
        BookingStatus::REJECTED => 'danger',
        default => 'neutral',
    };

    $items = $booking->inventoryItems;
    $durationHours = round($booking->duration_hours, 1);
@endphp

<x-pdf.document
    title="Bukti Booking Ruangan"
    :code="$booking->booking_code"
    :status="$booking->status->getLabel()"
    :status-tone="$tone"
    :meta="[
        'Ruangan' => $booking->room->name,
        'Tanggal' => $booking->start_at->translatedFormat('d M Y'),
        'Waktu' => $booking->start_at->format('H:i').'–'.$booking->end_at->format('H:i').' WIB',
        'Peserta' => ($booking->attendees ?? '—').' orang',
    ]"
    :terms="[
        'Hadir 15 menit sebelum jadwal dan konfirmasi kepada petugas MSC.',
        'Peralatan yang dipinjam dikembalikan lengkap segera setelah kegiatan selesai.',
        'Jaga kebersihan ruangan, matikan AC dan lampu setelah digunakan.',
        'Kerusakan atau kehilangan fasilitas menjadi tanggung jawab pemohon.',
    ]"
    :signatories="[
        'Pemohon' => ['name' => $booking->requester_name, 'caption' => $booking->unit],
        'Staff MSC' => ['name' => $booking->staffApprover?->name, 'caption' => $booking->staff_approved_at?->translatedFormat('d M Y')],
        'Kepala MSC' => ['name' => $booking->headApprover?->name, 'caption' => $booking->head_approved_at?->translatedFormat('d M Y')],
    ]"
>

    <div class="section">
        <div class="section-title">Pemohon</div>
        <table class="info-table">
            <tr><td class="key">Nama</td><td class="val">{{ $booking->requester_name }}</td></tr>
            <tr><td class="key">Email</td><td class="val">{{ $booking->requester_email }}</td></tr>
            <tr><td class="key">Unit / Fakultas</td><td class="val">{{ $booking->unit ?: '—' }}</td></tr>
            <tr><td class="key">Keperluan</td><td class="val">{{ $booking->purpose ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Ruangan &amp; Jadwal</div>
        <table class="info-table">
            <tr><td class="key">Ruangan</td><td class="val">{{ $booking->room->name }}</td></tr>
            <tr><td class="key">Lokasi</td><td class="val">{{ $booking->room->location ?: '—' }}</td></tr>
            @if($booking->room->capacity)
                <tr><td class="key">Kapasitas</td><td class="val">{{ $booking->room->capacity }} orang</td></tr>
            @endif
            <tr><td class="key">Hari, tanggal</td><td class="val">{{ $booking->start_at->translatedFormat('l, d F Y') }}</td></tr>
            <tr>
                <td class="key">Waktu</td>
                <td class="val">
                    {{ $booking->start_at->format('H:i') }} – {{ $booking->end_at->format('H:i') }} WIB
                    ({{ $durationHours == (int) $durationHours ? (int) $durationHours : $durationHours }} jam)
                </td>
            </tr>
        </table>
    </div>

    {{-- Daftar peralatan selalu dicetak, termasuk saat kosong, agar petugas
         tahu bedanya "tidak meminjam" dengan "data belum terisi". --}}
    <div class="section">
        <div class="section-title">Peralatan Multimedia yang Dipinjam</div>

        @if($items->isEmpty())
            <p class="empty-note">Pemohon tidak meminjam peralatan apa pun untuk booking ini.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="num">No</th>
                        <th>Kode</th>
                        <th>Nama Peralatan</th>
                        <th class="qty">Jumlah</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td class="num">{{ $loop->iteration }}</td>
                            <td>{{ $item->code }}</td>
                            <td>{{ $item->name }}</td>
                            <td class="qty">{{ $item->pivot->quantity }}</td>
                            <td>{{ $item->pivot->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total {{ $items->count() }} jenis peralatan</td>
                        <td class="qty">{{ $items->sum(fn ($item) => $item->pivot->quantity) }}</td>
                        <td>unit</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    @if($booking->staff_approved_at || $booking->head_approved_at || $booking->rejected_at)
        <div class="section">
            <div class="section-title">Riwayat Persetujuan</div>
            <table class="info-table">
                @if($booking->staff_approved_at)
                    <tr>
                        <td class="key">Disetujui Staff</td>
                        <td class="val">{{ $booking->staffApprover?->name ?: '—' }} · {{ $booking->staff_approved_at->translatedFormat('d M Y, H:i') }} WIB</td>
                    </tr>
                @endif
                @if($booking->head_approved_at)
                    <tr>
                        <td class="key">Disetujui Kepala MSC</td>
                        <td class="val">{{ $booking->headApprover?->name ?: '—' }} · {{ $booking->head_approved_at->translatedFormat('d M Y, H:i') }} WIB</td>
                    </tr>
                @endif
                @if($booking->rejected_at)
                    <tr>
                        <td class="key">Ditolak</td>
                        <td class="val">{{ $booking->rejectedByUser?->name ?: '—' }} · {{ $booking->rejected_at->translatedFormat('d M Y, H:i') }} WIB</td>
                    </tr>
                    <tr><td class="key">Alasan</td><td class="val">{{ $booking->reject_reason ?: '—' }}</td></tr>
                @endif
            </table>
        </div>
    @endif

</x-pdf.document>
