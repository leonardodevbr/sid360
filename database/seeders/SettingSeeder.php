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

        Settings::setDefault('whatsapp_bot_enabled', '1', 'boolean', 'whatsapp');
        // Desligado por padrão: dispara mensagem pedindo CPF/telefone pra
        // QUALQUER contato não identificado (inclusive pessoais, não só
        // clientes). Só ativar se o número conectado for de uso exclusivo
        // para atendimento — ver ProcessWhatsappBotMessageAction::promptForIdentification().
        Settings::setDefault('whatsapp_unknown_contact_prompt_enabled', '0', 'boolean', 'whatsapp');
        Settings::setDefault('whatsapp_bot_menu_message',
            "Olá, *{nome}*! Sou o assistente *Sid360*.\n\nDigite um comando:\n\n*2ª via* — receber PIX ou boleto\n*saldo* — parcelas pendentes\n*extrato* — histórico de pagamentos\n*contrato* — PDF do contrato\n*atendimento* — falar com o corretor\n\nPortal: {portal_url}",
            'string', 'whatsapp'
        );

        Settings::setDefault('wppconnect_base_url', '', 'string', 'whatsapp_integration');
        Settings::setDefault('wppconnect_session', '', 'string', 'whatsapp_integration');
        Settings::setDefault('wppconnect_token', '', 'string', 'whatsapp_integration');
        Settings::setDefault('whatsapp_webhook_key', '', 'string', 'whatsapp_integration');

        Settings::setDefault('email_notifications_enabled', '1', 'boolean', 'email');
        Settings::setDefault('email_welcome_enabled', '1', 'boolean', 'email');
        Settings::setDefault('email_reminder_enabled', '1', 'boolean', 'email');
        Settings::setDefault('email_overdue_enabled', '1', 'boolean', 'email');

        // Dados do vendedor/empresa exibidos no contrato (App\Support\ContractParty).
        // Podem ser sobrescritos por empreendimento em developments.seller_*.
        // Defaults reproduzem os valores que estavam hardcoded no template.
        Settings::setDefault('vendedor_nome', 'Sidiclei Novais Baretto', 'string', 'contrato');
        Settings::setDefault('vendedor_cpf', '311.168.558-60', 'string', 'contrato');
        Settings::setDefault('vendedor_rg', '08.280.665-90', 'string', 'contrato');
        Settings::setDefault('vendedor_rg_issuer', 'SSP/BA', 'string', 'contrato');
        Settings::setDefault(
            'vendedor_endereco',
            'Rua Arlindo Montino, nº 4, s/nº, Centro, Cafarnaum — Bahia',
            'string',
            'contrato',
        );
        Settings::setDefault('empresa_nome', 'Sid360 Imóveis', 'string', 'contrato');
        Settings::setDefault('empresa_tagline', 'Imóveis Residencial, Comercial e Rural', 'string', 'contrato');
        Settings::setDefault('empresa_site', 'sid360.com.br', 'string', 'contrato');
        Settings::setDefault('foro_cidade', 'Cafarnaum', 'string', 'contrato');
        Settings::setDefault('foro_estado', 'BA', 'string', 'contrato');
        Settings::setDefault('foro_estado_extenso', 'Bahia', 'string', 'contrato');

        $this->command->info('Configurações iniciais criadas.');
    }
}
