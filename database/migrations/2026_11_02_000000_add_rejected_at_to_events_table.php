<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
        });

        DB::table('events')
            ->where('status', 'Ditolak')
            ->whereNull('rejected_at')
            ->update(['rejected_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
