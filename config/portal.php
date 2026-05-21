<?php

declare(strict_types=1);

return [
    'token_ttl_minutes' => (int) env('PORTAL_TOKEN_TTL_MINUTES', 120),
    'whatsapp_number' => env('PORTAL_WHATSAPP_NUMBER', '5574988230151'),
];
