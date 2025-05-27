<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response; // Pastikan ini di-import untuk Response::HTTP_UNAUTHORIZED, dll.

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Array of allowed roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            // Jika pengguna belum login sama sekali
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();

        // Periksa apakah peran pengguna ada dalam daftar peran yang diizinkan
        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Forbidden. Anda tidak memiliki peran yang diizinkan untuk mengakses sumber daya ini.'], Response::HTTP_FORBIDDEN);
        }

        // Pemeriksaan tambahan khusus jika peran yang diharapkan adalah 'hr'
        // Pastikan HR tersebut sudah di-approve oleh Super Admin
        if ($user->role === 'hr' && in_array('hr', $roles)) { // Cek jika 'hr' adalah salah satu peran yang diminta
            if (!$user->is_hr_approved_by_sa) {
                return response()->json(['message' => 'Forbidden. Akun HR Anda belum disetujui oleh Super Admin.'], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
