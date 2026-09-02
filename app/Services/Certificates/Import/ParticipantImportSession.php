<?php

namespace App\Services\Certificates\Import;

use App\Models\CertificateEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Menjembatani alur import dua langkah: pratinjau lalu konfirmasi.
 *
 * Hasil pembacaan disimpan di cache selama 30 menit dengan kunci berbasis
 * berkas unggahan, sehingga konfirmasi tidak membaca ulang file. Berkas
 * sementara dan cache-nya selalu dibuang setelah proses selesai.
 */
class ParticipantImportSession
{
    public const CACHE_TTL_MINUTES = 30;

    public const TEMP_DIRECTORY = 'certificate-imports';

    public const TEMP_DISK = 'local';

    public function __construct(
        private readonly ParticipantImportParser $parser,
        private readonly ParticipantImporter $importer,
    ) {}

    public function preview(string $storedPath): ParticipantImportPreview
    {
        $cached = Cache::get($this->cacheKey($storedPath));

        if (is_array($cached)) {
            return ParticipantImportPreview::fromArray($cached);
        }

        $preview = $this->parser->parse($this->disk()->path($storedPath));

        Cache::put($this->cacheKey($storedPath), $preview->toArray(), now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $preview;
    }

    public function confirm(CertificateEvent $event, string $storedPath): ParticipantImportResult
    {
        $preview = $this->preview($storedPath);

        try {
            return $this->importer->import($event, $preview);
        } finally {
            $this->discard($storedPath);
        }
    }

    public function discard(string $storedPath): void
    {
        Cache::forget($this->cacheKey($storedPath));
        $this->disk()->delete($storedPath);
    }

    public function fileExists(?string $storedPath): bool
    {
        return filled($storedPath) && $this->disk()->exists($storedPath);
    }

    private function cacheKey(string $storedPath): string
    {
        return 'participant-import:'.sha1($storedPath);
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(self::TEMP_DISK);
    }
}
