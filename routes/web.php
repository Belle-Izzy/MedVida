<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailVerificationController;

Route::get('/verificar-email/{tipo}/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:30,1')->name('verification.verify');

Route::get('/app.css', function () {
    return response()->file(resource_path('css/app.css'), [
        'Content-Type' => 'text/css; charset=UTF-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
});

Route::get('/app.js', function () {
    return response()->file(resource_path('js/app.js'), [
        'Content-Type' => 'text/javascript; charset=UTF-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
});

Route::get('/', function () {
    return view('welcome');
});
