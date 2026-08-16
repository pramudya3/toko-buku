<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pre-Order
    |--------------------------------------------------------------------------
    |
    | Batas maksimum qty per item pre-order (stok belum tersedia, jadi
    | pembatasan tidak memakai stok melainkan config ini).
    |
    */
    'max_qty' => env('PREORDER_MAX_QTY', 99),
];
