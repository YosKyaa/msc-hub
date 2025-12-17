<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookingResource\Pages;
use App\Models\Room;
use App\Models\RoomBooking;
use BackedEnum;
use UnitEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
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
                    TextInput::make('unit')
                        ->label('Unit/Fakultas')
                        ->maxLength(255),
                    TextInput::make('attendees')
                        ->label('Jumlah Peserta')
                        ->numeric()
                        ->minValue(1),
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
                        ->native(false),
                    DateTimePicker::make('end_at')
                        ->label('Waktu Selesai')
                        ->required()
                        ->seconds(false)
                        ->after('start_at')
                        ->native(false),
                ])
                ->columns(2),

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
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make()
                        ->visible(fn ($record) => $record->status === BookingStatus::PENDING),

                    // Export PDF
                    Actions\Action::make('export_pdf')
                        ->label('Export PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->action(function ($record) {
                            $pdf = app('dompdf.wrapper')->loadView('pdf.room-booking', ['booking' => $record]);
                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'booking-room-' . $record->booking_code . '.pdf'
                            );
                        }),

                    // Staff Approve
                    Actions\Action::make('staff_approve')
                        ->label('Approve (Staff)')
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

                    // Head Approve
                    Actions\Action::make('head_approve')
                        ->label('Approve (Head)')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn ($record) => $record->canHeadApprove() && auth()->user()->hasAnyRole(['admin', 'head_msc']))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Booking (Head)')
                        ->modalDescription('Booking akan disetujui.')
                        ->action(function ($record) {
                            $record->update([
                                'head_approved_at' => now(),
                                'head_approved_by' => auth()->id(),
                                'status' => BookingStatus::APPROVED_HEAD,
                            ]);
                            Notification::make()->title('Booking di-approve (Head)')->success()->send();
                        }),

                    // Reject
                    Actions\Action::make('reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->canReject())
                        ->form([
                            Textarea::make('reject_reason')
                                ->label('Alasan Penolakan')
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

                    // Complete
                    Actions\Action::make('complete')
                        ->label('Selesai')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->canComplete())
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'completed_at' => now(),
                                'status' => BookingStatus::COMPLETED,
                            ]);
                            Notification::make()->title('Booking selesai')->success()->send();
                        }),

                    // Cancel
                    Actions\Action::make('cancel')
                        ->label('Batalkan')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible(fn ($record) => $record->canCancel())
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'cancelled_at' => now(),
                                'status' => BookingStatus::CANCELLED,
                            ]);
                            Notification::make()->title('Booking dibatalkan')->success()->send();
                        }),

                    Actions\DeleteAction::make()
                        ->visible(fn () => auth()->user()->hasRole('admin')),
                ]),
            ])
            ->bulkActions([]);
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
