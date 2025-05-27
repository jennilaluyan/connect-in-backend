<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdminController; // <-- Tambahkan ini
// use App\Http\Controllers\Api\HrController; // <-- Tambahkan ini jika Anda membuat controller untuk HR

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rute Publik untuk Autentikasi
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rute yang memerlukan autentikasi umum (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user(); // Mendapatkan data user yang sedang login
    });
    // Anda bisa tambahkan rute lain yang bisa diakses semua user terautentikasi di sini
    // Contoh: Route::post('/update-profile', [ProfileController::class, 'update']);
});

// Rute Khusus untuk Super Admin
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    // name('superadmin.') akan memberi prefix nama pada rute, contoh: superadmin.pending-hr
    // Berguna jika Anda menggunakan named routes di backend/testing. Opsional untuk API murni.

    Route::get('/pending-hr-applications', [SuperAdminController::class, 'getPendingHrApplications'])->name('pending-hr');
    Route::post('/hr-applications/{user}/approve', [SuperAdminController::class, 'approveHrApplication'])->name('hr.approve'); // {user} adalah ID user
    Route::post('/hr-applications/{user}/reject', [SuperAdminController::class, 'rejectHrApplication'])->name('hr.reject');
    Route::delete('/users/{user}', [SuperAdminController::class, 'deleteUser'])->name('users.delete');
    Route::get('/users', [SuperAdminController::class, 'getAllUsers'])->name('users.index');
});

// Rute Khusus untuk HR Department (yang sudah diapprove)
Route::middleware(['auth:sanctum', 'role:hr'])->prefix('hr')->name('hr.')->group(function () {
    // Contoh rute untuk HR
    // Route::get('/dashboard', [HrController::class, 'dashboard'])->name('dashboard');
    // Route::post('/job-postings', [HrController::class, 'createJobPosting'])->name('jobpostings.store');
    // Route::get('/job-postings', [HrController::class, 'getJobPostings'])->name('jobpostings.index');
    // Route::get('/applicants', [HrController::class, 'getApplicants'])->name('applicants.index');

    // Untuk saat ini, kita bisa tambahkan contoh sederhana:
    Route::get('/info', function () {
        return response()->json([
            'message' => 'Selamat datang di area HR Department!',
            'user' => auth()->user()
        ]);
    })->name('info');
});

// Rute untuk User Biasa (yang sudah diapprove - user biasa otomatis diapprove status HR-nya)
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->name('user.')->group(function () {
    // Contoh rute untuk user biasa
    // Route::get('/my-applications', [UserController::class, 'myApplications'])->name('applications');

    // Untuk saat ini, kita bisa tambahkan contoh sederhana:
    Route::get('/info', function () {
        return response()->json([
            'message' => 'Ini adalah area untuk user biasa.',
            'user' => auth()->user()
        ]);
    })->name('info');
});
