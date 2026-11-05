<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table): void {
            $table->timestamp('repaired_at')->nullable();
        });

        DB::table('complaints')
            ->where('status_repair', 'Sudah Diperbaiki')
            ->update(['repaired_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table): void {
            $table->dropColumn('repaired_at');
        });
    }
};
