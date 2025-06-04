<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Pastikan Log di-import
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
// Rule tidak lagi dibutuhkan karena email tidak diupdate dari sini
// use Illuminate\Validation\Rule; 

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }
        // Model User akan otomatis menyertakan 'avatar_img_url' dan 'role_name' karena ada di $appends
        return response()->json($user);
    }

    /**
     * Update the authenticated user's profile.
     * Menggunakan metode POST karena frontend mengirim FormData (terutama untuk file).
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        Log::info('BE: --- Profile Update Request Received ---'); // Logging awal
        Log::info('BE: Request data (excluding file):', $request->except('avatar_img'));


        $rules = [
            'name' => 'required|string|max:255',
            // Email tidak divalidasi untuk diubah dari sini
            'headline' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'avatar_img' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ];

        // Aturan validasi khusus untuk HR (company_name)
        if ($user->role === 'hr') {
            $rules['company_name'] = 'nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules, [
            'name.required' => 'Nama lengkap (gabungan nama depan dan belakang) tidak boleh kosong.',
            'avatar_img.image' => 'File yang diunggah harus berupa gambar.',
            'avatar_img.mimes' => 'Format gambar harus jpeg, png, atau jpg.',
            'avatar_img.max' => 'Ukuran gambar tidak boleh melebihi 2MB.',
        ]);

        if ($validator->fails()) {
            Log::warning('BE: Validation failed:', $validator->errors()->toArray()); // Logging validasi gagal
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil field yang diizinkan untuk diupdate.
        // Email tidak termasuk karena tidak diizinkan diubah dari form ini.
        $dataToUpdate = $request->only(['name', 'headline', 'city', 'province']);

        if ($user->role === 'hr') {
            if ($request->has('company_name')) {
                $dataToUpdate['company_name'] = $request->input('company_name');
            }
        } else {
            if (array_key_exists('company_name', $dataToUpdate)) {
                unset($dataToUpdate['company_name']);
            }
        }

        // Handle upload avatar_img jika ada file baru
        if ($request->hasFile('avatar_img')) {
            Log::info('BE: avatar_img file IS PRESENT in request.'); // Logging file ada
            $file = $request->file('avatar_img');

            if ($file->isValid()) {
                Log::info('BE: File is valid. Original Name: ' . $file->getClientOriginalName() . ', Size: ' . $file->getSize());

                // Hapus avatar lama jika ada dan bukan merupakan path ke asset default
                if ($user->avatar_img && Storage::disk('public')->exists($user->avatar_img)) {
                    if (!str_starts_with($user->avatar_img, 'assets/')) { // Contoh: 'assets/Default.jpg'
                        Storage::disk('public')->delete($user->avatar_img);
                        Log::info('BE: Old avatar deleted: ' . $user->avatar_img);
                    }
                }
                // Simpan avatar baru ke 'storage/app/public/avatars'
                try {
                    $path = $file->store('avatars', 'public');
                    $cleanedPath = trim(str_replace("\u{200B}", "", $path), '"'); // TAMBAHKAN trim('"') untuk menghapus kutip di awal/akhir path
                    Log::info('BE: New avatar stored. Original path: "' . $path . '", Cleaned path for DB: "' . $cleanedPath . '"');
                    $dataToUpdate['avatar_img'] = $cleanedPath;
                } catch (\Exception $e) {
                    Log::error('BE: Error storing avatar: ' . $e->getMessage());
                    return response()->json(['message' => 'Gagal menyimpan file gambar di server.', 'error' => $e->getMessage()], 500);
                }
            } else {
                Log::warning('BE: avatar_img file is present but NOT valid.');
            }
        } else {
            Log::info('BE: avatar_img file IS NOT PRESENT in request.');
        }

        $user->update($dataToUpdate);
        Log::info('BE: User model updated in DB with data:', $dataToUpdate);


        $user->refresh(); // Muat ulang model user untuk mendapatkan data terbaru (termasuk avatar_img_url)
        Log::info('BE: User model refreshed. Value of avatar_img from DB: ' . $user->avatar_img);
        Log::info('BE: Accessor getAvatarImgUrlAttribute will be called. Generated URL: ' . $user->avatar_img_url);


        return response()->json([
            'message' => 'Profil berhasil diperbarui!',
            'user' => $user // Kirim kembali data user yang sudah terupdate
        ]);
    }
}
