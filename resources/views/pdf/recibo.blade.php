<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  @page { size: A4 portrait; margin: 20mm 18mm; }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #1a1a1a;
    background: #fff;
  }

  table { border-collapse: collapse; width: 100%; }

  .brand-tagline { font-size: 7.5pt; color: rgba(255,255,255,0.72); margin-top: 1.5mm; }

  .doc-tag {
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1pt;
    color: #C8A96E;
  }

  .doc-nums { font-size: 7.5pt; color: rgba(255,255,255,0.85); margin-top: 1.5mm; line-height: 1.55; }

  .body-wrap {
    border: 0.8pt solid #e3e3e3;
    border-top: none;
    padding: 7mm 7mm 5mm;
  }

  .info-table td {
    font-size: 9.5pt;
    padding: 2.4mm 0;
    border-bottom: 0.5pt solid #e3e3e3;
    vertical-align: top;
  }

  .info-table td.label {
    width: 38%;
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #7a8a99;
  }

  .info-table td.value { font-weight: bold; color: #1a1a1a; }

  .pago-badge {
    font-size: 7.5pt;
    font-weight: bold;
    color: #2a6a2a;
    border: 0.8pt solid #2a6a2a;
    padding: 1pt 5pt;
    border-radius: 2pt;
    margin-left: 4pt;
  }

  .value-box {
    margin: 6mm 0;
    padding: 4.5mm 5mm;
    background: #f5f8fb;
    border: 0.8pt solid #c9d8e3;
    border-top: 2.2pt solid #C8A96E;
    border-radius: 2pt;
    text-align: center;
  }

  .value-box .lbl {
    font-size: 7.5pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #1a4a6e;
  }

  .value-box .val { font-size: 19pt; font-weight: 900; color: #1a3d5c; margin-top: 1mm; }

  .declaration {
    font-size: 9.5pt;
    line-height: 1.65;
    color: #333;
    text-align: justify;
    text-indent: 1cm;
    margin: 6mm 0 2mm;
  }

  .signature-row { margin-top: 14mm; }

  .signature-line {
    border-top: 0.6pt solid #444;
    text-align: center;
    font-size: 8.5pt;
    color: #333;
    padding-top: 1.5mm;
    width: 80mm;
    margin: 0 auto;
  }

  .signature-role { font-size: 7.5pt; color: #999; text-transform: uppercase; letter-spacing: 0.4pt; }
  .signature-name { font-weight: bold; margin-top: 0.5mm; }
  .signature-sub { font-size: 7.5pt; color: #888; margin-top: 0.5mm; }

  .footer {
    margin-top: 10mm;
    border-top: 0.4pt solid #ddd;
    padding-top: 2.5mm;
    font-size: 7.5pt;
    color: #999;
    text-align: center;
    line-height: 1.6;
  }
</style>
</head>
<body>

@php
  use Carbon\Carbon;

  $fmt     = fn ($v) => 'R$ '.number_format((int) $v / 100, 2, ',', '.');
  $fmtDate = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';

  $sale = $installment->sale;
  $allBuyers = $sale->buyers->count() > 0 ? $sale->buyers : collect([$sale->client]);
  $buyerNames = $allBuyers->pluck('name')->map(fn ($n) => strtoupper($n))->join(' / ');
  $buyerCpfs = $allBuyers->pluck('cpf')->filter()->join(' / ');

  $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

  // Lote(s) da venda (1 ou mais — lotes vizinhos comprados juntos no mesmo
  // contrato exibem "Lotes X, Y" em vez de mostrar só o lote principal).
  // Mesma lógica usada no carnê (carne.blade.php), pra não ficar incompleto.
  $reciboLots = ($sale->relationLoaded('lots') && $sale->lots->isNotEmpty())
      ? $sale->lots
      : collect([$sale->lot])->filter();

  if ($reciboLots->count() > 1) {
      $lotsArea = $reciboLots->sum(fn ($lot) => (float) ($lot->area ?? 0));
      $loteLabel = 'Lotes '.$reciboLots->pluck('number')->join(', ')
          .($lotsArea > 0 ? ' · '.number_format($lotsArea, 0, ',', '.').'m² (total)' : '');
  } else {
      $loteLabel = 'Q'.($sale->lot->block ?? '–').' · L'.$sale->lot->number
          .($sale->lot->area ? ' · '.number_format((float) $sale->lot->area, 0, ',', '.').'m²' : '');
  }

  $loteRowLabel = $reciboLots->count() > 1 ? 'Lotes' : 'Lote';

  $parcelLabel = $installment->type === \App\Models\Installment::TYPE_DOWN_PAYMENT
      ? 'Entrada'
      : 'Parcela '.$installment->number;

  $paymentMethodLabel = \App\Models\Installment::paymentMethodLabel($installment->payment_method) ?: 'Não informado';

  $receiptNo = str_pad((string) $installment->id, 6, '0', STR_PAD_LEFT);

  $capaBlueDark = '#1a3d5c';

  $brandLogoLightPath = public_path('img/logo-systema-light.png');
  $brandLogoLightSrc = is_readable($brandLogoLightPath)
      ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($brandLogoLightPath))
      : null;
@endphp

<table cellspacing="0" cellpadding="0">
  <tr>
    <td bgcolor="{{ $capaBlueDark }}" width="58%" style="padding:5mm 6mm;">
      @if($brandLogoLightSrc)
        <img src="{{ $brandLogoLightSrc }}" alt="{{ $company['nome'] }}" style="height:20pt;width:auto;display:block;">
      @else
        <div style="font-size:15pt;font-weight:900;color:#fff;letter-spacing:0.5pt;">SID<span style="color:#C8A96E;">360</span></div>
      @endif
      <div class="brand-tagline">{{ $company['tagline'] }}</div>
    </td>
    <td bgcolor="{{ $capaBlueDark }}" width="42%" style="padding:5mm 6mm;text-align:right;">
      <div class="doc-tag">Recibo de Pagamento</div>
      <div class="doc-nums">
        Recibo Nº {{ $receiptNo }}<br>
        Contrato Nº {{ $contractNo }}<br>
        Emitido em {{ now()->format('d/m/Y H:i') }}
      </div>
    </td>
  </tr>
  <tr>
    <td colspan="2" bgcolor="#C8A96E" style="height:2.2pt;line-height:2.2pt;font-size:0;">&nbsp;</td>
  </tr>
</table>

<div class="body-wrap">

<table class="info-table">
  <tr>
    <td class="label">Pagador</td>
    <td class="value">{{ $buyerNames }}</td>
  </tr>
  @if($buyerCpfs)
  <tr>
    <td class="label">CPF</td>
    <td class="value">{{ $buyerCpfs }}</td>
  </tr>
  @endif
  <tr>
    <td class="label">Empreendimento</td>
    <td class="value">{{ $sale->lot->development->name ?? '—' }}</td>
  </tr>
  <tr>
    <td class="label">{{ $loteRowLabel }}</td>
    <td class="value">{{ $loteLabel }}</td>
  </tr>
  <tr>
    <td class="label">Referente a</td>
    <td class="value">{{ $parcelLabel }}</td>
  </tr>
  <tr>
    <td class="label">Vencimento</td>
    <td class="value">{{ $fmtDate($installment->due_date) }}</td>
  </tr>
  <tr>
    <td class="label">Data do pagamento</td>
    <td class="value">{{ $fmtDate($installment->paid_at) }}<span class="pago-badge">PAGO</span></td>
  </tr>
  <tr>
    <td class="label">Meio de pagamento</td>
    <td class="value">{{ $paymentMethodLabel }}</td>
  </tr>
  @if($installment->payment_method_description)
  <tr>
    <td class="label">Descrição</td>
    <td class="value">{{ $installment->payment_method_description }}</td>
  </tr>
  @endif
</table>

<div class="value-box">
  <div class="lbl">Valor recebido</div>
  <div class="val">{{ $fmt($installment->value) }}</div>
</div>

<div class="declaration">
  Declaro, para os devidos fins, ter recebido de <strong>{{ $buyerNames }}</strong> a importância de
  <strong>{{ $fmt($installment->value) }}</strong> referente à <strong>{{ strtolower($parcelLabel) }}</strong> do
  contrato de compra e venda do imóvel acima identificado, dando plena quitação ao valor aqui descrito.
</div>

<table class="signature-row" width="100%">
  <tr>
    <td align="center">
      <div class="signature-line">
        <div class="signature-role">Vendedor</div>
        <div class="signature-name">{{ $seller['name'] }}</div>
        <div class="signature-sub">CPF: {{ $seller['cpf'] }} · RG: {{ $seller['rg'] }} {{ $seller['rg_issuer'] }}</div>
      </div>
    </td>
  </tr>
</table>

</div>

<div class="footer">
  Dúvidas sobre este recibo: (74) 9 8823-0151 · {{ $company['site'] }}<br>
  {{ $foro['cidade'] }}, {{ $foro['estado'] }} · Documento gerado eletronicamente.
</div>

</body>
</html>
