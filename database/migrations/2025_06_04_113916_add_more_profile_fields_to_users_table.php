<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('headline')->nullable()->after('avatar_img');
            $table->string('company_name')->nullable()->after('headline');
            $table->string('city')->nullable()->after('company_name');
            $table->string('province')->nullable()->after('city');
            // Tambahkan field lain jika perlu, misalnya nomor telepon, bio singkat, dll.
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['headline', 'company_name', 'city', 'province']);
        });
    }
};
