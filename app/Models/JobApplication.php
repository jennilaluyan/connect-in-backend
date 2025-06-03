<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // Import Storage facade

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'user_id',
        'cv_path',
        'status',
        'cover_letter',
    ];

    /**
     * Relasi ke JobPosting.
     */
    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * Relasi ke User (pelamar).
     */
    public function applicant()
    {
        // Pastikan User model ada di namespace App\Models\User
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor untuk mendapatkan URL CV yang bisa diakses publik.
     *
     * @return string|null
     */
    public function getCvUrlAttribute(): ?string
    {
        // cv_path akan menyimpan path relatif dari root disk 'public'
        // contoh: 'cvs/cv_user1_job2_timestamp.pdf'
        if ($this->cv_path && Storage::disk('public')->exists($this->cv_path)) {
            return Storage::url($this->cv_path);
        }
        return null;
    }

    /**
     * Tambahkan cv_url ke output JSON/array secara default.
     */
    protected $appends = ['cv_url'];
}
