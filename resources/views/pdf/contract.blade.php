<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  @page {
    size: A4 portrait;
    margin: 71pt 85pt 85pt 85pt;
  }

  body {
    margin: 0;
    padding: 0;
    font-family: 'Times New Roman', Times, serif;
    font-size: 12pt;
    line-height: 1.65;
    color: #000;
  }

  .doc-header {
    text-align: center;
    margin: 0 0 28pt;
    padding: 0 0 14pt;
    border-bottom: 1pt solid #000;
  }

  .doc-header-logo {
    margin-bottom: 6pt;
    text-align: center;
  }

  .doc-header-logo img {
    height: 42pt;
    width: auto;
    display: block;
    margin: 0 auto 4pt;
  }

  .doc-header-tagline {
    font-size: 9pt;
    color: #444;
    letter-spacing: 0.6pt;
    text-transform: uppercase;
  }

  .doc-number {
    font-size: 9pt;
    color: #444;
    margin-top: 4pt;
  }

  .doc-title {
    text-align: center;
    margin-bottom: 24pt;
    padding-top: 4pt;
  }

  .doc-title h1 {
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.8pt;
    line-height: 1.4;
    margin: 0;
  }

  p {
    text-align: justify;
    margin: 0 0 12pt;
    text-indent: 0;
    orphans: 3;
    widows: 3;
  }

  .clause {
    page-break-inside: avoid;
  }

  p.indent {
    text-indent: 2cm;
  }

  .clause-title {
    font-weight: bold;
    text-transform: uppercase;
    font-size: 11.5pt;
    margin: 20pt 0 8pt;
  }

  .sub-title {
    font-weight: bold;
    margin: 14pt 0 6pt;
  }

  .divider {
    border: none;
    border-top: 1pt solid #000;
    margin: 22pt 0;
    height: 0;
  }

  .local-data {
    text-align: center;
    margin: 36pt 0 48pt;
    font-size: 12pt;
  }

  .signatures {
    width: 100%;
    border-collapse: collapse;
    margin: 8pt 0 0;
  }

  .signatures td {
    width: 50%;
    text-align: center;
    padding: 0 16pt 28pt;
    vertical-align: top;
  }

  .sig-line {
    border-top: 1pt solid #000;
    margin-bottom: 5pt;
  }

  .sig-name {
    font-weight: bold;
    font-size: 11pt;
  }

  .sig-role {
    font-size: 10pt;
    color: #333;
  }

  .sig-doc {
    font-size: 9.5pt;
    color: #444;
  }

  .witnesses {
    width: 100%;
    border-collapse: collapse;
    margin: 36pt 0 0;
  }

  .witnesses td {
    width: 50%;
    text-align: center;
    padding: 0 20pt;
    vertical-align: top;
  }

  .doc-footer {
    margin-top: 36pt;
    padding-top: 8pt;
    border-top: 0.5pt solid #999;
    font-size: 8pt;
    color: #666;
    text-align: center;
  }

  .closing-block {
    page-break-inside: avoid;
  }

  .watermark {
    position: fixed;
    top: 42%;
    left: -15%;
    width: 130%;
    text-align: center;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 64pt;
    font-weight: bold;
    color: rgba(150, 20, 20, 0.16);
    text-transform: uppercase;
    letter-spacing: 4pt;
    transform: rotate(-30deg);
    z-index: -1;
  }

  .watermark-sub {
    display: block;
    font-size: 18pt;
    letter-spacing: 2pt;
    margin-top: 8pt;
  }
</style>
</head>
<body>

@php
  $isDraft = $isDraft ?? false;
@endphp

@if($isDraft)
<div class="watermark">
  Minuta
  <span class="watermark-sub">sem valor contratual</span>
</div>
@endif

