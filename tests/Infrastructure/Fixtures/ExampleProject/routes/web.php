<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ClientController;

Route::get('/login', function () {
    return view('login');
});

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin', [AdminController::class, 'index']);
Route::post('/admin/filter', [AdminController::class, 'filter']);

Route::resource('clients', ClientController::class);
