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
    background: #fff;
  }

  table { border-collapse: collapse; table-layout: fixed; }

  table.page { width: 210mm; }

  tr.strip { height: 277pt; max-height: 277pt; }

  td.strip-cell {
    height: 277pt;
    max-height: 277pt;
    vertical-align: top;
    padding: 0;
    overflow: hidden;
  }

  .ct {
    font-size: 5.5pt;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #999;
  }

  .cv { font-size: 7.5pt; font-weight: bold; color: #1a1a1a; }
  .cv-big { font-size: 9pt; font-weight: bold; color: #1a1a1a; }

  .lbl {
    font-size: 5.5pt;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #999;
    display: block;
    margin-bottom: 0.5pt;
  }

  .val { font-size: 7.5pt; font-weight: bold; color: #1a1a1a; line-height: 1.25; }
  .val-xl { font-size: 11pt; font-weight: bold; color: #1a1a1a; }

  .capa-table td {
    font-size: 6.5pt;
    padding: 1pt 0;
    border-bottom: 0.3pt solid #e0d5c5;
    vertical-align: top;
  }

  .capa-table td:first-child {
    font-size: 5.5pt;
    font-weight: bold;
    text-transform: uppercase;
    color: #888;
    width: 40%;
    padding-right: 2pt;
  }

  .pago-badge {
    font-size: 6.5pt;
    font-weight: bold;
    color: #2a6a2a;
    border: 0.8pt solid #2a6a2a;
    padding: 1pt 4pt;
  }

  .assinatura {
    border-top: 0.5pt solid #888;
    text-align: center;
    font-size: 5.5pt;
    color: #aaa;
    padding-top: 1pt;
    width: 95pt;
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
  $buyerNames = $allBuyers->pluck('name')->map(fn($n) => strtoupper($n))->join(' / ');
  $buyerCpfs  = $allBuyers->pluck('cpf')->join(' / ');
  $payer      = $allBuyers->first();
  $buyerAddr  = $payer->full_address ?? '';
  $buyerCity  = collect([$payer->city, $payer->state])->filter()->join(' — ');

  $loteLabel = 'Q' . ($sale->lot->block ?? '–') . ' · L' . $sale->lot->number
      . ($sale->lot->area ? ' · ' . number_format((float)$sale->lot->area, 0, ',', '.') . 'm²' : '');

  $parcelas      = $sale->installments->where('type', '!=', 'down_payment')->values();
  $firstParcela  = $parcelas->first();
  $stripH        = 277;
  $stripHeight   = '277pt';
@endphp

{{-- ═══════════════════════════════════════════════
     PÁGINA 1: CAPA + CONTRACAPA + INSTRUÇÕES
════════════════════════════════════════════════ --}}
<table class="page" cellspacing="0" cellpadding="0" style="page-break-after:always;">
  {{-- SEÇÃO 1: CAPA --}}
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell" style="padding:0;overflow:hidden;border-bottom:1pt dashed #aaa;" bgcolor="#f5f0e8">
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" style="table-layout:fixed;">
        <tr>
          {{-- Coluna esquerda verde --}}
          <td width="38mm" bgcolor="#2d4a2d" align="center" valign="middle" style="padding:4mm;">
            @if($brandLogoSrc)
              <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:18pt;width:auto;display:block;margin:0 auto 2mm;">
            @else
              <div style="font-size:13pt;font-weight:900;color:#fff;letter-spacing:1pt;margin-bottom:2mm;">SID<span style="color:#C8A96E;">360</span></div>
            @endif
            <div style="font-size:6.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:1pt;color:rgba(255,255,255,0.75);text-align:center;line-height:1.35;">
              Carnê de<br>Pagamento
            </div>
          </td>
          {{-- Coluna direita --}}
          <td valign="top" style="padding:0;">
            {{-- Topbar --}}
            <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#2d4a2d">
              <tr>
                <td style="padding:2mm 3mm;font-size:6pt;color:#fff;line-height:1.5;">
                  <span style="opacity:0.7;">CONTRATO Nº</span> <strong style="color:#C8A96E;">{{ $contractNo }}</strong>
                  &nbsp;&nbsp;
                  <span style="opacity:0.7;">EMISSÃO</span> <strong style="color:#C8A96E;">{{ now()->format('d/m/Y') }}</strong>
                  &nbsp;&nbsp;
                  <span style="opacity:0.7;">PARCELAS</span> <strong style="color:#C8A96E;">{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</strong>
                </td>
              </tr>
            </table>
            <div style="padding:2mm 2.5mm;">
              <div style="font-size:5pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.5pt;color:#7a5c2e;border-bottom:0.5pt solid #C8A96E;padding-bottom:1pt;margin-bottom:1pt;">Dados Pagador</div>
              <div style="font-size:6pt;line-height:1.3;margin-bottom:1.5mm;">
                <strong>{{ $buyerNames }}</strong> · CPF: {{ $buyerCpfs }}<br>
                @if($buyerAddr)
                  {{ $buyerAddr }}@if($buyerCity), {{ strtoupper($buyerCity) }}@endif
                @elseif($buyerCity)
                  {{ strtoupper($buyerCity) }}
                @endif
              </div>
              <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="50%" valign="top" style="padding-right:2mm;">
                    <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:#7a5c2e;border-bottom:0.5pt solid #C8A96E;padding-bottom:1pt;margin-bottom:1.5pt;">Dados Beneficiário</div>
                    <div style="font-size:6pt;line-height:1.3;">
                      <strong>SID360 IMÓVEIS</strong> · Rua Arlindo Montino, nº 4<br>
                      Centro, Cafarnaum — BA · (74) 9 8823-0151 · sid360.com.br
                    </div>
                  </td>
                  <td width="50%" valign="top">
                    <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:#7a5c2e;border-bottom:0.5pt solid #C8A96E;padding-bottom:1pt;margin-bottom:1.5pt;">Dados do Imóvel</div>
                    <table class="capa-table" width="100%">
                      <tr><td>Empreendimento</td><td>{{ $sale->lot->development->name }}</td></tr>
                      <tr><td>Lote</td><td>{{ $loteLabel }}</td></tr>
                    </table>
                    <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:#7a5c2e;border-bottom:0.5pt solid #C8A96E;padding-bottom:1pt;margin:1.5pt 0;">Resumo Financeiro</div>
                    <table class="capa-table" width="100%">
                      <tr><td>Valor total</td><td>{{ $fmt($sale->total_value) }}</td></tr>
                      <tr><td>Entrada paga</td><td>{{ $fmt($sale->down_payment) }} em {{ $fmtDate($sale->sale_date) }}</td></tr>
                      <tr><td>Parcelamento</td><td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td></tr>
                      <tr><td>Vencimento</td><td>Dia {{ $sale->payment_day }} de cada mês</td></tr>
                    </table>
                  </td>
                </tr>
              </table>
              <div style="font-size:5.5pt;color:#888;text-align:center;border-top:0.5pt solid #ccc;padding-top:1.5mm;margin-top:1mm;">
                Multa por atraso: <strong>2,5% ao mês</strong> · (74) 9 8823-0151 · sid360.com.br
              </div>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- SEÇÃO 2: CONTRACAPA (1ª parcela) --}}
  @if($firstParcela)
  @php
    $inst   = $firstParcela;
    $due    = Carbon::parse($inst->due_date);
    $isPaid = $inst->status === 'paid';
    $numStr = str_pad($inst->number, 2, '0', STR_PAD_LEFT);
    $totStr = str_pad($sale->installments_count, 2, '0', STR_PAD_LEFT);
  @endphp
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell" style="padding:0;overflow:hidden;border-bottom:1pt dashed #aaa;" bgcolor="#ffffff">
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0">
        <tr>
          {{-- Canhoto --}}
          <td width="55mm" bgcolor="#fafafa" valign="top" style="padding:3mm;border-right:1pt dashed #aaa;">
            <div class="ct">Recibo do Pagador</div>
            <div style="margin-top:2mm;"><div class="ct">Parcela</div><div class="cv">{{ $numStr }}/{{ $totStr }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Vencimento</div><div class="cv">{{ $due->format('d/m/Y') }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Contrato</div><div class="cv">{{ $contractNo }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Valor</div><div class="cv-big">{{ $fmt($inst->value) }}</div></div>
            <div style="font-size:6.5pt;font-weight:bold;color:#333;border-top:0.3pt solid #e0e0e0;padding-top:2mm;margin-top:3mm;">{{ $buyerNames }}</div>
            <div style="margin-top:4mm;border-top:0.5pt solid #888;padding-top:1.5mm;font-size:5.5pt;color:#aaa;text-align:center;">Autenticação / Recibo</div>
          </td>
          {{-- Corpo --}}
          <td valign="top" style="padding:3mm 4mm;">
            <table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:1pt solid #2d4a2d;margin-bottom:2mm;">
              <tr>
                <td style="padding-bottom:1.5mm;">
                  @if($brandLogoSrc)
                    <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:11pt;width:auto;">
                  @else
                    <span style="font-size:10pt;font-weight:900;color:#2d4a2d;">SID<span style="color:#C8A96E;">360</span></span>
                  @endif
                </td>
                <td align="right" style="padding-bottom:1.5mm;font-size:9pt;font-weight:bold;color:#C8A96E;">PARCELA {{ $numStr }}/{{ $totStr }}</td>
              </tr>
            </table>
            <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:2mm;">
              <tr>
                <td width="45%" valign="top"><span class="lbl">Beneficiário</span><span class="val">Sid360 Imóveis · Cafarnaum-BA</span></td>
                <td width="25%" valign="top"><span class="lbl">Vencimento</span><span class="val">{{ $due->format('d/m/Y') }}</span></td>
                <td width="30%" valign="top" align="right"><span class="lbl">Valor</span><span class="val-xl">{{ $fmt($inst->value) }}</span></td>
              </tr>
              <tr>
                <td colspan="2" valign="top" style="padding-top:2mm;"><span class="lbl">Pagador</span><span class="val">{{ $buyerNames }}</span></td>
                <td valign="top" style="padding-top:2mm;"><span class="lbl">CPF</span><span class="val">{{ $buyerCpfs }}</span></td>
              </tr>
              <tr>
                <td valign="top" style="padding-top:2mm;"><span class="lbl">Contrato</span><span class="val">Nº {{ $contractNo }}</span></td>
                <td colspan="2" valign="top" style="padding-top:2mm;border-top:0.3pt solid #e0e0e0;"><span class="val">{{ $sale->lot->development->name }} · {{ $loteLabel }}</span></td>
              </tr>
            </table>
            <table width="100%" cellspacing="0" cellpadding="0" style="border-top:0.5pt solid #e0e0e0;">
              <tr>
                <td style="font-size:5.5pt;color:#aaa;padding-top:1.5mm;">Multa por atraso: 2,5% ao mês · Pagamentos: (74) 9 8823-0151 · sid360.com.br</td>
                <td align="right" style="padding-top:1.5mm;">
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
    </td>
  </tr>
  @endif

  {{-- SEÇÃO 3: INSTRUÇÕES --}}
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell" bgcolor="#f9f9f9" align="center" valign="middle" style="padding:4mm;">
      <div style="font-size:7pt;color:#666;text-align:center;line-height:1.55;max-width:150mm;margin:0 auto;">
        <strong style="color:#444;">Instruções de pagamento</strong><br>
        Realize o pagamento até a data de vencimento indicada em cada parcela.<br>
        Após o vencimento, incidirá multa de <strong>2,5% ao mês</strong> calculada pro-rata por dia.<br>
        Dúvidas: <strong>(74) 9 8823-0151</strong> · <strong>sid360.com.br</strong>
      </div>
      <div style="font-size:6pt;color:#888;text-align:center;line-height:1.45;margin-top:3mm;">
        Este carnê é válido como comprovante de pagamento somente com autenticação do beneficiário.<br>
        Contrato Nº {{ $contractNo }} · Sid360 Imóveis · Cafarnaum-BA
      </div>
      @if($brandLogoSrc)
      <div style="text-align:center;margin-top:3mm;">
        <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:12pt;width:auto;opacity:0.85;">
      </div>
      @endif
    </td>
  </tr>
</table>

{{-- ═══════════════════════════════════════════════
     PÁGINAS 2+: 3 PARCELAS (pula a 1ª — está na contracapa)
════════════════════════════════════════════════ --}}
@foreach($parcelas->skip(1)->chunk(3) as $chunk)
@php
  $rows = $chunk->values();
  while ($rows->count() < 3) {
      $rows->push(null);
  }
@endphp
<table class="page" cellspacing="0" cellpadding="0" @if(!$loop->last) style="page-break-after:always;" @endif>
  @foreach($rows as $idx => $inst)
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell" style="padding:0;overflow:hidden;@if($idx < 2) border-bottom:1pt dashed #aaa; @endif" bgcolor="{{ $inst ? '#ffffff' : '#fafafa' }}">
      @if($inst)
      @php
        $due    = Carbon::parse($inst->due_date);
        $isPaid = $inst->status === 'paid';
        $numStr = str_pad($inst->number, 2, '0', STR_PAD_LEFT);
        $totStr = str_pad($sale->installments_count, 2, '0', STR_PAD_LEFT);
      @endphp
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0">
        <tr>
          <td width="55mm" bgcolor="#fafafa" valign="top" style="padding:3mm;border-right:1pt dashed #aaa;">
            <div class="ct">Recibo do Pagador</div>
            <div style="margin-top:2mm;"><div class="ct">Parcela</div><div class="cv">{{ $numStr }}/{{ $totStr }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Vencimento</div><div class="cv">{{ $due->format('d/m/Y') }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Contrato</div><div class="cv">{{ $contractNo }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Valor</div><div class="cv-big">{{ $fmt($inst->value) }}</div></div>
            <div style="font-size:6.5pt;font-weight:bold;color:#333;border-top:0.3pt solid #e0e0e0;padding-top:2mm;margin-top:3mm;">{{ $buyerNames }}</div>
            <div style="margin-top:4mm;border-top:0.5pt solid #888;padding-top:1.5mm;font-size:5.5pt;color:#aaa;text-align:center;">Autenticação / Recibo</div>
          </td>
          <td valign="top" style="padding:3mm 4mm;">
            <table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:1pt solid #2d4a2d;margin-bottom:2mm;">
              <tr>
                <td style="padding-bottom:1.5mm;">
                  @if($brandLogoSrc)
                    <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:11pt;width:auto;">
                  @else
                    <span style="font-size:10pt;font-weight:900;color:#2d4a2d;">SID<span style="color:#C8A96E;">360</span></span>
                  @endif
                </td>
                <td align="right" style="padding-bottom:1.5mm;font-size:9pt;font-weight:bold;color:#C8A96E;">PARCELA {{ $numStr }}/{{ $totStr }}</td>
              </tr>
            </table>
            <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:2mm;">
              <tr>
                <td width="45%" valign="top"><span class="lbl">Beneficiário</span><span class="val">Sid360 Imóveis · Cafarnaum-BA</span></td>
                <td width="25%" valign="top"><span class="lbl">Vencimento</span><span class="val">{{ $due->format('d/m/Y') }}</span></td>
                <td width="30%" valign="top" align="right"><span class="lbl">Valor</span><span class="val-xl">{{ $fmt($inst->value) }}</span></td>
              </tr>
              <tr>
                <td colspan="2" valign="top" style="padding-top:2mm;"><span class="lbl">Pagador</span><span class="val">{{ $buyerNames }}</span></td>
                <td valign="top" style="padding-top:2mm;"><span class="lbl">CPF</span><span class="val">{{ $buyerCpfs }}</span></td>
              </tr>
              <tr>
                <td valign="top" style="padding-top:2mm;"><span class="lbl">Contrato</span><span class="val">Nº {{ $contractNo }}</span></td>
                <td colspan="2" valign="top" style="padding-top:2mm;border-top:0.3pt solid #e0e0e0;"><span class="val">{{ $sale->lot->development->name }} · {{ $loteLabel }}</span></td>
              </tr>
            </table>
            <table width="100%" cellspacing="0" cellpadding="0" style="border-top:0.5pt solid #e0e0e0;">
              <tr>
                <td style="font-size:5.5pt;color:#aaa;padding-top:1.5mm;">Multa por atraso: 2,5% ao mês · Pagamentos: (74) 9 8823-0151 · sid360.com.br</td>
                <td align="right" style="padding-top:1.5mm;">
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
      @else
        &nbsp;
      @endif
    </td>
  </tr>
  @endforeach
</table>
@endforeach

</body>
</html>
