<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use Illuminate\Http\Request;

/**
 * Daftar penerima sertifikat sebuah kegiatan, untuk umum.
 *
 * Seperti pengumuman kelulusan yang ditempel di papan: siapa saja boleh
 * melihat siapa yang menerima. Karena itu isinya dibatasi pada yang memang
 * tercetak di sertifikatnya — nama, peran, dan nomor. Alamat email tidak
 * pernah ikut: itu data pribadi, bukan bagian dari dokumennya.
 */
class CertificateRecipientsController extends Controller
{
    private const PER_PAGE = 30;

    public function __invoke(Request $request, string $slug)
    {
        $event = CertificateEvent::with('issuer')
            ->where('slug', $slug)
            ->firstOrFail();

        // Kegiatan yang daftarnya tidak dibuka tidak mengakui keberadaannya
        // sama sekali, ketimbang memberi tahu bahwa daftarnya ada tapi
        // tertutup.
        abort_unless($event->recipientsArePublic(), 404);

        $cari = trim((string) $request->query('cari', ''));

        $penerima = Certificate::query()
            ->where('certificate_event_id', $event->id)
            ->whereNull('revoked_at')
            ->when($cari !== '', fn ($query) => $query->where(
                fn ($cocok) => $cocok
                    ->where('recipient_name', 'like', "%{$cari}%")
                    ->orWhere('certificate_number', 'like', "%{$cari}%"),
            ))
            ->orderBy('recipient_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('certificates.recipients', [
            'event' => $event,
            'penerima' => $penerima,
            'cari' => $cari,
            'total' => Certificate::where('certificate_event_id', $event->id)->whereNull('revoked_at')->count(),
        ]);
    }
}
