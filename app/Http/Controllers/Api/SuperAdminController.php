<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SuperAdminController extends Controller
{
    // Middleware bisa diterapkan di constructor atau di file rute

    public function getPendingHrApplications()
    {
        // Hanya Super Admin yang boleh akses (akan diproteksi oleh middleware)
        $pendingHrs = User::where('role', 'hr')
            ->where('is_hr_approved_by_sa', false)
            ->get();
        return response()->json($pendingHrs);
    }

    public function approveHrApplication(User $user) // Menggunakan Route Model Binding
    {
        if ($user->role !== 'hr' || $user->is_hr_approved_by_sa) {
            return response()->json(['message' => 'User ini bukan aplikasi HR yang valid atau sudah diapprove.'], 400);
        }
        $user->is_hr_approved_by_sa = true;
        $user->save();
        return response()->json(['message' => 'Aplikasi HR untuk ' . $user->name . ' telah disetujui.']);
    }

    public function rejectHrApplication(User $user)
    {
        if ($user->role !== 'hr' || $user->is_hr_approved_by_sa) {
            return response()->json(['message' => 'User ini bukan aplikasi HR yang valid untuk direject atau sudah diapprove.'], 400);
        }
        // Opsi: hapus user, atau ubah role jadi 'user' dan tandai rejected
        $userName = $user->name;
        $user->delete(); // Contoh: langsung hapus
        return response()->json(['message' => 'Aplikasi HR untuk ' . $userName . ' telah ditolak dan dihapus.']);
    }

    public function deleteUser(User $user)
    {
        if ($user->role === 'superadmin') { // Super admin tidak bisa menghapus dirinya sendiri atau SA lain
            return response()->json(['message' => 'Super Admin tidak dapat dihapus melalui endpoint ini.'], 403);
        }
        $userName = $user->name;
        $user->delete();
        return response()->json(['message' => 'User ' . $userName . ' telah dihapus.']);
    }

    public function getAllUsers()
    {
        $users = User::all();
        return response()->json($users);
    }
}
