# Sid360 — Roadmap de Desenvolvimento

> Atualizado em **29/05/2026** conforme proposta comercial fechada com Sid Nunes e auditoria do código.
> Marque cada item com `[x]` conforme for concluído.

---

## Legenda de Prioridade

- 🔴 **P1 — MVP Core** · Prometido no prazo de 1 mês. Sem isso o sistema não tem valor.
- 🟡 **P2 — MVP Completo** · Entrega nas semanas seguintes. Fecha o escopo da proposta.
- 🟢 **P3 — Pós-MVP** · Melhorias e integrações que agregam valor contínuo.

---

## 🔴 P1 — MVP Core (Semanas 1–4)

### Infraestrutura & Deploy
- [x] Repositório GitHub criado (`leonardodevbr/sid360`)
- [x] CI/CD via GitHub Actions → Branix (SSH deploy)
- [x] Domínio `sid360.com.br` configurado na Cloudflare
- [x] Subdomínio `sistema.sid360.com.br` apontado para o sistema
- [x] Laravel 12 instalado e configurado
- [x] Migrations base (users, settings, cache, jobs, sessions)
- [x] Autenticação via Sanctum (token-based)
- [x] Permissões via Spatie (roles: super-admin, admin)
- [x] Seeders: roles, permissões, usuário admin, settings iniciais
- [ ] `.env` de produção configurado no servidor Branix
- [ ] `php artisan storage:link` executado em produção
- [ ] `php artisan migrate --seed` executado em produção

### Site Público (`sid360.com.br`)
- [x] Site público em Vue SPA (`resources/js/site/`) com identidade Sid360
- [x] Página inicial com hero, estatísticas e simulador de parcelas
- [x] Listagem de loteamentos (`/loteamentos`) alimentada pela API
- [x] Página de detalhe do empreendimento com mapa, lotes e simulador
- [x] Seção de diferenciais e contato com botão WhatsApp
- [x] Paleta de cores e tipografia da identidade visual
- [x] Formulário de interesse (lead) na página do loteamento
- [x] Favicon no sistema admin (`public/favicon/`)
- [ ] Favicon e meta tags SEO (description, og:image, og:title) no site público
- [ ] Google Analytics / Meta Pixel (se o Sid quiser)

### Sistema — Empreendimentos & Lotes
- [x] CRUD de Empreendimentos (nome, descrição, localização, status)
- [x] CRUD de Lotes (número, quadra, área, valor, status)
- [x] Zonas e ruas por empreendimento, com geração automática de lotes
- [x] Filtro de lotes por empreendimento
- [x] Filtro por status (disponível, reservado, vendido)
- [x] Dashboard com totais e empreendimentos recentes
- [x] Status visual por cor nos lotes (badges + cores no mapa)
- [x] Galeria de mídia por empreendimento e por lote (Cloudflare R2)
- [ ] Exportação da listagem de lotes em Excel (Maatwebsite instalado, sem Export implementado)

### Sistema — Mapeamento de Lotes (Leaflet.js) · P1
- [x] Migration: coluna `coordinates` (JSON) na tabela `lots`
  - [x] Armazena array de pontos `[[lat, lng], ...]` do polígono desenhado
- [x] Coluna `area_computed` calculada automaticamente no frontend
- [ ] Colunas `center_lat` e `center_lng` persistidas no banco (centro calculado em runtime)
- [x] Leaflet instalado via npm (`leaflet`, `leaflet-draw`)
- [x] Componente `MapDrawingCanvas.vue` (mapa interativo — usado no formulário de lote)
- [x] Componente `LotMap.vue` (alternativa legada, disponível em `components/Common/`)
  - [x] Exibir mapa OpenStreetMap centralizado no empreendimento
  - [x] Ferramenta de desenho de polígono
  - [x] Ao finalizar polígono: calcular área automaticamente (m²) e preencher campo
  - [x] Editar polígono existente ao abrir lote já cadastrado
  - [x] Deletar polígono e redesenhar
  - [x] Exibir todos os lotes do empreendimento no mapa com cor por status
    - [x] Verde → disponível
    - [x] Amarelo → reservado
    - [x] Cinza → vendido
  - [x] Clique no polígono de outro lote → popup com dados básicos
