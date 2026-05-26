<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcelas em atraso — Sid360 Imóveis</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f1f5f9; color: #334155; }
        .wrapper { max-width: 520px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%); color: #fff; padding: 28px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .header p { margin: 8px 0 0; font-size: 0.9rem; opacity: .95; }
        .body { padding: 28px 24px; line-height: 1.6; }
        .body p { margin: 0 0 16px; font-size: 15px; }
        .alert-box { background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0; font-size: 15px; }
        .list-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        .list-table th { text-align: left; padding: 10px 8px; background: #f8fafc; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        .list-table td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; }
        .totals { margin: 16px 0; font-size: 15px; }
        .totals p { margin: 8px 0; }
        .totals strong { color: #1e293b; }
        .note { color: #64748b; font-size: 13px; }
        .btn { display: inline-block; background: #dc2626; color: #fff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; margin: 20px 0; }
        .footer { padding: 16px 24px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>Sid360 Imóveis</h1>
                <p>Parcelas em atraso</p>
            </div>
            <div class="body">
                <p>Olá, <strong>{{ $clientName }}</strong>!</p>
                <div class="alert-box">
                    Você tem parcelas em atraso no contrato <strong>{{ $contractNo }}</strong>.
                </div>
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Parcela</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Dias em atraso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($overdueList as $row)
                            <tr>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ $row['due_date'] }}</td>
                                <td>{{ $row['value'] }}</td>
                                <td>{{ $row['days_overdue'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="totals">
                    <p>Total em aberto: <strong>{{ $totalValue }}</strong></p>
                    <p>Total corrigido (previsto para {{ $paymentDate }}): <strong>{{ $totalCorrected }}</strong></p>
                </div>
                <p class="note">Estimativa com multa de 2,5% ao mês (pró-rata por dia).</p>
                <p style="text-align: center;">
                    <a href="{{ config('app.url') }}/pagamentos" class="btn">Regularizar agora</a>
                </p>
            </div>
            <div class="footer">
                Sid360 Imóveis · Não responda a este e-mail.
            </div>
        </div>
    </div>
</body>
</html>
