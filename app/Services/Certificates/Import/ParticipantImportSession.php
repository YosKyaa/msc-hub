<?php

namespace App\Services\Certificates\Import;

use App\Models\CertificateEvent;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Menjembatani alur import dua langkah: pratinjau lalu konfirmasi.
 *
 * Hasil pembacaan disimpan di cache selama 30 menit dengan kunci berbasis
 * berkas unggahan, sehingga pratinjau tidak membaca ulang file setiap kali
 * modal diperbarui. Berkas sementara dan cache-nya selalu dibuang setelah
 * proses selesai.
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

    /**
     * Pratinjau berkas yang sudah disimpan di disk sementara.
     */
    public function preview(string $storedPath): ParticipantImportPreview
    {
        return $this->remember(
            $this->cacheKey($storedPath),
            fn () => $this->parser->parse($this->disk()->path($storedPath)),
        );
    }

    /**
     * Pratinjau berkas yang baru diunggah dan belum disimpan.
     *
     * Tombol Lanjut pada wizard Filament hanya memvalidasi langkahnya, tidak
     * menyimpan berkasnya. Langkah pratinjau karena itu menerima unggahan
     * sementara Livewire, bukan path di disk impor. Berkas itu baru
     * dipindahkan saat konfirmasi, lalu dibaca ulang oleh confirm().
     */
    public function previewUpload(UploadedFile $file): ParticipantImportPreview
    {
        return $this->remember(
            $this->cacheKey('unggahan:'.$file->getFilename()),
            fn () => $this->parser->parse($file->getRealPath() ?: throw ParticipantImportException::unreadable()),
        );
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

    /**
     * @param  Closure(): ParticipantImportPreview  $parse
     */
    private function remember(string $key, Closure $parse): ParticipantImportPreview
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return ParticipantImportPreview::fromArray($cached);
        }

        $preview = $parse();

        Cache::put($key, $preview->toArray(), now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $preview;
    }

    private function cacheKey(string $storedPath): string
    {
        return 'participant-import:'.sha1($storedPath);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::TEMP_DISK);
    }
}
