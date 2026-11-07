<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::get('/', HomeController::class);

Route::post('/register', [RegisterController::class, 'store'])->middleware('guest');

Route::middleware('guest')->group(function () {
    Route::view('/admin/login', 'admin.admin-login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});
Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/list', [AttendanceController::class, 'list']);
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/detail/{id}', [AttendanceController::class, 'update'])->whereNumber('id');

    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index']);
});
