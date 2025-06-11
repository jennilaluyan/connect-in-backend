<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'API is alive!';
});

Route::get('/buat-super-admin-disini-12345', function () {
    // Menggunakan firstOrCreate agar aman jika dijalankan lebih dari sekali
    $user = User::firstOrCreate(
        ['email' => 'superadmin@connect.in'], // Kunci untuk mencari user
        [
            'name' => 'Super Admin',
            'password' => Hash::make('PasswordSuperAdminRahasia'), // GANTI DENGAN PASSWORD YANG AMAN
            'role' => 'superadmin'
        ]
    );

    if ($user->wasRecentlyCreated) {
        return 'Akun Super Admin berhasil DIBUAT! Hapus route ini dari kode Anda sekarang.';
    } else {
        return 'Akun Super Admin sudah ADA dari sebelumnya. Hapus route ini dari kode Anda sekarang.';
    }
});
