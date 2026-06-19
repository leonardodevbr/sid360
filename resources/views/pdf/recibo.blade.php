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

  .header-table td { vertical-align: middle; }

  .brand-name { font-size: 16pt; font-weight: 900; color: #1a3d5c; letter-spacing: 0.3pt; }
  .brand-tagline { font-size: 8pt; color: #666; margin-top: 1mm; }
  .brand-site { font-size: 8pt; color: #888; text-align: right; }

  .divider { border-top: 1.2pt solid #1a3d5c; margin: 4mm 0; }

  .title {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1pt;
    color: #1a3d5c;
    margin: 6mm 0 2mm;
  }

  .receipt-no { text-align: center; font-size: 8.5pt; color: #888; margin-bottom: 6mm; }

  .info-table td {
    font-size: 9.5pt;
    padding: 2.2mm 0;
    border-bottom: 0.5pt solid #e3e3e3;
    vertical-align: top;
  }

  .info-table td.label {
    width: 38%;
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #888;
  }

  .info-table td.value { font-weight: bold; color: #1a1a1a; }

  .value-box {
    margin: 6mm 0;
    padding: 4mm 5mm;
    background: #f5f8fb;
    border: 0.8pt solid #c9d8e3;
    border-radius: 2pt;
    text-align: center;
  }

  .value-box .lbl {
    font-size: 7.5pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #1a4a6e;
  }

  .value-box .val { font-size: 18pt; font-weight: 900; color: #1a3d5c; margin-top: 1mm; }

  .declaration {
    font-size: 9.5pt;
    line-height: 1.6;
    color: #333;
    text-align: justify;
    margin: 6mm 0;
  }

  .signature-row { margin-top: 16mm; }

  .signature-line {
    border-top: 0.6pt solid #444;
    text-align: center;
    font-size: 8.5pt;
    color: #333;
    padding-top: 1.5mm;
    width: 80mm;
    margin: 0 auto;
  }

  .signature-name { font-weight: bold; }
  .signature-sub { font-size: 7.5pt; color: #888; margin-top: 0.5mm; }

  .footer {
    margin-top: 12mm;
    border-top: 0.4pt solid #ddd;
    padding-top: 2mm;
    font-size: 7.5pt;
    color: #999;
    text-align: center;
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
  $loteLabel = 'Q'.($sale->lot->block ?? '–').' · L'.$sale->lot->number
      .($sale->lot->area ? ' · '.number_format((float) $sale->lot->area, 0, ',', '.').'m²' : '');

  $parcelLabel = $installment->type === \App\Models\Installment::TYPE_DOWN_PAYMENT
      ? 'Entrada'
      : 'Parcela '.$installment->number;

  $paymentMethodLabel = \App\Models\Installment::paymentMethodLabel($installment->payment_method) ?: 'Não informado';

  $receiptNo = str_pad((string) $installment->id, 6, '0', STR_PAD_LEFT);
@endphp

<table class="header-table">
  <tr>
    <td width="60%">
      <div class="brand-name">{{ $company['nome'] }}</div>
      <div class="brand-tagline">{{ $company['tagline'] }}</div>
    </td>
    <td width="40%" class="brand-site">{{ $company['site'] }}</td>
  </tr>
</table>
<div class="divider"></div>

<div class="title">Recibo de Pagamento</div>
<div class="receipt-no">Recibo Nº {{ $receiptNo }} &nbsp;·&nbsp; Contrato Nº {{ $contractNo }} &nbsp;·&nbsp; Emitido em {{ now()->format('d/m/Y H:i') }}</div>

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
    <td class="label">Imóvel</td>
    <td class="value">{{ $sale->lot->development->name ?? '—' }} · {{ $loteLabel }}</td>
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
    <td class="value">{{ $fmtDate($installment->paid_at) }}</td>
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
        <div class="signature-name">{{ $seller['name'] }}</div>
        <div class="signature-sub">CPF: {{ $seller['cpf'] }} · RG: {{ $seller['rg'] }} {{ $seller['rg_issuer'] }}</div>
      </div>
    </td>
  </tr>
</table>

<div class="footer">
  {{ $foro['cidade'] }}, {{ $foro['estado'] }} · {{ $company['site'] }} · Documento gerado eletronicamente.
</div>

</body>
</html>
