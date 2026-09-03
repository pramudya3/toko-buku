<?php

namespace App\Models;

/**
 * Alias konvensi untuk KasCategory — tabel cash_flow_categories.
 *
 * Dibuat karena migrasi membuat tabel cash_flow_categories
 * tetapi model awal dinamai KasCategory (Indonesia). Alias ini
 * memungkinkan kode yang mengacu ke CashFlowCategory tetap jalan.
 *
 * @see KasCategory
 */
class CashFlowCategory extends KasCategory
{
    // Mewarisi $table = 'cash_flow_categories', HasUuids, SoftDeletes, dll.
}
