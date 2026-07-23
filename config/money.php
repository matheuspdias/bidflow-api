<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    |
    | This is a single-currency demo system — every Money amount in the
    | database is assumed to be in this currency, since the "amount" columns
    | themselves don't store a currency code.
    |
    */
    'default_currency' => env('DEFAULT_CURRENCY', 'USD'),
];
