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
            "Olá, *{nome}*! ⚠️\n\nIdentificamos uma parcela em *atraso* em seu contrato:\n\n📋 Contrato: *{contrato}*\n🏠 Lote: *{lote}*\n💰 Valor: *{valor}*\n📅 Vencimento: *{vencimento}*\n\n⚠️ Após o vencimento incide multa de *2,5% ao mês*.\n\nPara regularizar: 📱 (74) 9 8823-0151\n_Sid360 Imóveis_",
            'string', 'whatsapp'
        );

        $this->command->info('Configurações iniciais criadas.');
    }
}
