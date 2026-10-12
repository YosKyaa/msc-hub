<?php

namespace App\Http\Controllers;

use App\Models\Twibbon;
use Illuminate\Http\Response;

/**
 * Halaman publik twibbon.
 *
 * Foto peserta tidak pernah sampai ke sini: bingkai dan foto digabungkan di
 * peramban peserta. Satu-satunya yang dikirim balik adalah tanda bahwa
 * twibbon dipakai, untuk menghitung jumlah pemakainya.
 */
class TwibbonController extends Controller
{
    public function index()
    {
        return view('twibbon.index', [
            'twibbons' => Twibbon::open()->latest()->get(),
        ]);
    }

    public function show(string $slug)
    {
        $twibbon = Twibbon::where('slug', $slug)->where('is_published', true)->firstOrFail();

        // Yang belum dibuka diperlakukan seperti belum ada: tautannya memang
        // belum dibagikan. Yang sudah ditutup tetap dijawab dengan penjelasan,
        // karena tautannya mungkin masih beredar di grup dan unggahan lama.
        abort_if($twibbon->status() === Twibbon::STATUS_SCHEDULED, 404);

        return view('twibbon.show', ['twibbon' => $twibbon]);
    }

    public function used(string $slug): Response
    {
        $twibbon = Twibbon::open()->where('slug', $slug)->firstOrFail();

        // Satu query, tanpa membaca lalu menulis: ratusan peserta bisa
        // mengunduh pada menit yang sama.
        Twibbon::whereKey($twibbon->getKey())->increment('uses_count');

        return response()->noContent();
    }
}
