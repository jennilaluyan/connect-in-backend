<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|string|in:user,hr', // Ubah validasi untuk menerima 'role'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil role dari request, default ke 'user' jika tidak disediakan atau tidak valid
        // Frontend Anda akan mengirim 'user' atau 'hr'
        $roleToRegister = $request->input('role', 'user');

        // Pastikan jika role yang tidak diinginkan dikirim, default ke 'user'
        if (!in_array($roleToRegister, ['user', 'hr'])) {
            $roleToRegister = 'user';
        }

        $isHrApproved = false; // Default value

        if ($roleToRegister === 'hr') {
            $isHrApproved = false; // HR baru selalu membutuhkan approval, jadi is_hr_approved_by_sa = false
        } else { // Untuk role 'user'
            // Untuk user biasa, field is_hr_approved_by_sa tidak terlalu relevan.
            // Kita bisa set ke true agar tidak ada ambiguitas, atau null jika kolomnya nullable.
            // Mengingat kolom Anda default(false) dan di-cast ke boolean, set true untuk user biasa
            // agar tidak dianggap pending HR secara tidak sengaja di logika lain.
            // Namun, field ini seharusnya hanya signifikan ketika role adalah 'hr'.
            // Untuk konsistensi dengan logika login, user biasa tidak perlu status approval ini.
            // Jika kolom `is_hr_approved_by_sa` tidak nullable dan defaultnya false,
            // kita set true agar tidak bentrok dengan kondisi pending HR.
            $isHrApproved = true; // User biasa tidak perlu approval HR
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $roleToRegister,
            'is_hr_approved_by_sa' => $isHrApproved,
        ]);

        $message = 'Registrasi berhasil.';
        if ($roleToRegister === 'hr') {
            $message .= ' Akun HR Anda sedang menunggu persetujuan Super Admin.';
        }

        return response()->json([
            'message' => $message,
            'user' => $user // Mengembalikan data user yang baru dibuat
        ], 201);
    }

    /**
     * Login a user.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            // Mengembalikan error validasi dengan struktur yang lebih konsisten
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $user = Auth::user(); // Mengambil user yang sudah terautentikasi

        // PENTING: Cek untuk HR yang belum diapprove
        // Kondisi ini akan berjalan dengan benar jika saat registrasi role 'hr' disimpan dengan benar
        // dan is_hr_approved_by_sa diatur ke false.
        if ($user->role === 'hr' && !$user->is_hr_approved_by_sa) {
            // Logout user yang mencoba login ini karena mereka belum diapprove
            // $request->user()->currentAccessToken()->delete(); // Jika sudah ada token (seharusnya belum di tahap ini jika Auth::attempt baru saja)
            Auth::logout(); // Ini akan membersihkan sesi guard web jika ada, tapi untuk API token mungkin perlu tindakan lain jika token sudah terbuat.
            // Namun, karena kita cek sebelum membuat token baru, ini seharusnya aman.

            return response()->json([
                'message' => 'Akun HR Anda belum disetujui oleh Super Admin. Silakan hubungi Super Admin.',
                // Frontend bisa menggunakan informasi ini untuk menampilkan pesan yang sesuai
                // dan mengarahkan ke halaman /pending-approval jika perlu.
                // Namun, dengan status 403, frontend seharusnya sudah tahu ini gagal.
                // Kita bisa tambahkan flag agar frontend lebih mudah mengarahkan.
                'is_pending_hr' => true,
                'user_details' => [ // Kirim detail minimal jika perlu ditampilkan di halaman pending
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_hr_approved_by_sa' => $user->is_hr_approved_by_sa
                ]
            ], 403); // Forbidden
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Memastikan user yang dikembalikan memiliki semua atribut yang dibutuhkan frontend
        // Model User Anda sudah mengatur $fillable dan $casts dengan benar.
        // $user->refresh(); // Opsional, untuk memastikan data terbaru jika ada perubahan setelah Auth::user()

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            // Menggunakan $user yang didapat dari Auth::user() karena sudah terautentikasi
            // dan instance model lengkap.
            'user' => $user,
        ]);
    }

    /**
     * Logout a user.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) { // Pastikan user terautentikasi sebelum mencoba logout
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }
        return response()->json(['message' => 'No authenticated user to logout.'], 401); // Atau respons lain yang sesuai
    }
}
