<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\JobPostingController;
use App\Http\Controllers\Api\JobApplicationController;

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

// --- RUTE PUBLIK UNTUK MELIHAT LOWONGAN KERJA ---
Route::get('/job-postings', [JobPostingController::class, 'index'])->name('job-postings.index.public');
Route::get('/job-postings/{job_posting}', [JobPostingController::class, 'show'])->name('job-postings.show.public');


// Rute yang memerlukan autentikasi umum (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // --- RUTE USER MELAMAR PEKERJAAN ---
    Route::post('/job-postings/{job_posting}/apply', [JobApplicationController::class, 'store'])
        ->middleware('role:user') // Middleware role:user bisa juga ditaruh di grup 'user' di bawah jika lebih sesuai
        ->name('job-applications.store.user'); // Penamaan lebih spesifik
});

// Rute Khusus untuk Super Admin
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/pending-hr-applications', [SuperAdminController::class, 'getPendingHrApplications'])->name('pending-hr');
    Route::post('/hr-applications/{user}/approve', [SuperAdminController::class, 'approveHrApplication'])->name('hr.approve');
    Route::post('/hr-applications/{user}/reject', [SuperAdminController::class, 'rejectHrApplication'])->name('hr.reject');
    Route::delete('/users/{user}', [SuperAdminController::class, 'deleteUser'])->name('users.delete');
    Route::get('/users', [SuperAdminController::class, 'getAllUsers'])->name('users.index');
});

// RUTE KHUSUS UNTUK HR DEPARTMENT (YANG SUDAH DIAPPROVE)
Route::middleware(['auth:sanctum', 'role:hr'])->prefix('hr')->name('hr.')->group(function () {
    
    // --- CRUD Job Postings oleh HR ---
    Route::post('/job-postings', [JobPostingController::class, 'store'])->name('job-postings.store');
    // Jika menggunakan FormData dengan _method spoofing dari frontend fetch untuk update file:
    Route::post('/job-postings/{job_posting}', [JobPostingController::class, 'update'])->name('job-postings.update.with-file'); 
    // Alternatif jika frontend bisa mengirim PUT murni (misal dengan Axios) dan tidak ada file, atau file dihandle terpisah:
    // Route::put('/job-postings/{job_posting}', [JobPostingController::class, 'update'])->name('job-postings.update');
    Route::delete('/job-postings/{job_posting}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');
    Route::get('/my-job-postings', [JobPostingController::class, 'index'])->name('job-postings.my-index');

    // --- Rute untuk HR Mengelola Lamaran ---
    Route::get('/applicants', [JobApplicationController::class, 'indexForHr'])->name('applicants.index');
    Route::get('/applicants/{application}/download-cv', [JobApplicationController::class, 'downloadCv'])->name('applicants.downloadCv');
    
    // RUTE BARU UNTUK AKSI TERIMA/TOLAK LAMARAN
    Route::post('/applicants/{application}/accept', [JobApplicationController::class, 'acceptApplication'])->name('applicants.accept');
    Route::post('/applicants/{application}/reject', [JobApplicationController::class, 'rejectApplication'])->name('applicants.reject');

    // Rute Info HR
    Route::get('/info', function () {
        return response()->json([
            'message' => 'Selamat datang di area HR Department!',
            'user' => auth()->user()
        ]);
    })->name('info');
});


// Rute untuk User Biasa (yang sudah diapprove)
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->name('user.')->group(function () {
    // Contoh rute, user biasa melihat lowongan melalui rute publik.
    // Fungsionalitas spesifik user (misal, melihat histori lamaran mereka) bisa ditambahkan di sini.
    Route::get('/info', function () {
        return response()->json([
            'message' => 'Ini adalah area untuk user biasa.',
            'user' => auth()->user()
        ]);
    })->name('info');
});