<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tambahkan kode ini
        $databaseUrl = env('DATABASE_URL');
        if ($databaseUrl) {
            // Ganti skema 'postgres://' menjadi 'pgsql://' yang dipahami Laravel
            $parsedUrl = str_replace('postgres://', 'pgsql://', $databaseUrl);
            config(['database.connections.pgsql.url' => $parsedUrl]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
