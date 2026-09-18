<?php

namespace App\Filament\Pages;

use App\Enums\CertificateNumberReset;
use App\Services\Certificates\CertificateNumberFormat;
use App\Support\AppSetting;
use App\Support\CertificatePermission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Pola penomoran sertifikat yang berlaku untuk seluruh kegiatan.
 *
 * Kegiatan tertentu boleh menimpanya lewat kolom pola pada formulir kegiatan;
 * yang diatur di sini adalah ketentuan kampus yang dipakai secara default.
 */
class CertificateNumberSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';

    protected static ?string $navigationLabel = 'Format Nomor';

    protected static ?string $title = 'Penomoran Sertifikat';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.certificate-number-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return CertificatePermission::allows('edit');
    }

    public function mount(): void
    {
        $format = CertificateNumberFormat::default();

        $this->form->fill([
            'pattern' => $format->pattern,
            'reset' => $format->reset->value,
            'unit_code' => $format->unitCode,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Pola Nomor')
                    ->description('Berlaku otomatis untuk setiap sertifikat yang diterbitkan, kecuali kegiatan tersebut memakai pola sendiri.')
                    ->schema([
                        TextInput::make('pattern')
                            ->label('Pola penomoran')
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->helperText('Gunakan token di bawah. Contoh: {nomor:4}/CERT/{kode_unit}/{bulan_romawi}/{tahun}')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if (! str_contains((string) $value, '{nomor')) {
                                        $fail('Pola wajib memuat token {nomor} agar setiap sertifikat memperoleh nomor urut berbeda.');
                                    }
                                },
                            ])
                            ->columnSpanFull(),

                        TextInput::make('unit_code')
                            ->label('Kode unit penerbit')
                            ->required()
                            ->maxLength(40)
                            ->live(onBlur: true)
                            ->helperText('Mengisi token {kode_unit}.'),

                        Select::make('reset')
                            ->label('Nomor urut diulang')
                            ->options(CertificateNumberReset::options())
                            ->required()
                            ->live()
                            ->helperText(fn ($state) => (CertificateNumberReset::tryFrom((string) $state) ?? CertificateNumberReset::YEARLY)->getDescription()),

                        Text::make(fn (Get $get) => $this->previewHtml($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Token yang Tersedia')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Text::make(new HtmlString($this->tokenHtml()))->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::set(CertificateNumberFormat::SETTING_PATTERN, $data['pattern']);
        AppSetting::set(CertificateNumberFormat::SETTING_RESET, $data['reset']);
        AppSetting::set(CertificateNumberFormat::SETTING_UNIT_CODE, $data['unit_code']);

        Notification::make()
            ->title('Pengaturan penomoran disimpan')
            ->body('Pola ini dipakai untuk sertifikat yang diterbitkan setelah ini. Nomor yang sudah terbit tidak berubah.')
            ->success()
            ->send();
    }

    private function previewHtml(Get $get): HtmlString
    {
        $format = new CertificateNumberFormat(
            pattern: (string) ($get('pattern') ?: CertificateNumberFormat::DEFAULT_PATTERN),
            reset: CertificateNumberReset::tryFrom((string) $get('reset')) ?? CertificateNumberReset::YEARLY,
            unitCode: (string) ($get('unit_code') ?: CertificateNumberFormat::DEFAULT_UNIT_CODE),
        );

        return new HtmlString(
            '<div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">'
            .'<p class="text-xs uppercase tracking-wide text-gray-500">Contoh hasil</p>'
            .'<p class="mt-1 font-mono text-base font-semibold text-gray-950 dark:text-white">'
            .e($format->preview()).'</p></div>'
        );
    }

    private function tokenHtml(): string
    {
        $rows = '';

        foreach (CertificateNumberFormat::tokens() as $token => $description) {
            $rows .= '<tr>'
                .'<td class="py-1 pe-4 align-top font-mono text-sm font-semibold">'.e($token).'</td>'
                .'<td class="py-1 align-top text-sm text-gray-600 dark:text-gray-400">'.e($description).'</td>'
                .'</tr>';
        }

        return '<table class="w-full">'.$rows.'</table>';
    }
}
