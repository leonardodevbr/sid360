<?php

return [
    'loteamento' => [
        'name' => env('LOTEAMENTO_NAME', 'Novo Loteamento — Cafarnaum, BA'),
        'address' => env('LOTEAMENTO_ADDRESS', 'Cafarnaum, Bahia, Brasil'),
        'lat' => (float) env('LOTEAMENTO_LAT', -11.4667),
        'lng' => (float) env('LOTEAMENTO_LNG', -39.9833),
        'maps_embed_url' => env('GOOGLE_MAPS_EMBED_URL'),
        'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'lots' => [
        'frente-br' => [
            'name' => 'Lote Frente à Rodovia',
            'price_original' => 70000,
            'price_installment' => 65000,
            'price_cash' => 60000,
            'down_payment_min' => 13000,
            'installment_ref' => 2000,
        ],
        'residencial' => [
            'name' => 'Lote Residencial 20×30',
            'price_original' => 30000,
            'price_installment' => 30000,
            'price_cash' => 25000,
            'down_payment_min' => 6000,
            'installment_ref' => 1000,
        ],
    ],
];
