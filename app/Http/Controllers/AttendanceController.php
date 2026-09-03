<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceAction;
use App\Models\CertificateEvent;
use App\Services\Certificates\AttendanceService;
use App\Services\Certificates\ParticipantRegistry;
use App\Services\QrCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Halaman absensi publik per kegiatan. Check-in dan check-out punya token, URL,
 * QR, dan window waktu terpisah supaya peserta tidak dapat menutup kehadiran
 * sesaat setelah membukanya. Seluruh aturan divalidasi ulang di server.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function show(AttendanceAction $action, string $token)
    {
        $event = $this->resolveEvent($action, $token);
        $requester = $this->requester();
        $participant = $requester ? $this->attendance->knownParticipant($requester['email']) : null;

        return view('attendance.show', [
            'action' => $action,
            'event' => $event,
            'windowState' => $event->attendanceWindowState($action),
            'requester' => $requester,
            'participant' => $participant,
            'loginUrl' => route('google.redirect', ['redirect' => $event->attendanceUrl($action)]),
            'participation' => $requester
                ? $this->attendance->findParticipation($event, $requester['email'])
                : null,
        ]);
    }

    public function store(Request $request, AttendanceAction $action, string $token)
    {
        $event = $this->resolveEvent($action, $token);
        $requester = $this->requester();

        if ($requester === null) {
            return redirect()->route('google.redirect', ['redirect' => $event->attendanceUrl($action)]);
        }

        if (! $event->attendanceIsOpen($action)) {
            return back()->with('error', $this->closedMessage($event, $action));
        }

        // Jalur absensi QR hanya untuk sivitas JGU (keputusan D6).
        if (! ParticipantRegistry::isJguDomain($requester['email'])) {
            return back()->with('error', 'Absensi hanya dapat digunakan oleh email @jgu.ac.id atau @student.jgu.ac.id.');
        }

        $declaredName = $this->validatedName($request, $requester['email'], $action);

        $outcome = $this->attendance->record($event, $action, [
            'email' => $requester['email'],
            'name' => $requester['name'] ?? null,
            'google_id' => $requester['google_id'] ?? null,
        ], $declaredName);

        return redirect($event->attendanceUrl($action))
            ->with($outcome->isError() ? 'error' : 'success', $outcome->message());
    }

    /**
     * Halaman QR layar penuh untuk diproyeksikan di lokasi kegiatan.
     */
    public function poster(CertificateEvent $event, AttendanceAction $action, QrCodeGenerator $qrCodes)
    {
        abort_unless($event->attendance_enabled && $event->attendanceToken($action), 404);

        $url = $event->attendanceUrl($action);

        return view('attendance.poster', [
            'action' => $action,
            'event' => $event,
            'attendanceUrl' => $url,
            'qrDataUri' => $qrCodes->dataUri($url, scale: 12),
        ]);
    }

    /**
     * Nama lengkap hanya diminta — dan hanya diterima — ketika peserta belum
     * ada di master. Sesudah tersimpan, koreksi menjadi wewenang admin.
     */
    private function validatedName(Request $request, string $email, AttendanceAction $action): ?string
    {
        if ($action !== AttendanceAction::CHECK_IN || $this->attendance->knownParticipant($email) !== null) {
            return null;
        }

        return $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[\p{L}\p{M}\.\,\'\-\s]+$/u'],
        ], [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.min' => 'Nama lengkap terlalu pendek.',
            'full_name.max' => 'Nama lengkap maksimal 150 karakter.',
            'full_name.regex' => 'Nama lengkap hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.',
        ])['full_name'];
    }

    private function closedMessage(CertificateEvent $event, AttendanceAction $action): string
    {
        $label = $action->getLabel();

        return match ($event->attendanceWindowState($action)) {
            'not_started' => "{$label} baru dibuka pada "
                .$event->{$action->openColumn()}->translatedFormat('d F Y H:i').' WIB.',
            'closed' => "{$label} sudah ditutup pada "
                .$event->{$action->closeColumn()}->translatedFormat('d F Y H:i').' WIB.',
            default => "{$label} kegiatan ini sedang tidak dibuka.",
        };
    }

    private function resolveEvent(AttendanceAction $action, string $token): CertificateEvent
    {
        return CertificateEvent::where($action->tokenColumn(), $token)
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
