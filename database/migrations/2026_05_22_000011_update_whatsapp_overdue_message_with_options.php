<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    private const OVERDUE_MESSAGE = <<<'TEXT'
Olá, *{nome}*! ⚠️

Identificamos *{qtd_atrasadas} parcela(s) em atraso* no contrato *{contrato}*:

{parcelas_atrasadas}

💰 Total em aberto: *{valor_total_atraso}*
💰 Total corrigido (prev. p/ {data_pagamento_prevista}): *{valor_total_corrigido}*

⚠️ Estimativa com multa de 2,5% ao mês (pró-rata por dia).

Responda com o número da opção desejada:
*1* - Estou ciente, vou regularizar em breve
*2* - Quero o link para pagar (PIX/boleto atualizado)
*3* - Preciso negociar / falar com o corretor

_Sid360 Imóveis · (74) 9 8823-0151_
TEXT;

    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'whatsapp_overdue_message'],
            [
                'value' => self::OVERDUE_MESSAGE,
                'type' => 'string',
                'group' => 'whatsapp',
            ]
        );

        Cache::forget('settings.all');
    }

    public function down(): void
    {
        //
    }
};
