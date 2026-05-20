# Sid360 — Roadmap de Desenvolvimento

> Atualizado conforme proposta comercial fechada com Sid Nunes.
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
- [x] Landing page HTML estática com identidade Sid360
- [x] Seção Hero com estatísticas e cards flutuantes
- [x] Seção de tipos de imóveis (casas, comercial, rural, frente BR)
- [x] Seção de lotes em destaque com preços reais
- [x] Seção de diferenciais
- [x] Seção de contato com botão WhatsApp
- [x] Footer com CRECI e dados
- [x] Paleta de cores e tipografia da identidade visual
- [ ] Favicon e logo PNG do Sid360 adicionados ao site
- [ ] Meta tags SEO (description, og:image, og:title)
- [ ] Google Analytics / Meta Pixel (se o Sid quiser)

### Sistema — Empreendimentos & Lotes
- [x] CRUD de Empreendimentos (nome, descrição, localização, status)
- [x] CRUD de Lotes (número, quadra, área, valor, status)
- [x] Filtro de lotes por empreendimento
- [x] Filtro por status (disponível, reservado, vendido)
- [x] Dashboard com totais e empreendimentos recentes
- [ ] Status visual por cor nos lotes (verde/amarelo/cinza)
- [ ] Exportação da listagem de lotes em Excel (maatwebsite já instalado)

### Sistema — Mapeamento de Lotes (Leaflet.js) · P1
- [ ] Migration: adicionar coluna `coordinates` (JSON) na tabela `lots`
  - [ ] Armazena array de pontos `[[lat, lng], ...]` do polígono desenhado
- [ ] Adicionar coluna `center_lat` e `center_lng` (calculado automaticamente)
- [ ] Instalar Leaflet via npm (`npm install leaflet`)
- [ ] Componente `LotMap.vue` (mapa interativo)
  - [ ] Exibir mapa OpenStreetMap centralizado em Cafarnaum-BA
  - [ ] Ferramenta de desenho de polígono (Leaflet.draw)
  - [ ] Ao finalizar polígono: calcular área automaticamente (m²) e preencher campo
  - [ ] Editar polígono existente ao abrir lote já cadastrado
  - [ ] Deletar polígono e redesenhar
  - [ ] Exibir todos os lotes do empreendimento no mapa com cor por status
    - [ ] Verde → disponível
    - [ ] Amarelo → reservado
    - [ ] Cinza → vendido
  - [ ] Clique no polígono de outro lote → abre popup com dados básicos
- [ ] Integrar `LotMap.vue` no formulário de cadastro/edição de lote
- [ ] Integrar mapa de visão geral na tela do empreendimento (readonly, todos os lotes)
- [ ] API: `GET /developments/{id}/lots-map` → retorna lotes com coordenadas para o mapa

### Sistema — Clientes
- [ ] Migration: tabela `clients`
  - [ ] campos: nome, CPF/CNPJ, telefone, WhatsApp, e-mail, endereço
  - [ ] campos: data de nascimento, observações
- [ ] Model `Client` com relacionamentos
- [ ] CRUD de Clientes no backend (Actions + Controller + Resource + FormRequest)
- [ ] Validação de CPF/CNPJ único
- [ ] Tela de listagem de clientes (Vue) com busca por nome/CPF
- [ ] Tela de cadastro/edição de cliente
- [ ] Histórico de transações do cliente (lotes vinculados)

### Sistema — Vendas (Vínculo Cliente ↔ Lote)
- [ ] Migration: tabela `sales`
  - [ ] campos: client_id, lot_id, total_value, down_payment, installment_value
  - [ ] campos: installments_count, status (active, finished, cancelled), sale_date
  - [ ] campos: notes
- [ ] Model `Sale` com relacionamentos (client, lot, installments)
- [ ] Ao criar venda: lote muda automaticamente para `reserved` ou `sold`
- [ ] Ao cancelar venda: lote volta para `available`
- [ ] CRUD de Vendas no backend
- [ ] Tela de nova venda (selecionar cliente + lote + condições)
- [ ] Listagem de vendas com filtros por status, cliente, empreendimento

### Sistema — Parcelas
- [ ] Migration: tabela `installments`
  - [ ] campos: sale_id, number, due_date, value, paid_at, paid_value, status
  - [ ] campos: status (pending, paid, overdue, cancelled)
- [ ] Model `Installment`
- [ ] Geração automática das parcelas ao criar uma venda
- [ ] Controller de Parcelas (marcar como pago, listar por venda)
- [ ] Tela de parcelas de uma venda
- [ ] Dashboard: card de inadimplência (parcelas vencidas)
- [ ] Dashboard: card de receita do mês

---

## 🟡 P2 — MVP Completo (Semanas 5–8)

### Sistema — Contratos em PDF
- [ ] Template Blade para contrato de compra e venda
  - [ ] Dados do vendedor (Sid Nunes + CRECI)
  - [ ] Dados do comprador (cliente)
  - [ ] Dados do imóvel (lote, quadra, empreendimento, área, valor)
  - [ ] Tabela de parcelas
  - [ ] Cláusulas contratuais padrão
  - [ ] Campo de assinatura
