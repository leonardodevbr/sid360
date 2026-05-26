<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Settings::set('app_name', 'Sid360', 'string', 'general');
        Settings::set('allowed_login_methods', ['email', 'username'], 'json', 'auth');

        Settings::setDefault('whatsapp_notifications_enabled', '1', 'boolean', 'whatsapp');

        Settings::setDefault('whatsapp_welcome_enabled', '1', 'boolean', 'whatsapp');
        Settings::setDefault('whatsapp_welcome_message',
            "Olá, *{nome}*! 🎉\n\nSua compra foi registrada com sucesso na *Sid360 Imóveis*!\n\n📋 Contrato: *{contrato}*\n🏠 Empreendimento: *{empreendimento}*\n📍 Lote: *{lote}*\n💰 Valor total: *{valor_total}*\n📅 1ª parcela: *{primeira_parcela}*\n\nAcompanhe seus pagamentos em:\n🔗 *sid360.com.br/pagamentos*\n\nDúvidas? Fale conosco: 📱 (74) 9 8823-0151\n\n_Bem-vindo(a) à família Sid360!_ 🏡",
            'string', 'whatsapp'
        );

        Settings::setDefault('whatsapp_reminder_enabled', '1', 'boolean', 'whatsapp');
        Settings::setDefault('whatsapp_reminder_days_before', '3', 'integer', 'whatsapp');
        Settings::setDefault('whatsapp_reminder_message',
            "Olá, *{nome}*! 👋\n\nLembrando que sua parcela está vencendo em *{dias} dias*:\n\n📋 Contrato: *{contrato}*\n🏠 Lote: *{lote}*\n💰 Valor: *{valor}*\n📅 Vencimento: *{vencimento}*\n\nDúvidas, fale conosco: 📱 (74) 9 8823-0151\n_Sid360 Imóveis_",
            'string', 'whatsapp'
        );

        Settings::setDefault('whatsapp_overdue_enabled', '1', 'boolean', 'whatsapp');
        Settings::setDefault('whatsapp_overdue_message',
            "Olá, *{nome}*! ⚠️\n\nIdentificamos *{qtd_atrasadas} parcela(s) em atraso* no contrato *{contrato}*:\n\n{parcelas_atrasadas}\n\n💰 Total em aberto: *{valor_total_atraso}*\n💰 Total corrigido (prev. p/ {data_pagamento_prevista}): *{valor_total_corrigido}*\n\n⚠️ Estimativa com multa de 2,5% ao mês (pró-rata por dia).\n\nResponda com o número da opção desejada:\n*1* - Estou ciente, vou regularizar em breve\n*2* - Quero o link para pagar (PIX/boleto atualizado)\n*3* - Preciso negociar / falar com o corretor\n\n_Sid360 Imóveis · (74) 9 8823-0151_",
            'string', 'whatsapp'
        );

        Settings::setDefault('whatsapp_manual_overdue_message',
            "Olá, *{nome}*! Tudo bem?\n\nAqui é da *Sid360 Imóveis*. Estou entrando em contato pessoalmente sobre o pagamento abaixo:\n\nContrato: *{contrato}*\n{parcela}\nVencimento: *{vencimento}*\nValor: *{valor}*\n\nGostaria de entender se houve alguma dificuldade e como podemos ajudar na regularização.\n\nAguardo seu retorno.\n_Sid360 Imóveis_",
            'string', 'whatsapp'
        );

        Settings::setDefault('whatsapp_reply_window_hours', '48', 'integer', 'whatsapp');
        Settings::setDefault('whatsapp_sid_phone', '5574988230151', 'string', 'whatsapp');

        Settings::setDefault('email_notifications_enabled', '1', 'boolean', 'email');
        Settings::setDefault('email_welcome_enabled', '1', 'boolean', 'email');
        Settings::setDefault('email_reminder_enabled', '1', 'boolean', 'email');
        Settings::setDefault('email_overdue_enabled', '1', 'boolean', 'email');

        $this->command->info('Configurações iniciais criadas.');
    }
}
