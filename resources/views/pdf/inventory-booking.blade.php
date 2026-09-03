@php
    use App\Enums\BookingStatus;

    $tone = match ($booking->status) {
        BookingStatus::APPROVED_HEAD, BookingStatus::RETURNED, BookingStatus::COMPLETED => 'success',
        BookingStatus::APPROVED_STAFF, BookingStatus::CHECKED_OUT => 'info',
        BookingStatus::PENDING => 'warning',
        BookingStatus::REJECTED => 'danger',
        default => 'neutral',
    };

    $items = $booking->items;
@endphp

<x-pdf.document
    title="Bukti Peminjaman Inventaris"
    :code="$booking->booking_code"
    :status="$booking->status->getLabel()"
    :status-tone="$tone"
    :meta="[
        'Jumlah item' => $items->count().' unit',
        'Mulai' => $booking->start_at->translatedFormat('d M Y, H:i'),
        'Selesai' => $booking->end_at->translatedFormat('d M Y, H:i'),
        'Unit' => $booking->unit,
    ]"
    :terms="[
        'Periksa kelengkapan dan kondisi alat saat pengambilan dan pengembalian.',
        'Alat dikembalikan tepat waktu dalam kondisi seperti saat dipinjam.',
        'Kerusakan atau kehilangan menjadi tanggung jawab peminjam.',
        'Bukti ini wajib ditunjukkan kepada petugas saat pengambilan alat.',
    ]"
    :signatories="[
        'Peminjam' => ['name' => $booking->requester_name, 'caption' => $booking->unit],
        'Staff MSC' => ['name' => $booking->staffApprover?->name, 'caption' => $booking->staff_approved_at?->translatedFormat('d M Y')],
        'Kepala MSC' => ['name' => $booking->headApprover?->name, 'caption' => $booking->head_approved_at?->translatedFormat('d M Y')],
    ]"
>

    <div class="section">
        <div class="section-title">Peminjam</div>
        <table class="info-table">
            <tr><td class="key">Nama</td><td class="val">{{ $booking->requester_name }}</td></tr>
            <tr><td class="key">Email</td><td class="val">{{ $booking->requester_email }}</td></tr>
            <tr><td class="key">Unit / Fakultas</td><td class="val">{{ $booking->unit ?: '—' }}</td></tr>
            <tr><td class="key">Tujuan peminjaman</td><td class="val">{{ $booking->purpose ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Jadwal Peminjaman</div>
        <table class="info-table">
            <tr><td class="key">Mulai</td><td class="val">{{ $booking->start_at->translatedFormat('l, d F Y, H:i') }} WIB</td></tr>
            <tr><td class="key">Selesai</td><td class="val">{{ $booking->end_at->translatedFormat('l, d F Y, H:i') }} WIB</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Daftar Item yang Dipinjam</div>

        @if($items->isEmpty())
            <p class="empty-note">Tidak ada item yang tercatat pada peminjaman ini.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="num">No</th>
                        <th>Kode</th>
                        <th>Nama Item</th>
                        <th>Kategori</th>
                        <th class="qty">Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td class="num">{{ $loop->iteration }}</td>
                            <td>{{ $item->code }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->category?->getLabel() ?: '—' }}</td>
                            <td class="qty">{{ $item->condition_status?->getLabel() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Total item dipinjam</td>
                        <td class="qty">{{ $items->count() }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    @if($booking->checked_out_at || $booking->returned_at)
        <div class="section">
            <div class="section-title">Serah Terima</div>
            <table class="info-table">
                @if($booking->checked_out_at)
                    <tr><td class="key">Diambil</td><td class="val">{{ $booking->checked_out_at->translatedFormat('d M Y, H:i') }} WIB</td></tr>
                    <tr><td class="key">Catatan pengambilan</td><td class="val">{{ $booking->checkout_note ?: '—' }}</td></tr>
                @endif
                @if($booking->returned_at)
                    <tr><td class="key">Dikembalikan</td><td class="val">{{ $booking->returned_at->translatedFormat('d M Y, H:i') }} WIB</td></tr>
                    <tr><td class="key">Catatan pengembalian</td><td class="val">{{ $booking->return_note ?: '—' }}</td></tr>
                @endif
            </table>
        </div>
    @endif

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
