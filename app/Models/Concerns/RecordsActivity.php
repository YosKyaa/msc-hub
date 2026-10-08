<?php

namespace App\Models\Concerns;

use App\Support\AuditLog;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Jejak audit: siapa mengubah apa, kapan, dari nilai apa menjadi apa.
 *
 * Sebelumnya tidak ada catatan sama sekali. Ketika sertifikat dicabut, nama
 * penerima dikoreksi, atau peminjaman disetujui, tidak ada cara mengetahui
 * siapa yang melakukannya bila kemudian dipersoalkan.
 *
 * Hanya kolom yang disebut model lewat auditedAttributes() yang dicatat, dan
 * hanya bila nilainya benar-benar berubah. Kolom teknis seperti token,
 * penanda waktu kirim email, dan kata sandi sengaja tidak pernah ikut.
 */
trait RecordsActivity
{
    use LogsActivity;

    /**
     * @return list<string>
     */
    abstract protected static function auditedAttributes(): array;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(AuditLog::NAME)
            ->logOnly(static::auditedAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
