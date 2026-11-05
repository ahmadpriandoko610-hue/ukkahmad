<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skema awal sudah memuat tiga status yang dipakai aplikasi.
        // Tidak perlu ALTER TABLE khusus MySQL agar migrasi juga aman di SQLite.
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak ada perubahan skema yang perlu dikembalikan.
    }
};
