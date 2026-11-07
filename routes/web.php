<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReportController;
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
    Route::get('/attendance/report', [ReportController::class, 'index']);

    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index']);
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index']);
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'update'])->whereNumber('id');

    Route::get('/admin/staff/list', [StaffController::class, 'index']);
    Route::get('/admin/attendance/staff/{id}', [StaffController::class, 'attendance'])->whereNumber('id');
    Route::post('/export', [StaffController::class, 'export']);

    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [StampCorrectionRequestController::class, 'show'])
        ->whereNumber('attendance_correct_request_id');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [StampCorrectionRequestController::class, 'approve'])
        ->whereNumber('attendance_correct_request_id');
});
