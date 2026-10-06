<?php

namespace App\Filament\Resources;

use App\Enums\AchievementCategory;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\AchievementResource\Pages;
use App\Models\Achievement;
use App\Support\UploadLimit;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Pengelolaan prestasi MSC yang tampil di beranda.
 */
class AchievementResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Achievement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Prestasi MSC';

    protected static ?string $modelLabel = 'Prestasi';

    protected static ?string $pluralModelLabel = 'Prestasi';

    protected static ?string $recordTitleAttribute = 'title';

    /** Foto piala dan pindaian sertifikat jarang perlu lebih besar dari ini. */
    private const FOTO_DISARANKAN_KB = 2048;

    protected static function permissionPrefix(): string
    {
        return 'achievements';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Prestasi')
                ->description('Yang tampil di beranda sebagai bukti rekam jejak MSC.')
                ->schema([
                    TextInput::make('title')
                        ->label('Nama prestasi')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Juara 1 Lomba Film Pendek Antar Perguruan Tinggi')
                        ->columnSpanFull(),

                    TextInput::make('awarded_by')
                        ->label('Diberikan oleh')
                        ->maxLength(255)
                        ->placeholder('LLDIKTI Wilayah III')
                        ->helperText('Inilah yang membuat sebuah prestasi berarti. Tanpa penyebutan pemberinya, ia terbaca sebagai klaim sepihak.'),

                    Select::make('category')
                        ->label('Jenis')
                        ->options(AchievementCategory::options())
                        ->default(AchievementCategory::AWARD->value)
                        ->required()
                        ->native(false),

                    TextInput::make('level')
                        ->label('Tingkat')
                        ->maxLength(100)
                        ->placeholder('Nasional')
                        ->datalist(['Kampus', 'Regional', 'Nasional', 'Internasional'])
                        ->helperText('Boleh dikosongkan bila tidak relevan.'),

                    DatePicker::make('achieved_at')
                        ->label('Tanggal diraih')
                        ->native(false)
                        ->maxDate(now())
                        ->helperText('Tahunnya ditampilkan di beranda.'),

                    Textarea::make('description')
                        ->label('Keterangan singkat')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Satu atau dua kalimat. Yang panjang justru tidak terbaca di kartu beranda.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Foto')
                ->description('Foto piala atau pindaian sertifikatnya.')
                ->schema([
                    FileUpload::make('image')
                        ->label('Foto prestasi')
                        ->image()
                        ->disk('public')
                        ->directory('achievements')
                        ->visibility('public')
                        ->imageEditor()
                        ->maxSize(UploadLimit::forField(self::FOTO_DISARANKAN_KB))
                        ->helperText(new HtmlString(
                            'Maksimal '.e(UploadLimit::describe(UploadLimit::forField(self::FOTO_DISARANKAN_KB))).'.'
                            .'<br><span style="color:rgb(113 113 122);">Boleh dikosongkan. Prestasi tanpa foto tetap tampil, '
                            .'dengan lambang sesuai jenisnya.</span>'
                        ))
                        ->columnSpanFull(),
                ]),

            Section::make('Tampilan di Beranda')
                ->schema([
                    TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0)
                        ->helperText('Makin kecil makin depan. Yang bernilai sama diurutkan dari yang terbaru.'),

                    Toggle::make('is_active')
                        ->label('Tampilkan di beranda')
                        ->default(true)
                        ->helperText('Matikan untuk menyembunyikan tanpa menghapusnya.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(asset('img/jgusolo.png')),

                TextColumn::make('title')
                    ->label('Prestasi')
                    ->description(fn (Achievement $record) => $record->awarded_by ?: 'Pemberi belum diisi')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category')
                    ->label('Jenis')
                    ->badge(),

                TextColumn::make('level')
                    ->label('Tingkat')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('achieved_at')
                    ->label('Diraih')
                    ->date('d M Y')
                    ->placeholder('Belum diisi')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Tampil')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Jenis')
                    ->options(AchievementCategory::options()),

                TernaryFilter::make('is_active')
                    ->label('Tampil di beranda')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya yang tampil')
                    ->falseLabel('Hanya yang disembunyikan'),
            ])
            ->emptyStateHeading('Belum ada prestasi')
            ->emptyStateDescription('Penghargaan, sertifikat, dan piala yang ditambahkan di sini tampil di beranda sebagai bukti rekam jejak MSC.')
            ->emptyStateIcon('heroicon-o-trophy')
            ->actions([
                // Menyembunyikan tanpa membuka formulirnya: keputusan ini
                // sering diambil mendadak, misalnya saat satu foto ternyata
                // keliru terunggah.
                Actions\Action::make('toggleAktif')
                    ->label(fn (Achievement $record) => $record->is_active ? 'Sembunyikan' : 'Tampilkan')
                    ->icon(fn (Achievement $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Achievement $record) => $record->is_active ? 'gray' : 'success')
                    ->iconButton()
                    ->visible(fn () => static::canCreate())
                    ->action(function (Achievement $record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'Prestasi ditampilkan di beranda' : 'Prestasi disembunyikan')
                            ->success()
                            ->send();
                    }),

                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAchievements::route('/'),
            'create' => Pages\CreateAchievement::route('/create'),
            'edit' => Pages\EditAchievement::route('/{record}/edit'),
        ];
    }
}
