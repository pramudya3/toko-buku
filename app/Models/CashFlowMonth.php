<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bulan pencatatan kas yang dibuka manual — tampil di daftar bulan
 * meski belum punya entri (CashFlow) sama sekali.
 *
 * @property int $id
 * @property string $bulan
 */
#[Fillable(['bulan'])]
class CashFlowMonth extends Model
{
    //
}
