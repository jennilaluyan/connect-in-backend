<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User; // Pastikan User model di-import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str; // Ditambahkan untuk Str::slug

class JobApplicationController extends Controller
{
    /**
     * User melamar pekerjaan.
     * Request akan datang ke route seperti: POST /api/job-postings/{job_posting}/apply
     */
    public function store(Request $request, JobPosting $job_posting) // Menggunakan Route Model Binding untuk $job_posting
    {
        $user = Auth::user();

        // Pastikan user yang melamar adalah 'user' biasa, bukan HR atau SA (jika ada aturan seperti itu)
        if (!$user || $user->role !== 'user') { // Ditambahkan pengecekan !$user
            return response()->json(['message' => 'Hanya pencari kerja yang terautentikasi yang dapat melamar.'], 403);
        }

        // Validasi: User hanya bisa melamar sekali untuk pekerjaan yang sama
        $existingApplication = JobApplication::where('job_posting_id', $job_posting->id)
            ->where('user_id', $user->id)
            ->first();
        if ($existingApplication) {
            return response()->json(['message' => 'Anda sudah pernah melamar untuk pekerjaan ini.'], 409); // 409 Conflict
        }

        $validator = Validator::make($request->all(), [
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120', // Max 5MB (5 * 1024 KB)
            'cover_letter' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cvPath = null;
        if ($request->hasFile('cv')) {
            try {
                // Buat nama file yang unik
                $originalFileName = pathinfo($request->file('cv')->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $request->file('cv')->getClientOriginalExtension();
                $fileNameToStore = 'cv_' . $user->id . '_' . $job_posting->id . '_' . time() . '_' . Str::slug($originalFileName) . '.' . $extension;

                // Simpan file ke 'storage/app/public/cvs'
                // cvPath akan menyimpan path relatif dari root disk 'public', misal 'cvs/namafile.pdf'
                $cvPath = $request->file('cv')->storeAs('cvs', $fileNameToStore, 'public');
                Log::info('CV uploaded by User ID ' . $user->id . ' for Job ID ' . $job_posting->id . ' to path: ' . $cvPath);
            } catch (\Exception $e) {
                Log::error('Gagal mengunggah CV untuk User ID ' . $user->id . ': ' . $e->getMessage());
                return response()->json(['message' => 'Gagal mengunggah CV.', 'errors' => ['cv' => ['Terjadi kesalahan saat mengunggah file.']]], 500);
            }
        } else {
            return response()->json(['message' => 'File CV dibutuhkan.', 'errors' => ['cv' => ['File CV wajib diunggah.']]], 422);
        }


        try {
            $application = JobApplication::create([
                'job_posting_id' => $job_posting->id,
                'user_id' => $user->id,
                'cv_path' => $cvPath, // Simpan path relatif yang dikembalikan oleh storeAs()
                'cover_letter' => $request->input('cover_letter'),
                'status' => 'pending', // Status awal lamaran
            ]);

            // Load relasi untuk data respons yang lebih informatif
            $application->load(['applicant:id,name,email', 'jobPosting:id,title']);

            return response()->json([
                'message' => 'Lamaran Anda berhasil dikirim!',
                'data' => $application
            ], 201);
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan lamaran untuk User ID ' . $user->id . ' ke database: ' . $e->getMessage());
            // Jika file sudah terunggah tapi gagal simpan ke DB, idealnya file dihapus
            if ($cvPath && Storage::disk('public')->exists($cvPath)) {
                Storage::disk('public')->delete($cvPath);
                Log::info('Uploaded CV file deleted due to DB save failure: ' . $cvPath);
            }
            return response()->json(['message' => 'Terjadi kesalahan internal saat mengirim lamaran Anda. Silakan coba lagi nanti.'], 500);
        }
    }

    /**
     * HR melihat daftar pelamar.
     * Bisa difilter berdasarkan job_posting_id atau menampilkan semua pelamar
     * untuk pekerjaan yang diposting oleh HR tersebut.
     * Dipanggil dari route: GET /api/hr/applicants
     */
    public function indexForHr(Request $request)
    {
        $hrUser = Auth::user();
        // Pastikan method isApprovedHr() ada di model User
        if (!$hrUser || !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr())) {
            return response()->json(['message' => 'Unauthorized. Hanya HR yang diapprove yang dapat melihat pelamar.'], 403);
        }

        // Ambil semua ID pekerjaan yang diposting oleh HR ini
        $hrJobPostingIds = JobPosting::where('posted_by', $hrUser->id)->pluck('id');

        if ($hrJobPostingIds->isEmpty()) {
            return response()->json(['data' => [], 'links' => null, 'meta' => null, 'message' => 'Anda belum memiliki pekerjaan yang diposting atau tidak ada pelamar untuk pekerjaan Anda.']);
        }

        $query = JobApplication::with([
            'applicant:id,name,email,avatar_img_url', // Data pelamar yang dibutuhkan
            'jobPosting:id,title,company_name'      // Data pekerjaan yang dilamar
        ])
            ->whereIn('job_posting_id', $hrJobPostingIds); // Hanya lamaran untuk pekerjaan yang diposting HR ini

        // Opsional: filter berdasarkan job_posting_id spesifik jika diberikan dari frontend
        if ($request->filled('job_posting_id')) {
            $jobPostingId = $request->input('job_posting_id');
            // Pastikan job_posting_id ini memang milik HR tersebut
            if ($hrJobPostingIds->contains($jobPostingId)) {
                $query->where('job_posting_id', $jobPostingId);
            } else {
                // Jika HR mencoba mengakses pelamar untuk pekerjaan yang bukan miliknya
                return response()->json(['message' => 'Anda tidak memiliki akses ke pelamar untuk pekerjaan ini.'], 403);
            }
        }
        
        // Urutkan berdasarkan tanggal lamaran terbaru untuk setiap pekerjaan
        $applications = $query->latest('created_at')->paginate($request->input('limit', 15));

        return response()->json($applications);
    }

    /**
     * HR mengunduh CV pelamar.
     * Dipanggil dari route: GET /api/hr/applicants/{application}/download-cv
     */
    public function downloadCv(JobApplication $application) // Menggunakan Route Model Binding
    {
        $hrUser = Auth::user();

        // Otorisasi: Pastikan HR yang meminta adalah HR yang memposting pekerjaan terkait
        // dan HR tersebut sudah diapprove
        $jobPosting = $application->jobPosting; // Mengambil jobPosting dari relasi

        if (
            !$hrUser ||
            !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr()) ||
            !$jobPosting || // Pastikan jobPosting ada
            $jobPosting->posted_by !== $hrUser->id
        ) {
            Log::warning('Unauthorized CV download attempt.', [
                'hr_user_id' => $hrUser ? $hrUser->id : 'Guest/Unauthorized',
                'application_id' => $application->id,
                'job_posted_by' => $jobPosting ? $jobPosting->posted_by : 'N/A'
            ]);
            return response()->json(['message' => 'Unauthorized untuk mengakses CV ini.'], 403);
        }

        if (!$application->cv_path || !Storage::disk('public')->exists($application->cv_path)) {
            Log::error('CV file not found for application ID ' . $application->id . '. Path: ' . $application->cv_path);
            return response()->json(['message' => 'File CV tidak ditemukan.'], 404);
        }

        // Buat nama file download yang lebih informatif
        $applicantName = $application->applicant ? Str::slug($application->applicant->name, '_') : 'applicant_' . $application->user_id;
        $jobTitle = $application->jobPosting ? Str::slug($application->jobPosting->title, '_') : 'job_' . $application->job_posting_id;
        $originalExtension = pathinfo(Storage::disk('public')->path($application->cv_path), PATHINFO_EXTENSION);
        $downloadFileName = 'CV_' . $applicantName . '_' . $jobTitle . '.' . $originalExtension;

        Log::info('HR User ID ' . $hrUser->id . ' downloading CV for application ID ' . $application->id . '. Path: ' . $application->cv_path);
        return Storage::disk('public')->download($application->cv_path, $downloadFileName);
    }

    // --- METODE BARU UNTUK TERIMA LAMARAN ---
    /**
     * HR menerima lamaran pekerjaan (mengubah status).
     * Dipanggil dari route: POST /api/hr/applicants/{application}/accept
     */
    public function acceptApplication(Request $request, JobApplication $application)
    {
        $hrUser = Auth::user();
        $jobPosting = $application->jobPosting; 

        if (
            !$hrUser ||
            !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr()) || 
            !$jobPosting ||
            $jobPosting->posted_by !== $hrUser->id
        ) {
            Log::warning('Unauthorized application status update attempt (accept).', [
                'hr_user_id' => $hrUser ? $hrUser->id : 'Guest/Unauthorized',
                'application_id' => $application->id,
                'job_posted_by' => $jobPosting ? $jobPosting->posted_by : 'N/A'
            ]);
            return response()->json(['message' => 'Unauthorized untuk mengubah status lamaran ini.'], 403);
        }

        if ($application->status === 'pending' || $application->status === 'reviewed') {
            $application->status = 'shortlisted'; // Atau 'hired'
            $application->save();
            $application->load(['applicant:id,name,email,avatar_img_url', 'jobPosting:id,title,company_name']);
            
            Log::info('Application ID ' . $application->id . ' accepted (shortlisted) by HR ID ' . $hrUser->id);
            return response()->json([
                'message' => 'Lamaran berhasil diterima (status diubah ke Shortlisted).',
                'data' => $application
            ]);
        }

        Log::info('Application ID ' . $application->id . ' could not be accepted by HR ID ' . $hrUser->id . '. Current status: ' . $application->status);
        return response()->json([
            'message' => 'Lamaran tidak dapat diterima (mungkin statusnya bukan Pending atau Reviewed).',
            'data' => $application
        ], 400);
    }

