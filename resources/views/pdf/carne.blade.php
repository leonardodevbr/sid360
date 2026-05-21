<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  @page { size: A4 portrait; margin: 0; }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 8pt;
    color: #1a1a1a;
  }

  table { border-collapse: collapse; }

  /* Página A4 → 3 faixas de 277pt (841pt ÷ 3) */
  table.page {
    width: 210mm;
    table-layout: fixed;
  }

  tr.strip {
    height: 277pt;
    max-height: 277pt;
  }

  td.strip-cell {
    height: 277pt;
    max-height: 277pt;
    vertical-align: top;
    padding: 0;
    overflow: hidden;
    background: #fff;
  }

  .strip-content {
    width: 100%;
    table-layout: fixed;
  }

  .strip-content > tbody > tr > td {
    overflow: hidden;
    vertical-align: top;
  }

  td.strip-capa   { background: #e8dcc8; }
  td.strip-contra { background: #f7f5ee; }
  td.strip-parcela { background: #fff; }

  td.strip-blank {
    background: #fafafa;
  }

  /* Utilitários */
  .lbl {
    font-size: 6pt;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #888;
    display: block;
    margin-bottom: 1pt;
  }

  .val {
    font-size: 7.5pt;
    font-weight: bold;
    color: #1a1a1a;
    line-height: 1.25;
  }

  .val-lg { font-size: 9pt; }
  .val-xl { font-size: 11pt; color: #2d6a45; }

  .box-white {
    border: 1pt solid #c9a84c;
    padding: 3mm;
  }

  .box-title {
    font-size: 6.5pt;
    font-weight: bold;
    text-transform: uppercase;
    color: #c9a84c;
    margin-bottom: 2pt;
  }

  .box-body {
    font-size: 7pt;
    line-height: 1.4;
    color: #fff;
  }

  .section-title {
    font-size: 6.5pt;
    font-weight: bold;
    text-transform: uppercase;
    color: #555;
    border-bottom: 0.5pt solid #ccc;
    padding-bottom: 1pt;
    margin-bottom: 2pt;
  }

  .mini-grid td {
    padding: 1.5pt 2pt;
    font-size: 7pt;
    border-bottom: 0.5pt solid #e5e0d4;
    vertical-align: top;
  }

  .mini-grid td:first-child {
    width: 32%;
    font-size: 6pt;
    font-weight: bold;
    text-transform: uppercase;
    color: #888;
  }

  .notice {
    font-size: 6pt;
    color: #666;
    text-align: center;
    line-height: 1.4;
    border-top: 0.5pt solid #ddd;
    padding-top: 2mm;
  }

  /* Conteúdo interno — herda 99mm da faixa */

  .recibo-table td {
    padding: 1pt 0;
    font-size: 6.5pt;
    vertical-align: top;
  }

  .recibo-table td.lbl-cell {
    width: 42%;
    font-size: 5.5pt;
    text-transform: uppercase;
    color: #999;
  }

  .recibo-table td.val-cell {
    font-weight: bold;
    font-size: 7pt;
  }

  .ficha td {
    border: 0.5pt solid #ccc;
    padding: 1.5pt 3pt;
    vertical-align: top;
    font-size: 6.5pt;
  }

  .ficha .hdr td {
    border: none;
    border-bottom: 1pt solid #bbb;
    padding-bottom: 2pt;
    vertical-align: middle;
  }

  .ficha .ftr td {
    border: none;
    border-top: 0.5pt dashed #ccc;
    padding-top: 2pt;
    vertical-align: bottom;
  }

  .recibo-sep {
    border-right: 1pt dashed #aaa;
  }

  .pago-badge {
    font-size: 7pt;
    font-weight: bold;
    color: #2a6a2a;
    border: 1pt solid #2a6a2a;
    padding: 1pt 5pt;
  }

  .assinatura {
    border-top: 0.5pt solid #888;
    text-align: center;
    font-size: 6pt;
    color: #aaa;
    padding-top: 1pt;
    width: 110pt;
  }
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
  $fmtDate = fn($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';

  $contractNo = str_pad($sale->id, 4, '0', STR_PAD_LEFT) . '/' . $sale->sale_date->format('Y');
  $allBuyers  = $sale->buyers->count() > 0 ? $sale->buyers : collect([$sale->client]);
  $buyerNames = $allBuyers->pluck('name')->join(' / ');
  $buyerCpfs  = $allBuyers->pluck('cpf')->join(' / ');
  $payer      = $allBuyers->first();
  $payerAddr  = $payer->full_address ?? '';
  $payerCity  = collect([$payer->city, $payer->state])->filter()->join(' — ');

  $parcelas = $sale->installments->where('type', '!=', 'down_payment')->values();

  $lotLabel = 'Q' . ($sale->lot->block ?? '–') . ' · L' . $sale->lot->number
      . ($sale->lot->area ? ' · ' . number_format((float)$sale->lot->area, 0, ',', '.') . 'm²' : '');

  $stripH      = 277;
  $stripHeight = '277pt';

  $firstParcela = $parcelas->first();
  $restParcelas = $parcelas->slice(1)->values();

  $pages = collect([
      collect([
          ['type' => 'capa'],
          ['type' => 'contra'],
          $firstParcela
              ? ['type' => 'parcela', 'inst' => $firstParcela]
              : ['type' => 'blank'],
      ]),
  ]);

  foreach ($restParcelas->chunk(3) as $chunk) {
      $rows = $chunk->values();
      while ($rows->count() < 3) {
          $rows->push(['type' => 'blank']);
      }
      $pages->push($rows);
  }
@endphp

{{-- Pág.1 = capa + contra + parc.1 | demais = 3 parcelas --}}
@foreach($pages as $pageRows)
<table class="page" width="210mm" cellspacing="0" cellpadding="0"
  style="table-layout:fixed;@if(!$loop->last) page-break-after:always; @endif">
  @foreach($pageRows as $idx => $strip)
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell {{ $strip['type'] === 'blank' ? 'strip-blank' : 'strip-'.$strip['type'] }}"
        style="padding:0;overflow:hidden;@if($idx < 2) border-bottom:1pt dashed #999; @endif"
        @if($strip['type'] === 'capa') bgcolor="#2d6a45" @elseif($strip['type'] === 'contra') bgcolor="#f7f5ee" @elseif($strip['type'] === 'parcela') bgcolor="#ffffff" @endif>

      @if($strip['type'] === 'blank')
        &nbsp;

      @elseif($strip['type'] === 'capa')
        {{-- CAPA — 1/3, fundo preenchendo toda a faixa --}}
        <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" class="strip-content" style="table-layout:fixed;">
          <tr>
            <td width="32%" bgcolor="#e8dcc8" align="center" valign="middle" style="padding:3mm;">
              @if($brandLogoSrc)
                <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:20pt;width:auto;display:block;margin:0 auto 2mm;">
              @else
                <div style="font-size:12pt;font-weight:bold;color:#2d6a45;margin-bottom:2mm;">SID360</div>
              @endif
              <div style="font-size:9pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.8pt;color:#2d6a45;line-height:1.3;">
                Carnê de<br>Pagamento
              </div>
            </td>
            <td width="68%" bgcolor="#2d6a45" valign="top" style="padding:3mm 4mm;color:#fff;">
              <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="right" style="font-size:6.5pt;line-height:1.4;">
                    <span style="font-size:5.5pt;color:#d4e8dc;text-transform:uppercase;">Contrato Nº</span>
                    <strong style="color:#fff;"> {{ $contractNo }}</strong>
                    &nbsp;·&nbsp;
                    <span style="font-size:5.5pt;color:#d4e8dc;text-transform:uppercase;">Emissão</span>
                    <strong style="color:#fff;"> {{ now()->format('d/m/Y') }}</strong>
                    &nbsp;·&nbsp;
                    <span style="font-size:5.5pt;color:#d4e8dc;text-transform:uppercase;">Parcelas</span>
                    <strong style="color:#fff;"> {{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</strong>
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:2mm;">
                    <div class="box-white" style="padding:2mm;">
                      <div class="box-title">Dados Pagador</div>
                      <div class="box-body" style="font-size:6.5pt;line-height:1.35;">
                        <strong style="font-size:7pt;">{{ strtoupper($buyerNames) }}</strong><br>
                        @if($payerAddr){{ $payerAddr }}@if($payerCity), {{ strtoupper($payerCity) }}@endif<br>@elseif($payerCity){{ strtoupper($payerCity) }}<br>@endif
                        @if($buyerCpfs)CPF: {{ $buyerCpfs }}@endif
                      </div>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

      @elseif($strip['type'] === 'contra')
        {{-- CONTRA CAPA — 1/3, fundo preenchendo toda a faixa --}}
        <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" class="strip-content" style="table-layout:fixed;">
          <tr>
            <td width="36%" bgcolor="#2d6a45" valign="top" style="padding:3mm;color:#fff;">
              <div class="box-white" style="padding:2mm;">
                <div class="box-title">Dados Beneficiário</div>
                <div class="box-body" style="font-size:6.5pt;line-height:1.35;">
                  <strong style="font-size:7pt;">SID360 IMÓVEIS</strong><br>
                  Rua Arlindo Montino, nº 4<br>
                  Centro, Cafarnaum — BA<br>
                  (74) 9 8823-0151
                </div>
              </div>
              <div style="font-size:6.5pt;font-weight:bold;color:#e8c96a;padding-top:4mm;text-align:right;">
                sid360.com.br
              </div>
            </td>
            <td width="64%" bgcolor="#f7f5ee" valign="top" style="padding:2.5mm 3mm;">
              <div class="section-title">Dados do Imóvel</div>
              <table class="mini-grid" width="100%">
                <tr><td>Empreendimento</td><td>{{ $sale->lot->development->name }}</td></tr>
                <tr><td>Lote</td><td>{{ $lotLabel }}</td></tr>
              </table>
              <div class="section-title" style="margin-top:1mm;">Resumo Financeiro</div>
              <table class="mini-grid" width="100%">
                <tr><td>Valor total</td><td>{{ $fmt($sale->total_value) }}</td></tr>
                <tr><td>Entrada paga</td><td>{{ $fmt($sale->down_payment) }} em {{ $fmtDate($sale->sale_date) }}</td></tr>
                <tr><td>Parcelamento</td><td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td></tr>
                <tr><td>Vencimento</td><td>Dia {{ $sale->payment_day }} de cada mês</td></tr>
              </table>
              <div style="font-size:5.5pt;color:#666;text-align:center;line-height:1.35;padding-top:1.5mm;border-top:0.5pt solid #ddd;margin-top:1mm;">
                Multa por atraso: <strong>2,5% ao mês</strong> · <strong>(74) 9 8823-0151</strong> · sid360.com.br
              </div>
              @if($brandLogoSrc)
              <div style="text-align:center;padding-top:1.5mm;">
                <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:12pt;width:auto;">
              </div>
              @endif
            </td>
          </tr>
        </table>

      @elseif($strip['type'] === 'parcela')
        @php
          $inst   = $strip['inst'];
          $due    = Carbon::parse($inst->due_date);
          $isPaid = $inst->status === 'paid';
          $numStr = str_pad($inst->number, 2, '0', STR_PAD_LEFT);
          $totStr = str_pad($sale->installments_count, 2, '0', STR_PAD_LEFT);
        @endphp
        {{-- PARCELA — 1/3 (recibo + ficha), altura fixa --}}
        <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" class="strip-content" style="table-layout:fixed;">
          <tr>
            {{-- Recibo --}}
            <td width="26%" bgcolor="#f7f5ee" class="recibo-sep" valign="top" style="padding:2mm 2.5mm;">
              <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:#888;border-bottom:0.5pt solid #ccc;padding-bottom:1pt;margin-bottom:2pt;">
                Recibo do Pagador
              </div>
              <table class="recibo-table" width="100%" cellspacing="0" cellpadding="0">
                <tr><td class="lbl-cell">Parcela</td><td class="val-cell">{{ $numStr }}/{{ $totStr }}</td></tr>
                <tr><td class="lbl-cell">Vencimento</td><td class="val-cell">{{ $due->format('d/m/Y') }}</td></tr>
                <tr><td class="lbl-cell">Contrato</td><td class="val-cell">{{ $contractNo }}</td></tr>
                <tr><td class="lbl-cell">Valor</td><td class="val-cell val-lg">{{ $fmt($inst->value) }}</td></tr>
                <tr><td class="lbl-cell" colspan="2" style="padding-top:2pt;font-size:5.5pt;font-weight:normal;color:#666;">{{ $buyerNames }}</td></tr>
              </table>
              <div style="margin-top:5mm;font-size:5pt;color:#bbb;text-align:center;border-top:0.5pt solid #eee;padding-top:2pt;">
                Autenticação / Recibo
              </div>
            </td>

            {{-- Ficha --}}
            <td width="74%" bgcolor="#ffffff" valign="top" style="padding:2mm 2.5mm;">
              <table width="100%" cellspacing="0" cellpadding="0" class="ficha">
                <tr class="hdr">
                  <td width="55%">
                    @if($brandLogoSrc)
                      <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:10pt;width:auto;">
                    @else
                      <strong style="font-size:8pt;">SID360 IMÓVEIS</strong>
                    @endif
                  </td>
                  <td width="45%" align="right" style="font-size:7.5pt;font-weight:bold;color:#8B6A2E;">
                    PARCELA {{ $numStr }}/{{ $totStr }}@if($isPaid) · PAGO @endif
                  </td>
                </tr>
                <tr>
                  <td width="42%">
                    <span class="lbl">Beneficiário</span>
                    <span class="val">Sid360 Imóveis · Cafarnaum-BA</span>
                  </td>
                  <td width="23%">
                    <span class="lbl">Vencimento</span>
                    <span class="val">{{ $due->format('d/m/Y') }}</span>
                  </td>
                  <td width="35%" align="right">
                    <span class="lbl">Valor</span>
                    <span class="val val-xl">{{ $fmt($inst->value) }}</span>
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <span class="lbl">Pagador</span>
                    <span class="val">{{ $buyerNames }}</span>
                  </td>
                  <td>
                    <span class="lbl">CPF</span>
                    <span class="val">{{ $buyerCpfs }}</span>
                  </td>
                </tr>
                <tr>
                  <td>
                    <span class="lbl">Contrato</span>
                    <span class="val">Nº {{ $contractNo }}</span>
                  </td>
                  <td colspan="2">
                    <span class="lbl">Imóvel</span>
                    <span class="val">{{ $sale->lot->development->name }} · {{ $lotLabel }}</span>
                  </td>
                </tr>
                <tr>
                  <td colspan="3" style="font-size:5.5pt;color:#666;line-height:1.3;padding:2pt 3pt;">
                    Multa por atraso: <strong>2,5% ao mês</strong> · Pagamentos: <strong>(74) 9 8823-0151</strong> · sid360.com.br
                  </td>
                </tr>
                <tr class="ftr">
                  <td colspan="2" style="font-size:5.5pt;color:#aaa;">
                    Sid360 Imóveis · Contrato {{ $contractNo }}
                  </td>
                  <td align="right">
                    @if($isPaid)
                      <span class="pago-badge">PAGO {{ $fmtDate($inst->paid_at) }}</span>
                    @else
                      <div class="assinatura">Assinatura / Recibo</div>
                    @endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      @endif

    </td>
  </tr>
  @endforeach
</table>
@endforeach

</body>
</html>
