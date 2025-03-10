<?php

use App\Http\Controllers\Delete;
use App\Http\Controllers\Get;
use App\Http\Controllers\Mkcalendar;
use App\Http\Controllers\Options;
use App\Http\Controllers\PropFind;
use App\Http\Controllers\PropPatch;
use App\Http\Controllers\Put;
use App\Http\Controllers\Report;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::options('{any}', [Options::class, 'index'])->where('any', '.*');
Route::match(['HEAD', 'GET'], '/{username}/calendars/{any}', [Get::class, 'index'])->where('any', '.+');
Route::withoutMiddleware(ValidateCsrfToken::class)->match('MKCALENDAR', '/{username}/calendars/{any}', [Mkcalendar::class, 'index'])->where('any', '.+');
Route::withoutMiddleware(ValidateCsrfToken::class)->put('/{username}/calendars/{any}/',[Put::class, 'index'])->where('any', '.+');
Route::withoutMiddleware(ValidateCsrfToken::class)->match('PROPPATCH', '/{username}/calendars/{any}', [PropPatch::class, 'index'])->where('any', '.+');
Route::withoutMiddleware(ValidateCsrfToken::class)->match('DELETE', '/{username}/calendars/{any}', [Delete::class, 'index'])->where('any', '.+');
Route::withoutMiddleware(ValidateCsrfToken::class)->match('PROPFIND', '{any}', [PropFind::class, 'index'])->where('any', '.*');
Route::withoutMiddleware(ValidateCsrfToken::class)->match('REPORT', '/{username}/calendars/{any}', [Report::class, 'index'])->where('any', '.+');