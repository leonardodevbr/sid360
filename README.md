# Sid360

Sistema de gestão de empreendimentos e lotes.

## Stack

- Laravel 12 (API REST) + Sanctum
- Vue 3 + Vite + Tailwind CSS 3
- Pinia + Vue Router 4
- MySQL

## Estrutura

- Backend Laravel na raiz
- Frontend Vue em `resources/js/`
- Site estático em `resources/site/index.html` (rota `GET /`)
- Painel administrativo em `/app` (SPA Vue)

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate

# Criar banco MySQL: sid360
php artisan migrate --seed

npm install
npm run dev
```

## Acesso

- Site: http://localhost:8000/
- Painel: http://localhost:8000/app
- Login: http://localhost:8000/login
- API: http://localhost:8000/api

**Usuário seed:** `admin@sid360.com.br` / `123$qweR---`

## Módulos

- Empreendimentos (CRUD)
- Lotes (CRUD, filtro por empreendimento)
- Usuários, perfis e permissões (Spatie)
- Configurações do sistema
