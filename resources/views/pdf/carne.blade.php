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

  tr.strip { overflow: hidden; }

  td.strip-cell {
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

  @media screen {
    .strip-flex-fill {
      flex: 1 1 auto;
      min-height: 0;
    }

    .strip-flex-column {
      display: flex;
      flex-direction: column;
    }
  }

  @media screen {
    body.preview {
      background: #e8e4d8;
      padding: 24px 16px;
      min-height: 100vh;
    }

    body.preview .preview-shell {
      width: 210mm;
      max-width: 100%;
      margin: 0 auto;
    }

    body.preview .preview-shell table.page {
      background: #fff;
      box-shadow: 0 4px 24px rgba(26, 58, 40, 0.12);
      margin-bottom: 16px;
    }

    body.preview .preview-shell table.page:last-child {
      margin-bottom: 0;
    }
  }
</style>
</head>
<body @if($isPreview ?? false) class="preview" @endif>

@if($isPreview ?? false)
<div class="preview-shell">
@endif

@php
  use Carbon\Carbon;

  $brandLogoPath = public_path('img/logo-systema.png');
  $brandLogoSrc = is_readable($brandLogoPath)
      ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($brandLogoPath))
      : null;

  $brandLogoLightPath = public_path('img/logo-systema-light.png');
  $brandLogoLightSrc = is_readable($brandLogoLightPath)
      ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($brandLogoLightPath))
      : $brandLogoSrc;

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
  $stripH              = 270;
  $stripHeight         = '270pt';
  $stripTopbarPt       = 16;
  $stripCapaBodyPt     = $stripH - $stripTopbarPt;
  $capaBlueDark        = '#1a3d5c';
  $capaBlueLabel    = '#1a4a6e';
  $capaBlueLine     = '#4a7dab';
  $isPreview        = $isPreview ?? false;
@endphp

<style>
  tr.strip { height: {{ $stripHeight }}; max-height: {{ $stripHeight }}; }
  td.strip-cell { height: {{ $stripHeight }}; max-height: {{ $stripHeight }}; }
</style>

