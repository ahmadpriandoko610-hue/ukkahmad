<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController as ApiPasswordResetController;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'REST API RAPI-Sekolah berhasil terhubung'
    ]);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/forgot-password', [ApiPasswordResetController::class, 'forgotPassword'])->middleware('throttle:10,1');
Route::post('/verify-password-code', [ApiPasswordResetController::class, 'verifyCode'])->middleware('throttle:15,1');
Route::post('/reset-password', [ApiPasswordResetController::class, 'resetPassword'])->middleware('throttle:10,1');
Route::get('/ruangan', [RoomController::class, 'index']);
Route::get('/dataRuang', [RoomController::class, 'index']);
Route::get('/ruangan/{id}', [RoomController::class, 'show']);
Route::get('/kalender', [CalendarController::class, 'index']);

Route::middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    Route::middleware('role:admin,pic,guru,siswa')->group(function () {
        Route::get('/agenda', [EventController::class, 'index']);
        Route::get('/agenda/{id}', [EventController::class, 'show']);
    });

    Route::middleware('role:guru')->group(function () {
        Route::post('/agenda', [EventController::class, 'store']);
        Route::post('/peminjaman', [EventController::class, 'store']);
        Route::put('/agenda/{id}', [EventController::class, 'update']);
        Route::delete('/agenda/{id}', [EventController::class, 'destroy']);
        Route::post('/pengaduan', [ComplaintController::class, 'store']);
    });

    Route::middleware('role:guru,pic')->group(function () {
        Route::get('/pengaduan', [ComplaintController::class, 'index']);
        Route::get('/pengaduan/{id}', [ComplaintController::class, 'show']);
    });

    Route::middleware('role:pic')->group(function () {
        Route::get('/pic/pengaduan/report', [ComplaintController::class, 'picReport']);
        Route::get('/pengaduan/report', [ComplaintController::class, 'picReport']);
        Route::put('/pengaduan/{id}/status', [ComplaintController::class, 'updateStatus']);
        Route::post('/agenda/{id}/approve', [EventController::class, 'approve']);
        Route::post('/agenda/{id}/reject', [EventController::class, 'reject']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::put('/agenda/{id}/pindah-ruangan', [EventController::class, 'moveRoom']);
    });

    Route::get('/notifikasi', [NotificationController::class, 'index']);
    Route::put('/notifikasi/{id}/read', [NotificationController::class, 'markAsRead']);
});
