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
        if (Schema::hasTable('events') && !Schema::hasColumn('events', 'group_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('group_id', 64)->nullable()->index()->after('room_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('events') || !Schema::hasColumn('events', 'group_id')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('events'))
            ->filter(fn (array $index): bool => in_array('group_id', $index['columns'] ?? [], true) && !($index['primary'] ?? false))
            ->pluck('name')
            ->filter()
            ->values();

        if ($indexes->isNotEmpty()) {
            Schema::table('events', function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $indexName) {
                    $table->dropIndex($indexName);
                }
            });
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};