{{-- ═══════════════════════════════════════════════
     PÁGINA 1: CAPA + CONTRACAPA + INSTRUÇÕES
════════════════════════════════════════════════ --}}
<table class="page" cellspacing="0" cellpadding="0" style="page-break-after:always;">
  {{-- SEÇÃO 1: CAPA --}}
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell" style="padding:0;overflow:hidden;border-bottom:1pt dashed #aaa;" bgcolor="#f5f0e8">
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" style="table-layout:fixed;height:{{ $stripHeight }};">
        <tr>
          {{-- Coluna esquerda --}}
          <td
            width="35%"
            bgcolor="{{ $capaBlueDark }}"
            align="center"
            valign="middle"
            style="width:35%;padding:3mm;"
          >
            @if($brandLogoLightSrc)
              <img src="{{ $brandLogoLightSrc }}" alt="Sid360" style="height:20pt;width:auto;display:block;margin:0 auto 1.5mm;">
            @else
              <div style="font-size:13pt;font-weight:900;color:#fff;letter-spacing:1pt;margin-bottom:2mm;">SID<span style="color:#fff;">360</span></div>
            @endif
            <div style="font-size:6.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:1pt;color:rgba(255,255,255,0.85);text-align:center;line-height:1.35;">
              Carnê de<br>Pagamento
            </div>
          </td>
          {{-- Coluna direita --}}
          <td width="65%" valign="top" style="width:65%;padding:0;">
            <table width="100%" cellspacing="0" cellpadding="0" bgcolor="{{ $capaBlueDark }}">
              <tr>
                <td style="padding:2mm 3mm;font-size:6pt;color:#fff;line-height:1.5;">
                  <span style="opacity:0.75;">CONTRATO Nº</span> <strong style="color:#fff;">{{ $contractNo }}</strong>
                  &nbsp;&nbsp;
                  <span style="opacity:0.75;">EMISSÃO</span> <strong style="color:#fff;">{{ now()->format('d/m/Y') }}</strong>
                  &nbsp;&nbsp;
                  <span style="opacity:0.75;">PARCELAS</span> <strong style="color:#fff;">{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</strong>
                </td>
              </tr>
            </table>
            @if($isPreview)
            <div style="height:{{ $stripCapaBodyPt }}pt;padding:2mm 2.5mm 2mm;overflow:hidden;" class="strip-flex-column">
              <div>
              <div style="font-size:5pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.5pt;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1pt;">Dados Pagador</div>
              <div style="font-size:6pt;line-height:1.25;margin-bottom:3mm;">
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
                    <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1.5pt;">Dados Beneficiário</div>
                    <div style="font-size:6pt;line-height:1.3;">
                      <strong>SID360 IMÓVEIS</strong> · Rua Arlindo Montino, nº 4<br>
                      Centro, Cafarnaum — BA · (74) 9 8823-0151 · sid360.com.br
                    </div>
                  </td>
                  <td width="50%" valign="top">
                    <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1.5pt;">Dados do Imóvel</div>
                    <table class="capa-table" width="100%">
                      <tr><td>Empreendimento</td><td>{{ $sale->lot->development->name }}</td></tr>
                      <tr><td>Lote</td><td>{{ $loteLabel }}</td></tr>
                    </table>
                    <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin:1.5pt 0;">Resumo Financeiro</div>
                    <table class="capa-table" width="100%">
                      <tr><td>Valor total</td><td>{{ $fmt($sale->total_value) }}</td></tr>
                      <tr><td>Entrada paga</td><td>{{ $fmt($sale->down_payment) }} em {{ $fmtDate($sale->sale_date) }}</td></tr>
                      <tr><td>Parcelamento</td><td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td></tr>
                      <tr><td>Vencimento</td><td>Dia {{ $sale->payment_day }} de cada mês</td></tr>
                    </table>
                  </td>
                </tr>
              </table>
              </div>
              <div class="strip-flex-fill" style="font-size:0;line-height:0;">&nbsp;</div>
              <div style="font-size:5.5pt;color:#888;text-align:center;border-top:0.5pt solid #ccc;padding-top:1mm;">
                Multa por atraso: <strong>2,5% ao mês</strong> · (74) 9 8823-0151 · sid360.com.br
              </div>
            </div>
            @else
            <table width="100%" height="{{ $stripCapaBodyPt }}" cellspacing="0" cellpadding="0" style="height:{{ $stripCapaBodyPt }}pt;table-layout:fixed;">
              <tr>
                <td valign="top" style="padding:2mm 2.5mm 0;">
                  <div style="font-size:5pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.5pt;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1pt;">Dados Pagador</div>
                  <div style="font-size:6pt;line-height:1.25;margin-bottom:3mm;">
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
                        <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1.5pt;">Dados Beneficiário</div>
                        <div style="font-size:6pt;line-height:1.3;">
                          <strong>SID360 IMÓVEIS</strong> · Rua Arlindo Montino, nº 4<br>
                          Centro, Cafarnaum — BA · (74) 9 8823-0151 · sid360.com.br
                        </div>
                      </td>
                      <td width="50%" valign="top">
                        <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1.5pt;">Dados do Imóvel</div>
                        <table class="capa-table" width="100%">
                          <tr><td>Empreendimento</td><td>{{ $sale->lot->development->name }}</td></tr>
                          <tr><td>Lote</td><td>{{ $loteLabel }}</td></tr>
                        </table>
                        <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin:1.5pt 0;">Resumo Financeiro</div>
                        <table class="capa-table" width="100%">
                          <tr><td>Valor total</td><td>{{ $fmt($sale->total_value) }}</td></tr>
                          <tr><td>Entrada paga</td><td>{{ $fmt($sale->down_payment) }} em {{ $fmtDate($sale->sale_date) }}</td></tr>
                          <tr><td>Parcelamento</td><td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td></tr>
                          <tr><td>Vencimento</td><td>Dia {{ $sale->payment_day }} de cada mês</td></tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td valign="bottom" style="padding:0 2.5mm 2mm;">
                  <div style="font-size:5.5pt;color:#888;text-align:center;border-top:0.5pt solid #ccc;padding-top:1mm;">
                    Multa por atraso: <strong>2,5% ao mês</strong> · (74) 9 8823-0151 · sid360.com.br
                  </div>
                </td>
              </tr>
            </table>
            @endif
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- SEÇÃO 2: CONTRACAPA --}}
  <tr class="strip" height="{{ $stripH }}" style="height:{{ $stripHeight }};max-height:{{ $stripHeight }};">
    <td class="strip-cell" style="padding:0;overflow:hidden;border-bottom:1pt dashed #aaa;" bgcolor="#f5f0e8">
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" style="table-layout:fixed;height:{{ $stripHeight }};">
        <tr>
          <td
            width="35%"
            bgcolor="{{ $capaBlueDark }}"
            align="center"
            valign="middle"
            style="width:35%;padding:3mm;"
          >
            @if($brandLogoLightSrc)
              <img src="{{ $brandLogoLightSrc }}" alt="Sid360" style="height:20pt;width:auto;display:block;margin:0 auto 1.5mm;">
            @else
              <div style="font-size:13pt;font-weight:900;color:#fff;letter-spacing:1pt;margin-bottom:2mm;">SID<span style="color:#fff;">360</span></div>
            @endif
            <div style="font-size:6.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:1pt;color:rgba(255,255,255,0.85);text-align:center;line-height:1.35;">
              Guia de<br>Pagamento
            </div>
          </td>
          <td width="65%" valign="top" style="width:65%;padding:0;">
            @if($isPreview)
            <div style="height:{{ $stripH }}pt;padding:2mm 2.5mm 2mm;overflow:hidden;" class="strip-flex-column">
              <div>
              <div style="font-size:7pt;font-weight:bold;color:{{ $capaBlueLabel }};margin-bottom:2mm;">Como utilizar este carnê</div>
              <div style="font-size:6pt;line-height:1.35;color:#444;margin-bottom:2mm;">
                1. Detache cada parcela na linha tracejada indicada.<br>
                2. Realize o pagamento até a data de vencimento de cada parcela.<br>
                3. Guarde o comprovante e apresente-o quando solicitado.<br>
                4. Após o vencimento, incidirá multa de <strong>2,5% ao mês</strong>, calculada pro-rata por dia.
              </div>
              <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1.5pt;">Resumo do contrato</div>
              <table class="capa-table" width="100%">
                <tr><td>Contrato</td><td>Nº {{ $contractNo }}</td></tr>
                <tr><td>Pagador</td><td>{{ $buyerNames }}</td></tr>
                <tr><td>Imóvel</td><td>{{ $sale->lot->development->name }} · {{ $loteLabel }}</td></tr>
                <tr><td>Parcelamento</td><td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td></tr>
                <tr><td>Vencimento</td><td>Dia {{ $sale->payment_day }} de cada mês</td></tr>
              </table>
              </div>
              <div class="strip-flex-fill" style="font-size:0;line-height:0;">&nbsp;</div>
              <div style="font-size:5.5pt;color:#888;text-align:center;border-top:0.5pt solid #ccc;padding-top:1mm;">
                Dúvidas: <strong>(74) 9 8823-0151</strong> · <strong>sid360.com.br</strong>
              </div>
            </div>
            @else
            <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" style="height:{{ $stripHeight }};table-layout:fixed;">
              <tr>
                <td valign="top" style="padding:2mm 2.5mm 0;">
                  <div style="font-size:7pt;font-weight:bold;color:{{ $capaBlueLabel }};margin-bottom:2mm;">Como utilizar este carnê</div>
                  <div style="font-size:6pt;line-height:1.35;color:#444;margin-bottom:2mm;">
                    1. Detache cada parcela na linha tracejada indicada.<br>
                    2. Realize o pagamento até a data de vencimento de cada parcela.<br>
                    3. Guarde o comprovante e apresente-o quando solicitado.<br>
                    4. Após o vencimento, incidirá multa de <strong>2,5% ao mês</strong>, calculada pro-rata por dia.
                  </div>
                  <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;color:{{ $capaBlueLabel }};border-bottom:0.5pt solid {{ $capaBlueLine }};padding-bottom:1pt;margin-bottom:1.5pt;">Resumo do contrato</div>
                  <table class="capa-table" width="100%">
                    <tr><td>Contrato</td><td>Nº {{ $contractNo }}</td></tr>
                    <tr><td>Pagador</td><td>{{ $buyerNames }}</td></tr>
                    <tr><td>Imóvel</td><td>{{ $sale->lot->development->name }} · {{ $loteLabel }}</td></tr>
                    <tr><td>Parcelamento</td><td>{{ $sale->installments_count }}x de {{ $fmt($sale->installment_value) }}</td></tr>
                    <tr><td>Vencimento</td><td>Dia {{ $sale->payment_day }} de cada mês</td></tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td valign="bottom" style="padding:0 2.5mm 2mm;">
                  <div style="font-size:5.5pt;color:#888;text-align:center;border-top:0.5pt solid #ccc;padding-top:1mm;">
                    Dúvidas: <strong>(74) 9 8823-0151</strong> · <strong>sid360.com.br</strong>
                  </div>
                </td>
              </tr>
            </table>
            @endif
        </tr>
      </table>
    </td>
  </tr>

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
     PÁGINAS 2+: 3 PARCELAS POR PÁGINA
════════════════════════════════════════════════ --}}
@foreach($parcelas->chunk(3) as $chunk)
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
        @include('pdf.partials.carne-parcela-strip', [
          'inst' => $inst,
          'stripH' => $stripH,
          'stripHeight' => $stripHeight,
          'contractNo' => $contractNo,
          'buyerNames' => $buyerNames,
          'buyerCpfs' => $buyerCpfs,
          'fmt' => $fmt,
          'fmtDate' => $fmtDate,
          'brandLogoSrc' => $brandLogoSrc,
          'sale' => $sale,
          'loteLabel' => $loteLabel,
          'capaBlueDark' => $capaBlueDark,
          'isPreview' => $isPreview,
        ])
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
