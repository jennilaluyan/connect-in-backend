<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\JobPostingController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\UserJobController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rute Publik
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/job-postings', [JobPostingController::class, 'index'])->name('job-postings.index.public');
Route::get('/job-postings/{job_posting}', [JobPostingController::class, 'show'])->name('job-postings.show.public');

// Rute yang Memerlukan Autentikasi Umum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::post('/job-postings/{job_posting}/apply', [JobApplicationController::class, 'store'])
        ->middleware('role:user')
        ->name('job-applications.store');
});

// Rute Khusus untuk User Biasa
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->name('user.')->group(function () {
    Route::get('/info', fn() => response()->json(['message' => 'Ini area user biasa.', 'user' => auth()->user()]))->name('info');
    Route::get('/my-jobs', [UserJobController::class, 'myJobs'])->name('my-jobs');
    
    // --- RUTE BARU UNTUK RIWAYAT LAMARAN ---
    Route::get('/my-applications', [UserJobController::class, 'myApplications'])->name('my-applications');
});

// Rute Khusus untuk HR
Route::middleware(['auth:sanctum', 'role:hr'])->prefix('hr')->name('hr.')->group(function () {
    Route::get('/info', fn() => response()->json(['message' => 'Selamat datang di area HR.', 'user' => auth()->user()]))->name('info');
    
    // CRUD Job Postings oleh HR
    Route::get('/my-job-postings', [JobPostingController::class, 'index'])->name('job-postings.my-index');
    Route::post('/job-postings', [JobPostingController::class, 'store'])->name('job-postings.store');
    Route::post('/job-postings/{job_posting}', [JobPostingController::class, 'update'])->name('job-postings.update');
    Route::delete('/job-postings/{job_posting}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');

    // Mengelola Lamaran
    Route::get('/applicants', [JobApplicationController::class, 'indexForHr'])->name('applicants.index');
    Route::get('/applicants/{application}/download-cv', [JobApplicationController::class, 'downloadCv'])->name('applicants.downloadCv');
    Route::post('/applicants/{application}/accept', [JobApplicationController::class, 'acceptApplication'])->name('applicants.accept');
    Route::post('/applicants/{application}/reject', [JobApplicationController::class, 'rejectApplication'])->name('applicants.reject');
    Route::post('/applicants/{application}/hire', [JobApplicationController::class, 'hireApplicant'])->name('applicants.hire');
});

// Rute Khusus untuk Super Admin
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/users', [SuperAdminController::class, 'getAllUsers'])->name('users.index');
    Route::delete('/users/{user}', [SuperAdminController::class, 'deleteUser'])->name('users.delete');
    Route::get('/pending-hr-applications', [SuperAdminController::class, 'getPendingHrApplications'])->name('pending-hr');
    Route::post('/hr-applications/{user}/approve', [SuperAdminController::class, 'approveHrApplication'])->name('hr.approve');
    Route::post('/hr-applications/{user}/reject', [SuperAdminController::class, 'rejectHrApplication'])->name('hr.reject');
});