- [ ] Geração do PDF com DomPDF (já instalado)
- [ ] Rota `/api/sales/{id}/contract` → retorna PDF
- [ ] Botão "Gerar Contrato" na tela da venda

### Sistema — Promissórias
- [ ] Migration: tabela `promissory_notes`
  - [ ] campos: sale_id, installment_id, number, value, due_date, issued_at, status
- [ ] Model `PromissoryNote`
- [ ] CRUD de Promissórias
- [ ] Geração de PDF da promissória com DomPDF
- [ ] Listagem de promissórias com filtro por vencimento e status
- [ ] Alerta de promissórias a vencer (próximos 7 dias)

### Sistema — Financeiro
- [ ] Tela de relatório financeiro
  - [ ] Receita prevista vs recebida por mês
  - [ ] Inadimplência total (valor e %)
  - [ ] Parcelas vencidas listadas
  - [ ] Parcelas a vencer nos próximos 30 dias
- [ ] Exportação do relatório em Excel
- [ ] Dashboard atualizado com gráficos básicos (Chart.js ou recharts)

### Pagamentos PIX (EfiPay ou MercadoPago)
- [ ] Integração com gateway (MercadoPago SDK já no composer)
- [ ] Geração de QR Code PIX por parcela
- [ ] Webhook de confirmação automática de pagamento
- [ ] Ao confirmar: parcela marcada como paga automaticamente
- [ ] Notificação de confirmação enviada ao cliente (WhatsApp ou e-mail)
- [ ] Tela de segunda via de boleto/PIX para o cliente

### WhatsApp — Lembretes Automáticos
- [ ] Configuração do WPPConnect no servidor
- [ ] Job agendado: lembretes de parcelas a vencer (3 dias antes)
- [ ] Job agendado: aviso de parcela vencida (no dia + D+3)
- [ ] Template de mensagem de lembrete personalizado
- [ ] Template de mensagem de cobrança (inadimplência)
- [ ] Log de mensagens enviadas
- [ ] Configuração on/off por cliente ("não enviar WhatsApp")

### WhatsApp — Auto-atendimento (Bot)
- [ ] Comando: "2ª via" → cliente recebe link do PIX/boleto
- [ ] Comando: "saldo" → cliente vê parcelas pendentes
- [ ] Comando: "extrato" → histórico de pagamentos
- [ ] Comando: "contrato" → link para download do PDF
- [ ] Fallback: "Falar com o Sid" → transfere para atendimento humano

---

## 🟢 P3 — Pós-MVP (Mês 2+)

### Site Público — Melhorias
- [ ] Listagem dinâmica de lotes disponíveis (puxado da API)
- [ ] Página de detalhe do lote com mapa Leaflet (readonly, exibe polígono)
- [ ] Formulário de interesse (nome + telefone) → notifica o Sid
- [ ] Botão de compartilhar lote individual (WhatsApp)
- [ ] Galeria de fotos por empreendimento

### Sistema — Mídia
- [ ] Upload de fotos por empreendimento
- [ ] Upload de fotos por lote
- [ ] Armazenamento em nuvem (S3 ou similar)
- [ ] Galeria de fotos nas telas do sistema

### Sistema — Multi-usuário
- [ ] Perfil "Vendedor" com acesso restrito
  - [ ] Só vê clientes e vendas que criou
  - [ ] Não vê dados financeiros completos
- [ ] Perfil "Suporte" com acesso ao bot WhatsApp
- [ ] Log de atividades por usuário (auditoria)

### Sistema — Notificações Web Push
- [x] Service Worker registrado
- [x] VAPID configurado
- [ ] Notificação quando pagamento é confirmado
- [ ] Notificação de nova mensagem no bot
- [ ] Notificação de parcela vencida sem pagamento (3+ dias)

### Melhorias Gerais
- [ ] Tela de configurações do sistema (dados do Sid/CRECI, templates WhatsApp)
- [ ] Backup automático do banco de dados
- [ ] Modo escuro (opcional)
- [ ] App PWA instalável no celular do Sid

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
| Login admin | `admin@sid360.com.br` |
| Senha padrão | `123$qweR---` _(trocar em produção)_ |
| WhatsApp Sid | `(74) 9 8823-0151` |
| Hospedagem | Branix (shared) |
| DNS | Cloudflare |
| Repositório | `github.com/leonardodevbr/sid360` |

---

## Progresso Geral

```
Infraestrutura  ████████░░  80%
Site Público    ████████░░  85%
Empreend/Lotes  ████████░░  90%
Clientes        ░░░░░░░░░░   0%
Vendas          ░░░░░░░░░░   0%
Parcelas        ░░░░░░░░░░   0%
Contratos PDF   ░░░░░░░░░░   0%
Promissórias    ░░░░░░░░░░   0%
Financeiro      ░░░░░░░░░░   0%
PIX             ░░░░░░░░░░   0%
WhatsApp        ░░░░░░░░░░   0%
```

**MVP Core estimado:** 4 semanas · **MVP Completo:** 8 semanas
