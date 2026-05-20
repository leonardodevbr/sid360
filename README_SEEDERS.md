# Seeders - Lei 001/2025 Cafarnaum-BA

## 📋 Descrição

Seeders completos baseados na **Lei nº 001 de 10 de Janeiro de 2025** do Município de Cafarnaum-BA, que dispõe sobre a Reorganização da Estrutura Administrativa e do Quadro de Cargos em Comissão.

## 📁 Arquivos Criados

### 1. **DepartmentSeeder.php**
Popula a estrutura organizacional completa do município:
- **15 órgãos principais** (4 de assessoramento + 4 meio + 7 fim)
- **Subdepartamentos** das principais secretarias
- **Hierarquia organizacional** completa
- **Códigos identificadores** para cada órgão

**Total**: 15 secretarias/órgãos principais + subdepartamentos

### 2. **CargoSeeder.php**
Popula todos os cargos comissionados:
- **17 categorias de cargos** (CC-01 a CC-15 + LEI-EDU)
- **393 posições totais**
- **Faixa salarial**: R$ 1.600,00 a R$ 8.000,00
- **Vínculo automático** com itens de legislação

### 3. **CargoReferenceSeeder.php** (Opcional)
Seeder de referência com dados detalhados:
- Detalhamento de cada categoria
- Distribuição por secretaria
- Regras de gratificação
- Subdepartamentos principais

## 🚀 Como Usar

### 1. Copiar os Arquivos
```bash
# Copie os arquivos para a pasta de seeders
cp DepartmentSeeder.php database/seeders/
cp CargoSeeder.php database/seeders/
cp CargoReferenceSeeder.php database/seeders/  # Opcional
```

### 2. Registrar no DatabaseSeeder

Edite `database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    // Certifique-se que o município existe primeiro
    $this->call([
        // ... outros seeders ...
        
        // Estrutura organizacional
        DepartmentSeeder::class,
        
        // Cargos e símbolos
        CargoSeeder::class,
        
        // Referência (opcional)
        CargoReferenceSeeder::class,
    ]);
}
```

### 3. Executar os Seeders

```bash
# Executar todos os seeders
php artisan db:seed

# Ou executar individualmente
php artisan db:seed --class=DepartmentSeeder
php artisan db:seed --class=CargoSeeder
php artisan db:seed --class=CargoReferenceSeeder
```

### 4. Limpar e Reexecutar (se necessário)

```bash
# Resetar banco e executar tudo novamente
php artisan migrate:fresh --seed
```

## 📊 Estrutura de Dados

### Campos do Model Department
```php
- municipality_id: ID do município
- name: Nome do órgão/secretaria
- code: Código identificador (ex: SEMED, SESAU)
- description: Descrição das atribuições
- is_main: Se é órgão principal ou subdepartamento
- parent_id: ID do departamento pai (se for subdepartamento)
- total_employees: Total de cargos comissionados
```

### Campos do Model Cargo
```php
- municipality_id: ID do município
- name: Nome da categoria do cargo
- symbol: Símbolo (CC-01, CC-02, etc)
- salary: Salário base (pode ser null para Lei Própria)
- description: Descrição do cargo
- total_positions: Quantidade total de posições
```

## 📈 Estatísticas dos Dados

### Departamentos/Secretarias
| Tipo | Quantidade |
|------|-----------|
| Órgãos de Assessoramento | 4 |
| Secretarias Meio | 4 |
| Secretarias Fim | 7 |
| **Total Principal** | **15** |
| Subdepartamentos | ~8 |

### Cargos por Faixa Salarial
| Símbolo | Salário | Posições |
|---------|---------|----------|
| CC-01 | Lei Própria | 15 |
| CC-02 | R$ 8.000,00 | 2 |
| CC-2A | R$ 6.800,00 | 2 |
| CC-03 | R$ 5.000,00 | 2 |
| CC-04 | R$ 4.600,00 | 2 |
| CC-05 | R$ 4.000,00 | 11 |
| CC-06 | R$ 3.500,00 | 21 |
| CC-07 | R$ 3.000,00 | 42 |
| CC-08 | R$ 2.800,00 | 6 |
| CC-09 | R$ 2.700,00 | 3 |
| CC-10 | R$ 2.500,00 | 23 |
| CC-11 | R$ 2.400,00 | 3 |
| CC-12 | R$ 2.300,00 | 16 |
| CC-13 | R$ 2.000,00 | 46 |
| CC-14 | R$ 1.800,00 | 86 |
| CC-15 | R$ 1.600,00 | 32 |
| LEI-EDU | Lei 134/2024 | 81 |
| **Total** | - | **393** |

