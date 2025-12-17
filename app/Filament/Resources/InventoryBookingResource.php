<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Enums\InventoryLogType;
use App\Filament\Resources\InventoryBookingResource\Pages;
use App\Models\InventoryBooking;
use App\Models\InventoryItem;
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

class InventoryBookingResource extends Resource
{
    protected static ?string $model = InventoryBooking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory & Room';

    protected static ?string $navigationLabel = 'Booking Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'booking_code';

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
                    Textarea::make('purpose')
                        ->label('Tujuan Peminjaman')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Jadwal & Item')
                ->schema([
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
                    Select::make('items')
                        ->label('Item yang Dipinjam')
                        ->multiple()
                        ->relationship('items', 'name')
                        ->options(fn () => InventoryItem::where('is_active', true)
                            ->whereIn('condition_status', ['good', 'minor_issue'])
                            ->get()
                            ->mapWithKeys(fn ($item) => [$item->id => "[{$item->code}] {$item->name}"])
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
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
                TextColumn::make('requester_name')
                    ->label('Pemohon')
                    ->searchable()
                    ->description(fn ($record) => $record->unit ?? '-'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('start_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('end_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
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
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(BookingStatus::class)
                    ->multiple(),
                Filter::make('date_range')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('Dari'),
                        DateTimePicker::make('until')
                            ->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->where('start_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->where('end_at', '<=', $date));
                    }),
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
                            $pdf = app('dompdf.wrapper')->loadView('pdf.inventory-booking', ['booking' => $record]);
                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'booking-inventory-' . $record->booking_code . '.pdf'
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
                            $record->createLog(InventoryLogType::STATUS_CHANGE, 'Approved by Staff');
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
                        ->modalDescription('Booking akan disetujui dan siap untuk check-out.')
                        ->action(function ($record) {
                            $record->update([
                                'head_approved_at' => now(),
                                'head_approved_by' => auth()->id(),
                                'status' => BookingStatus::APPROVED_HEAD,
                            ]);
                            $record->createLog(InventoryLogType::STATUS_CHANGE, 'Approved by Head');
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
                            $record->createLog(InventoryLogType::STATUS_CHANGE, 'Rejected: ' . $data['reject_reason']);
                            Notification::make()->title('Booking ditolak')->warning()->send();
                        }),

                    // Check-out
                    Actions\Action::make('checkout')
                        ->label('Check-out')
                        ->icon('heroicon-o-arrow-right-start-on-rectangle')
                        ->color('primary')
                        ->visible(fn ($record) => $record->canCheckOut())
                        ->form([
                            Textarea::make('checkout_note')
                                ->label('Catatan Check-out')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'checked_out_at' => now(),
                                'checked_out_by' => auth()->id(),
                                'checkout_note' => $data['checkout_note'] ?? null,
                                'status' => BookingStatus::CHECKED_OUT,
                            ]);
                            $record->createLog(InventoryLogType::CHECK_OUT, $data['checkout_note'] ?? 'Items checked out');
                            Notification::make()->title('Items berhasil di-checkout')->success()->send();
                        }),

                    // Return
                    Actions\Action::make('return')
                        ->label('Return')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->visible(fn ($record) => $record->canReturn())
                        ->form([
                            Textarea::make('return_note')
                                ->label('Catatan Return')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'returned_at' => now(),
                                'returned_by' => auth()->id(),
                                'return_note' => $data['return_note'] ?? null,
                                'status' => BookingStatus::RETURNED,
                            ]);
                            $record->createLog(InventoryLogType::RETURN, $data['return_note'] ?? 'Items returned');
                            Notification::make()->title('Items berhasil dikembalikan')->success()->send();
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
                            $record->createLog(InventoryLogType::STATUS_CHANGE, 'Booking cancelled');
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
        return [
            InventoryBookingResource\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryBookings::route('/'),
            'create' => Pages\CreateInventoryBooking::route('/create'),
            'view' => Pages\ViewInventoryBooking::route('/{record}'),
            'edit' => Pages\EditInventoryBooking::route('/{record}/edit'),
        ];
    }
}
