<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User; // Pastikan model User diimpor
use Illuminate\Support\Facades\Hash; // Impor Hash untuk password
use Illuminate\Support\Str; // Impor Str untuk password acak (opsional)

class CreateSuperAdmin extends Command
{
    /**
     * Nama dan signature dari console command.
     * 'app:create-super-admin' adalah perintah yang akan Anda ketik di terminal.
     *
     * @var string
     */
    protected $signature = 'app:create-super-admin';

    /**
     * Deskripsi dari console command.
     *
     * @var string
     */
    protected $description = 'Membuat akun Super Admin pertama untuk aplikasi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai proses pembuatan Super Admin...');

        // Cek apakah Super Admin sudah ada untuk mencegah duplikasi
        if (User::where('role', 'superadmin')->exists()) {
            $this->error('Akun Super Admin sudah ada. Perintah dibatalkan untuk mencegah duplikasi.');
            return 1; // Mengembalikan status error
        }

        // Tentukan data Super Admin di sini
        $superAdminData = [
            'name' => 'Super Admin Utama',
            'email' => 'superadmin@connect.in', // Ganti dengan email yang Anda inginkan
            'password' => 'PasswordSuperAdminRahasia123', // Ganti dengan password yang kuat dan aman
            'role' => 'superadmin',
            'is_hr_approved_by_sa' => true, // Super admin otomatis terapprove
            'email_verified_at' => now(),
        ];

        // Konfirmasi sebelum membuat
        if ($this->confirm('Anda akan membuat Super Admin dengan email: ' . $superAdminData['email'] . '. Lanjutkan?')) {
            try {
                // Buat user di database
                User::create([
                    'name' => $superAdminData['name'],
                    'email' => $superAdminData['email'],
                    'password' => Hash::make($superAdminData['password']), // SANGAT PENTING: Selalu hash password
                    'role' => $superAdminData['role'],
                    'is_hr_approved_by_sa' => $superAdminData['is_hr_approved_by_sa'],
                    'email_verified_at' => $superAdminData['email_verified_at'],
                ]);

                // Tampilkan pesan sukses
                $this->info('=============================================');
                $this->info('Akun Super Admin berhasil dibuat!');
                $this->line('Email: ' . $superAdminData['email']);
                $this->line('Password: ' . $superAdminData['password']); // Ditampilkan untuk Anda catat
                $this->info('=============================================');
                $this->warn('Harap catat password di atas dan segera ganti setelah login pertama kali.');
            } catch (\Exception $e) {
                // Tangkap error jika terjadi
                $this->error('Terjadi kesalahan saat membuat Super Admin: ' . $e->getMessage());
                return 1;
            }
        } else {
            $this->info('Proses pembuatan Super Admin dibatalkan.');
        }

        return 0; // Mengembalikan status sukses
    }
}