### Top 5 Secretarias (por nº de cargos)
1. **Educação**: 156 cargos
2. **Saúde**: 45 cargos
3. **Administração e Finanças**: 32 cargos
4. **Assistência Social**: 29 cargos
5. **Infraestrutura**: 22 cargos

## ⚙️ Funcionalidades Extras

### Gratificações Adicionais (conforme lei)

Os seeders incluem referência às seguintes gratificações:

1. **Função Gratificada** (Art. 75, §1º)
   - Servidor efetivo: até 80% adicional

2. **Gratificação Especial de Desempenho** (Art. 76)
   - Serviços extraordinários: até 80%

3. **Órgão Colegiado** (Art. 76, §1º)
   - Participação em comissões: até 20%

4. **Servidor Cedido** (Art. 76, §2º)
   - Servidor de outro ente: até 50%

## 🔍 Detalhes Técnicos

### Hierarquia Organizacional
```
Prefeitura Municipal de Cafarnaum
├── Órgãos de Assessoramento (4)
│   ├── Gabinete do Prefeito
│   ├── Procuradoria Geral
│   ├── Controladoria Geral
│   └── Ouvidoria Geral
├── Órgãos Meio (4)
│   ├── Administração e Finanças
│   ├── Planejamento e Desenvolvimento
│   ├── Governo
│   └── Relações Institucionais
└── Órgãos Fim (7)
    ├── Infraestrutura
    ├── Agricultura
    ├── Meio Ambiente
    ├── Assistência Social
    ├── Educação
    ├── Saúde
    └── Cultura, Esportes e Juventude
```

### Códigos das Secretarias
- **GAB**: Gabinete do Prefeito
- **PGM**: Procuradoria Geral
- **CGM**: Controladoria Geral
- **OUV**: Ouvidoria Geral
- **SEMAF**: Administração e Finanças
- **SEMPLAD**: Planejamento
- **SEGOV**: Governo
- **SERIN**: Relações Institucionais
- **SEINFRA**: Infraestrutura
- **SEAGRI**: Agricultura
- **SEMAM**: Meio Ambiente
- **SEDAS**: Assistência Social
- **SEMED**: Educação
- **SESAU**: Saúde
- **SECULT**: Cultura

## 📝 Observações Importantes

1. **Pré-requisito**: Certifique-se de que existe pelo menos 1 município cadastrado
2. **Legislação**: Os cargos são vinculados automaticamente ao primeiro item de legislação encontrado
3. **Salários CC-01**: Secretários têm salário definido em lei própria (não especificado)
4. **Cargos Educação**: 81 cargos regulados pela Lei 134/2024
5. **Jornada**: Todos os cargos têm jornada de 40h semanais
6. **Vigência**: Lei em vigor desde 02/01/2025

## 🔄 Atualizações Futuras

Para atualizar os dados quando houver alterações na lei:

1. Edite os arrays nos seeders
2. Execute novamente: `php artisan db:seed --class=NomeDoSeeder`
3. Os dados serão atualizados usando `firstOrCreate()`

## 📚 Referência Legal

- **Lei**: nº 001/2025
- **Data**: 10 de Janeiro de 2025
- **Município**: Cafarnaum-BA
- **Gestor**: Carlan Novais Sena Xavier
- **Vigência**: A partir de 02/01/2025

## 🆘 Troubleshooting

### Erro: "Nenhum município encontrado"
```bash
# Execute primeiro o seeder de municípios
php artisan db:seed --class=MunicipalitySeeder
```

### Erro: Duplicate entry
```bash
# Limpe o banco antes de executar
php artisan migrate:fresh
php artisan db:seed
```

### Dados não aparecem
```bash
# Verifique se os models têm os relacionamentos corretos
# Verifique as migrations
php artisan migrate:status
```

## 📞 Suporte

Para dúvidas sobre a estrutura legal, consulte:
- Diário Oficial do Município de Cafarnaum-BA
- Lei nº 001/2025
- Site: www.indap.org.br

---

**Desenvolvido com base na Lei nº 001/2025 - Prefeitura Municipal de Cafarnaum-BA**
