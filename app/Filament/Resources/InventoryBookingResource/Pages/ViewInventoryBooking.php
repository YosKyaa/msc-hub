<?php

namespace App\Filament\Resources\InventoryBookingResource\Pages;

use App\Enums\BookingStatus;
use App\Enums\InventoryLogType;
use App\Filament\Resources\InventoryBookingResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewInventoryBooking extends ViewRecord
{
    protected static string $resource = InventoryBookingResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Booking')
                ->schema([
                    TextEntry::make('booking_code')
                        ->label('Kode Booking')
                        ->badge()
                        ->color('primary'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge(),
                    TextEntry::make('requester_name')
                        ->label('Pemohon'),
                    TextEntry::make('requester_email')
                        ->label('Email')
                        ->copyable(),
                    TextEntry::make('unit')
                        ->label('Unit')
                        ->placeholder('-'),
                    TextEntry::make('purpose')
                        ->label('Tujuan')
                        ->placeholder('-'),
                ])
                ->columns(3),

            Section::make('Jadwal')
                ->schema([
                    TextEntry::make('start_at')
                        ->label('Waktu Mulai')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('end_at')
                        ->label('Waktu Selesai')
                        ->dateTime('d M Y H:i'),
                ])
                ->columns(2),

            Section::make('Item yang Dipinjam')
                ->schema([
                    TextEntry::make('items.name')
                        ->label('')
                        ->badge()
                        ->color('info')
                        ->separator(', '),
                ]),

            Section::make('Approval History')
                ->schema([
                    TextEntry::make('staff_approved_at')
                        ->label('Staff Approve')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('staffApprover.name')
                        ->label('Oleh')
                        ->placeholder('-'),
                    TextEntry::make('head_approved_at')
                        ->label('Head Approve')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('headApprover.name')
                        ->label('Oleh')
                        ->placeholder('-'),
                ])
                ->columns(4),

            Section::make('Check-out / Return')
                ->schema([
                    TextEntry::make('checked_out_at')
                        ->label('Check-out')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('checkedOutByUser.name')
                        ->label('Oleh')
                        ->placeholder('-'),
                    TextEntry::make('checkout_note')
                        ->label('Catatan')
                        ->placeholder('-'),
                    TextEntry::make('returned_at')
                        ->label('Return')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('returnedByUser.name')
                        ->label('Oleh')
                        ->placeholder('-'),
                    TextEntry::make('return_note')
                        ->label('Catatan')
                        ->placeholder('-'),
                ])
                ->columns(3)
                ->visible(fn ($record) => $record->checked_out_at || $record->returned_at),

            Section::make('Rejection')
                ->schema([
                    TextEntry::make('rejected_at')
                        ->label('Ditolak')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('rejectedByUser.name')
                        ->label('Oleh'),
                    TextEntry::make('reject_reason')
                        ->label('Alasan')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn ($record) => $record->rejected_at !== null),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === BookingStatus::PENDING),

            Actions\Action::make('staff_approve')
                ->label('Approve (Staff)')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->canStaffApprove() && auth()->user()->hasAnyRole(['admin', 'staff_msc', 'head_msc']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'staff_approved_at' => now(),
                        'staff_approved_by' => auth()->id(),
                        'status' => BookingStatus::APPROVED_STAFF,
                    ]);
                    $this->record->createLog(InventoryLogType::STATUS_CHANGE, 'Approved by Staff');
                    Notification::make()->title('Booking di-approve (Staff)')->success()->send();
                }),

            Actions\Action::make('head_approve')
                ->label('Approve (Head)')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->canHeadApprove() && auth()->user()->hasAnyRole(['admin', 'head_msc']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'head_approved_at' => now(),
                        'head_approved_by' => auth()->id(),
                        'status' => BookingStatus::APPROVED_HEAD,
                    ]);
                    $this->record->createLog(InventoryLogType::STATUS_CHANGE, 'Approved by Head');
                    Notification::make()->title('Booking di-approve (Head)')->success()->send();
                }),

            Actions\Action::make('checkout')
                ->label('Check-out')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('primary')
                ->visible(fn () => $this->record->canCheckOut())
                ->form([
                    Textarea::make('checkout_note')->label('Catatan')->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'checked_out_at' => now(),
                        'checked_out_by' => auth()->id(),
                        'checkout_note' => $data['checkout_note'] ?? null,
                        'status' => BookingStatus::CHECKED_OUT,
                    ]);
                    $this->record->createLog(InventoryLogType::CHECK_OUT, $data['checkout_note'] ?? 'Items checked out');
                    Notification::make()->title('Items berhasil di-checkout')->success()->send();
                }),

            Actions\Action::make('return')
                ->label('Return')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(fn () => $this->record->canReturn())
                ->form([
                    Textarea::make('return_note')->label('Catatan')->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'returned_at' => now(),
                        'returned_by' => auth()->id(),
                        'return_note' => $data['return_note'] ?? null,
                        'status' => BookingStatus::RETURNED,
                    ]);
                    $this->record->createLog(InventoryLogType::RETURN, $data['return_note'] ?? 'Items returned');
                    Notification::make()->title('Items berhasil dikembalikan')->success()->send();
                }),
        ];
    }
}
