
<?php

use Illuminate\Support\Facades\Route;

/**
 * Sid360 - Rotas Web
 * GET / serve o site estático; demais rotas carregam a SPA Vue.
 */
Route::get('/', fn () => view('site'));

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
