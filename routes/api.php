<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\JobPostingController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\ProfileController; // Tambahkan ini

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/job-postings', [JobPostingController::class, 'index'])->name('job-postings.index.public');
Route::get('/job-postings/{job_posting}', [JobPostingController::class, 'show'])->name('job-postings.show.public');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rute Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update'); // Menggunakan POST karena FormData

    Route::post('/job-postings/{job_posting}/apply', [JobApplicationController::class, 'store'])
        ->middleware('role:user')
        ->name('job-applications.store.user');
});

// Rute Khusus untuk Super Admin
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    // ... rute superadmin lainnya
    Route::get('/pending-hr-applications', [SuperAdminController::class, 'getPendingHrApplications'])->name('pending-hr');
    Route::post('/hr-applications/{user}/approve', [SuperAdminController::class, 'approveHrApplication'])->name('hr.approve');
    Route::post('/hr-applications/{user}/reject', [SuperAdminController::class, 'rejectHrApplication'])->name('hr.reject');
    Route::delete('/users/{user}', [SuperAdminController::class, 'deleteUser'])->name('users.delete');
    Route::get('/users', [SuperAdminController::class, 'getAllUsers'])->name('users.index');
});

// RUTE KHUSUS UNTUK HR DEPARTMENT (YANG SUDAH DIAPPROVE)
Route::middleware(['auth:sanctum', 'role:hr'])->prefix('hr')->name('hr.')->group(function () {
    // ... rute HR lainnya
    Route::post('/job-postings', [JobPostingController::class, 'store'])->name('job-postings.store');
    Route::post('/job-postings/{job_posting}', [JobPostingController::class, 'update'])->name('job-postings.update.with-file');
    Route::delete('/job-postings/{job_posting}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');
    Route::get('/my-job-postings', [JobPostingController::class, 'index'])->name('job-postings.my-index');
    Route::get('/applicants', [JobApplicationController::class, 'indexForHr'])->name('applicants.index');
    Route::get('/applicants/{application}/download-cv', [JobApplicationController::class, 'downloadCv'])->name('applicants.downloadCv');
    Route::post('/applicants/{application}/accept', [JobApplicationController::class, 'acceptApplication'])->name('applicants.accept');
    Route::post('/applicants/{application}/reject', [JobApplicationController::class, 'rejectApplication'])->name('applicants.reject');
    Route::get('/info', function () {
        return response()->json([
            'message' => 'Selamat datang di area HR Department!',
            'user' => auth()->user()
        ]);
    })->name('info');
});

// Rute untuk User Biasa
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->name('user.')->group(function () {
    // ... rute user lainnya
    Route::get('/info', function () {
        return response()->json([
            'message' => 'Ini adalah area untuk user biasa.',
            'user' => auth()->user()
        ]);
    })->name('info');
});
