<?php

return [
    'loteamento' => [
        'name' => env('LOTEAMENTO_NAME', 'Novo Loteamento — Cafarnaum, BA'),
        'address' => env('LOTEAMENTO_ADDRESS', 'Cafarnaum, Bahia, Brasil'),
        'lat' => (float) env('LOTEAMENTO_LAT', -11.4667),
        'lng' => (float) env('LOTEAMENTO_LNG', -39.9833),
        'maps_embed_url' => env('GOOGLE_MAPS_EMBED_URL'),
    ],
];
