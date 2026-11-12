<?php

use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\VerificationController;
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
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::get('{path}', [StorefrontController::class, 'index'])->where('path', '.*');
