# Controle chatbot WhatsApp SID360

## Migration (rodar manualmente — não automático em produção)

```bash
php artisan migrate --path=database/migrations/2026_06_09_000001_create_whatsapp_conversation_states_table.php
```

Tabela: `whatsapp_conversation_states` — estado por telefone (`bot_active`, `bot_paused`, `human`).

## Arquivos criados

- `database/migrations/2026_06_09_000001_create_whatsapp_conversation_states_table.php`
- `app/Models/WhatsappConversationState.php`
- `app/Services/WhatsappConversationStateService.php`
- `app/Support/WhatsappBotMessageFooter.php`
- `tests/Feature/WhatsappBotConversationStateTest.php`

## Arquivos modificados

- `app/Support/WhatsappCommandParser.php`
- `app/Actions/Whatsapp/ProcessWhatsappBotMessageAction.php`
- `app/Services/WhatsappBotService.php`
- `app/Models/InstallmentInteraction.php`
- `tests/Unit/WhatsappCommandParserTest.php`
- `tests/Unit/WhatsappBotServiceTest.php`

## Testes locais

```bash
composer install
./vendor/bin/phpunit tests/Feature/WhatsappBotConversationStateTest.php
./vendor/bin/phpunit tests/Feature/WhatsappWebhookBotTest.php
./vendor/bin/phpunit tests/Unit/WhatsappCommandParserTest.php
```

## Comportamento

| Comando | Ação |
|---|---|
| SAIR, PARAR, CANCELAR, ENCERRAR, REMOVER | Pausa bot (confirma 1x) |
| INICIAR, MENU, VOLTAR | Reativa bot + menu |
| ATENDIMENTO, HUMANO, CORRETOR, FALAR COM CORRETOR | Modo humano 24h |
| Contato desconhecido | Registra evento, sem resposta |
| fromMe=true | Ignorado no webhook |
