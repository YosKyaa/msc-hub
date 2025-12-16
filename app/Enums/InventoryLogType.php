<?php

namespace App\Enums;

enum InventoryLogType: string
{
    case CHECK_OUT = 'check_out';
    case RETURN = 'return';
    case CONDITION_UPDATE = 'condition_update';
    case STATUS_CHANGE = 'status_change';

    public function getLabel(): string
    {
        return match ($this) {
            self::CHECK_OUT => 'Check-out',
            self::RETURN => 'Return',
            self::CONDITION_UPDATE => 'Kondisi Diupdate',
            self::STATUS_CHANGE => 'Status Berubah',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CHECK_OUT => 'warning',
            self::RETURN => 'success',
            self::CONDITION_UPDATE => 'info',
            self::STATUS_CHANGE => 'gray',
        };
    }
}
