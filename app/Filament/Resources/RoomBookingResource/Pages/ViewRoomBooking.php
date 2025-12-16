<?php

namespace App\Filament\Resources\RoomBookingResource\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookingResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewRoomBooking extends ViewRecord
{
    protected static string $resource = RoomBookingResource::class;

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
                    TextEntry::make('attendees')
                        ->label('Jumlah Peserta')
                        ->placeholder('-'),
                ])
                ->columns(3),

            Section::make('Ruangan & Jadwal')
                ->schema([
                    TextEntry::make('room.name')
                        ->label('Ruangan'),
                    TextEntry::make('room.location')
                        ->label('Lokasi')
                        ->placeholder('-'),
                    TextEntry::make('start_at')
                        ->label('Waktu Mulai')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('end_at')
                        ->label('Waktu Selesai')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('purpose')
                        ->label('Keperluan')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columns(2),

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
                    Notification::make()->title('Booking di-approve (Head)')->success()->send();
                }),

            Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canReject())
                ->form([
                    Textarea::make('reject_reason')
                        ->label('Alasan')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'rejected_at' => now(),
                        'rejected_by' => auth()->id(),
                        'reject_reason' => $data['reject_reason'],
                        'status' => BookingStatus::REJECTED,
                    ]);
                    Notification::make()->title('Booking ditolak')->warning()->send();
                }),

            Actions\Action::make('complete')
                ->label('Selesai')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canComplete())
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'completed_at' => now(),
                        'status' => BookingStatus::COMPLETED,
                    ]);
                    Notification::make()->title('Booking selesai')->success()->send();
                }),
        ];
    }
}
