<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Enums\InventoryCategory;
use App\Filament\Resources\RoomBookingResource\Pages;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RoomBookingResource extends Resource
{
    protected static ?string $model = RoomBooking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory & Room';

    protected static ?string $navigationLabel = 'Booking Ruangan';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'booking_code';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('room_bookings.view') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', BookingStatus::PENDING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Pemohon')
                ->schema([
                    TextInput::make('booking_code')
                        ->label('Kode Booking')
                        ->disabled()
                        ->visibleOn('edit'),
                    TextInput::make('requester_name')
                        ->label('Nama Pemohon')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('requester_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Select::make('unit')
                        ->label('Unit/Fakultas')
                        ->options([
                            'HIMATIF' => 'HIMATIF',
                            'HME' => 'HME',
                            'HMS' => 'HMS',
                            'HMTI' => 'HMTI',
                            'HIMAMEN' => 'HIMAMEN',
                            'HIMABID' => 'HIMABID',
                            'HIMFA' => 'HIMFA',
                            'Mahasiswa' => 'Mahasiswa',
                            'Dosen' => 'Dosen',
                            'Staff' => 'Staff',
                        ])
                        ->required()
                        ->searchable()
                        ->native(false),
                    TextInput::make('attendees')
                        ->label('Jumlah Peserta')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(7)
                        ->helperText('Maksimal 7 orang')
                        ->required(),
                    Textarea::make('purpose')
                        ->label('Keperluan')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Ruangan & Jadwal')
                ->schema([
                    Select::make('room_id')
                        ->label('Ruangan')
                        ->options(fn () => Room::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn ($state, $set) => $set('room_info', $state ? Room::find($state)?->location : null)),
                    TextInput::make('room_info')
                        ->label('Lokasi')
                        ->disabled()
                        ->dehydrated(false),
                    DateTimePicker::make('start_at')
                        ->label('Waktu Mulai')
                        ->required()
                        ->seconds(false)
                        ->minDate(now())
                        ->native(false)
                        ->helperText('Hanya hari Senin - Jumat')
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                $date = new \DateTime($value);
                                $dayOfWeek = (int) $date->format('N'); // 1 (Monday) to 7 (Sunday)
                                if ($dayOfWeek > 5) { // Saturday (6) or Sunday (7)
                                    $fail('Booking hanya dapat dilakukan pada hari Senin - Jumat.');
                                }
                            },
                        ]),
                    DateTimePicker::make('end_at')
                        ->label('Waktu Selesai')
                        ->required()
                        ->seconds(false)
                        ->after('start_at')
                        ->native(false)
                        ->helperText('Hanya hari Senin - Jumat')
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                $date = new \DateTime($value);
                                $dayOfWeek = (int) $date->format('N'); // 1 (Monday) to 7 (Sunday)
                                if ($dayOfWeek > 5) { // Saturday (6) or Sunday (7)
                                    $fail('Booking hanya dapat dilakukan pada hari Senin - Jumat.');
                                }
                            },
                        ]),
                ])
                ->columns(2),

            Section::make('Pinjam Peralatan Multimedia')
                ->description('Opsional - Pilih peralatan multimedia yang ingin dipinjam bersamaan dengan ruangan')
                ->schema([
                    Repeater::make('inventoryItems')
                        ->label('')
                        ->schema([
                            Select::make('inventory_item_id')
                                ->label('Peralatan')
                                ->options(fn () => InventoryItem::where('is_active', true)
                                    ->whereIn('category', [
                                        InventoryCategory::CAMERA,
                                        InventoryCategory::MICROPHONE,
                                        InventoryCategory::AUDIO,
                                        InventoryCategory::LIGHTING,
                                        InventoryCategory::VIDEO,
                                        InventoryCategory::PROJECTOR,
                                    ])
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [
                                        $item->id => "{$item->code} - {$item->name}",
                                    ]))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(2),
                            TextInput::make('quantity')
                                ->label('Jumlah')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->columnSpan(1),
                            TextInput::make('notes')
                                ->label('Catatan')
                                ->placeholder('Opsional')
                                ->columnSpan(1),
                        ])
                        ->columns(4)
                        ->addActionLabel('Tambah Peralatan')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => isset($state['inventory_item_id'])
                                ? InventoryItem::find($state['inventory_item_id'])?->name
                                : null
                        )
                        ->saveRelationshipsUsing(function () {
                            // Do nothing - we handle this manually in afterCreate/afterSave
                        })
                        ->dehydrated(true),
                ])
                ->collapsible()
                ->collapsed(fn ($operation) => $operation === 'edit'),

            Section::make('Status')
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(BookingStatus::class)
                        ->disabled(),
                ])
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->sortable(),
                TextColumn::make('requester_name')
                    ->label('Pemohon')
                    ->searchable()
                    ->description(fn ($record) => $record->unit ?? '-'),
                TextColumn::make('start_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('end_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('attendees')
                    ->label('Peserta')
                    ->placeholder('-'),
                TextColumn::make('inventory_items_count')
                    ->label('Peralatan')
                    ->counts('inventoryItems')
                    ->badge()
                    ->color('info')
                    ->suffix(' item')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(BookingStatus::class)
                    ->multiple(),
                SelectFilter::make('room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name'),
                Filter::make('date_range')
                    ->form([
                        DateTimePicker::make('from')->label('Dari'),
                        DateTimePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->where('start_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->where('end_at', '<=', $date));
                    }),
                Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn (Builder $query) => $query->whereDate('start_at', today())),
                Filter::make('pending_approval')
                    ->label('Perlu Approval')
                    ->query(fn (Builder $query) => $query->whereIn('status', [
                        BookingStatus::PENDING,
                        BookingStatus::APPROVED_STAFF,
                    ])),
            ])
            // Tombol ikon inline, bukan dropdown: panel ActionGroup Filament
            // dirender tanpa modifier `.flip`, sehingga selalu membuka ke bawah
            // dan terpotong tepi layar pada baris terakhir tabel.
            //
            // Baris hanya memuat aksi cepat. Persetujuan lengkap ada di halaman
            // detail, tempat peralatan yang dipinjam ikut terlihat sebelum
            // booking disetujui.
            ->actions([
                Actions\ViewAction::make()->iconButton()->tooltip('Lihat detail booking'),

                Actions\Action::make('export_pdf')
                    ->iconButton()
                    ->tooltip('Unduh bukti booking (PDF)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(fn ($record) => static::downloadPdf($record)),

                Actions\Action::make('staff_approve')
                    ->iconButton()
                    ->tooltip('Setujui sebagai Staff')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->canStaffApprove() && auth()->user()->hasAnyRole(['admin', 'staff_msc', 'head_msc']))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Booking (Staff)')
                    ->modalDescription('Booking akan diteruskan ke Head MSC untuk approval final.')
                    ->action(function ($record) {
                        $record->update([
                            'staff_approved_at' => now(),
                            'staff_approved_by' => auth()->id(),
                            'status' => BookingStatus::APPROVED_STAFF,
                        ]);
                        Notification::make()->title('Booking di-approve (Staff)')->success()->send();
                    }),

                Actions\Action::make('head_approve')
                    ->iconButton()
                    ->tooltip('Setujui sebagai Kepala MSC')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->canHeadApprove() && auth()->user()->hasAnyRole(['admin', 'head_msc']))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Booking (Head)')
                    ->action(function ($record) {
                        $record->update([
                            'head_approved_at' => now(),
                            'head_approved_by' => auth()->id(),
                            'status' => BookingStatus::APPROVED_HEAD,
                        ]);
                        Notification::make()->title('Booking di-approve (Head)')->success()->send();
                    }),

                Actions\Action::make('reject')
                    ->iconButton()
                    ->tooltip('Tolak booking')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->canReject())
                    ->modalHeading('Tolak Booking')
                    ->schema([
                        Textarea::make('reject_reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'rejected_at' => now(),
                            'rejected_by' => auth()->id(),
                            'reject_reason' => $data['reject_reason'],
                            'status' => BookingStatus::REJECTED,
                        ]);
                        Notification::make()->title('Booking ditolak')->warning()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * Bukti booking dicetak dari template bersama `x-pdf.document`.
     */
    public static function downloadPdf(RoomBooking $booking): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $booking->loadMissing(['room', 'inventoryItems', 'staffApprover', 'headApprover', 'rejectedByUser']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.room-booking', ['booking' => $booking]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'bukti-booking-ruangan-'.$booking->booking_code.'.pdf',
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomBookings::route('/'),
            'create' => Pages\CreateRoomBooking::route('/create'),
            'view' => Pages\ViewRoomBooking::route('/{record}'),
            'edit' => Pages\EditRoomBooking::route('/{record}/edit'),
        ];
    }
}
