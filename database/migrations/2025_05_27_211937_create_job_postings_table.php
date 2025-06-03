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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // $table->string('company_logo')->nullable(); // Dihapus
            $table->string('company_name');
            $table->enum('type', ['part-time', 'magang', 'full-time', 'kontrak']);
            $table->decimal('salary_min', 15, 2)->nullable();
            $table->decimal('salary_max', 15, 2)->nullable();
            $table->string('location');
            $table->unsignedBigInteger('posted_by');
            $table->text('description');
            $table->text('requirements');
            $table->text('responsibilities');
            $table->text('benefits')->nullable();
            $table->timestamps();

            $table->foreign('posted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // Jika Anda perlu rollback, pastikan kolom ada sebelum drop
            // if (Schema::hasColumn('job_postings', 'company_logo')) {
            //     $table->dropColumn('company_logo');
            // }
        });
        Schema::dropIfExists('job_postings');
    }
};
