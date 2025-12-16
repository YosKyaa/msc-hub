<?php

namespace App\Enums;

enum RequesterType: string
{
    case STUDENT = 'student';
    case LECTURER = 'lecturer';
    case STAFF = 'staff';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::STUDENT => 'Mahasiswa',
            self::LECTURER => 'Dosen',
            self::STAFF => 'Staff',
            self::OTHER => 'Lainnya',
        };
    }
}
