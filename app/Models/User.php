<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_hr_approved_by_sa',
        'avatar_img',
        'headline',       // Ditambahkan
        'company_name',   // Ditambahkan
        'city',           // Ditambahkan
        'province',       // Ditambahkan
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_hr_approved_by_sa' => 'boolean',
        ];
    }

    // app/Models/User.php
    public function getAvatarImgUrlAttribute(): ?string
    {
        if ($this->avatar_img && Storage::disk('public')->exists($this->avatar_img)) {
            // Storage::url() akan menghasilkan path relatif seperti /storage/avatars/file.jpg
            // Laravel secara otomatis akan mengubahnya menjadi URL absolut saat serialisasi ke JSON jika APP_URL benar
            $urlPath = Storage::url($this->avatar_img);
            // Pastikan tidak ada tanda kutip yang ditambahkan di sini secara manual.
            // Jika $this->avatar_img sendiri mengandung kutip, itu masalah di penyimpanan.
            return $urlPath;
        }
        return asset('assets/Default.jpg');
    }

    // Untuk memastikan role_name juga ada saat user di-serialize
    public function getRoleNameAttribute(): string
    {
        switch ($this->role) {
            case 'superadmin':
                return 'Super Administrator';
            case 'hr':
                return 'HR Department';
            case 'user':
                return 'User';
            default:
                return ucfirst($this->role);
        }
    }


    protected $appends = [
        'avatar_img_url',
        'role_name', // Tambahkan ini jika ingin role_name selalu ada di output JSON
    ];

    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class, 'posted_by');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName;
    }

    public function isApprovedHr(): bool
    {
        return $this->role === 'hr' && $this->is_hr_approved_by_sa === true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }
}
