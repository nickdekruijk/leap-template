<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('{any}', [PageController::class, 'route'])->where('any', '(.*)');
