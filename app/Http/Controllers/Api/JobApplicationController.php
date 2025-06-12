<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
// Notifikasi sudah di-import
use App\Notifications\NewJobApplicationNotification;
use App\Notifications\ApplicationStatusUpdatedNotification;

class JobApplicationController extends Controller
{
    /**
     * User melamar pekerjaan.
     */
    public function store(Request $request, JobPosting $job_posting)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'user') {
            return response()->json(['message' => 'Hanya pencari kerja yang terautentikasi yang dapat melamar.'], 403);
        }

        $existingApplication = JobApplication::where('job_posting_id', $job_posting->id)
            ->where('user_id', $user->id)
            ->first();
        if ($existingApplication) {
            return response()->json(['message' => 'Anda sudah pernah melamar untuk pekerjaan ini.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'cover_letter' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cvPath = null;
        if ($request->hasFile('cv')) {
            try {
                $originalFileName = pathinfo($request->file('cv')->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $request->file('cv')->getClientOriginalExtension();
                $fileNameToStore = 'cv_' . $user->id . '_' . $job_posting->id . '_' . time() . '_' . Str::slug($originalFileName) . '.' . $extension;

                $cvPath = $request->file('cv')->storeAs('cvs', $fileNameToStore, 'public');
                Log::info('CV diunggah oleh User ID ' . $user->id, ['path' => $cvPath]);
            } catch (\Exception $e) {
                Log::error('Gagal mengunggah CV', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                return response()->json(['message' => 'Gagal mengunggah CV.', 'errors' => ['cv' => ['Terjadi kesalahan saat mengunggah file.']]], 500);
            }
        }

        try {
            $application = JobApplication::create([
                'job_posting_id' => $job_posting->id,
                'user_id' => $user->id,
                'cv_path' => $cvPath,
                'cover_letter' => $request->input('cover_letter'),
                'status' => 'pending',
            ]);

            // Load relasi yang diperlukan untuk notifikasi dan respons
            $application->load(['applicant:id,name,email', 'jobPosting.poster']);

            // --- NOTIFIKASI DIKIRIM DI SINI ---
            $hrUser = $application->jobPosting->poster; // Ambil HR dari relasi
            if ($hrUser) {
                $hrUser->notify(new NewJobApplicationNotification($application));
                Log::info('Notifikasi lamaran baru dikirim ke HR ID: ' . $hrUser->id);
            } else {
                Log::error('Gagal mengirim notifikasi: Data HR (poster) tidak ditemukan untuk JobPosting ID: ' . $application->job_posting_id);
            }
            // --- BATAS PENAMBAHAN ---

            return response()->json(['message' => 'Lamaran Anda berhasil dikirim!', 'data' => $application], 201);
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan lamaran', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            if ($cvPath && Storage::disk('public')->exists($cvPath)) {
                Storage::disk('public')->delete($cvPath);
            }
            return response()->json(['message' => 'Terjadi kesalahan internal saat mengirim lamaran Anda.'], 500);
        }
    }

    /**
     * HR melihat daftar pelamar.
     */
    public function indexForHr(Request $request)
    {
        $hrUser = Auth::user();
        if (!$hrUser || !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr())) {
            return response()->json(['message' => 'Unauthorized. Hanya HR yang diapprove yang dapat melihat pelamar.'], 403);
        }

        $hrJobPostingIds = JobPosting::where('posted_by', $hrUser->id)->pluck('id');

        if ($hrJobPostingIds->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'Tidak ada pelamar atau Anda belum memposting pekerjaan.']);
        }

        $query = JobApplication::with(['applicant:id,name,email,avatar_img', 'jobPosting:id,title,company_name'])
            ->whereIn('job_posting_id', $hrJobPostingIds);

        if ($request->filled('job_posting_id') && $hrJobPostingIds->contains($request->input('job_posting_id'))) {
            $query->where('job_posting_id', $request->input('job_posting_id'));
        }

        $applications = $query->latest('created_at')->paginate($request->input('limit', 15));
        return response()->json($applications);
    }

    /**
     * HR mengunduh CV pelamar.
     */
    public function downloadCv(JobApplication $application)
    {
        $hrUser = Auth::user();
        $jobPosting = $application->jobPosting;

        if (!$hrUser || !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr()) || !$jobPosting || $jobPosting->posted_by !== $hrUser->id) {
            return response()->json(['message' => 'Unauthorized untuk mengakses CV ini.'], 403);
        }

        if (!$application->cv_path || !Storage::disk('public')->exists($application->cv_path)) {
            return response()->json(['message' => 'File CV tidak ditemukan.'], 404);
        }

        $applicantName = $application->applicant ? Str::slug($application->applicant->name, '_') : 'applicant_' . $application->user_id;
        $jobTitle = $application->jobPosting ? Str::slug($application->jobPosting->title, '_') : 'job_' . $application->job_posting_id;
        $originalExtension = pathinfo(Storage::disk('public')->path($application->cv_path), PATHINFO_EXTENSION);
        $downloadFileName = 'CV_' . $applicantName . '_' . $jobTitle . '.' . $originalExtension;

        return Storage::disk('public')->download($application->cv_path, $downloadFileName);
    }

    /**
     * HR menerima lamaran pekerjaan (mengubah status ke 'shortlisted').
     */
    public function acceptApplication(JobApplication $application)
    {
        return $this->updateApplicationStatus($application, 'shortlisted', 'diterima (shortlisted)');
    }

    /**
     * HR menolak lamaran pekerjaan (mengubah status ke 'rejected').
     */
    public function rejectApplication(JobApplication $application)
    {
        return $this->updateApplicationStatus($application, 'rejected', 'ditolak');
    }

    /**
     * HR mempekerjakan pelamar (mengubah status ke 'hired').
     */
    public function hireApplicant(JobApplication $application)
    {
        return $this->updateApplicationStatus($application, 'hired', 'di-hire');
    }

    /**
     * Metode private untuk menangani logika update status.
     */
    private function updateApplicationStatus(JobApplication $application, string $newStatus, string $actionVerb)
    {
        $hrUser = Auth::user();
        $jobPosting = $application->jobPosting;

        if (!$hrUser || !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr()) || !$jobPosting || $jobPosting->posted_by !== $hrUser->id) {
            return response()->json(['message' => 'Unauthorized untuk mengubah status lamaran ini.'], 403);
        }

        $validTransitions = [
            'shortlisted' => ['pending', 'reviewed'],
            'rejected' => ['pending', 'reviewed', 'shortlisted'],
            'hired' => ['shortlisted'],
        ];

        if (!isset($validTransitions[$newStatus]) || !in_array($application->status, $validTransitions[$newStatus])) {
            return response()->json(['message' => "Lamaran dengan status '{$application->status}' tidak dapat diubah menjadi '{$newStatus}'."], 400);
        }

        $application->status = $newStatus;
        $application->save();

        // Load relasi applicant agar bisa mengirim notifikasi
        $application->load('applicant');

        // --- NOTIFIKASI DIKIRIM DI SINI ---
        $applicantUser = $application->applicant; // Ambil user yang melamar dari relasi
        if ($applicantUser) {
            $applicantUser->notify(new ApplicationStatusUpdatedNotification($application));
            Log::info("Notifikasi status '{$newStatus}' dikirim ke User ID: " . $applicantUser->id);
        }
        // --- BATAS PENAMBAHAN ---

        $application->load(['applicant:id,name,email,avatar_img', 'jobPosting:id,title,company_name']);

        return response()->json([
            'message' => "Lamaran berhasil {$actionVerb}.",
            'data' => $application
        ]);
    }
}
