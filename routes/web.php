<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::get('/', HomeController::class);

Route::post('/register', [RegisterController::class, 'store'])->middleware('guest');

Route::middleware('guest')->group(function () {
    Route::view('/admin/login', 'admin.admin-login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});
Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth');
