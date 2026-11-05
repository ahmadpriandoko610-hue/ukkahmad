<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'pic', 'guru', 'organisasi'])
                ->default('guru')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'organisasi')
            ->update(['role' => 'guru']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'pic', 'guru'])
                ->default('guru')
                ->change();
        });
    }
};