- [x] Mapa integrado no formulário de cadastro/edição de lote
- [x] Mapa de visão geral na tela do empreendimento (readonly, todos os lotes, zonas e ruas)
- [x] API: `GET /developments/{id}/lots` → retorna lotes com coordenadas para o mapa

### Sistema — Clientes
- [x] Migration: tabela `clients`
  - [x] campos: nome, CPF/CNPJ, telefone, e-mail, endereço (CEP, cidade, UF)
  - [x] campos: RG, profissão, estado civil, observações
  - [ ] campo: data de nascimento
  - [ ] campo: WhatsApp separado do telefone (usa `phone` + `whatsapp_status`)
- [x] Model `Client` com relacionamentos
- [x] CRUD de Clientes no backend (Actions + Controller + Resource + FormRequest)
- [x] Validação de CPF/CNPJ único
- [x] Tela de listagem de clientes (Vue) com busca por nome/CPF
- [x] Tela de cadastro/edição de cliente
- [ ] Histórico de transações do cliente (vendas/lotes vinculados na tela do cliente)

### Sistema — Vendas (Vínculo Cliente ↔ Lote)
- [x] Migration: tabela `sales`
  - [x] campos: client_id, lot_id, total_value, down_payment, installment_value
  - [x] campos: installments_count, status (active, completed, cancelled), sale_date
  - [x] campos: notes, descontos, contrato assinado (upload)
- [x] Tabela pivot `sale_clients` (co-compradores)
- [x] Model `Sale` com relacionamentos (client, lot, installments, buyers)
- [x] Ao criar venda: lote muda automaticamente para `sold`
- [ ] Ao criar venda: opção de marcar lote como `reserved` antes da venda final
- [x] Ao excluir venda: lote volta para `available` (`SaleObserver`)
- [ ] Ao cancelar venda (status `cancelled`): lote volta para `available` sem excluir registro
- [x] CRUD de Vendas no backend
- [x] Tela de nova venda (selecionar cliente + lote + condições + co-compradores)
- [x] Tela de detalhe da venda com parcelas, contrato, carnê e interações WhatsApp
- [x] Listagem de vendas com filtros por status, cliente, empreendimento

### Sistema — Parcelas
- [x] Migration: tabela `installments`
  - [x] campos: sale_id, number, due_date, value, paid_at, paid_value, status
  - [x] campos: type (down_payment, financing), status (pending, paid, overdue)
  - [x] campos Efi: PIX, boleto, carnê bancário
- [x] Model `Installment`
- [x] Geração automática das parcelas ao criar uma venda (`SaleObserver`)
- [x] Controller de Parcelas (marcar como pago, listar por venda)
- [x] Tela de parcelas na venda (`Sales/Show.vue`)
- [x] Dashboard: card de inadimplência (parcelas vencidas)
- [x] Dashboard: card de receita do mês (recebido / pendente / atrasado)
- [x] Cálculo de multa/juros em parcelas atrasadas (`InstallmentPenaltyService`)

### Sistema — Leads (implementado além do escopo original P1)
- [x] Migration: tabela `leads`
- [x] Captura de leads pelo site público (`POST /public/leads`)
- [x] Listagem admin de leads com status
- [x] Conversão de lead em venda

### Sistema — Portal do Cliente (implementado além do escopo original P1)
- [x] Acesso por CPF + OTP via WhatsApp (`/pagamentos`)
- [x] Visualização de parcelas pendentes e pagas
- [x] Solicitação de PIX, boleto e segunda via

---

## 🟡 P2 — MVP Completo (Semanas 5–8)

### Sistema — Contratos em PDF
- [x] Template Blade para contrato de compra e venda
  - [x] Dados do vendedor (Sid Nunes + CRECI via settings)
  - [x] Dados do comprador (cliente)
  - [x] Dados do imóvel (lote, quadra, empreendimento, área, valor)
  - [x] Tabela de parcelas
  - [x] Cláusulas contratuais padrão
  - [x] Campo de assinatura