    // --- METODE BARU UNTUK TOLAK LAMARAN ---
    /**
     * HR menolak lamaran pekerjaan (mengubah status).
     * Dipanggil dari route: POST /api/hr/applicants/{application}/reject
     */
    public function rejectApplication(Request $request, JobApplication $application)
    {
        $hrUser = Auth::user();
        $jobPosting = $application->jobPosting;

        if (
            !$hrUser ||
            !(method_exists($hrUser, 'isApprovedHr') && $hrUser->isApprovedHr()) ||
            !$jobPosting ||
            $jobPosting->posted_by !== $hrUser->id
        ) {
            Log::warning('Unauthorized application status update attempt (reject).', [
                'hr_user_id' => $hrUser ? $hrUser->id : 'Guest/Unauthorized',
                'application_id' => $application->id,
                'job_posted_by' => $jobPosting ? $jobPosting->posted_by : 'N/A'
            ]);
            return response()->json(['message' => 'Unauthorized untuk mengubah status lamaran ini.'], 403);
        }

        if ($application->status !== 'rejected' && $application->status !== 'hired') {
            $application->status = 'rejected';
            $application->save();
            $application->load(['applicant:id,name,email,avatar_img_url', 'jobPosting:id,title,company_name']);

            Log::info('Application ID ' . $application->id . ' rejected by HR ID ' . $hrUser->id);
            return response()->json([
                'message' => 'Lamaran berhasil ditolak.',
                'data' => $application
            ]);
        }
        
        Log::info('Application ID ' . $application->id . ' could not be rejected by HR ID ' . $hrUser->id . '. Current status: ' . $application->status);
        return response()->json([
            'message' => 'Lamaran tidak dapat ditolak (mungkin statusnya sudah final atau sudah ditolak).',
            'data' => $application
        ], 400);
    }
}