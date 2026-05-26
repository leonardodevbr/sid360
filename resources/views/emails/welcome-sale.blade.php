<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra registrada — Sid360 Imóveis</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f1f5f9; color: #334155; }
        .wrapper { max-width: 520px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: #fff; padding: 28px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .header p { margin: 8px 0 0; font-size: 0.9rem; opacity: .95; }
        .body { padding: 28px 24px; line-height: 1.6; }
        .body p { margin: 0 0 16px; font-size: 15px; }
        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .data-table td:first-child { color: #64748b; width: 40%; }
        .data-table td:last-child { font-weight: 600; color: #1e293b; }
        .btn { display: inline-block; background: #2563eb; color: #fff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; margin: 20px 0; }
        .muted { color: #64748b; font-size: 13px; margin-top: 24px; }
        .footer { padding: 16px 24px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>Sid360 Imóveis</h1>
                <p>Compra registrada com sucesso!</p>
            </div>
            <div class="body">
                <p>Olá, <strong>{{ $buyerName }}</strong>!</p>
                <p>Sua compra foi registrada. Aqui estão os detalhes:</p>
                <table class="data-table">
                    <tr>
                        <td>Contrato</td>
                        <td>{{ $contractNo }}</td>
                    </tr>
                    <tr>
                        <td>Lote</td>
                        <td>{{ $lotDescription }}</td>
                    </tr>
                    <tr>
                        <td>Valor total</td>
                        <td>{{ $totalValue }}</td>
                    </tr>
                    <tr>
                        <td>1ª parcela</td>
                        <td>{{ $firstDueDate }}</td>
                    </tr>
                </table>
                <p style="text-align: center;">
                    <a href="{{ config('app.url') }}/pagamentos" class="btn">Acompanhar pagamentos</a>
                </p>
                <p class="muted">Dúvidas? (74) 9 8823-0151</p>
            </div>
            <div class="footer">
                Sid360 Imóveis · Não responda a este e-mail.
            </div>
        </div>
    </div>
</body>
</html>