@php
  $brandLogoPath = public_path('img/logo-systema.png');
  $brandLogoSrc = is_readable($brandLogoPath)
      ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($brandLogoPath))
      : null;

  $seller = \App\Support\ContractParty::seller($sale->lot->development ?? null);
  $company = \App\Support\ContractParty::company();
  $foro = \App\Support\ContractParty::foro();

  $fmt = fn ($v) => 'R$ ' . number_format((int) $v / 100, 2, ',', '.');
  $saleDate = \Carbon\Carbon::parse($sale->sale_date)->translatedFormat('d \d\e F \d\e Y');
  $firstDue = \Carbon\Carbon::parse($sale->first_due_date)->translatedFormat('d \d\e F \d\e Y');
  $isCash = (int) $sale->installments_count < 1;
  $allBuyers = $sale->buyers->count() > 0 ? $sale->buyers : collect([$sale->client]);
  $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT) . '/' . $sale->sale_date->format('Y');
  $cashPay = (int) ($sale->cash_value ?? $sale->total_value);
@endphp

<div class="doc-header">
  <div class="doc-header-logo">
    @if($brandLogoSrc)
      <img src="{{ $brandLogoSrc }}" alt="{{ $company['nome'] }}">
    @endif
    <div class="doc-header-tagline">{{ $company['tagline'] }}</div>
  </div>
  <div class="doc-number">
    Contrato nº {{ $contractNo }} · Emitido em: {{ now()->translatedFormat('d \d\e F \d\e Y') }}
    @if($isDraft)
      · <strong>MINUTA — sem valor contratual</strong>
    @endif
  </div>
</div>

<div class="doc-title">
  <h1>Contrato Particular de Compromisso<br>de Compra e Venda de Lote</h1>
</div>

<p class="indent">
  Pelo presente instrumento particular, de um lado, como
  <strong>OUTORGANTE VENDEDOR</strong>:
  <strong>{{ strtoupper($seller['name']) }}</strong>, brasileiro, maior, capaz,
  portador do RG nº {{ $seller['rg'] }} {{ $seller['rg_issuer'] }} e CPF nº {{ $seller['cpf'] }},
  residente e domiciliado na {{ $seller['address'] }}; e do outro lado, como
  @if($allBuyers->count() > 1)
    <strong>OUTORGADOS COMPRADORES</strong>:
  @else
    <strong>OUTORGADO COMPRADOR</strong>:
  @endif

  @foreach($allBuyers as $i => $buyer)
    <strong>{{ strtoupper($buyer->name) }}</strong>,
    brasileiro(a), maior, capaz
    @if($buyer->marital_status)
      , {{ \App\Models\Client::maritalStatusLabel($buyer->marital_status) }}
    @endif
    @if($buyer->profession)
      , {{ $buyer->profession }}
    @endif
    @if($buyer->cpf)
      , portador(a) do CPF nº {{ $buyer->cpf }}
    @endif
    @if($buyer->rg)
      e RG nº {{ $buyer->rg }}
      @if($buyer->rg_issuer)
        {{ $buyer->rg_issuer }}
      @endif
    @endif
    @if($buyer->address)
      , residente e domiciliado(a) na {{ $buyer->address }}
      @if($buyer->address_number)
        , nº {{ $buyer->address_number }}
      @endif
      @if($buyer->neighborhood)
        , {{ $buyer->neighborhood }}
      @endif
      @if($buyer->city)
        , {{ $buyer->city }}
      @endif
      @if($buyer->state)
        — {{ $buyer->state }}
      @endif
    @endif
    @if(!$loop->last)
      ;
    @endif
  @endforeach;

  têm, entre si, justo e contratado o presente Compromisso de Compra e Venda,
  que se regerá pelas cláusulas e condições seguintes:
</p>

<hr class="divider">

<div class="clause">
<p class="clause-title">Cláusula Primeira — Do Objeto</p>

