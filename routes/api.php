<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KamarController;
use App\Http\Controllers\Api\PenghuniController;
use App\Http\Controllers\Api\TagihanController;
use App\Http\Controllers\Api\PembayaranController;
use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PengumumanController;
use App\Http\Controllers\Api\FasilitasController;

/*
|--------------------------------------------------------------------------
| HOMIA API Routes
|--------------------------------------------------------------------------
*/

// ─── PUBLIC ───────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register',         [AuthController::class, 'register']);
    Route::post('/login',            [AuthController::class, 'login']);
    Route::post('/login-admin',      [AuthController::class, 'loginAdmin']);
    Route::post('/google',           [AuthController::class, 'googleLogin']);
    Route::post('/forgot-password',  [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',   [AuthController::class, 'resetPassword']);
});

// Midtrans webhook (no auth, verified by signature)
Route::post('/midtrans/notification', [MidtransController::class, 'handleNotification']);

// ─── AUTHENTICATED ─────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard/penghuni', [DashboardController::class, 'penghuni']);
    Route::get('/dashboard/admin',    [DashboardController::class, 'admin'])->middleware('admin.only');

    // Kamar
    Route::get('/kamar',              [KamarController::class, 'index']);
    Route::get('/kamar/summary',      [KamarController::class, 'summary'])->middleware('admin.only');
    Route::get('/kamar/{id}',         [KamarController::class, 'show']);
    Route::post('/kamar',             [KamarController::class, 'store'])->middleware('admin.only');
    Route::put('/kamar/{id}',         [KamarController::class, 'update'])->middleware('admin.only');
    Route::delete('/kamar/{id}',      [KamarController::class, 'destroy'])->middleware('admin.only');

    // Fasilitas
    Route::get('/fasilitas',          [FasilitasController::class, 'index']);
    Route::post('/fasilitas',         [FasilitasController::class, 'store'])->middleware('admin.only');
    Route::delete('/fasilitas/{id}',  [FasilitasController::class, 'destroy'])->middleware('admin.only');

    // Penghuni
    Route::get('/penghuni',           [PenghuniController::class, 'index'])->middleware('admin.only');
    Route::get('/penghuni/me',        [PenghuniController::class, 'myProfile']);
    Route::get('/penghuni/{id}',      [PenghuniController::class, 'show'])->middleware('admin.only');
    Route::post('/penghuni',          [PenghuniController::class, 'store'])->middleware('admin.only');
    Route::put('/penghuni/{id}',      [PenghuniController::class, 'update']);
    Route::delete('/penghuni/{id}',   [PenghuniController::class, 'destroy'])->middleware('admin.only');

    // Tagihan
    Route::get('/tagihan',                        [TagihanController::class, 'index']);
    Route::get('/tagihan/summary',                [TagihanController::class, 'summary'])->middleware('admin.only');
    Route::get('/tagihan/{id}',                   [TagihanController::class, 'show']);
    Route::post('/tagihan',                       [TagihanController::class, 'store'])->middleware('admin.only');
    Route::put('/tagihan/{id}/denda',             [TagihanController::class, 'updateDenda'])->middleware('admin.only');
    Route::post('/tagihan/generate-bulanan',      [TagihanController::class, 'generateBulanan'])->middleware('admin.only');

    // Pembayaran (manual upload bukti)
    Route::get('/pembayaran',                     [PembayaranController::class, 'index']);
    Route::post('/pembayaran',                    [PembayaranController::class, 'store']);
    Route::get('/pembayaran/menunggu',            [PembayaranController::class, 'menungguValidasi'])->middleware('admin.only');
    Route::put('/pembayaran/{id}/validasi',       [PembayaranController::class, 'validasi'])->middleware('admin.only');

    // Payment Gateway - Midtrans
    Route::post('/midtrans/create-transaction',   [MidtransController::class, 'createTransaction']);
    Route::get('/midtrans/status/{orderId}',      [MidtransController::class, 'checkStatus']);

    // Forum Komunikasi
    Route::get('/forum',              [ForumController::class, 'index']);
    Route::post('/forum',             [ForumController::class, 'store']);
    Route::delete('/forum/{id}',      [ForumController::class, 'destroy']);

    // Notifikasi
    Route::get('/notifikasi',                     [NotifikasiController::class, 'index']);
    Route::put('/notifikasi/{id}/baca',           [NotifikasiController::class, 'markRead']);
    Route::put('/notifikasi/baca-semua',          [NotifikasiController::class, 'markAllRead']);

    // Pengumuman
    Route::get('/pengumuman',         [PengumumanController::class, 'index']);
    Route::post('/pengumuman',        [PengumumanController::class, 'store'])->middleware('admin.only');
});
