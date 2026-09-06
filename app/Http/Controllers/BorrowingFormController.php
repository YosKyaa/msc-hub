<?php

namespace App\Http\Controllers;

use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use App\Support\BorrowingFormData;
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
        $pdf = app('dompdf.wrapper')->loadView('pdf.borrowing-form', ['form' => $form]);

        return $request->boolean('unduh')
            ? $pdf->download($form->fileName())
            : $pdf->stream($form->fileName());
    }
}
