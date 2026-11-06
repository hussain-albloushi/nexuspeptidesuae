<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', function () {
    return view('welcome');
});
Route::get('/admin/{path}', function () {
    return view('welcome');
})->where('path', '.*');

Route::get('/login', function () {
    return view('welcome');
});

Route::get('/', [StorefrontController::class, 'index']);
Route::get('{path}', [StorefrontController::class, 'index'])->where('path', '.*');
