<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  @page { margin: 0; }

  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 11.5pt;
    line-height: 1.65;
    color: #1a1a1a;
    background: #fff;
  }

  .header {
    background: #1C0A06;
    padding: 18pt 2.5cm 16pt;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .header-brand {
    color: #fff;
    font-size: 22pt;
    font-weight: bold;
    font-family: Arial, sans-serif;
    letter-spacing: -0.5pt;
  }

  .header-brand span { color: #C9A84C; }

  .header-meta {
    text-align: right;
    color: rgba(255,255,255,0.6);
    font-size: 8pt;
    font-family: Arial, sans-serif;
    line-height: 1.6;
  }

  .header-meta strong {
    display: block;
    color: #C9A84C;
    font-size: 9pt;
  }

  .title-bar {
    background: #C9A84C;
    padding: 8pt 2.5cm;
    text-align: center;
  }

  .title-bar h1 {
    font-family: Arial, sans-serif;
    font-size: 12pt;
    font-weight: bold;
    color: #1C0A06;
    text-transform: uppercase;
    letter-spacing: 2pt;
  }

  .page {
    padding: 1.5cm 2.5cm 2cm;
  }

  .parties-section {
    margin-bottom: 18pt;
  }

  .section-heading {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1.5pt;
    color: #7A4535;
    margin-bottom: 10pt;
    padding-bottom: 4pt;
    border-bottom: 1.5pt solid #C9A84C;
  }

  .party-card {
    background: #FAF5EE;
    border: 0.5pt solid #E0D5C5;
    border-left: 3pt solid #C23028;
    padding: 10pt 12pt;
    margin-bottom: 8pt;
    border-radius: 2pt;
  }

  .party-card.vendedor {
    border-left-color: #1C0A06;
  }

  .party-label {
    font-family: Arial, sans-serif;
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1pt;
    color: #7A4535;
    margin-bottom: 4pt;
  }

  .party-name {
    font-size: 12pt;
    font-weight: bold;
    color: #1C0A06;
    margin-bottom: 3pt;
  }

  .party-details {
    font-size: 10pt;
    color: #444;
    line-height: 1.5;
  }

  .intro {
    font-size: 10.5pt;
    color: #333;
    text-align: justify;
    margin: 14pt 0;
    padding: 10pt 12pt;
    background: #F7F2EB;
    border: 0.5pt solid #E0D5C5;
    border-radius: 2pt;
  }

  .clause {
    margin-bottom: 12pt;
  }

  .clause-title {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #1C0A06;
    margin-bottom: 5pt;
    margin-top: 14pt;
  }

  .clause p {
    font-size: 11pt;
    text-align: justify;
    margin-bottom: 6pt;
    color: #1a1a1a;
  }

  .sub-title {
    font-weight: bold;
    font-size: 10.5pt;
    margin-top: 8pt;
    margin-bottom: 4pt;
  }

  .payment-box {
    background: #FAF5EE;
    border: 0.5pt solid #E0D5C5;
    border-radius: 2pt;
    padding: 10pt 12pt;
    margin: 10pt 0;
  }

  .payment-grid {
    display: flex;
    gap: 0;
  }

  .payment-item {
    flex: 1;
    text-align: center;
    padding: 6pt 8pt;
    border-right: 0.5pt solid #E0D5C5;
  }

  .payment-item:last-child { border-right: none; }

  .payment-label {
    font-family: Arial, sans-serif;
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 1pt;
    color: #7A4535;
    margin-bottom: 3pt;
  }

  .payment-value {
    font-size: 11pt;
    font-weight: bold;
    color: #1C0A06;
  }

  .obligation {
    padding: 4pt 0 4pt 14pt;
    border-left: 2pt solid #C9A84C;
    margin: 6pt 0;
    font-size: 10.5pt;
    color: #1a1a1a;
  }

  .signatures-section {
    margin-top: 40pt;
    page-break-inside: avoid;
  }

  .signature-date {
    text-align: center;
    font-size: 11pt;
    color: #333;
    margin-bottom: 30pt;
  }

  .signatures-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20pt;
    justify-content: center;
  }

  .signature-block {
    text-align: center;
    width: 200pt;
    padding-top: 8pt;
    border-top: 1pt solid #1C0A06;
  }

  .signature-name {
    font-size: 10.5pt;
    font-weight: bold;
    color: #1C0A06;
    margin-bottom: 2pt;
  }

  .signature-role {
    font-size: 9pt;
    color: #555;
    margin-bottom: 1pt;
  }

  .signature-cpf {
    font-size: 8.5pt;
    color: #777;
  }

  .footer {
    margin-top: 30pt;
    padding-top: 8pt;
    border-top: 0.5pt solid #E0D5C5;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .footer-left {
    font-size: 7.5pt;
    color: #aaa;
    font-family: Arial, sans-serif;
  }

  .footer-right {
    font-size: 7.5pt;
    color: #C9A84C;
    font-family: Arial, sans-serif;
    font-weight: bold;
  }
</style>
</head>
<body>

<div class="header">
  <div class="header-brand">SID<span>360</span></div>
  <div class="header-meta">
    <strong>Pré-Contrato de Compra e Venda</strong>
    Contrato Nº {{ str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT) }}/{{ $sale->sale_date->format('Y') }}<br>
    Emitido em: {{ $sale->sale_date->translatedFormat('d \d\e F \d\e Y') }}
  </div>
