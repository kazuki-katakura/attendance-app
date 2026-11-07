<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index'])->name('attendance-records.index');
    Route::get('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show'])->name('attendance-records.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/attendance-records', [AttendanceRecordController::class, 'store'])->name('attendance-records.store');
        Route::match(['put', 'patch'], '/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update'])->name('attendance-records.update');
        Route::delete('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy'])->name('attendance-records.destroy');
    });
});
