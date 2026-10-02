<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Emojis\Laravel\Http\Controllers\EmojiController;
use Simtabi\Laranail\Emojis\Laravel\Http\Controllers\PickerController;
use Simtabi\Laranail\Emojis\Laravel\Http\Controllers\DescribeController;
use Simtabi\Laranail\Emojis\Laravel\Http\Controllers\CatalogueController;

/*
| Loaded only when laranail.emojis.api.enabled is true. Prefix, version and middleware come from config;
| this file names the endpoints and nothing else. Every route is a GET and nothing here writes, which is
| what makes the surface safe to expose and to cache: the data changes when the package is upgraded.
|
| {key} is deliberately unconstrained: a where() pattern would turn a malformed key into "no such
| endpoint". The controller looks the key up and answers 404 for one that names no emoji.
*/

Route::get('/', DescribeController::class)->name('laranail.emojis.api.describe');
Route::get('/emojis', [EmojiController::class, 'index'])->name('laranail.emojis.api.emojis.index');
Route::get('/emojis/{key}', [EmojiController::class, 'show'])->name('laranail.emojis.api.emojis.show');
Route::get('/picker', PickerController::class)->name('laranail.emojis.api.picker');
Route::get('/symbols', [CatalogueController::class, 'symbols'])->name('laranail.emojis.api.symbols.index');
Route::get('/symbols/{group}', [CatalogueController::class, 'symbolGroup'])->name('laranail.emojis.api.symbols.show');
Route::get('/kaomoji', [CatalogueController::class, 'kaomoji'])->name('laranail.emojis.api.kaomoji');
Route::get('/emoticons', [CatalogueController::class, 'emoticons'])->name('laranail.emojis.api.emoticons');
Route::get('/tags', [CatalogueController::class, 'tags'])->name('laranail.emojis.api.tags');
