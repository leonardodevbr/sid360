<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 12pt;
    line-height: 1.6;
    color: #000;
  }

  .page {
    padding: 2cm 2.5cm;
  }

  h1 {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    text-decoration: underline;
    margin-bottom: 6pt;
  }

  h2 {
    text-align: center;
    font-size: 12pt;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 18pt;
  }

  .clause-title {
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 14pt;
    margin-bottom: 4pt;
  }

  .sub-title {
    font-weight: bold;
    margin-top: 10pt;
    margin-bottom: 4pt;
  }

  p {
    text-align: justify;
    margin-bottom: 8pt;
  }

  .party {
    margin-bottom: 10pt;
  }

  .party strong {
    font-weight: bold;
    text-transform: uppercase;
  }

  .signatures {
    margin-top: 60pt;
    display: flex;
    justify-content: space-between;
  }

  .signature-block {
    text-align: center;
    width: 45%;
  }

  .signature-line {
    border-top: 1px solid #000;
    margin-bottom: 4pt;
  }

  .footer-note {
    margin-top: 40pt;
    font-size: 9pt;
    border-top: 1px solid #ccc;
    padding-top: 6pt;
    text-align: center;
    color: #555;
  }
</style>
</head>
<body>
<div class="page">

  <h1>Pré-Contrato de Compra e Venda de Lote</h1>
  <h2>Identificação das Partes Contratantes</h2>

  {{-- VENDEDOR --}}
  <div class="party">
    <p>
      <strong>Vendedor:</strong> Sr° <strong>Sidiclei Novais Baretto</strong>,
      brasileiro, maior, capaz, portador do RG 08.280.665-90 SSP/BA e do CPF: 311.168.558-60,
      residente e domiciliado na Rua Arlindo Montino 4, s/n° no centro desta cidade de
      Cafarnaum no Estado da Bahia, aqui denominado de <strong>Outorgante Vendedor</strong>.
    </p>
  </div>

  {{-- COMPRADOR --}}
  <div class="party">
    <p>
      <strong>Comprador(a):</strong> Sr°/Srª <strong>{{ strtoupper($sale->client->name) }}</strong>,
      brasileiro(a), maior, capaz
      @if($sale->client->marital_status)
        , {{ \App\Models\Client::maritalStatusLabel($sale->client->marital_status) }}
      @endif
      @if($sale->client->profession)
        , {{ $sale->client->profession }}
      @endif
      @if($sale->client->cpf)
        portador(a) do CPF de N° {{ $sale->client->cpf }}
      @endif
      @if($sale->client->rg)
        e RG de n° {{ $sale->client->rg }}
        @if($sale->client->rg_issuer)
          {{ $sale->client->rg_issuer }}
        @endif
      @endif
      @if($sale->client->full_address)
        , residente e domiciliado(a) na {{ $sale->client->full_address }},
        {{ $sale->client->city ?? 'Cafarnaum' }} no Estado da {{ $sale->client->state ?? 'Bahia' }}
      @endif
      , aqui denominado(a) de <strong>Outorgado(a) Comprador(a)</strong>.
    </p>
  </div>

  <p>
    Têm entre os mesmos, de maneira justa e acordada, o presente Contrato particular de
    Compromisso de Compra e Venda de um <strong>Imóvel</strong>, ficando desde já aceito,
    pelas cláusulas abaixo descritas.
  </p>

  {{-- CLÁUSULA PRIMEIRA --}}
  <p class="clause-title">Cláusula Primeira:</p>
  <p>
    O primeiro acima qualificado é legítimo proprietário de um
    <strong>Terreno (Lote)</strong>,
    localizado no Loteamento <strong>{{ $sale->lot->development->name }}</strong>,
    Quadra n° <strong>{{ $sale->lot->block ?? '–' }}</strong>,
    Lote n° <strong>{{ $sale->lot->number }}</strong>,
    @if($sale->lot->area)
      totalizando uma área de <strong>{{ number_format((float)$sale->lot->area, 0, ',', '.') }}m²
      ({{ $sale->lot->area_extenso ?? number_format((float)$sale->lot->area, 0, ',', '.') . ' metros quadrados' }})</strong>
    @endif
    , no Município desta cidade de Cafarnaum no Estado da Bahia.
    Ficando desde já autorizado o Sr° Oficial de registro responsável para efetuar todas
    as alterações para a devida transferência.
  </p>

  {{-- CLÁUSULA SEGUNDA --}}
  <p class="clause-title">Cláusula Segunda:</p>
  <p class="sub-title">Do Pagamento:</p>

  @php
    $totalFormatted    = 'R$ ' . number_format((int) $sale->total_value / 100, 2, ',', '.');
    $cashFormatted     = $sale->cash_value ? 'R$ ' . number_format((int) $sale->cash_value / 100, 2, ',', '.') : null;
    $downFormatted     = 'R$ ' . number_format((int) $sale->down_payment / 100, 2, ',', '.');
    $financedFormatted = 'R$ ' . number_format((int) $sale->financed_value / 100, 2, ',', '.');
    $installFormatted  = 'R$ ' . number_format((int) $sale->installment_value / 100, 2, ',', '.');
    $firstDue          = \Carbon\Carbon::parse($sale->first_due_date)->translatedFormat('d \d\e F \d\e Y');
    $saleDate          = \Carbon\Carbon::parse($sale->sale_date)->translatedFormat('d \d\e F \d\e Y');
  @endphp

  <p>
    O comprador assegura ter ciência do preço
    @if($cashFormatted)
      <strong>à vista</strong> de <strong>{{ $cashFormatted }}</strong> e opta pelo
    @endif
    pagamento <strong>a prazo</strong> no valor de <strong>{{ $totalFormatted }}</strong>,
    pagando neste ato de assinatura a importância de <strong>{{ $downFormatted }}</strong>,
    ficando o saldo devedor de <strong>{{ $financedFormatted }}</strong> a ser dividido em
    <strong>{{ $sale->installments_count }} ({{ $sale->installments_count_extenso ?? $sale->installments_count }}) parcelas</strong>
    iguais, mensais e sucessivas no valor de <strong>{{ $installFormatted }}</strong>,
    vencendo a primeira no dia <strong>{{ $firstDue }}</strong> e as demais nas mesmas datas
    dos meses subsequentes, devendo ser pagas por meio de <strong>Promissória</strong>.
  </p>

  <p class="sub-title">Das Obrigações:</p>

  <p>
    (1ª) Toda e qualquer importância paga com atraso pelo <strong>Comprador</strong>
    será cobrada multa de <strong>2,5% ao mês</strong>.
  </p>

  <p>
    (2ª) O presente contrato é intransferível, vedado ao comprador a transferência a Terceiros.
  </p>

  {{-- RESCISÃO --}}
  <p class="sub-title">Da Rescisão Contratual:</p>
  <p>
    Em caso de desistência, implicará como quebra de contrato, acarretando multa de
    <strong>30% (Trinta por cento)</strong> do valor já pago e
    <strong>10% (Dez por cento)</strong> com despesas administrativas.
  </p>

  {{-- CLÁUSULA TERCEIRA --}}
  <p class="clause-title">Cláusula Terceira:</p>
  <p>
    O <strong>Vendedor</strong> passa esse documento para o nome do comprador tudo
    conforme a Lei.
  </p>

  {{-- CLÁUSULA QUARTA --}}
  <p class="clause-title">Cláusula Quarta:</p>
  <p>
    Este instrumento é <strong>Irrevogável</strong> e <strong>Irretratável</strong>,
    sendo seus herdeiros e sucessores obrigados a respeitar os termos desse instrumento
    tudo conforme da lei.
  </p>

  {{-- DATA E ASSINATURAS --}}
  <p style="margin-top: 20pt; text-align: center;">
    Cafarnaum – BA, {{ $saleDate }}.
  </p>

  <table style="width:100%; margin-top: 50pt;">
    <tr>
      <td style="width:45%; text-align:center; vertical-align:bottom; padding: 0 20pt;">
        <div style="border-top: 1px solid #000; padding-top: 4pt;">
          <strong>Sidiclei Novais Baretto</strong><br>
          <span style="font-size:10pt;">Outorgante Vendedor</span><br>
          <span style="font-size:9pt;">CPF: 311.168.558-60</span>
        </div>
      </td>
      <td style="width:10%;"></td>
      <td style="width:45%; text-align:center; vertical-align:bottom; padding: 0 20pt;">
        <div style="border-top: 1px solid #000; padding-top: 4pt;">
          <strong>{{ $sale->client->name }}</strong><br>
          <span style="font-size:10pt;">Outorgado(a) Comprador(a)</span><br>
          <span style="font-size:9pt;">CPF: {{ $sale->client->cpf }}</span>
        </div>
      </td>
    </tr>
  </table>

  <div class="footer-note">
    Documento gerado pelo sistema Sid360 · sid360.com.br
  </div>

</div>
</body>
</html>
