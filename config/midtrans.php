<?php

$environment = env('MIDTRANS_ENVIRONMENT', 'sandbox');
$isProductionRequested = $environment === 'production';

return [

    /*
    |--------------------------------------------------------------------------
    | Integrasi Midtrans
    |--------------------------------------------------------------------------
    |
    | Nilai bawaan selalu Sandbox. Mode production hanya aktif jika dua
    | konfigurasi berikut diisi secara eksplisit: environment=production dan
    | production_enabled=true. Mengisi key production saja tidak cukup.
    |
    | checkout_enabled memisahkan kesiapan integrasi dari penawaran di
    | checkout. Kode Midtrans tetap disimpan, tetapi opsi pembayaran baru
    | hanya muncul jika flag ini diaktifkan secara eksplisit.
    |
    */

    'enabled' => (bool) env('MIDTRANS_ENABLED', true),
    'checkout_enabled' => (bool) env('MIDTRANS_CHECKOUT_ENABLED', false),
    'environment' => $environment,
    'production_enabled' => (bool) env('MIDTRANS_PRODUCTION_ENABLED', false),

    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'platform_merchant_id' => env('MIDTRANS_PLATFORM_MERCHANT_ID'),
    'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),

    'expiry_minutes' => (int) env('MIDTRANS_EXPIRY_MINUTES', 1440),
    'commission_percent' => 0,

    /*
    |--------------------------------------------------------------------------
    | Partner Solutions: Transaction Split
    |--------------------------------------------------------------------------
    |
    | Aktifkan hanya setelah akun Sikitaya memiliki akses Partner Solutions
    | dan merchant ID platform. Setiap toko juga wajib memiliki merchant ID
    | yang terdaftar di bawah Partner Sikitaya.
    |
    */

    'split' => [
        'enabled' => (bool) env('MIDTRANS_SPLIT_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Disbursement API (Iris)
    |--------------------------------------------------------------------------
    |
    | Jalur fallback jika split tidak tersedia. Dana masuk ke akun platform,
    | lalu admin mengirimkannya ke rekening penjual melalui API Iris. Status
    | berhasil hanya boleh berasal dari respons API Midtrans.
    |
    */

    'disbursement' => [
        'enabled' => (bool) env('MIDTRANS_DISBURSEMENT_ENABLED', false),
        'base_url' => env('MIDTRANS_IRIS_BASE_URL') ?: (
            $isProductionRequested
                ? 'https://app.midtrans.com/iris/api/v1'
                : 'https://app.sandbox.midtrans.com/iris/api/v1'
        ),
        'creator_key' => env('MIDTRANS_IRIS_CREATOR_KEY'),
        'approver_key' => env('MIDTRANS_IRIS_APPROVER_KEY'),
        'auto_approve' => (bool) env('MIDTRANS_IRIS_AUTO_APPROVE', true),
        'default_email' => env('MIDTRANS_IRIS_DEFAULT_EMAIL', 'finance@sikitaya.test'),
        'timeout' => (int) env('MIDTRANS_IRIS_TIMEOUT', 30),
    ],

];
