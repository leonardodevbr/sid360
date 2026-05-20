# Análise do Escopo e Reflexão sobre Branch

## 1. Nome comercial sugerido

O projeto já utiliza **SISCONDI** (Sistema de Concessão de Diárias), que atende bem ao escopo:

- **Sigla:** SISCONDI — identificação curta e governamental.
- **Nome completo:** Sistema de Concessão de Diárias — alinhado ao objetivo do sistema.

**Alternativas possíveis (caso queira variar):**

| Nome        | Tipo        | Observação                                      |
|------------|-------------|--------------------------------------------------|
| **SISCONDI** | Sigla       | Recomendado: já em uso, claro e memorável       |
| **SICODI**  | Sigla       | Sistema de Concessão de Diárias (mais curto)    |
| **Sistema de Diárias Municipais** | Nome longo | Mais descritivo, menos técnico |

**Recomendação:** Manter **SISCONDI** como nome comercial: sigla adequada ao uso governamental e já adotada no sistema (logo, menu, título).

---

## 2. Análise das imagens anexadas

### 2.1 Imagem 1 – PDF oficial (Solicitação e Autorização de Diárias)

Documento que define o **layout e os blocos** que o sistema deve gerar em PDF.

- **Cabeçalho (11.1):** Estado, Prefeitura, Secretaria, Fundo, CNPJ, Endereço, E-mail, Ano exercício.
- **Bloco Solicitante (11.2):** Setor solicitante (ex.: SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL), data da solicitação, responsável pela solicitação (ex.: secretário).
- **Bloco Autorização de Concessão (11.2):** Texto padrão, local e data, assinatura da autoridade concedente.
- **Dados do Servidor (11.3):** Nome, Cargo/Função, Matrícula, CPF, Identidade (RG + órgão), E-mail, Dados bancários (Ag/CC).
- **Dados da Solicitação (11.4):** Nº de diárias, Valor unitário, Valor total, Finalidade.
- **Relatório de Viagem (11.5):** Localidade(s) destino, Data partida/retorno, Motivo da viagem.
- **Autorizações Finais (11.6):** Autorização de pagamento (autoridade pagadora, ex.: Prefeito) e Declaração do servidor.

**Impacto no sistema:** A geração do PDF deve seguir esse modelo campo a campo; o “Setor Solicitante” pode vir da **lotação do servidor** (Branch/Secretaria).

---

### 2.2 Imagem 2 – Legislação vigente (ANEXO ÚNICO – valores por categoria e destino)

Tabela que define **valores de diária** por:

- **Categoria funcional** (ex.: Prefeito/Vice, Secretários, Diretores, Coordenadores, Demais servidores).
- **Classe/Código do cargo** (ex.: CC-01, CC-05, CC-10).
- **Tipo de destino:** até 200 km, acima de 200 km, Capital do Estado, Demais capitais e DF, Exterior.

**Impacto no sistema:** O cadastro de **Legislação** precisa suportar:

- Código da legislação, lei/decreto/portaria, vigência.
- **Cargo/Função** e **Código do cargo** (ex.: CC-01, CC-02).
- **Valor unitário por tipo de destino** (não apenas um valor único). Ou seja, a legislação pode precisar de **mais de um valor por cargo** (um por faixa/destino), conforme a tabela anexa.

Isso pode exigir evolução do model `Legislation`: hoje há um único `daily_value`; a tabela sugere vários valores por cargo (por destino/distância). Uma opção é ter **linhas de legislação por (cargo + tipo_destino)** ou uma estrutura JSON/tabela auxiliar de valores por destino.

---

### 2.3 Imagem 3 – Tabela de cargos (Secretarias – CARGO × SÍMBOLO/SALÁRIO)

Mostra o vínculo **Secretaria → Cargos → Código (ex.: CC-01, CC-09, CC-14)**.

- **CARGO:** nome da função (ex.: Secretário, Diretor, Coordenador).
- **SÍMBOLO/SALÁRIO:** código do cargo (ex.: CC-01, CC-13), que liga ao valor da diária na legislação.
- **Lotação:** cada cargo está vinculado a uma secretaria (no caso, Assistência Social, Meio Ambiente).

