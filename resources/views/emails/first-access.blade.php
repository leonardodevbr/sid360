<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isFirstAccess ? 'Primeiro acesso' : 'Redefinir senha' }} - {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f1f5f9; color: #334155; }
        .wrapper { max-width: 520px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: #fff; padding: 28px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .header p { margin: 8px 0 0; font-size: 0.9rem; opacity: .95; }
        .body { padding: 28px 24px; line-height: 1.6; }
        .body p { margin: 0 0 16px; font-size: 15px; }
        .btn { display: inline-block; background: #2563eb; color: #fff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; margin: 20px 0; }
        .btn:hover { background: #1d4ed8; }
        .muted { color: #64748b; font-size: 13px; margin-top: 24px; }
        .footer { padding: 16px 24px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>{{ config('app.name') }}</h1>
                <p>{{ $isFirstAccess ? 'Primeiro acesso ao sistema' : 'Redefinição de senha' }}</p>
            </div>
            <div class="body">
                <p>Olá, <strong>{{ $user->name }}</strong>!</p>
                @if($isFirstAccess)
                    <p>Foi criado um usuário para você acessar o sistema de diárias. Para definir sua senha e entrar, clique no botão abaixo:</p>
                @else
                    <p>Você solicitou a redefinição de senha. Clique no botão abaixo para criar uma nova senha:</p>
                @endif
                <p style="text-align: center;">
                    <a href="{{ $resetUrl }}" class="btn">{{ $isFirstAccess ? 'Definir minha senha' : 'Redefinir senha' }}</a>
                </p>
                <p class="muted">Este link expira em 60 minutos. Se você não solicitou esta ação, ignore este e-mail.</p>
            </div>
            <div class="footer">
                Este e-mail foi enviado por {{ config('app.name') }}. Não responda a esta mensagem.
            </div>
        </div>
    </div>
</body>
</html>
