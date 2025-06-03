<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage; // Ditambahkan untuk accessor avatar

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // Kemungkinan nilai: 'user', 'hr', 'superadmin'
        'is_hr_approved_by_sa',
        'avatar_img', // Path ke file avatar, contoh: 'avatars/namafile.jpg'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_hr_approved_by_sa' => 'boolean',
        ];
    }

    /**
     * Mendapatkan URL lengkap untuk gambar avatar pengguna.
     *
     * @return string|null
     */
    public function getAvatarImgUrlAttribute(): ?string
    {
        if ($this->avatar_img && Storage::disk('public')->exists($this->avatar_img)) {
            // Asumsi $this->avatar_img menyimpan path relatif dari root 'storage/app/public/',
            // contoh: 'avatars/user_1.jpg'
            return Storage::url($this->avatar_img);
        }
        // URL ke avatar default jika tidak ada atau file tidak ditemukan
        // Anda bisa menggunakan layanan seperti ui-avatars.com atau path ke gambar default lokal
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random&color=fff&size=128&font-size=0.33';
    }

    /**
     * Tambahkan avatar_img_url ke output JSON/array secara default.
     *
     * @var array
     */
    protected $appends = [
        'avatar_img_url' // Ini akan membuat accessor getAvatarImgUrlAttribute() dipanggil otomatis
        // saat model di-serialize ke JSON atau array.
    ];

    /**
     * Mendapatkan semua lowongan pekerjaan yang diposting oleh user (jika user adalah HR).
     */
    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class, 'posted_by');
    }

    /**
     * Memeriksa apakah pengguna memiliki peran tertentu.
     *
     * @param  string  $roleName
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName;
    }

    /**
     * Memeriksa apakah pengguna adalah HR yang sudah diapprove.
     *
     * @return bool
     */
    public function isApprovedHr(): bool
    {
        return $this->role === 'hr' && $this->is_hr_approved_by_sa === true;
    }

    /**
     * Memeriksa apakah pengguna adalah Super Admin.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }
}
