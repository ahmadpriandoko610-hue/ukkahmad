<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_in')->default(false)->after('phone');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
            $table->string('whatsapp_opt_in_source', 50)->nullable()->after('whatsapp_opt_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_opt_in',
                'whatsapp_opt_in_at',
                'whatsapp_opt_in_source',
            ]);
        });
    }
};

