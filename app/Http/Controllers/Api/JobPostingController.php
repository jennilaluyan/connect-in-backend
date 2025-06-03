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
        Log::info('Authenticated User ID: ' . ($currentUserId ?: 'Guest/None'));
        if ($currentUser) {
            Log::info('Authenticated User Role: ' . $currentUser->role);
            Log::info('Authenticated User isApprovedHr(): ' . (method_exists($currentUser, 'isApprovedHr') && $currentUser->isApprovedHr() ? 'Yes' : 'No'));
        }

        // Tidak perlu lagi eager load company_logo_url jika sudah dihapus dari model
        $query = JobPosting::with('poster:id,name,avatar_img_url');

        $expectedHrRouteName = 'hr.job-postings.my-index';
        $actualRouteName = $request->route()->getName();

        Log::info('Expected HR Route Name for filter: ' . $expectedHrRouteName);
        Log::info('Actual Route Name from request: ' . $actualRouteName);

        $isHrMyPostingsRoute = $actualRouteName === $expectedHrRouteName;
        Log::info('Is HR MyPostings Route? ' . ($isHrMyPostingsRoute ? 'Yes' : 'No'));

        if ($isHrMyPostingsRoute) {
            if ($currentUser && method_exists($currentUser, 'isApprovedHr') && $currentUser->isApprovedHr()) {
                Log::info('[HR MyPostings Route Matched] Applying filter: posted_by = ' . $currentUserId);
                $query->where('posted_by', $currentUserId);
            } else {
                Log::warning('[HR MyPostings Route Matched] User not an approved HR or not properly authenticated.', [
                    'user_id' => $currentUserId,
                    'is_approved_hr' => ($currentUser && method_exists($currentUser, 'isApprovedHr')) ? $currentUser->isApprovedHr() : 'N/A'
                ]);
                return response()->json(['data' => [], 'message' => 'Akses ditolak atau akun HR Anda belum diapprove.'], 403);
            }
        } elseif ($request->has('my_postings') && $currentUser && method_exists($currentUser, 'isApprovedHr') && $currentUser->isApprovedHr()) {
            Log::info('[Public Route with my_postings flag] Applying filter: posted_by = ' . $currentUserId);
            $query->where('posted_by', $currentUserId);
        } else {
            Log::info('[Public Route] No user-specific posted_by filter applied.');
            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                        ->orWhere('company_name', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }
        }

        try {
            Log::debug('SQL Query (before paginate): ' . $query->toSql());
            Log::debug('SQL Bindings (before paginate): ', $query->getBindings());
        } catch (\Exception $e) {
            Log::error('Error getting SQL for logging: ' . $e->getMessage());
        }

        $jobPostings = $query->latest()->paginate($request->input('limit', 10));
        Log::info('Number of job postings retrieved: ' . $jobPostings->count() . ' for page ' . $jobPostings->currentPage());

        $jobPostings->getCollection()->transform(function ($job) {
            // Tidak ada lagi transformasi untuk company_logo_url
            if (!$job->offsetExists('posted_date_formatted') && method_exists($job, 'getPostedDateAttribute')) {
                $job->posted_date_formatted = $job->getPostedDateAttribute();
            }
            return $job;
        });

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
