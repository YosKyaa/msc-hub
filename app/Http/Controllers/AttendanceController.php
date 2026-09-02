<?php

namespace App\Http\Controllers;

use App\Models\CertificateEvent;
use App\Services\Certificates\AttendanceService;
use App\Services\Certificates\ParticipantRegistry;
use App\Services\QrCodeGenerator;
use Illuminate\Support\Facades\Session;

/**
 * Halaman absensi publik per kegiatan (satu URL ber-token untuk check-in
 * maupun check-out). Seluruh aturan divalidasi ulang di server; tampilan
 * hanya membantu peserta memilih aksi yang benar.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function show(string $token)
    {
        $event = $this->resolveEvent($token);
        $requester = $this->requester();

        return view('attendance.show', [
            'event' => $event,
            'windowState' => $event->attendanceWindowState(),
            'requester' => $requester,
            'loginUrl' => route('google.redirect', ['redirect' => route('attendance.show', $token)]),
            'participation' => $requester
                ? $this->attendance->findParticipation($event, $requester['email'])
                : null,
        ]);
    }

    public function store(string $token)
    {
        $event = $this->resolveEvent($token);
        $requester = $this->requester();

        if ($requester === null) {
            return redirect()->route('google.redirect', ['redirect' => route('attendance.show', $token)]);
        }

        if (! $event->attendanceIsOpen()) {
            return back()->with('error', 'Absensi kegiatan ini sedang tidak dibuka.');
        }

        // Jalur absensi QR hanya untuk sivitas JGU (keputusan D6).
        if (! ParticipantRegistry::isJguDomain($requester['email'])) {
            return back()->with('error', 'Absensi hanya dapat digunakan oleh email @jgu.ac.id atau @student.jgu.ac.id.');
        }

        $outcome = $this->attendance->record($event, [
            'email' => $requester['email'],
            'name' => $requester['name'] ?? null,
            'google_id' => $requester['google_id'] ?? null,
        ]);

        return redirect()
            ->route('attendance.show', $token)
            ->with('success', $outcome->message());
    }

    /**
     * Halaman QR layar penuh untuk diproyeksikan di lokasi kegiatan.
     */
    public function poster(CertificateEvent $event, QrCodeGenerator $qrCodes)
    {
        abort_unless($event->attendance_enabled && $event->attendance_token, 404);

        $url = $event->attendanceUrl();

        return view('attendance.poster', [
            'event' => $event,
            'attendanceUrl' => $url,
            'qrDataUri' => $qrCodes->dataUri($url, scale: 12),
        ]);
    }

    private function resolveEvent(string $token): CertificateEvent
    {
        return CertificateEvent::where('attendance_token', $token)
            ->where('attendance_enabled', true)
            ->firstOrFail();
    }

    /**
     * @return array{email: string, name?: string|null, google_id?: string|null}|null
     */
    private function requester(): ?array
    {
        $requester = Session::get('requester');

        return is_array($requester) && filled($requester['email'] ?? null) ? $requester : null;
    }
}