- [x] Geração do PDF com DomPDF
- [x] Rota `GET /api/sales/{id}/contract` → retorna PDF
- [x] Botão "Gerar Contrato" na tela da venda
- [x] Upload e download de contrato assinado

### Sistema — Promissórias / Carnê
- [x] Geração de PDF do carnê/promissórias a partir das parcelas (`/sales/{id}/carne`)
- [x] Template Blade `pdf/carne.blade.php` + preview HTML (dev)
- [x] Geração de carnê bancário via Efi
- [ ] Migration: tabela `promissory_notes` (entidade independente)
- [ ] Model `PromissoryNote` + CRUD standalone
- [ ] Listagem de promissórias com filtro por vencimento e status
- [ ] Alerta de promissórias a vencer (próximos 7 dias)

### Sistema — Financeiro
- [x] Resumo financeiro no dashboard (receita, pendente, inadimplência)
- [x] Listagem de parcelas vencidas e a vencer no dashboard
- [ ] Tela dedicada de relatório financeiro
  - [ ] Receita prevista vs recebida por mês
  - [ ] Inadimplência total (valor e %)
  - [ ] Parcelas vencidas listadas (filtros avançados)
  - [ ] Parcelas a vencer nos próximos 30 dias
- [ ] Exportação do relatório em Excel
- [ ] Dashboard com gráficos básicos (Chart.js ou recharts)

### Pagamentos PIX (EfiPay — gateway em uso)
- [x] Integração com EfiPay (`EfiService`, SDK no composer)
- [ ] Integração MercadoPago (SDK instalado, não utilizado)
- [x] Geração de QR Code PIX por parcela
- [x] Geração de boleto por parcela
- [x] Webhook PIX e cobranças → confirmação automática de pagamento
- [x] Ao confirmar: parcela marcada como paga automaticamente
- [x] Envio manual de PIX/boleto via WhatsApp pelo admin
- [ ] Notificação automática de confirmação ao cliente (e-mail implementado parcialmente)
- [x] Tela de segunda via PIX/boleto para o cliente (portal `/pagamentos`)

### WhatsApp — Lembretes Automáticos
- [x] Integração WhatsApp (`WhatsappService` + webhook WPPConnect)
- [x] Job agendado: lembretes de parcelas a vencer (`installments:send-reminders`, diário 09:00)
- [x] Job agendado: aviso de parcela vencida (resumo por contrato)
- [x] Mensagem de boas-vindas ao cadastrar venda
- [x] Templates de mensagem configuráveis em Configurações
- [x] Log de mensagens enviadas (`installment_interactions`)
- [x] Opt-out por cliente respeitado nos envios automáticos (`whatsapp_status = none`)

### WhatsApp — Auto-atendimento (Bot)
- [x] Respostas numéricas (1/2/3) após lembrete: confirmar, PIX/boleto Efi, negociar
- [x] Link do portal de pagamentos enviado nas mensagens
- [x] Comando livre: "2ª via" → gera e envia PIX (fallback boleto) via Efi
- [x] Comando livre: "saldo" → cliente vê parcelas pendentes
- [x] Comando livre: "extrato" → histórico de pagamentos
- [x] Comando livre: "contrato" → envia PDF do contrato
- [x] Fallback: "atendimento" / "falar com sid" → notifica corretor
- [x] Menu de ajuda para mensagens não reconhecidas (template configurável)
- [x] Opt-out respeitado nos jobs (`whatsapp_status = none`)

### Sistema — Usuários & Autenticação (implementado além do escopo original P2)
- [x] CRUD de usuários com atribuição de roles
- [x] Recuperação de senha (forgot/reset)
- [x] Perfil do usuário logado

---

## 🟢 P3 — Pós-MVP (Mês 2+)

### Site Público — Melhorias
- [x] Listagem dinâmica de lotes disponíveis (API `/public/developments`, `/public/lots/available`)
- [x] Página de detalhe do lote/empreendimento com mapa Leaflet (readonly, exibe polígono)
- [x] Formulário de interesse (nome + telefone) → grava lead e notifica admin
- [x] Links WhatsApp com mensagem pré-preenchida (simulador e interesse)
- [x] Galeria de fotos por empreendimento (via API de mídia)
- [ ] Botão de compartilhar lote individual (Web Share API)

