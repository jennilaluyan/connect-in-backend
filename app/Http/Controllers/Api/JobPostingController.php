<?php
// app/Http/Controllers/Api/JobPostingController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Models\User; // Pastikan User model di-import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // Penting untuk debugging

class JobPostingController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $currentUserId = Auth::id();

        Log::info('JobPostingController@index accessed.');
        Log::info('Request Path: ' . $request->path()); // Log path untuk debug

        // Mulai query dasar
        $query = JobPosting::with('poster:id,name,avatar_img_url');

        // --- LOGIKA BARU YANG LEBIH ROBUST ---
        // Cek apakah request datang dari path HR
        if ($request->is('api/hr/*')) {
            Log::info('HR-specific path detected.');
            // Jika user tidak terautentikasi atau bukan HR yang disetujui, tolak akses.
            if (!$currentUser || !$currentUser->isApprovedHr()) {
                Log::warning('[HR Path] User not an approved HR or not properly authenticated.', [
                    'user_id' => $currentUserId,
                    'role' => $currentUser ? $currentUser->role : 'Guest'
                ]);
                return response()->json(['data' => [], 'message' => 'Akses ditolak atau akun HR Anda belum diapprove.'], 403);
            }
            // Filter postingan berdasarkan ID HR yang login
            Log::info('[HR Path] Applying filter: posted_by = ' . $currentUserId);
            $query->where('posted_by', $currentUserId);
        } else {
            // --- INI ADALAH LOGIKA UNTUK PUBLIC DASHBOARD ---
            Log::info('[Public Path] Fetching all job postings for public dashboard.');
            // Di sini, kita tidak menerapkan filter posted_by
            // Tambahkan filter pencarian jika ada
            if ($request->has('search')) {
                $searchTerm = $request->search;
                Log::info('Applying search filter with term: ' . $searchTerm);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company_name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('description', 'like', '%' . $searchTerm . '%')
                        ->orWhere('location', 'like', '%' . $searchTerm . '%');
                });
            }
        }
        // --- AKHIR DARI LOGIKA BARU ---

        try {
            Log::debug('SQL Query (before paginate): ' . $query->toSql(), $query->getBindings());
        } catch (\Exception $e) {
            Log::error('Error getting SQL for logging: ' . $e->getMessage());
        }

        $jobPostings = $query->latest()->paginate($request->input('limit', 10));
        Log::info('Number of job postings retrieved: ' . $jobPostings->count() . ' for page ' . $jobPostings->currentPage());

        // Transformasi koleksi tidak diperlukan lagi jika Anda sudah mengatur accessor di model
        // Namun, jika ada, pastikan tidak ada error di dalamnya.

        return response()->json($jobPostings);
    }

    public function store(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser || !(method_exists($currentUser, 'isApprovedHr') && $currentUser->isApprovedHr())) {
            return response()->json(['message' => 'Unauthorized. Hanya HR yang sudah diapprove yang dapat memposting pekerjaan.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'type' => 'required|in:part-time,magang,full-time,kontrak',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'requirements' => 'required|string',
            'responsibilities' => 'required|string',
            'salary_min' => 'nullable|numeric|gte:0|required_with:salary_max|lte:salary_max',
            'salary_max' => 'nullable|numeric|gte:salary_min',
            'benefits' => 'nullable|string',
            // Validasi 'company_logo' dihapus
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all(); // Tidak perlu except company_logo lagi
        $data['posted_by'] = $currentUser->id;

        // Logika penyimpanan file logo dihapus
        // if ($request->hasFile('company_logo')) { ... }

        try {
            $jobPosting = JobPosting::create($data);
            $jobPosting->load('poster:id,name,avatar_img_url');
            return response()->json([
                'message' => 'Lowongan pekerjaan berhasil diposting!',
                'data' => $jobPosting
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error saat membuat JobPosting di database (store): ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan data lowongan ke database.'], 500);
        }
    }

    public function show(JobPosting $jobPosting)
    {
        $jobPosting->load('poster:id,name,email,avatar_img_url');
        // Tidak ada lagi company_logo_url yang perlu di-set secara manual
        return response()->json($jobPosting);
    }

    public function update(Request $request, JobPosting $jobPosting)
    {
        $currentUser = Auth::user();
        // ... (Logika otorisasi tetap sama) ...
        $isOwner = $currentUser && (method_exists($currentUser, 'isApprovedHr') && $currentUser->isApprovedHr()) && $currentUser->id === $jobPosting->posted_by;
        $isSuperAdmin = $currentUser && (method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin());

        if (!$isOwner && !$isSuperAdmin) {
            return response()->json(['message' => 'Unauthorized untuk memperbarui lowongan ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'company_name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:part-time,magang,full-time,kontrak',
            'location' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'requirements' => 'sometimes|required|string',
            'responsibilities' => 'sometimes|required|string',
            'salary_min' => 'nullable|numeric|gte:0|required_with:salary_max|lte:salary_max',
            'salary_max' => 'nullable|numeric|gte:salary_min',
            'benefits' => 'nullable|string',
            // Validasi 'company_logo' dihapus
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except(['_method', 'company_logo', 'remove_company_logo']); // Hapus field terkait logo

        // Logika update dan penghapusan file logo dihapus
        // if ($request->hasFile('company_logo')) { ... }
        // elseif ($request->input('remove_company_logo') === 'true' && $jobPosting->company_logo) { ... }

        try {
            $jobPosting->update($data);
            $jobPosting->load('poster:id,name,avatar_img_url');
            return response()->json([
                'message' => 'Lowongan pekerjaan berhasil diperbarui!',
                'data' => $jobPosting
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat update JobPosting di database (update): ' . $e->getMessage());
            return response()->json(['message' => 'Gagal memperbarui data lowongan di database.'], 500);
        }
    }

    public function destroy(JobPosting $jobPosting)
    {
        $currentUser = Auth::user();
        // ... (Logika otorisasi tetap sama) ...
        $isOwner = $currentUser && (method_exists($currentUser, 'isApprovedHr') && $currentUser->isApprovedHr()) && $currentUser->id === $jobPosting->posted_by;
        $isSuperAdmin = $currentUser && (method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin());

        if (!$isOwner && !$isSuperAdmin) {
            return response()->json(['message' => 'Unauthorized untuk menghapus lowongan ini.'], 403);
        }

        // Logika penghapusan file logo dihapus
        // if ($jobPosting->company_logo) { ... }

        try {
            $jobPosting->delete();
            return response()->json(['message' => 'Lowongan pekerjaan berhasil dihapus!'], 200);
        } catch (\Exception $e) {
            Log::error('Error saat menghapus JobPosting dari database (destroy): ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus lowongan pekerjaan dari database.'], 500);
        }
    }
}
