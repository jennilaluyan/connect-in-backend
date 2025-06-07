<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobApplication;
use App\Models\JobPosting;

class UserJobController extends Controller
{
    /**
     * Menampilkan daftar pekerjaan di mana user diterima (status 'hired').
     * Metode ini bisa tetap ada jika diperlukan untuk fitur lain.
     */
    public function myJobs(Request $request)
    {
        $user = Auth::user();

        $hiredJobIds = JobApplication::where('user_id', $user->id)
            ->where('status', 'hired')
            ->pluck('job_posting_id');

        if ($hiredJobIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $myJobs = JobPosting::with('poster:id,name')
            ->whereIn('id', $hiredJobIds)
            ->latest('updated_at')
            ->paginate(10);

        return response()->json($myJobs);
    }

    /**
     * [METODE BARU] Menampilkan semua riwayat lamaran dari user yang login.
     * Metode ini akan digunakan oleh halaman "Pekerjaan Saya" yang baru.
     */
    public function myApplications(Request $request)
    {
        $user = Auth::user();

        // Ambil semua lamaran milik user, beserta detail pekerjaan yang terkait.
        // Eager load memastikan kita tidak membuat N+1 query.
        $applications = JobApplication::where('user_id', $user->id)
            ->with('jobPosting:id,title,company_name,type,company_logo_url') // Ambil hanya data yang relevan
            ->latest() // Urutkan berdasarkan yang paling baru dilamar
            ->paginate(20); // Anda bisa sesuaikan limit paginasi

        return response()->json($applications);
    }
}