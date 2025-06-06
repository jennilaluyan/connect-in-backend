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
}