<p class="indent">
  O <strong>OUTORGANTE VENDEDOR</strong> é legítimo proprietário de um
  <strong>TERRENO (LOTE)</strong>, localizado no Loteamento
  <strong>{{ strtoupper($sale->lot->development->name) }}</strong>,
  situado no Município de {{ $foro['cidade'] }}, Estado da {{ $foro['estado_extenso'] }},
  @if($sale->lot->street)
    com frente para a <strong>{{ $sale->lot->street->name }}</strong>,
  @endif
  @if($sale->lot->zone?->parent)
    <strong>{{ $sale->lot->zone->parent->name }}</strong>,
  @endif
  @if($sale->lot->zone)
    <strong>{{ $sale->lot->zone->name }}</strong>,
  @elseif($sale->lot->block)
    Quadra <strong>{{ $sale->lot->block }}</strong>,
  @endif
  Lote <strong>{{ $sale->lot->number }}</strong>
  @if($sale->lot->area)
    , com área total de
    <strong>{{ number_format((float) $sale->lot->area, 0, ',', '.') }}m²
    ({{ number_format((float) $sale->lot->area, 0, ',', '.') }} metros quadrados)</strong>
  @endif
  , com seus limites, confrontações e características constantes da matrícula
  no Cartório de Registro de Imóveis competente. Pelo presente instrumento,
  o <strong>OUTORGANTE VENDEDOR</strong> compromete-se a vender ao
  <strong>OUTORGADO COMPRADOR</strong> o imóvel acima descrito, ficando desde já
  autorizado o Sr.º Oficial de Registro competente a efetuar todas as alterações
  necessárias para a devida transferência do domínio.
</p>
</div>

<div class="clause">
<p class="clause-title">Cláusula Segunda — Do Preço e Forma de Pagamento</p>

@if($isCash)
<p class="indent">
  O preço total e certo da compra e venda ora ajustada é de
  <strong>{{ $fmt($cashPay) }}</strong>
  @if((int) $sale->discount_amount > 0)
    (valor de tabela: <strong>{{ $fmt($sale->total_value) }}</strong>;
    desconto de <strong>{{ $fmt($sale->discount_amount) }}</strong>
    @if($sale->discount_percent)
      — {{ number_format((float) $sale->discount_percent, 2, ',', '.') }}%
    @endif
    )
  @endif
  , pago integralmente neste ato, em moeda corrente nacional, à vista, em
  {{ $saleDate }}, dando o <strong>OUTORGANTE VENDEDOR</strong> plena,
  rasa e irrevogável quitação do referido valor.
</p>
@else
<p class="indent">
  O preço total e certo da compra e venda ora ajustada é de
  <strong>{{ $fmt($sale->total_value) }}</strong>
  @if($sale->cash_value && (int) $sale->cash_value !== (int) $sale->total_value)
    (valor de tabela: <strong>{{ $fmt($sale->total_value) }}</strong>;
    valor à vista: <strong>{{ $fmt($sale->cash_value) }}</strong>)
  @endif
  , a ser pago da seguinte forma:
</p>

<p class="indent">
  <strong>a) Entrada:</strong> O valor de <strong>{{ $fmt($sale->down_payment) }}</strong>
  será pago no ato da assinatura do presente instrumento, em {{ $saleDate }};
</p>

<p class="indent">
  <strong>b) Saldo:</strong> O valor restante de
  <strong>{{ $fmt($sale->financed_value) }}</strong>
  será pago em <strong>{{ $sale->installments_count }}
  ({{ $sale->installments_count }})
  parcelas</strong> iguais, mensais e consecutivas, no valor de
  <strong>{{ $fmt($sale->installment_value) }}</strong> cada,
  com vencimento da primeira parcela em <strong>{{ $firstDue }}</strong>
  e as demais nas mesmas datas dos meses subsequentes,
  mediante <strong>Nota Promissória</strong>.
</p>
@endif

<p class="sub-title">Parágrafo Primeiro — Da Mora:</p>
<p class="indent">
  O não pagamento de qualquer parcela na data do respectivo vencimento
  implicará a incidência de multa moratória de <strong>2,5% (dois e meio
  por cento)</strong> ao mês, calculada sobre o valor da parcela em atraso,
  sem prejuízo das demais cláusulas deste instrumento.
</p>

<p class="sub-title">Parágrafo Segundo — Da Intransferibilidade:</p>
<p class="indent">
  O presente compromisso é <strong>intransferível</strong>, sendo vedada ao
  <strong>OUTORGADO COMPRADOR</strong> a cessão ou transferência, a qualquer
  título, dos direitos ora adquiridos, sem a prévia e expressa anuência,
  por escrito, do <strong>OUTORGANTE VENDEDOR</strong>.
</p>
</div>

