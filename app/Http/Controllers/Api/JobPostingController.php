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
        Log::info('JobPostingController@index accessed. Path: ' . $request->path());
        if ($currentUser) {
            Log::info('User authenticated. ID: ' . $currentUser->id . ', Role: ' . $currentUser->role);
        } else {
            Log::info('Request from unauthenticated/guest user.');
        }

        $query = JobPosting::with('poster:id,name,avatar_img');

        // --- LOGIKA BARU YANG LEBIH AMAN ---
        if ($request->is('api/hr/*')) {
            Log::info('HR-specific path detected.');

            // 1. Cek dulu apakah user login
            if (!$currentUser) {
                Log::warning('[HR Path] Access attempt by guest. Denying.');
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // 2. Baru cek rolenya, karena kita sudah yakin $currentUser tidak null
            if (!$currentUser->isApprovedHr()) {
                Log::warning('[HR Path] User is not an approved HR. Denying.', ['user_id' => $currentUser->id]);
                return response()->json(['data' => [], 'message' => 'Akses ditolak. Hanya untuk HR yang sudah diapprove.'], 403);
            }

            Log::info('[HR Path] Applying filter for HR ID: ' . $currentUser->id);
            $query->where('posted_by', $currentUser->id);
        } else {
            // --- INI ADALAH LOGIKA UNTUK PUBLIC DASHBOARD ---
            Log::info('[Public Path] Fetching all job postings for public dashboard.');
            if ($request->has('search')) {
                // ... (logika search tetap sama) ...
            }
        }
        // --- AKHIR DARI LOGIKA BARU ---

        try {
            $jobPostings = $query->latest()->paginate($request->input('limit', 10));
            Log::info('Successfully retrieved ' . $jobPostings->count() . ' job postings.');
            return response()->json($jobPostings);
        } catch (\Exception $e) {
            // Tangkap jika ada error saat query ke database
            Log::error('DATABASE QUERY FAILED in JobPostingController@index: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil data dari server.', 'error' => $e->getMessage()], 500);
        }
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
            $jobPosting->load('poster:id,name,avatar_img');
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
        $jobPosting->load('poster:id,name,email,avatar_img');
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
            $jobPosting->load('poster:id,name,avatar_img');
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
