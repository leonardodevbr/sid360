@php
  use Carbon\Carbon;

  $due    = Carbon::parse($inst->due_date);
  $isPaid = $inst->status === 'paid';
  $numStr = str_pad($inst->number, 2, '0', STR_PAD_LEFT);
  $totStr = str_pad($sale->installments_count, 2, '0', STR_PAD_LEFT);
  $isPreview = $isPreview ?? false;
@endphp

<table width="100%" cellspacing="0" cellpadding="0" style="table-layout:fixed;">
  <tr>
    <td width="35%" bgcolor="#fafafa" valign="top" style="width:35%;padding:0;border-right:1pt dashed #aaa;">
      @if($isPreview)
      <div style="height:{{ $stripHeight }};padding:2.5mm 2.5mm 2.5mm 5%;overflow:hidden;" class="strip-flex-column">
        <div>
          <div class="ct">Recibo do Pagador</div>
          <div style="margin-top:2mm;"><div class="ct">Parcela</div><div class="cv">{{ $numStr }}/{{ $totStr }}</div></div>
          <div style="margin-top:1.5mm;"><div class="ct">Vencimento</div><div class="cv">{{ $due->format('d/m/Y') }}</div></div>
          <div style="margin-top:1.5mm;"><div class="ct">Contrato</div><div class="cv">{{ $contractNo }}</div></div>
          <div style="margin-top:1.5mm;"><div class="ct">Valor</div><div class="cv-big">{{ $fmt($inst->value) }}</div></div>
          <div style="font-size:6.5pt;font-weight:bold;color:#333;border-top:0.3pt solid #e0e0e0;padding-top:2mm;margin-top:3mm;">{{ $buyerNames }}</div>
        </div>
        <div class="strip-flex-fill" style="font-size:0;line-height:0;">&nbsp;</div>
        <div style="border-top:0.5pt solid #888;padding-top:1.5mm;font-size:5.5pt;color:#aaa;text-align:center;">Autenticação / Recibo</div>
      </div>
      @else
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" style="height:{{ $stripHeight }};">
        <tr>
          <td valign="top" style="padding:2.5mm 2.5mm 0 5%;">
            <div class="ct">Recibo do Pagador</div>
            <div style="margin-top:2mm;"><div class="ct">Parcela</div><div class="cv">{{ $numStr }}/{{ $totStr }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Vencimento</div><div class="cv">{{ $due->format('d/m/Y') }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Contrato</div><div class="cv">{{ $contractNo }}</div></div>
            <div style="margin-top:1.5mm;"><div class="ct">Valor</div><div class="cv-big">{{ $fmt($inst->value) }}</div></div>
            <div style="font-size:6.5pt;font-weight:bold;color:#333;border-top:0.3pt solid #e0e0e0;padding-top:2mm;margin-top:3mm;">{{ $buyerNames }}</div>
          </td>
        </tr>
        <tr>
          <td valign="bottom" style="padding:0 2.5mm 2.5mm 5%;">
            <div style="border-top:0.5pt solid #888;padding-top:1.5mm;font-size:5.5pt;color:#aaa;text-align:center;">Autenticação / Recibo</div>
          </td>
        </tr>
      </table>
      @endif
    </td>
    <td width="65%" valign="top" style="width:65%;padding:0;">
      @if($isPreview)
      <div style="height:{{ $stripHeight }};padding:2.5mm 3.5mm;overflow:hidden;" class="strip-flex-column">
        <div>
          <table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:1pt solid {{ $capaBlueDark }};margin-bottom:2mm;">
            <tr>
              <td style="padding-bottom:1.5mm;">
                @if($brandLogoSrc)
                  <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:11pt;width:auto;">
                @else
                  <span style="font-size:10pt;font-weight:900;color:{{ $capaBlueDark }};">SID<span style="color:#C8A96E;">360</span></span>
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
        </div>
        <div class="strip-flex-fill" style="font-size:0;line-height:0;">&nbsp;</div>
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
      </div>
      @else
      <table width="100%" height="{{ $stripH }}" cellspacing="0" cellpadding="0" style="height:{{ $stripHeight }};">
        <tr>
          <td valign="top" style="padding:2.5mm 3.5mm 0;">
            <table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:1pt solid {{ $capaBlueDark }};margin-bottom:2mm;">
              <tr>
                <td style="padding-bottom:1.5mm;">
                  @if($brandLogoSrc)
                    <img src="{{ $brandLogoSrc }}" alt="Sid360" style="height:11pt;width:auto;">
                  @else
                    <span style="font-size:10pt;font-weight:900;color:{{ $capaBlueDark }};">SID<span style="color:#C8A96E;">360</span></span>
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
          </td>
        </tr>
        <tr>
          <td valign="bottom" style="padding:0 3.5mm 2.5mm;">
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
      @endif
    </td>
  </tr>
</table>
