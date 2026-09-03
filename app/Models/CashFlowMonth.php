<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bulan pencatatan kas yang dibuka manual — tampil di daftar bulan
 * meski belum punya entri (CashFlow) sama sekali.
 *
 * @property int $id
 * @property string $bulan
 * @property bool $is_closed
 * @property CarbonInterface|null $closed_at
 * @property int|null $closed_by
 */
#[Fillable(['bulan', 'is_closed', 'closed_at', 'closed_by'])]
class CashFlowMonth extends Model
{
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }
}
