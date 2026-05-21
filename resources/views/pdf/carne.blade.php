<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  @page { margin: 1cm 1.2cm; }

  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 9pt;
    color: #000;
    background: #fff;
  }

  .cover {
    text-align: center;
    padding: 20pt 10pt;
    border: 2pt solid #000;
    margin-bottom: 12pt;
    page-break-after: always;
  }

  .cover-brand {
    font-size: 20pt;
    font-weight: bold;
    letter-spacing: 1pt;
    margin-bottom: 4pt;
  }

  .cover-subtitle {
    font-size: 10pt;
    color: #444;
    margin-bottom: 20pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
  }

  .cover-title {
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1pt;
    margin-bottom: 20pt;
    padding: 8pt 0;
    border-top: 1pt solid #000;
    border-bottom: 1pt solid #000;
  }

  .cover-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0 auto 20pt;
    text-align: left;
  }

  .cover-table td {
    padding: 5pt 8pt;
    font-size: 10pt;
    border-bottom: 0.5pt solid #ddd;
    vertical-align: top;
  }

  .cover-table td:first-child {
    font-weight: bold;
    width: 35%;
    color: #333;
    font-size: 8.5pt;
    text-transform: uppercase;
  }

  .cover-notice {
    font-size: 8pt;
    color: #555;
    margin-top: 16pt;
    border-top: 0.5pt solid #ccc;
    padding-top: 8pt;
    text-align: center;
    line-height: 1.5;
  }

  .parcela {
    border: 1.5pt solid #000;
    margin-bottom: 8pt;
    page-break-inside: avoid;
  }

  .parcela-header {
    background: #1C0A06;
    color: #fff;
    padding: 5pt 8pt;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .parcela-header-brand {
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: 0.5pt;
  }

  .parcela-header-num {
    font-size: 9pt;
    text-align: right;
    color: #C9A84C;
    font-weight: bold;
  }

  .parcela-body {
    padding: 6pt 8pt;
  }

  .parcela-grid {
    display: flex;
    width: 100%;
  }

  .parcela-col {
    flex: 1;
    padding: 0 6pt 0 0;
  }

  .parcela-col:last-child { padding-right: 0; }

  .parcela-label {
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #666;
    margin-bottom: 1pt;
  }

  .parcela-value {
    font-size: 10pt;
    font-weight: bold;
    color: #000;
  }

  .parcela-value.valor {
    font-size: 13pt;
    color: #1C0A06;
  }

  .parcela-divider {
    border: none;
    border-top: 0.5pt dashed #999;
    margin: 5pt 0;
  }

  .parcela-info {
    font-size: 8pt;
    color: #555;
    display: flex;
    justify-content: space-between;
  }

  .parcela-assinatura {
    margin-top: 6pt;
    border-top: 0.5pt solid #999;
    padding-top: 4pt;
    display: flex;
    justify-content: flex-end;
  }

  .parcela-assinatura-line {
    width: 160pt;
    border-top: 0.5pt solid #000;
    text-align: center;
    padding-top: 2pt;
    font-size: 7pt;
    color: #555;
  }

  .page-break { page-break-after: always; }
</style>
</head>
<body>

@php
  $fmt = fn ($v) => 'R$ ' . number_format((int) $v / 100, 2, ',', '.');
  $saleDate = \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y');
  $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT) . '/' . $sale->sale_date->format('Y');
  $allBuyers = $sale->buyers->count() > 0 ? $sale->buyers : collect([$sale->client]);
  $buyerNames = $allBuyers->pluck('name')->join(' / ');
  $buyerCpfs = $allBuyers->pluck('cpf')->filter()->join(' / ');
  $paymentInfo = $sale->lot->development->payment_info ?? 'Entre em contato com o corretor: (74) 9 8823-0151';
@endphp

