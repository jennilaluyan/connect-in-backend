<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => true, 'message' => 'Connect-In API is online and healthy!']);
});

Route::get('/test-sqlite', function () {
    try {
        DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();
        return "Koneksi ke SQLite berhasil! Database file: " . $dbName;
    } catch (\Exception $e) {
        return "Gagal terkoneksi ke SQLite: " . $e->getMessage();
    }
});
