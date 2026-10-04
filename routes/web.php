<?php

use Illuminate\Support\Facades\Route;

Route::get('/app.css', function () {
    return response()->file(resource_path('css/app.css'), [
        'Content-Type' => 'text/css; charset=UTF-8',
    ]);
});

Route::get('/app.js', function () {
    return response()->file(resource_path('js/app.js'), [
        'Content-Type' => 'text/javascript; charset=UTF-8',
    ]);
});

Route::get('/', function () {
    return view('welcome');
});