</div>

<div class="title-bar">
  <h1>Compromisso de Compra e Venda de Lote</h1>
</div>

<div class="page">

  <div class="parties-section">
    <p class="section-heading">Identificação das Partes</p>

    <div class="party-card vendedor">
      <p class="party-label">Outorgante Vendedor</p>
      <p class="party-name">Sidiclei Novais Baretto</p>
      <p class="party-details">
        Brasileiro, maior, capaz · CPF: 311.168.558-60 · RG: 08.280.665-90 SSP/BA<br>
        Rua Arlindo Montino, 4, s/nº, Centro — Cafarnaum, Bahia
      </p>
    </div>

    @php
      $allBuyers = $sale->buyers->count() > 0
        ? $sale->buyers
        : collect([$sale->client]);
    @endphp

    @foreach($allBuyers as $i => $buyer)
    <div class="party-card">
      <p class="party-label">{{ $i === 0 ? 'Outorgado Comprador' : 'Co-Comprador' }}</p>
      <p class="party-name">{{ strtoupper($buyer->name) }}</p>
      <p class="party-details">
        Brasileiro(a), maior, capaz
        @if($buyer->marital_status)
          · {{ \App\Models\Client::maritalStatusLabel($buyer->marital_status) }}
        @endif
        @if($buyer->profession)
          · {{ $buyer->profession }}
        @endif
        @if($buyer->cpf)
          · CPF: {{ $buyer->cpf }}
        @endif
        @if($buyer->rg)
          · RG: {{ $buyer->rg }}
          @if($buyer->rg_issuer)
            {{ $buyer->rg_issuer }}
          @endif
        @endif
        @if($buyer->address)
          <br>{{ $buyer->address }}
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
            – {{ $buyer->state }}
          @endif
        @endif
      </p>
    </div>
    @endforeach
  </div>

  <p class="intro">
    Têm entre si, de maneira justa e acordada, o presente Contrato Particular de
    Compromisso de Compra e Venda do imóvel abaixo descrito, que mutuamente outorgam
    e aceitam nos termos das cláusulas seguintes.
  </p>

  <div class="clause">
    <p class="clause-title">Cláusula Primeira — Do Objeto</p>
    <p>
      O <strong>Outorgante Vendedor</strong> é legítimo proprietário de um
      <strong>Terreno (Lote)</strong> localizado no Loteamento
      <strong>{{ $sale->lot->development->name }}</strong>,
      Quadra n° <strong>{{ $sale->lot->block ?? '–' }}</strong>,
      Lote n° <strong>{{ $sale->lot->number }}</strong>
      @if($sale->lot->area)
      , totalizando uma área de
      <strong>{{ number_format((float) $sale->lot->area, 0, ',', '.') }}m²</strong>
      @endif
      , situado no Município de Cafarnaum, Estado da Bahia. Ficando desde já autorizado
      o Srº Oficial de Registro competente a efetuar todas as alterações necessárias para
      a devida transferência do domínio.
    </p>
  </div>

  @php
    $fmt = fn ($v) => 'R$ ' . number_format((int) $v / 100, 2, ',', '.');
    $firstDue = \Carbon\Carbon::parse($sale->first_due_date)->translatedFormat('d \d\e F \d\e Y');
    $saleDate = \Carbon\Carbon::parse($sale->sale_date)->translatedFormat('d \d\e F \d\e Y');
    $isCashOnly = (int) $sale->installments_count < 1;
    $cashPay = (int) ($sale->cash_value ?? $sale->total_value);
  @endphp

  <div class="clause">
    <p class="clause-title">Cláusula Segunda — Do Preço e Forma de Pagamento</p>

    <div class="payment-box">
      <div class="payment-grid">
        <div class="payment-item">
          <p class="payment-label">Valor Tabela</p>
          <p class="payment-value">{{ $fmt($sale->total_value) }}</p>
        </div>
        @if($isCashOnly)
        @if((int) $sale->discount_amount > 0)
        <div class="payment-item">
          <p class="payment-label">Desconto</p>
          <p class="payment-value">{{ $fmt($sale->discount_amount) }}</p>
        </div>
        @endif
        <div class="payment-item">
          <p class="payment-label">À Vista</p>
          <p class="payment-value">{{ $fmt($cashPay) }}</p>
        </div>
        @else
        <div class="payment-item">
          <p class="payment-label">Entrada</p>
          <p class="payment-value">{{ $fmt($sale->down_payment) }}</p>
        </div>
        <div class="payment-item">
          <p class="payment-label">Saldo</p>
          <p class="payment-value">{{ $fmt($sale->financed_value) }}</p>
        </div>
        <div class="payment-item">
          <p class="payment-label">{{ $sale->installments_count }}x de</p>
          <p class="payment-value">{{ $fmt($sale->installment_value) }}</p>
        </div>
        @endif
      </div>
    </div>

    @if($isCashOnly)
    <p>
      O(s) <strong>Outorgado(s) Comprador(es)</strong> efetuará(ão) o pagamento <strong>à vista</strong>
      da importância de <strong>{{ $fmt($cashPay) }}</strong>
      @if((int) $sale->discount_amount > 0)
        (valor de tabela {{ $fmt($sale->total_value) }}, com desconto de {{ $fmt($sale->discount_amount) }}
        @if($sale->discount_percent)
          — {{ number_format((float) $sale->discount_percent, 2, ',', '.') }}%
        @endif
        )
      @endif
      , na data de assinatura deste instrumento, em {{ $saleDate }}.
    </p>
    @else
    <p>
      O(s) <strong>Outorgado(s) Comprador(es)</strong> pagará(ão) o valor total de
      <strong>{{ $fmt($sale->total_value) }}</strong>, sendo <strong>{{ $fmt($sale->down_payment) }}</strong>
      pagos no ato da assinatura deste instrumento, ficando o saldo devedor de
      <strong>{{ $fmt($sale->financed_value) }}</strong> dividido em
      <strong>{{ $sale->installments_count }} parcelas</strong>
      iguais, mensais e sucessivas, no valor de <strong>{{ $fmt($sale->installment_value) }}</strong> cada,
      vencendo a primeira em <strong>{{ $firstDue }}</strong> e as demais nas mesmas datas
      dos meses subsequentes, por meio de <strong>Nota Promissória</strong>.
    </p>
    @endif

    <p class="sub-title">Das Obrigações:</p>
    <div class="obligation">
      (1ª) Toda e qualquer importância paga com atraso pelo <strong>Comprador</strong>
      será cobrada multa de <strong>2,5% ao mês</strong>.
    </div>
    <div class="obligation">
      (2ª) O presente contrato é intransferível, vedada ao Comprador a cessão
      a terceiros sem anuência expressa do Vendedor.
    </div>

    <p class="sub-title">Da Rescisão Contratual:</p>
    <p>
      Em caso de desistência do Comprador, implicará rescisão contratual com
      retenção de <strong>30% (trinta por cento)</strong> dos valores pagos a título
      de multa compensatória, acrescida de <strong>10% (dez por cento)</strong>
      referente a despesas administrativas.
    </p>
  </div>

  <div class="clause">
    <p class="clause-title">Cláusula Terceira — Da Transferência</p>
    <p>
      O <strong>Vendedor</strong> obriga-se a transferir o imóvel objeto deste contrato
      para o nome do(s) <strong>Comprador(es)</strong>, tudo em conformidade com a legislação
      vigente, após a quitação integral do preço ajustado.
    </p>
  </div>

  <div class="clause">
    <p class="clause-title">Cláusula Quarta — Da Irrevogabilidade</p>
    <p>
      Este instrumento é celebrado em caráter <strong>irrevogável e irretratável</strong>,
      obrigando as partes, seus herdeiros e sucessores ao fiel cumprimento de todas as
      cláusulas e condições aqui estabelecidas, tudo nos termos da legislação civil vigente.
    </p>
  </div>

  <div class="signatures-section">
    <p class="signature-date">Cafarnaum – BA, {{ $saleDate }}.</p>

    <div class="signatures-grid">
      <div class="signature-block">
        <p class="signature-name">Sidiclei Novais Baretto</p>
        <p class="signature-role">Outorgante Vendedor</p>
        <p class="signature-cpf">CPF: 311.168.558-60</p>
      </div>

      @foreach($allBuyers as $i => $buyer)
      <div class="signature-block">
        <p class="signature-name">{{ $buyer->name }}</p>
        <p class="signature-role">{{ $i === 0 ? 'Outorgado(a) Comprador(a)' : 'Co-Comprador(a)' }}</p>
        <p class="signature-cpf">CPF: {{ $buyer->cpf }}</p>
      </div>
      @endforeach
    </div>
  </div>

  <div class="footer">
    <p class="footer-left">
      Contrato Nº {{ str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT) }}/{{ $sale->sale_date->format('Y') }}
      · Gerado em {{ now()->translatedFormat('d/m/Y H:i') }}
    </p>
    <p class="footer-right">sid360.com.br</p>
  </div>

</div>
</body>
</html>
