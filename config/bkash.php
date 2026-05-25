<?php

return [
    'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
    'app_key' => env('BKASH_APP_KEY'),
    'app_secret' => env('BKASH_APP_SECRET'),
    'username' => env('BKASH_USERNAME'),
    'password' => env('BKASH_PASSWORD'),
    'sandbox' => (bool) str_contains(env('BKASH_BASE_URL', ''), 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    | bKash production rejects callback URLs that are not (a) publicly
    | resolvable HTTPS and (b) whitelisted on the merchant dashboard. Local
    | dev domains like `.test` will be rejected with `2049 Invalid Merchant
    | Callback URL`. Set BKASH_CALLBACK_URL to your whitelisted URL (e.g.
    | https://embaadmission.your-domain.edu.bd/applicant/payment/bkash/callback
    | or a tunnel URL like https://abcd.ngrok.app/applicant/payment/bkash/callback)
    | and leave blank to fall back to the named route.
    */
    'callback_url' => env('BKASH_CALLBACK_URL'),
];
