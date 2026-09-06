<?php

namespace App\Http\Controllers;

use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use App\Support\BorrowingFormData;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formulir resmi FM/JGU/L.89 untuk booking ruangan maupun inventaris.
 *
 * Bawaannya dibuka sebagai pratinjau di dalam browser; berkas hanya terunduh
 * ketika diminta secara eksplisit, sehingga petugas dapat memeriksa isinya
 * lebih dulu tanpa menumpuk berkas di folder unduhan.
 */
class BorrowingFormController extends Controller
{
    public function room(Request $request, RoomBooking $roomBooking): Response
    {
        return $this->render(BorrowingFormData::fromRoomBooking($roomBooking), $request);
    }

    public function inventory(Request $request, InventoryBooking $inventoryBooking): Response
    {
        return $this->render(BorrowingFormData::fromInventoryBooking($inventoryBooking), $request);
    }

    private function render(BorrowingFormData $form, Request $request): Response
    {
        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper')->loadView('pdf.borrowing-form', ['form' => $form]);

        $this->stampPageNumbers($pdf);

        return $request->boolean('unduh')
            ? $pdf->download($form->fileName())
            : $pdf->stream($form->fileName());
    }

    /**
     * Daftar fasilitas yang panjang dapat memakai lebih dari satu lembar, jadi
     * tiap halaman diberi penomoran agar ketahuan bila ada lembar yang hilang
     * setelah dicetak.
     *
     * Digambar langsung pada kanvas karena hanya di situ Dompdf mengetahui
     * jumlah halaman akhir.
     */
    private function stampPageNumbers(PDF $pdf): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();

        if ($canvas->get_page_count() < 2) {
            return;
        }

        $canvas->page_text(
            x: $canvas->get_width() - 150,
            y: $canvas->get_height() - 100,
            text: 'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            font: $dompdf->getFontMetrics()->getFont('times', 'normal'),
            size: 8,
            color: [0.35, 0.35, 0.35],
        );
    }
}
