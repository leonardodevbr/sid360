# DiáriaSys – Visão geral para apresentação

Visão geral do **Sistema de Concessão de Diárias** para uso em apresentações e divulgação do sistema.

---

## O que é o DiáriaSys

O DiáriaSys é um sistema para **gestão de diárias e passagens** de servidores públicos municipais. Controla todo o ciclo: da solicitação à validação, autorização e pagamento, com regras por secretaria e por perfil de acesso.

**Público:** prefeituras e órgãos que precisam padronizar e auditar a concessão de diárias conforme legislação e controle interno.

---

## Fluxo de uma diária

1. **Solicitação** – O requerente registra a diária (servidor, destino, datas, finalidade).
2. **Validação** – O responsável pela secretaria valida a solicitação.
3. **Concessão** – O concedente (ex.: prefeito) autoriza o pagamento.
4. **Pagamento** – O pagador registra que a diária foi paga.
5. **Cancelamento** – Possível em etapas permitidas, quando aplicável.

Cada etapa pode exigir assinatura e confirmação, e o sistema guarda o histórico de quem fez cada ação e quando.

---

## Quem usa o sistema

- **Super-admin** – Configura municípios e parâmetros gerais do sistema.
- **Admin do município** – Acessa dados do município, secretarias e usuários.
- **Requerente** – Cria e acompanha solicitações.
- **Validador** – Valida solicitações da sua secretaria.
- **Concedente** – Autoriza diárias já validadas.
- **Pagador** – Registra o pagamento das diárias.
- **Beneficiário** – Servidor que pode consultar e solicitar diárias em seu nome.

O menu e as telas variam conforme o perfil e as permissões de cada usuário.

---

## Módulos principais

| Módulo | O que faz |
|--------|-----------|
| **Dashboard** | Visão por status das solicitações, resumo financeiro e acesso rápido a nova solicitação. |
| **Solicitações de diárias** | Listagem, criação, edição e acompanhamento; validação, concessão, pagamento e cancelamento. |
| **Servidores** | Cadastro de servidores e importação em massa por planilha. |
| **Cargos** | Cadastro de cargos/posições vinculados à legislação de diárias. |
| **Legislações** | Cadastro de leis e itens com valores por tipo de destino (ex.: até 200 km, capital, exterior). |
| **Secretarias** | Cadastro de secretarias/setores por município. |
| **Usuários** | Cadastro de usuários, perfis, secretarias e opções de assinatura e confirmação em ações sensíveis. |
| **Relatórios** | Relatórios de solicitações e de servidores, com exportação em planilha e PDF. |
| **Dados do município** | Dados cadastrais e identidade visual do município (admin). |
| **Municípios** | Gestão de municípios em ambiente multi-município (super-admin). |
| **Configurações** | Nome do sistema, formas de login permitidas (e-mail, usuário, matrícula), entre outras (super-admin). |

---

## Destaques do sistema

- **Múltiplas formas de login** – E-mail, nome de usuário ou matrícula, conforme configuração do município.
- **Multi-município e multi-secretaria** – Suporte a vários municípios e à escolha de secretaria pelo usuário quando aplicável.
- **Importação de servidores** – Planilha com modelo; geração automática de usuário de acesso quando necessário.
- **Fluxo com assinaturas** – Validação, concessão e pagamento com confirmação e, se configurado, PIN ou senha de operação.
- **Auditoria** – Histórico de quem fez cada ação e quando.
- **Notificações** – Avisos no navegador (push) e acompanhamento de processos em tempo real quando em uso.

---

## Portal da Transparência (público)

O portal permite que **qualquer cidadão** consulte as diárias e passagens pagas pelo município, **sem precisar entrar no sistema**.

- **Acesso:** pela URL do município, por exemplo: `/transparencia/cafarnaum` (cada município tem um endereço próprio configurável).
- **Conteúdo:** apenas diárias **já pagas**, com filtros por ano, mês, secretaria, destino e servidor.
- **Layout:** cabeçalho fixo com brasão e nome do município; filtros; tabela com os dados; totais e paginação.
- **Ações:** pesquisar com os filtros, limpar, **gerar PDF** com a listagem atual e exportar em planilha (CSV).

O objetivo é atender à transparência e à Lei de Acesso à Informação, permitindo consulta fácil e exportação dos dados.

---

## Resumo

O DiáriaSys centraliza a **solicitação, validação, concessão e pagamento** de diárias, com **controle por perfil e secretaria**, **auditoria** e **portal público** para transparência. É voltado a prefeituras e órgãos que precisam de um fluxo claro, rastreável e alinhado à legislação.

---

*Documento para apoio a apresentações e divulgação do DiáriaSys.*
