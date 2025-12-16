<?php

namespace App\Http\Controllers;

use App\Enums\CommentAuthorType;
use App\Enums\ContentType;
use App\Enums\RequestStatus;
use App\Enums\RequesterType;
use App\Models\ContentRequest;
use App\Models\ContentRequestComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class ContentRequestController extends Controller
{
    public function showForm()
    {
        $requester = Session::get('requester');
        $contentTypes = ContentType::cases();
        $requesterTypes = RequesterType::cases();

        return view('content-request.form', compact('requester', 'contentTypes', 'requesterTypes'));
    }

    public function submitForm(Request $request)
    {
        $requester = Session::get('requester');

        if (!$requester) {
            return redirect()->route('request.content')
                ->with('error', 'Silakan login dengan Google terlebih dahulu.');
        }

        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_type' => ['required', Rule::enum(RequesterType::class)],
            'unit' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'content_type' => ['required', Rule::enum(ContentType::class)],
            'platform_target' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:2000',
            'audience' => 'nullable|string|max:1000',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'deadline' => 'required|date|after_or_equal:today',
            'materials_link' => 'nullable|url|max:2048',
            'notes' => 'nullable|string|max:5000',
        ], [
            'requester_name.required' => 'Nama wajib diisi.',
            'content_type.required' => 'Jenis konten wajib dipilih.',
            'deadline.required' => 'Deadline wajib diisi.',
            'deadline.after_or_equal' => 'Deadline tidak boleh di masa lalu.',
            'materials_link.url' => 'Link materi harus berupa URL yang valid.',
        ]);

        // Generate request code
        $requestCode = ContentRequest::generateRequestCode();

        // Create content request
        $contentRequest = ContentRequest::create([
            'request_code' => $requestCode,
            'requester_name' => $validated['requester_name'],
            'requester_email' => $requester['email'],
            'requester_google_id' => $requester['google_id'],
            'requester_type' => $validated['requester_type'],
            'unit' => $validated['unit'],
            'phone' => $validated['phone'],
            'content_type' => $validated['content_type'],
            'platform_target' => $validated['platform_target'],
            'purpose' => $validated['purpose'],
            'audience' => $validated['audience'],
            'event_date' => $validated['event_date'],
            'location' => $validated['location'],
            'deadline' => $validated['deadline'],
            'materials_link' => $validated['materials_link'],
            'notes' => $validated['notes'],
            'status' => RequestStatus::INCOMING,
        ]);

        return redirect()->route('request.success', ['code' => $requestCode]);
    }

    public function showSuccess(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('request.content');
        }

        return view('content-request.success', ['code' => $code]);
    }

    public function showStatus()
    {
        $requester = Session::get('requester');

        return view('content-request.status', compact('requester'));
    }

    public function checkStatus(Request $request)
    {
        $requester = Session::get('requester');

        if (!$requester) {
            return redirect()->route('request.status')
                ->with('error', 'Silakan login dengan Google terlebih dahulu.');
        }

        $validated = $request->validate([
            'request_code' => 'required|string|max:20',
        ]);

        $contentRequest = ContentRequest::with(['assignedTo', 'comments', 'linkedProject', 'createdAsset'])
            ->where('request_code', $validated['request_code'])
            ->where('requester_email', $requester['email'])
            ->first();

        if (!$contentRequest) {
            return redirect()->route('request.status')
                ->with('error', 'Request tidak ditemukan atau bukan milik Anda.');
        }

        return view('content-request.status-detail', [
            'requester' => $requester,
            'contentRequest' => $contentRequest,
        ]);
    }

    public function addComment(Request $request, ContentRequest $contentRequest)
    {
        $requester = Session::get('requester');

        if (!$requester) {
            return redirect()->route('request.status')
                ->with('error', 'Silakan login dengan Google terlebih dahulu.');
        }

        // Verify ownership
        if ($contentRequest->requester_email !== $requester['email']) {
            return redirect()->route('request.status')
                ->with('error', 'Anda tidak memiliki akses ke request ini.');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        ContentRequestComment::create([
            'content_request_id' => $contentRequest->id,
            'author_type' => CommentAuthorType::REQUESTER,
            'author_name' => $requester['name'],
            'author_email' => $requester['email'],
            'user_id' => null,
            'message' => $validated['message'],
        ]);

        return redirect()->route('request.status.detail', ['request_code' => $contentRequest->request_code])
            ->with('success', 'Komentar berhasil ditambahkan.');
    }
}
