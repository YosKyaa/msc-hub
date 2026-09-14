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
use Illuminate\Support\HtmlString;

/**
 * Kulit UI untuk alur import peserta dua langkah.
 * Seluruh aturan baca, cache, dan pembersihan berkas ada di
 * App\Services\Certificates\Import\ParticipantImportSession.
 */
class ImportParticipantsAction extends Action
{
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
                    ->description('Format .xlsx atau .csv sesuai template resmi, maksimal '.ParticipantImportParser::MAX_ROWS.' baris data.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Berkas peserta')
                            ->disk(ParticipantImportSession::TEMP_DISK)
                            ->directory(ParticipantImportSession::TEMP_DIRECTORY)
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                                'text/plain',
                            ])
                            ->maxSize(2048)
                            ->required()
                            ->helperText('Kolom yang dibaca: '.implode(', ', ParticipantImportParser::KNOWN_HEADERS).'. Urutan kolom bebas.'),
                    ]),

                Step::make('Pratinjau')
                    ->description('Periksa ringkasan sebelum data ditulis.')
                    ->schema([
                        Text::make(fn (Get $get) => $this->renderPreview($get('file'))),
                    ]),
            ])
            ->action(fn (array $data) => $this->import($data['file'] ?? null));
    }

    private function renderPreview(mixed $file): HtmlString
    {
        $path = $this->resolveStoredPath($file);

        if ($path === null) {
            return new HtmlString('<p class="text-sm">Unggah berkas terlebih dahulu.</p>');
        }

        try {
            return new HtmlString($this->previewHtml($this->session()->preview($path)));
        } catch (ParticipantImportException $exception) {
            return new HtmlString('<p class="text-sm text-danger-600">'.e($exception->getMessage()).'</p>');
        }
    }

    private function previewHtml(ParticipantImportPreview $preview): string
    {
        $html = sprintf(
            '<p class="text-sm"><strong>%d baris valid</strong> siap diimport, <strong>%d baris bermasalah</strong> akan dilewati.</p>',
            $preview->validCount(),
            $preview->problemCount(),
        );

        foreach ($preview->warnings as $warning) {
            $html .= '<p class="mt-2 text-sm text-warning-600">'.e($warning).'</p>';
        }

        if ($preview->problems !== []) {
            $html .= '<p class="mt-3 text-sm font-semibold">Baris bermasalah</p><ul class="mt-1 list-disc space-y-1 ps-5 text-sm">';

            foreach ($preview->problems as $problem) {
                $html .= '<li>Baris '.$problem['line'].': '.e($problem['message']).'</li>';
            }

            $html .= '</ul>';
        }

        $sample = array_slice($preview->validRows, 0, 5);

        if ($sample !== []) {
            $html .= '<p class="mt-3 text-sm font-semibold">Contoh baris valid</p><ul class="mt-1 list-disc space-y-1 ps-5 text-sm">';

            foreach ($sample as $row) {
                $html .= '<li>Baris '.$row->line.': '.e($row->name).' &lt;'.e($row->email).'&gt; — '.e($row->role->getLabel()).'</li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    private function import(mixed $file): void
    {
        $path = $this->resolveStoredPath($file);

        if ($path === null) {
            Notification::make()->title('Berkas import tidak ditemukan. Silakan unggah ulang.')->danger()->send();

            return;
        }

        try {
            $result = $this->session()->confirm($this->event(), $path);
        } catch (ParticipantImportException $exception) {
            $this->session()->discard($path);
            Notification::make()->title('Import dibatalkan')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title('Import selesai')
            ->body(trim($result->summary().' '.implode(' ', $result->warnings)))
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * FileUpload menyimpan state sebagai array bertoken; ambil path pertamanya.
     */
    private function resolveStoredPath(mixed $file): ?string
    {
        $path = is_array($file) ? (reset($file) ?: null) : $file;

        return is_string($path) && $this->session()->fileExists($path) ? $path : null;
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
