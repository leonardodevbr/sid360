<?php

use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

/**
 * Sid360 - Rotas Web
 * GET / serve o site estático; demais rotas carregam a SPA Vue.
 */
Route::get('/', fn () => view('site'));

Route::get('/sales/{id}/carne/preview', [SaleController::class, 'carnePreview'])
    ->whereNumber('id');

Route::get('/sitemap.xml', function () {
    $content = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc>https://sid360.com.br/</loc>
    <lastmod>' . now()->toAtomString() . '</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <image:image>
      <image:loc>https://sid360.com.br/img/og-image.jpg</image:loc>
      <image:title>Sid360 — Loteamento em Cafarnaum-BA</image:title>
    </image:image>
  </url>
</urlset>';

    return response($content, 200)
        ->header('Content-Type', 'application/xml');
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