<div class="clause">
<p class="clause-title">Cláusula Terceira — Da Rescisão</p>

<p class="indent">
  Em caso de desistência ou inadimplemento por parte do
  <strong>OUTORGADO COMPRADOR</strong>, o presente contrato considerar-se-á
  rescindido de pleno direito, acarretando ao infrator o pagamento de multa
  compensatória equivalente a <strong>30% (trinta por cento)</strong> dos valores
  já pagos, acrescida de <strong>10% (dez por cento)</strong> a título de
  despesas administrativas, retidos pelo <strong>OUTORGANTE VENDEDOR</strong>
  como perdas e danos.
</p>
</div>

<div class="clause">
<p class="clause-title">Cláusula Quarta — Da Transferência</p>

<p class="indent">
  O <strong>OUTORGANTE VENDEDOR</strong> obriga-se a providenciar, após a
  quitação integral do preço ora ajustado, todos os documentos necessários
  à lavratura da escritura pública definitiva de compra e venda, em nome do
  <strong>OUTORGADO COMPRADOR</strong>, arcando cada parte com as despesas
  que lhe couberem, nos termos da legislação em vigor.
</p>
</div>

<div class="clause">
<p class="clause-title">Cláusula Quinta — Da Irrevogabilidade</p>

<p class="indent">
  O presente instrumento é firmado em caráter <strong>irrevogável e
  irretratável</strong>, obrigando as partes contratantes, seus herdeiros
  e sucessores a qualquer título, ao fiel cumprimento de tudo quanto aqui
  ficou estipulado, valendo o presente como título executivo extrajudicial,
  nos termos do artigo 784 do Código de Processo Civil.
</p>
</div>

<div class="clause">
<p class="clause-title">Cláusula Sexta — Do Foro</p>

<p class="indent">
  As partes elegem o Foro da Comarca de <strong>{{ $foro['cidade'] }}, Estado da {{ $foro['estado_extenso'] }}</strong>,
  com renúncia expressa a qualquer outro, por mais privilegiado que seja,
  para dirimir quaisquer dúvidas ou litígios oriundos do presente instrumento.
</p>
</div>

@php
  $signatureParties = collect([
    [
      'name' => $seller['name'],
      'role' => 'Outorgante Vendedor',
      'doc' => 'CPF: ' . $seller['cpf'],
    ],
  ])->merge($allBuyers->map(function ($buyer, $i) {
    return [
      'name' => $buyer->name,
      'role' => $i === 0 ? 'Outorgado(a) Comprador(a)' : 'Co-Comprador(a)',
      'doc' => 'CPF: ' . $buyer->cpf,
    ];
  }));
  $signatureRows = $signatureParties->chunk(2);
@endphp

<div class="closing-block">
<p class="local-data">
  {{ $foro['cidade'] }} — {{ $foro['estado'] }}, {{ $saleDate }}.
</p>

<table class="signatures">
  @foreach($signatureRows as $row)
  <tr>
    @foreach($row as $party)
    <td>
      <div class="sig-line"></div>
      <div class="sig-name">{{ $party['name'] }}</div>
      <div class="sig-role">{{ $party['role'] }}</div>
      <div class="sig-doc">{{ $party['doc'] }}</div>
    </td>
    @endforeach
    @if($row->count() === 1)
    <td></td>
    @endif
  </tr>
  @endforeach
</table>

<table class="witnesses">
  <tr>
    <td>
      <div class="sig-line"></div>
      <div class="sig-role">Testemunha 1</div>
      <div class="sig-doc">CPF: ___.___.___-__</div>
    </td>
    <td>
      <div class="sig-line"></div>
      <div class="sig-role">Testemunha 2</div>
      <div class="sig-doc">CPF: ___.___.___-__</div>
    </td>
  </tr>
</table>
</div>

<div class="doc-footer">
  Contrato nº {{ $contractNo }} · {{ $company['nome'] }} · {{ $foro['cidade'] }}-{{ $foro['estado'] }} · {{ $company['site'] }} ·
  Documento gerado em {{ now()->format('d/m/Y \à\s H:i') }}
</div>

</body>
</html>
