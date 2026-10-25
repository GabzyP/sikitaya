<?php

return [

    /*
    | Mode "dummy" memakai data kota dan tarif simulasi bawaan sehingga
    | aplikasi dapat dijalankan tanpa API key. Mode "api" akan memanggil
    | RajaOngkir Starter dan menampilkan error yang jelas jika gagal.
    */
    'mode' => env('RAJAONGKIR_MODE', 'dummy'),

    'key' => env('RAJAONGKIR_API_KEY'),

    'base_url' => rtrim(env('RAJAONGKIR_BASE_URL', 'https://api.rajaongkir.com/starter'), '/'),

    'origin_city_id' => (int) env('RAJAONGKIR_ORIGIN_CITY_ID', 278),

    'couriers' => [
        'jne' => 'JNE',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
    ],

    'timeout' => (int) env('RAJAONGKIR_TIMEOUT', 15),

];
