<?php

// app/Models/JobPosting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // Untuk format tanggal

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'company_name',
        'type',
        'salary_min',
        'salary_max',
        'location',
        'posted_by',
        'description',
        'requirements',
        'responsibilities',
        'benefits',
    ];

    /**
     * Relasi ke user (HR) yang memposting pekerjaan.
     */
    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Casts untuk tipe data.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];

    /**
     * Accessor untuk postedDate yang diformat.
     *
     * @return string
     */
    public function getPostedDateAttribute()
    {
        // Carbon::parse($this->created_at)->locale('id')->diffForHumans(); // Contoh: "1 minggu yang lalu"
        // atau format spesifik
        return Carbon::parse($this->created_at)->locale('id')->isoFormat('D MMMM YYYY, HH:mm');
    }
}