**Impacto no sistema:**

- **Servidor:** Cargo/Função e Código do cargo vêm da **Legislação** (ou de um cadastro de cargos que referencia a legislação). O Servant já tem `legislation_id` (cargo/valor) e `department_id` (Branch = secretaria/lotação).
- **Branch** continua necessária para representar **Secretaria** (Setor) e **Lotação** do servidor, usada no PDF como “Setor Solicitante”.

---

## 3. Precisamos mesmo usar o model de Branch?

**Sim. O modelo de Branch (Secretaria/Setor) é necessário.** Justificativa:

### 3.1 Exigências do escopo e do PDF

1. **Setor Solicitante no PDF (item 11.2)**  
   O documento exige “Setor Solicitante” (ex.: SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL). Esse setor é a **unidade organizacional** de onde parte a solicitação. Na prática, é a **lotação do servidor** (onde ele está lotado). Logo, precisamos de um cadastro de “Setores/Secretarias” — no sistema atual isso é o **Branch**.

2. **Cadastro de Servidores (item 4.2) – Lotação**  
   O escopo prevê o campo **Lotação**. No modelo atual, isso é `Servant.department_id` → Branch. Sem Branch não há como registrar em qual secretaria/setor o servidor está lotado.

3. **Fluxo e permissões**  
   O “Responsável pela Solicitação” e a “Autoridade Concedente” costumam ser gestores da **mesma secretaria** (ex.: secretário). Associar usuários a **secretarias** (Branch) permite restringir quem valida/autoriza por setor — hoje isso já existe com `branch_user` e `User.branches()`.

### 3.2 Conclusão sobre Branch

- **Manter o model Branch.**  
- Ele representa **Secretaria/Setor** no domínio municipal e atende a:
  - Lotação do servidor.
  - Setor Solicitante no PDF (via `servant.department`).
  - Controle de acesso por secretaria (usuários × branches).

Sugestão de **nomenclatura na interface:** usar “Secretaria” ou “Setor” nos labels (mantendo `branches` na base e no código se preferir), para alinhar à linguagem do escopo e do PDF.

---

## 4. Checklist rápido: escopo × sistema atual

| Escopo | Situação no sistema |
|--------|----------------------|
| Cadastro Legislação (código, cargo, valor, vigência) | ✅ Model `Legislation`; pode evoluir para vários valores por tipo de destino |
| Cadastro Servidores (incl. cargo, código cargo, lotação) | ✅ Model `Servant` com `legislation_id` e `department_id` (Branch) |
| Cadastro Usuários + perfil + servidor vinculado | ✅ Users, roles, `branch_user`; vínculo User–Servant pode ser via `Servant.user_id` |
| Status Requerido / Autorizado / Cancelado / Pago | ✅ Enum com Requested, Validated, Authorized, Paid, Cancelled (e Draft) |
| Requerimento (servidor, viagem, cálculo automático) | ✅ `DailyRequest` com servant, datas, quantidade, unit_value, total_value |
| Autorização Concessão / Autorização Pagamento | ✅ `validator_id`, `authorizer_id`, `payer_id`, datas |
| Geração PDF oficial conforme modelo | ⏳ A implementar conforme layout da imagem 1 |
| Cabeçalho configurável (Prefeitura, CNPJ, etc.) | ⏳ Configurações/parâmetros do município a definir |

---

## 5. Próximos passos sugeridos

1. **PDF oficial:** Implementar geração do PDF seguindo rigorosamente o layout da imagem 1, usando dados de `DailyRequest`, `Servant`, `Legislation` e Branch (Setor Solicitante = `servant.department`).
2. **Legislação:** Avaliar extensão do model para múltiplos valores por cargo (por tipo de destino/distância), conforme imagem 2.
3. **Configurações do município:** Cadastro para cabeçalho do PDF (Estado, Prefeitura, Secretaria, Fundo, CNPJ, Endereço, E-mail, Ano).
4. **Nomenclatura:** Usar “Secretaria” ou “Setor” na UI onde hoje aparece “Branch/Filial”, mantendo o model `Branch` e o nome SISCONDI.
