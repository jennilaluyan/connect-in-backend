<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User yang melamar
            $table->string('cv_path'); // Path ke file CV yang diunggah (relatif dari storage/app/public)
            $table->enum('status', ['pending', 'reviewed', 'shortlisted', 'rejected', 'hired'])->default('pending');
            $table->text('cover_letter')->nullable(); // Opsional, jika ada surat lamaran
            $table->timestamps(); // application_date (created_at) dan updated_at

            $table->unique(['job_posting_id', 'user_id']); // Seorang user hanya bisa melamar satu kali untuk pekerjaan yang sama
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