### Sistema — Mídia
- [x] Upload de fotos por empreendimento
- [x] Upload de fotos por lote
- [x] Armazenamento em nuvem (Cloudflare R2)
- [x] Galeria de fotos nas telas do sistema (`MediaGallery.vue`)

### Sistema — Multi-usuário
- [ ] Perfil "Vendedor" com acesso restrito (opção na UI, role não seedada)
  - [ ] Só vê clientes e vendas que criou
  - [ ] Não vê dados financeiros completos
- [ ] Perfil "Suporte" com acesso ao bot WhatsApp
- [ ] Log de atividades por usuário (auditoria)

### Sistema — Notificações Web Push
- [x] Service Worker registrado
- [x] VAPID configurado
- [x] Endpoints de inscrição e teste
- [ ] Notificação quando pagamento é confirmado
- [ ] Notificação de nova mensagem no bot
- [ ] Notificação de parcela vencida sem pagamento (3+ dias)

### Melhorias Gerais
- [x] Tela de configurações do sistema (dados do Sid/CRECI, templates WhatsApp, e-mail)
- [ ] Backup automático do banco de dados
- [ ] Modo escuro (opcional)
- [ ] App PWA instalável no celular do Sid (manifest parcial via favicon)

---

## Contrato & Acordo

- [x] Proposta comercial entregue (PDF)
- [x] Negociação fechada via WhatsApp
- [x] Acordo: sistema vale R$15.000 como entrada no lote
- [x] Lote: R$30.000 total (R$15k entrada via sistema + R$1.000/mês)
- [ ] Contrato formal entre Leonardo e Sid assinado
- [ ] 1 ano de infraestrutura (Branix + Cloudflare) absorvido por Leonardo

---

## Dados de Acesso (Produção)

| Item | Valor |
|---|---|
| Site | `sid360.com.br` |
| Sistema | `sistema.sid360.com.br` |
| Portal cliente | `sid360.com.br/pagamentos` |
| Login admin | `admin@sid360.com.br` |
| Senha padrão | `123$qweR---` _(trocar em produção)_ |
| WhatsApp Sid | `(74) 9 8823-0151` |
| Hospedagem | Branix (shared) |
| DNS | Cloudflare |
| Repositório | `github.com/leonardodevbr/sid360` |

---

## Progresso Geral

```
Infraestrutura  ████████░░  80%   (pendente: .env produção, migrate/seed)
Site Público    █████████░  90%   (pendente: SEO, analytics)
Empreend/Lotes  ██████████  98%   (pendente: export Excel)
Mapeamento      █████████░  92%   (pendente: center_lat/lng no banco)
Clientes        ████████░░  85%   (pendente: histórico, data nascimento)
Vendas          █████████░  90%   (pendente: cancelamento sem delete)
Parcelas        ██████████  98%
Leads/Portal    ██████████ 100%   (extra — implementado)
Contratos PDF   ██████████ 100%
Promissórias    █████░░░░░  50%   (PDF/carnê ok; CRUD standalone pendente)
Financeiro      ████░░░░░░  40%   (dashboard ok; relatório dedicado pendente)
PIX (Efi)       █████████░  90%   (MercadoPago não utilizado)
WhatsApp        █████████░  92%   (bot completo; templates configuráveis)
Mídia           ██████████ 100%   (extra — implementado)
Multi-usuário   ██░░░░░░░░  20%   (roles admin/super-admin apenas)
```

**MVP Core (P1):** ~90% concluído · **MVP Completo (P2):** ~75% concluído

### Principais pendências para fechar o escopo

1. Deploy final em produção (`.env`, `storage:link`, `migrate --seed`)
2. Relatório financeiro dedicado + exportação Excel
3. ~~Bot WhatsApp com comandos livres~~ (concluído)
4. Cancelamento de venda sem exclusão + histórico do cliente
5. Roles Vendedor/Suporte com escopo de permissões
6. SEO do site público (meta tags, og:image)