<div class="cover">
  <div class="cover-brand">SID360</div>
  <div class="cover-subtitle">Imóveis Residencial, Comercial e Rural · Cafarnaum — BA</div>
  <div class="cover-title">Carnê de Pagamento</div>

  <table class="cover-table">
    <tr>
      <td>Contrato nº</td>
      <td>{{ $contractNo }}</td>
    </tr>
    <tr>
      <td>Comprador(es)</td>
      <td>{{ $buyerNames }}</td>
    </tr>
    <tr>
      <td>CPF</td>
      <td>{{ $buyerCpfs ?: '—' }}</td>
    </tr>
    <tr>
      <td>Empreendimento</td>
      <td>{{ $sale->lot->development->name }}</td>
    </tr>
    <tr>
      <td>Lote</td>
      <td>
        Quadra {{ $sale->lot->block ?? '–' }} · Lote {{ $sale->lot->number }}
        @if($sale->lot->area)
          · {{ number_format((float) $sale->lot->area, 0, ',', '.') }}m²
        @endif
      </td>
    </tr>
    <tr>
      <td>Valor total</td>
      <td>{{ $fmt($sale->total_value) }}</td>
    </tr>
    <tr>
      <td>Entrada paga</td>
      <td>{{ $fmt($sale->down_payment) }} em {{ $saleDate }}</td>
    </tr>
    <tr>
      <td>Parcelamento</td>
      <td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td>
    </tr>
    <tr>
      <td>Vencimento</td>
      <td>Todo dia {{ $sale->payment_day }} de cada mês</td>
    </tr>
    <tr>
      <td>Emissão</td>
      <td>{{ now()->format('d/m/Y') }}</td>
    </tr>
  </table>

  <div class="cover-notice">
    Em caso de atraso no pagamento, incidirá multa de 2,5% ao mês sobre o valor da parcela.<br>
    Pagamentos realizados em: {{ $paymentInfo }}<br>
    <strong>Sid360 Imóveis · sid360.com.br · (74) 9 8823-0151</strong>
  </div>
</div>

@foreach($sale->installments->chunk(3) as $pageIndex => $chunk)
  @if($pageIndex > 0)
    <div class="page-break"></div>
  @endif

  @foreach($chunk as $inst)
  @php
    $due = \Carbon\Carbon::parse($inst->due_date);
    $isPaid = $inst->status === 'paid';
  @endphp
  <div class="parcela">
    <div class="parcela-header">
      <div class="parcela-header-brand">SID360 IMÓVEIS</div>
      <div class="parcela-header-num">
        PARCELA {{ str_pad((string) $inst->number, 2, '0', STR_PAD_LEFT) }}
        / {{ str_pad((string) $sale->installments_count, 2, '0', STR_PAD_LEFT) }}
        @if($isPaid)
          · PAGO
        @endif
      </div>
    </div>
    <div class="parcela-body">
      <div class="parcela-grid">
        <div class="parcela-col" style="flex:2">
          <div class="parcela-label">Comprador(es)</div>
          <div class="parcela-value" style="font-size:9pt;">{{ $buyerNames }}</div>
        </div>
        <div class="parcela-col">
          <div class="parcela-label">Contrato</div>
          <div class="parcela-value" style="font-size:9pt;">Nº {{ $contractNo }}</div>
        </div>
      </div>

      <hr class="parcela-divider">

      <div class="parcela-grid">
        <div class="parcela-col" style="flex:2">
          <div class="parcela-label">Lote</div>
          <div class="parcela-value" style="font-size:9pt;">
            {{ $sale->lot->development->name }} ·
            Q{{ $sale->lot->block ?? '–' }} · L{{ $sale->lot->number }}
          </div>
        </div>
        <div class="parcela-col">
          <div class="parcela-label">Vencimento</div>
          <div class="parcela-value" style="font-size:10pt;">{{ $due->format('d/m/Y') }}</div>
        </div>
        <div class="parcela-col">
          <div class="parcela-label">Valor</div>
          <div class="parcela-value valor">{{ $fmt($inst->value) }}</div>
        </div>
      </div>

      @if($isPaid && $inst->paid_at)
      <hr class="parcela-divider">
      <div class="parcela-info">
        <span>Pago em {{ \Carbon\Carbon::parse($inst->paid_at)->format('d/m/Y') }}</span>
        <span>Sid360 · (74) 9 8823-0151</span>
      </div>
      @else
      <div class="parcela-assinatura">
        <div class="parcela-assinatura-line">Assinatura / Recibo do pagamento</div>
      </div>
      @endif
    </div>
  </div>
  @endforeach
@endforeach

</body>
</html>
