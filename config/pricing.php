<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tier Discounts
    |--------------------------------------------------------------------------
    |
    | Aturan diskon tier (BR-03) kini dikelola dari panel admin dan tersimpan
    | di tabel `tier_discounts` (format min_qty). Contoh: bazaf min_qty 1 → 5%,
    | min_qty 6 → 10% artinya qty 1–5 = 5%, qty 6+ = 10%.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Ambang batas stok menipis untuk peringatan dashboard & filter inventori.
    |
    */

    'low_stock_threshold' => 5,

];
