<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | Default tax rate (PPN) used for calculations throughout the ERP system.
    | Value 0.11 = 11% (current Indonesian PPN rate).
    |
    */

    'tax_rate' => (float) env('TAX_RATE', 0.11),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency used for pricing, invoicing, and financial reports.
    |
    */

    'currency' => env('DEFAULT_CURRENCY', 'IDR'),

];