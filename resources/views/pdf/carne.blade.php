<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  @page {
    size: A4 portrait;
    margin: 1cm 1.2cm;
  }

  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 9pt;
    color: #1a1a1a;
    background: #fff;
  }

  /* ── CAPA ─────────────────────────────────────── */
  .cover {
    text-align: center;
    padding: 24pt 20pt 20pt;
    border: 1.5pt solid #555;
    margin-bottom: 0;
    page-break-after: always;
  }

  .cover-logo {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 28pt;
    font-weight: 900;
    letter-spacing: 2pt;
    color: #1a1a1a;
    margin-bottom: 3pt;
  }

  .cover-logo span {
    color: #7a5c2e;
  }

  .cover-tagline {
    font-size: 8pt;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1.5pt;
    margin-bottom: 16pt;
  }

  .cover-rule {
    border: none;
    border-top: 2pt solid #1a1a1a;
    margin: 0 auto 12pt;
    width: 100%;
  }

  .cover-title {
    font-size: 13pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 3pt;
    color: #1a1a1a;
    margin-bottom: 16pt;
  }

  .cover-rule-thin {
    border: none;
    border-top: 0.5pt solid #aaa;
    margin: 0 auto 16pt;
    width: 100%;
  }

  .cover-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    margin-bottom: 16pt;
  }

  .cover-table tr {
    border-bottom: 0.5pt solid #e0e0e0;
  }

  .cover-table tr:last-child {
    border-bottom: none;
  }

  .cover-table td {
    padding: 5pt 4pt;
    font-size: 9.5pt;
    vertical-align: top;
  }

  .cover-table td:first-child {
    font-weight: bold;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #555;
    width: 32%;
  }

  .cover-notice {
    font-size: 7.5pt;
    color: #666;
    border-top: 0.5pt solid #ccc;
    padding-top: 10pt;
    line-height: 1.6;
  }

  /* ── PARCELAS ─────────────────────────────────── */
  /* Container de uma parcela */
  .parcela {
    border: 1pt solid #999;
    margin-bottom: 6pt;
    page-break-inside: avoid;
  }

  /* Cabeçalho da parcela — sem cor escura pesada */
  .parcela-header {
    background: #f0f0f0;
    border-bottom: 1pt solid #999;
    padding: 4pt 8pt;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .parcela-header-brand {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    font-weight: bold;
    color: #1a1a1a;
    letter-spacing: 0.5pt;
  }

  .parcela-header-num {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    font-weight: bold;
    color: #7a5c2e;
    text-align: right;
  }

  /* Corpo da parcela */
  .parcela-body {
    padding: 5pt 8pt 4pt;
  }

  /* Grid de 4 colunas: Comprador | Contrato | Vencimento | Valor */
  .parcela-grid {
    display: flex;
    gap: 0;
    width: 100%;
    margin-bottom: 4pt;
  }

  .parcela-col {
    flex: 1;
    padding-right: 8pt;
    border-right: 0.5pt solid #e0e0e0;
    margin-right: 8pt;
  }

  .parcela-col:last-child {
    border-right: none;
    padding-right: 0;
    margin-right: 0;
  }

  .parcela-col.wide { flex: 2; }
  .parcela-col.valor-col { text-align: right; }

  .p-label {
    font-size: 6.5pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #888;
    margin-bottom: 1pt;
  }

  .p-value {
    font-size: 9pt;
    font-weight: bold;
    color: #1a1a1a;
    line-height: 1.2;
  }

  .p-value.destaque {
    font-size: 11.5pt;
    color: #2a2a2a;
  }

  /* Linha de assinatura */
  .parcela-footer {
    border-top: 0.5pt dashed #bbb;
    padding-top: 3pt;
    margin-top: 3pt;
    display: flex;
    justify-content: flex-end;
    align-items: center;
  }

  .assinatura-line {
    width: 160pt;
    border-top: 0.5pt solid #555;
    text-align: center;
    padding-top: 2pt;
    font-size: 6.5pt;
    color: #888;
  }

  .pago-stamp {
    font-size: 8pt;
    font-weight: bold;
    color: #2a6a2a;
    border: 1pt solid #2a6a2a;
    padding: 2pt 6pt;
    border-radius: 2pt;
    margin-right: 4pt;
    letter-spacing: 1pt;
  }

  /* Quebra de página */
  .page-break { page-break-after: always; }
</style>
</head>
<body>

@php
  use Carbon\Carbon;
  $fmt = fn($v) => 'R$ ' . number_format((int)$v / 100, 2, ',', '.');
  $fmtDate = fn($d) => Carbon::parse($d)->format('d/m/Y');
  $saleDate   = $fmtDate($sale->sale_date);
  $contractNo = str_pad($sale->id, 4, '0', STR_PAD_LEFT) . '/' . $sale->sale_date->format('Y');
  $allBuyers  = $sale->buyers->count() > 0 ? $sale->buyers : collect([$sale->client]);
  $buyerNames = $allBuyers->pluck('name')->join(' / ');
  $buyerCpfs  = $allBuyers->pluck('cpf')->join(' / ');
  // Filtrar só parcelas de financiamento (excluir entrada)
  $parcelas   = $sale->installments->where('type', '!=', 'down_payment')->values();
@endphp

{{-- ── CAPA ──────────────────────────────────────────── --}}
<div class="cover">
  <div class="cover-logo">SID<span>360</span></div>
  <div class="cover-tagline">Imóveis Residencial, Comercial e Rural · Cafarnaum — BA</div>
  <hr class="cover-rule">
  <div class="cover-title">Carnê de Pagamento</div>
  <hr class="cover-rule-thin">

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
      <td>{{ $buyerCpfs }}</td>
    </tr>
    <tr>
      <td>Empreendimento</td>
      <td>{{ $sale->lot->development->name }}</td>
    </tr>
    <tr>
      <td>Lote</td>
      <td>
        Quadra {{ $sale->lot->block ?? '–' }} · Lote {{ $sale->lot->number }}
        @if($sale->lot->area) · {{ number_format((float)$sale->lot->area, 0, ',', '.') }}m²@endif
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
      <td>Dia do vencimento</td>
      <td>Todo dia {{ $sale->payment_day }} de cada mês</td>
    </tr>
    <tr>
      <td>Emissão</td>
      <td>{{ now()->format('d/m/Y') }}</td>
    </tr>
  </table>

  <div class="cover-notice">
    Em caso de atraso no pagamento, incidirá multa de <strong>2,5% ao mês</strong> sobre o valor da parcela.<br>
    Pagamentos e informações: <strong>(74) 9 8823-0151</strong> · <strong>sid360.com.br</strong>
  </div>
</div>

{{-- ── PARCELAS — 4 por página ───────────────────────── --}}
@foreach($parcelas->chunk(4) as $pageIndex => $chunk)
  @if($pageIndex > 0)<div class="page-break"></div>@endif

  @foreach($chunk as $inst)
  @php
    $due    = Carbon::parse($inst->due_date);
    $isPaid = $inst->status === 'paid';
  @endphp

  <div class="parcela">
    <div class="parcela-header">
      <div class="parcela-header-brand">SID360 IMÓVEIS</div>
      <div class="parcela-header-num">
        PARCELA {{ str_pad($inst->number, 2, '0', STR_PAD_LEFT) }}
        / {{ str_pad($sale->installments_count, 2, '0', STR_PAD_LEFT) }}
      </div>
    </div>

    <div class="parcela-body">
      <div class="parcela-grid">
        <div class="parcela-col wide">
          <div class="p-label">Comprador(es)</div>
          <div class="p-value">{{ $buyerNames }}</div>
        </div>
        <div class="parcela-col">
          <div class="p-label">Contrato</div>
          <div class="p-value">Nº {{ $contractNo }}</div>
        </div>
        <div class="parcela-col">
          <div class="p-label">Lote</div>
          <div class="p-value">Q{{ $sale->lot->block }} · L{{ $sale->lot->number }}</div>
        </div>
        <div class="parcela-col">
          <div class="p-label">Vencimento</div>
          <div class="p-value">{{ $due->format('d/m/Y') }}</div>
        </div>
        <div class="parcela-col valor-col">
          <div class="p-label">Valor</div>
          <div class="p-value destaque">{{ $fmt($inst->value) }}</div>
        </div>
      </div>

      <div class="parcela-footer">
        @if($isPaid)
          <span class="pago-stamp">✓ PAGO em {{ $fmtDate($inst->paid_at) }}</span>
        @else
          <div class="assinatura-line">Assinatura / Recibo do pagamento</div>
        @endif
      </div>
    </div>
  </div>
  @endforeach
@endforeach

</body>
</html>
