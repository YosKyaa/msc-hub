<?php

namespace App\Filament\Actions;

use App\Models\CertificateEvent;
use App\Services\Certificates\Import\ParticipantImportException;
use App\Services\Certificates\Import\ParticipantImportParser;
use App\Services\Certificates\Import\ParticipantImportPreview;
use App\Services\Certificates\Import\ParticipantImportSession;
use App\Support\CertificatePermission;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Kulit UI untuk alur import peserta dua langkah.
 * Seluruh aturan baca, cache, dan pembersihan berkas ada di
 * App\Services\Certificates\Import\ParticipantImportSession.
 *
 * Panel ini tidak memakai tema Vite, jadi kelas Tailwind yang tidak dipakai
 * Filament sendiri tidak ikut terkompilasi. Warna karena itu diserahkan ke
 * Text::color() — yang juga menyesuaikan mode gelap — dan tata letak kecil
 * ditulis sebagai gaya sebaris.
 */
class ImportParticipantsAction extends Action
{
    /**
     * Jenis berkas yang diterima, diperiksa dari isi berkasnya, bukan
     * ekstensinya. .ods, dan .xlsx di server dengan libmagic lama, dikenali
     * sebagai zip; isi yang sebenarnya diperiksa pembaca berkas, yang menolak
     * apa pun yang bukan lembar kerja dengan pesan yang jelas.
     */
    public const ACCEPTED_FILE_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
        'application/csv',
        'text/x-csv',
        'text/plain',
        'application/zip',
        'application/octet-stream',
    ];

    /** Berapa baris bermasalah yang dirinci di pratinjau. */
    private const MAX_PROBLEMS_SHOWN = 50;

    /** Berapa peringatan yang dirinci, di pratinjau maupun di pemberitahuan. */
    private const MAX_WARNINGS_SHOWN = 10;

    /**
     * Hasil pratinjau dalam satu permintaan, supaya kelima bagian pratinjau
     * tidak masing-masing membaca cache.
     *
     * @var array<string, array{preview: ?ParticipantImportPreview, error: ?string}>
     */
    private array $previewMemo = [];

    public static function getDefaultName(): ?string
    {
        return 'importParticipants';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Import Peserta')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Import Peserta dari Excel')
            ->modalSubmitActionLabel('Import Baris Valid')
            ->visible(fn () => CertificatePermission::allows('create'))
            ->steps([
                Step::make('Unggah Berkas')
                    ->description('Format .xlsx, .xls, .ods, atau .csv sesuai template resmi, maksimal '.ParticipantImportParser::MAX_ROWS.' baris data.')
                    ->schema([
                        // Tautan template diletakkan tepat di langkah ini:
                        // di situlah orang menyadari ia belum punya berkasnya.
                        Text::make(new HtmlString(
                            '<a href="'.route('certificates.import-template').'" '
                            .'style="display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;color:rgb(29 78 216);text-decoration:none;">'
                            .'<svg style="width:1rem;height:1rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
                            .'<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>'
                            .'Unduh template Excel</a>'
                            .'<span style="display:block;margin-top:0.375rem;font-size:0.8125rem;color:rgb(113 113 122);">'
                            .'Berisi nama kolom yang benar, contoh isian, pilihan peran siap klik, dan lembar petunjuk. '
                            .'Nama pada sertifikat dicetak apa adanya, jadi pakai template ini agar tidak salah ketik.</span>'
                        )),

                        FileUpload::make('file')
                            ->label('Berkas peserta')
                            ->disk(ParticipantImportSession::TEMP_DISK)
                            ->directory(ParticipantImportSession::TEMP_DIRECTORY)
                            ->visibility('private')
                            ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES)
                            ->maxSize(2048)
                            ->required()
                            ->helperText('Kolom yang dibaca: '.implode(', ', ParticipantImportParser::KNOWN_HEADERS).'. Urutan kolom bebas.'),
                    ]),

                Step::make('Pratinjau')
                    ->description('Periksa ringkasan sebelum data ditulis.')
                    ->schema([
                        Text::make(fn (Get $get) => $this->summaryHtml($get('file'))),

                        Text::make(fn (Get $get) => $this->listHtml(
                            'Tidak bisa dibaca',
                            [$this->previewState($get('file'))['error'] ?? ''],
                            1,
                        ))
                            ->color('danger')
                            ->visible(fn (Get $get) => $this->previewState($get('file'))['error'] !== null),

                        Text::make(fn (Get $get) => $this->listHtml(
                            'Baris bermasalah, tidak ikut diimpor',
                            array_map(
                                fn (array $problem) => 'Baris '.$problem['line'].': '.$problem['message'],
                                $this->previewState($get('file'))['preview']?->problems ?? [],
                            ),
                            self::MAX_PROBLEMS_SHOWN,
                        ))
                            ->color('danger')
                            ->visible(fn (Get $get) => ($this->previewState($get('file'))['preview']?->problemCount() ?? 0) > 0),

                        Text::make(fn (Get $get) => $this->listHtml(
                            'Perlu diperhatikan',
                            $this->previewState($get('file'))['preview']?->warnings ?? [],
                            self::MAX_WARNINGS_SHOWN,
                        ))
                            ->color('warning')
                            ->visible(fn (Get $get) => ($this->previewState($get('file'))['preview']?->warnings ?? []) !== []),

                        Text::make(fn (Get $get) => $this->sampleHtml($this->previewState($get('file'))['preview']))
                            ->visible(fn (Get $get) => ($this->previewState($get('file'))['preview']?->validCount() ?? 0) > 0),
                    ]),
            ])
            ->action(fn (array $data) => $this->import($data['file'] ?? null));
    }

    /**
     * Pratinjau beserta galatnya, untuk berkas apa pun bentuk isian unggahannya.
     *
     * @return array{preview: ?ParticipantImportPreview, error: ?string}
     */
    private function previewState(mixed $file): array
    {
        $file = $this->uploadedFile($file);

        $kunci = match (true) {
            $file instanceof UploadedFile => 'unggahan:'.$file->getFilename(),
            is_string($file) => 'tersimpan:'.$file,
            default => 'kosong',
        };

        return $this->previewMemo[$kunci] ??= $this->readPreview($file);
    }

    /**
     * @return array{preview: ?ParticipantImportPreview, error: ?string}
     */
    private function readPreview(UploadedFile|string|null $file): array
    {
        try {
            $preview = match (true) {
                $file instanceof UploadedFile => $this->session()->previewUpload($file),
                is_string($file) && $this->session()->fileExists($file) => $this->session()->preview($file),
                default => null,
            };

            return ['preview' => $preview, 'error' => null];
        } catch (ParticipantImportException $exception) {
            return ['preview' => null, 'error' => $exception->getMessage()];
        } catch (Throwable $exception) {
            // Galat yang tidak diduga tidak boleh merusak modalnya; admin
            // tetap mendapat penjelasan, dan rinciannya tersimpan di log.
            Log::error('Pratinjau impor peserta gagal.', ['exception' => $exception]);

            return ['preview' => null, 'error' => 'Berkas tidak dapat diproses karena kesalahan sistem. '
                .'Coba unggah ulang; bila masih gagal, simpan ulang berkasnya sebagai .xlsx.'];
        }
    }

    /**
     * Isian FileUpload berbentuk larik bertoken. Selama wizard belum
     * dikirim, isinya unggahan sementara Livewire; setelah dikirim, path
     * berkas yang sudah disimpan.
     */
    private function uploadedFile(mixed $file): UploadedFile|string|null
    {
        if (is_array($file)) {
            $file = reset($file) ?: null;
        }

        if (is_string($file) && TemporaryUploadedFile::canUnserialize($file)) {
            $file = TemporaryUploadedFile::unserializeFromLivewireRequest($file);
            $file = is_array($file) ? (reset($file) ?: null) : $file;
        }

        return $file instanceof UploadedFile || is_string($file) ? $file : null;
    }

    private function summaryHtml(mixed $file): HtmlString
    {
        $state = $this->previewState($file);
        $preview = $state['preview'];

        if ($state['error'] !== null) {
            return new HtmlString('Berkas ini belum bisa diimpor. Kembali ke langkah sebelumnya untuk mengunggah berkas lain.');
        }

        if ($preview === null) {
            return new HtmlString('Unggah berkas terlebih dahulu.');
        }

        if ($preview->validCount() === 0) {
            return new HtmlString('<strong>Tidak ada baris yang bisa diimpor.</strong> '
                .'Perbaiki baris bermasalah di bawah ini di berkasnya, lalu unggah ulang.');
        }

        return new HtmlString(sprintf(
            '<strong>%d baris valid</strong> siap diimpor, <strong>%d baris bermasalah</strong> akan dilewati.',
            $preview->validCount(),
            $preview->problemCount(),
        ));
    }

    private function sampleHtml(?ParticipantImportPreview $preview): HtmlString
    {
        $baris = array_map(
            fn ($row) => 'Baris '.$row->line.': '.$row->name.' <'.$row->email.'>, '.$row->role->getLabel(),
            array_slice($preview?->validRows ?? [], 0, 5),
        );

        return $this->listHtml('Contoh baris valid', $baris, 5);
    }

    /**
     * Daftar berjudul dengan batas rincian, supaya berkas berisi ratusan
     * masalah tidak membuat modalnya tak berujung.
     *
     * @param  array<int, string>  $items
     */
    private function listHtml(string $judul, array $items, int $batas): HtmlString
    {
        $html = '<span style="display:block;font-weight:600;">'.e($judul).'</span>'
            .'<ul style="margin:0.25rem 0 0;padding-inline-start:1.25rem;list-style:disc;">';

        foreach (array_slice($items, 0, $batas) as $item) {
            $html .= '<li style="margin-top:0.125rem;">'.e($item).'</li>';
        }

        $html .= '</ul>';

        $sisa = count($items) - $batas;

        if ($sisa > 0) {
            $html .= '<span style="display:block;margin-top:0.25rem;">dan '.$sisa.' lainnya.</span>';
        }

        return new HtmlString($html);
    }

    private function import(mixed $file): void
    {
        $path = $this->uploadedFile($file);

        if (! is_string($path) || ! $this->session()->fileExists($path)) {
            Notification::make()->title('Berkas import tidak ditemukan. Silakan unggah ulang.')->danger()->send();

            return;
        }

        try {
            $result = $this->session()->confirm($this->event(), $path);
        } catch (ParticipantImportException $exception) {
            $this->session()->discard($path);
            Notification::make()->title('Import dibatalkan')->body($exception->getMessage())->danger()->send();

            return;
        } catch (Throwable $exception) {
            // Penulisan berjalan dalam satu transaksi, jadi kegagalan di tengah
            // jalan tidak meninggalkan setengah peserta. Itu yang perlu
            // diketahui admin; rincian teknisnya untuk log.
            $this->session()->discard($path);
            Log::error('Impor peserta gagal ditulis.', [
                'certificate_event_id' => $this->event()->id,
                'exception' => $exception,
            ]);

            Notification::make()
                ->title('Import gagal')
                ->body('Terjadi kesalahan sistem saat menyimpan peserta. Tidak ada satu pun baris yang tersimpan, '
                    .'jadi berkas yang sama aman diunggah ulang.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $peringatan = array_slice($result->warnings, 0, self::MAX_WARNINGS_SHOWN);
        $sisa = count($result->warnings) - count($peringatan);

        Notification::make()
            ->title('Import selesai')
            ->body(trim($result->summary().' '.implode(' ', $peringatan)
                .($sisa > 0 ? " Dan {$sisa} peringatan lainnya." : '')))
            ->status($result->warnings === [] ? 'success' : 'warning')
            ->persistent()
            ->send();
    }

    private function session(): ParticipantImportSession
    {
        return app(ParticipantImportSession::class);
    }

    private function event(): CertificateEvent
    {
        return $this->getLivewire()->getOwnerRecord();
    }
}
