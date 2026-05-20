<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Sid360 - Rotas Web
 * GET / serve o site estático; demais rotas carregam a SPA Vue.
 */
Route::get('/', function () {
    $path = resource_path('site/index.html');

    if (! is_file($path)) {
        abort(404, 'Página inicial não encontrada.');
    }

    return response()->file($path);
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
