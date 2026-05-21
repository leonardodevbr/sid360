<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  @page {
    size: A4 portrait;
    margin: 0;
  }

  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #1a1a1a;
    background: #fff;
  }

  /* ══════════════════════════════════════
     CAPA — página inteira
  ══════════════════════════════════════ */
  .cover-page {
    width: 210mm;
    height: 297mm;
    page-break-after: always;
    display: flex;
    flex-direction: column;
  }

  /* Bloco superior — fundo escuro com diagonal */
  .cover-top {
    background: #2C2C2C;
    flex: 0 0 45%;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2cm;
  }

  /* Triângulo decorativo no canto direito */
  .cover-top::after {
    content: '';
    position: absolute;
    bottom: 0; right: 0;
    width: 0; height: 0;
    border-style: solid;
    border-width: 0 0 60mm 80mm;
    border-color: transparent transparent #fff transparent;
  }

  .cover-top-left {
    color: #fff;
    z-index: 1;
  }

  .cover-logo img {
    height: 48pt;
    width: auto;
    display: block;
    margin-bottom: 8pt;
  }

  .cover-top-title {
    font-size: 11pt;
    font-weight: normal;
    text-transform: uppercase;
    letter-spacing: 3pt;
    color: rgba(255,255,255,0.7);
    margin-bottom: 6pt;
  }

  .cover-top-big {
    font-size: 28pt;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 2pt;
    line-height: 1;
    color: #fff;
  }

  .cover-top-big span { color: #C8A96E; }

  .cover-top-right {
    z-index: 1;
    text-align: right;
    color: rgba(255,255,255,0.85);
  }

  .cover-top-right .label {
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 1pt;
    color: rgba(255,255,255,0.5);
    margin-bottom: 2pt;
  }

  .cover-top-right .value {
    font-size: 9.5pt;
    font-weight: bold;
    color: #fff;
    margin-bottom: 8pt;
  }

  /* Divisor pontilhado entre blocos da capa */
  .cover-divider {
    border: none;
    border-top: 2pt dashed #aaa;
    margin: 0;
  }

  /* Bloco inferior — fundo claro com dados do pagador */
  .cover-bottom {
    flex: 1;
    background: #f7f7f7;
    padding: 1cm 2cm;
    display: flex;
    flex-direction: column;
    gap: 16pt;
  }

  .cover-bottom-title {
    font-size: 8pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1pt;
    color: #555;
    border-bottom: 1pt solid #ccc;
    padding-bottom: 4pt;
    margin-bottom: 6pt;
  }

  .cover-data-grid {
    width: 100%;
    border-collapse: collapse;
  }

  .cover-data-grid td {
    padding: 5pt 4pt;
    font-size: 9.5pt;
    border-bottom: 0.5pt solid #e0e0e0;
    vertical-align: top;
  }

  .cover-data-grid td:first-child {
    font-weight: bold;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #777;
    width: 36%;
    white-space: nowrap;
  }

  .cover-footer-notice {
    margin-top: auto;
    padding-top: 12pt;
    border-top: 0.5pt solid #ccc;
    font-size: 8pt;
    color: #666;
    text-align: center;
    line-height: 1.6;
  }

  /* ══════════════════════════════════════
     PÁGINAS DE PARCELAS — 3 por A4
  ══════════════════════════════════════ */
  .parcelas-page {
    width: 210mm;
    height: 297mm;
    page-break-after: always;
    display: flex;
    flex-direction: column;
  }

  /* Cada parcela ocupa exatamente 1/3 da página */
  .parcela {
    flex: 0 0 33.333%;
    height: 99mm;
    border-bottom: 2pt dashed #bbb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .parcela:last-child {
    border-bottom: none;
  }

  /* Cabeçalho da parcela */
  .parcela-header {
    background: #e8e8e8;
    border-bottom: 1pt solid #bbb;
    padding: 5pt 12pt;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
  }

  .parcela-brand img {
    height: 14pt;
    width: auto;
    display: block;
  }

  .parcela-brand {
    font-size: 10pt;
    font-weight: 900;
    color: #2C2C2C;
    letter-spacing: 1pt;
    text-transform: uppercase;
  }

  .parcela-brand span { color: #8B6A2E; }

  .parcela-num {
    font-size: 10pt;
    font-weight: bold;
    color: #8B6A2E;
    letter-spacing: 0.5pt;
  }

  /* Corpo da parcela */
  .parcela-body {
    flex: 1;
    padding: 8pt 12pt 6pt;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  /* Linha principal de dados */
  .parcela-data-row {
    display: flex;
    gap: 0;
    align-items: flex-start;
    flex: 1;
  }

  .parcela-data-col {
    flex: 1;
    padding-right: 10pt;
    border-right: 0.5pt solid #ddd;
    margin-right: 10pt;
  }

  .parcela-data-col:last-child {
    border-right: none;
    padding-right: 0;
    margin-right: 0;
  }

  .parcela-data-col.right {
    text-align: right;
    flex: 0 0 auto;
    min-width: 80pt;
  }

  .p-label {
    font-size: 6.5pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #999;
    margin-bottom: 2pt;
  }

  .p-val {
    font-size: 9pt;
    font-weight: bold;
    color: #1a1a1a;
    line-height: 1.3;
  }

  .p-val.destaque {
    font-size: 15pt;
    color: #1a1a1a;
    letter-spacing: -0.5pt;
  }

  /* Rodapé da parcela */
  .parcela-footer {
    border-top: 0.5pt dashed #ccc;
    padding-top: 4pt;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6pt;
  }

  .parcela-info-small {
    font-size: 7pt;
    color: #aaa;
  }

  .assinatura-line {
    width: 140pt;
    border-top: 0.5pt solid #888;
    text-align: center;
    padding-top: 2pt;
    font-size: 6.5pt;
    color: #aaa;
  }

  .pago-badge {
    font-size: 8pt;
    font-weight: bold;
    color: #2a6a2a;
    border: 1pt solid #2a6a2a;
    padding: 2pt 8pt;
    border-radius: 3pt;
    letter-spacing: 1pt;
  }

  .page-break { page-break-after: always; }
</style>
</head>
<body>

@php
  use Carbon\Carbon;

  $brandLogoPath = public_path('img/logo-systema.png');
  $brandLogoSrc = is_readable($brandLogoPath)
      ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($brandLogoPath))
      : null;

  $fmt     = fn($v) => 'R$ ' . number_format((int)$v / 100, 2, ',', '.');
  $fmtDate = fn($d) => Carbon::parse($d)->format('d/m/Y');

  $contractNo = str_pad($sale->id, 4, '0', STR_PAD_LEFT) . '/' . $sale->sale_date->format('Y');
  $allBuyers  = $sale->buyers->count() > 0 ? $sale->buyers : collect([$sale->client]);
  $buyerNames = $allBuyers->pluck('name')->join(' / ');
  $buyerCpfs  = $allBuyers->pluck('cpf')->join(' / ');
  $parcelas   = $sale->installments->where('type', '!=', 'down_payment')->values();
@endphp

{{-- ══════════════════════════════════════
     CAPA
══════════════════════════════════════ --}}
<div class="cover-page">

  {{-- Bloco superior escuro --}}
  <div class="cover-top">
    <div class="cover-top-left">
      @if($brandLogoSrc)
        <div class="cover-logo">
          <img src="{{ $brandLogoSrc }}" alt="Sid360">
        </div>
      @else
        <div class="cover-top-big">SID<span>360</span></div>
      @endif
      <div class="cover-top-title">Carnê de Pagamento</div>
    </div>
    <div class="cover-top-right">
      <div class="label">Contrato Nº</div>
      <div class="value">{{ $contractNo }}</div>
      <div class="label">Emissão</div>
      <div class="value">{{ now()->format('d/m/Y') }}</div>
      <div class="label">Parcelas</div>
      <div class="value">{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</div>
    </div>
  </div>

  <hr class="cover-divider">

  {{-- Bloco inferior claro --}}
  <div class="cover-bottom">

    <div>
      <div class="cover-bottom-title">Dados do Pagador</div>
      <table class="cover-data-grid">
        <tr>
          <td>Comprador(es)</td>
          <td>{{ $buyerNames }}</td>
        </tr>
        <tr>
          <td>CPF</td>
          <td>{{ $buyerCpfs }}</td>
        </tr>
      </table>
    </div>

    <div>
      <div class="cover-bottom-title">Dados do Imóvel</div>
      <table class="cover-data-grid">
        <tr>
          <td>Empreendimento</td>
          <td>{{ $sale->lot->development->name }}</td>
        </tr>
        <tr>
          <td>Lote</td>
          <td>
            Quadra {{ $sale->lot->block ?? '–' }} · Lote {{ $sale->lot->number }}
            @if($sale->lot->area) · {{ number_format((float)$sale->lot->area, 0, ',', '.') }}m² @endif
          </td>
        </tr>
      </table>
    </div>

    <div>
      <div class="cover-bottom-title">Resumo Financeiro</div>
      <table class="cover-data-grid">
        <tr>
          <td>Valor total</td>
          <td>{{ $fmt($sale->total_value) }}</td>
        </tr>
        <tr>
          <td>Entrada paga</td>
          <td>{{ $fmt($sale->down_payment) }} em {{ $fmtDate($sale->sale_date) }}</td>
        </tr>
        <tr>
          <td>Parcelamento</td>
          <td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td>
        </tr>
        <tr>
          <td>Dia do vencimento</td>
          <td>Todo dia {{ $sale->payment_day }} de cada mês</td>
        </tr>
      </table>
    </div>

    <div class="cover-footer-notice">
      Em caso de atraso, incidirá multa de <strong>2,5% ao mês</strong> sobre o valor da parcela.<br>
      Pagamentos e informações: <strong>(74) 9 8823-0151</strong> · <strong>sid360.com.br</strong>
    </div>

  </div>
</div>

{{-- ══════════════════════════════════════
     PARCELAS — 3 por página
══════════════════════════════════════ --}}
@foreach($parcelas->chunk(3) as $pageIndex => $chunk)

<div class="parcelas-page">
  @foreach($chunk as $inst)
  @php
    $due    = Carbon::parse($inst->due_date);
    $isPaid = $inst->status === 'paid';
  @endphp

  <div class="parcela">
    <div class="parcela-header">
      <div class="parcela-brand">
        @if($brandLogoSrc)
          <img src="{{ $brandLogoSrc }}" alt="Sid360 Imóveis">
        @else
          SID<span>360</span> IMÓVEIS
        @endif
      </div>
      <div class="parcela-num">
        PARCELA {{ str_pad($inst->number, 2, '0', STR_PAD_LEFT) }}
        / {{ str_pad($sale->installments_count, 2, '0', STR_PAD_LEFT) }}
        @if($isPaid) &nbsp;·&nbsp; ✓ PAGO @endif
      </div>
    </div>

    <div class="parcela-body">
      <div class="parcela-data-row">

        <div class="parcela-data-col">
          <div class="p-label">Comprador(es)</div>
          <div class="p-val">{{ $buyerNames }}</div>
        </div>

        <div class="parcela-data-col">
          <div class="p-label">Contrato</div>
          <div class="p-val">Nº {{ $contractNo }}</div>
          <div class="p-label" style="margin-top:6pt;">Lote</div>
          <div class="p-val">Q{{ $sale->lot->block }} · L{{ $sale->lot->number }}</div>
        </div>

        <div class="parcela-data-col">
          <div class="p-label">Vencimento</div>
          <div class="p-val" style="font-size:11pt;">{{ $due->format('d/m/Y') }}</div>
        </div>

        <div class="parcela-data-col right">
          <div class="p-label">Valor</div>
          <div class="p-val destaque">{{ $fmt($inst->value) }}</div>
        </div>

      </div>

      <div class="parcela-footer">
        <div class="parcela-info-small">
          Multa por atraso: 2,5% ao mês &nbsp;·&nbsp; (74) 9 8823-0151
        </div>
        @if($isPaid)
          <span class="pago-badge">✓ PAGO em {{ $fmtDate($inst->paid_at) }}</span>
        @else
          <div class="assinatura-line">Assinatura / Recibo do pagamento</div>
        @endif
      </div>
    </div>
  </div>
  @endforeach

  {{-- Preencher espaço se tiver menos de 3 parcelas na última página --}}
  @for($i = $chunk->count(); $i < 3; $i++)
  <div class="parcela" style="background:#fafafa;"></div>
  @endfor

</div>

@endforeach

</body>
</html>
