<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            $addMoveVersion = !Schema::hasColumn('events', 'room_move_version');
            $addTransitionId = !Schema::hasColumn('events', 'last_room_move_transition_id');

            if ($addMoveVersion || $addTransitionId) {
                Schema::table('events', function (Blueprint $table) use ($addMoveVersion, $addTransitionId) {
                    if ($addMoveVersion) {
                        $table->unsignedInteger('room_move_version')
                            ->default(0)
                            ->after('move_reason');
                    }

                    if ($addTransitionId) {
                        $table->string('last_room_move_transition_id', 64)
                            ->nullable()
                            ->after($addMoveVersion ? 'room_move_version' : 'move_reason');
                    }
                });
            }
        }

        if (Schema::hasTable('whatsapp_notification_logs')) {
            $addLogMoveVersion = !Schema::hasColumn('whatsapp_notification_logs', 'room_move_version');
            $addLogTransitionId = !Schema::hasColumn('whatsapp_notification_logs', 'transition_id');
            $addReconciledAt = !Schema::hasColumn('whatsapp_notification_logs', 'reconciled_at');
            $addReconciliationNote = !Schema::hasColumn('whatsapp_notification_logs', 'reconciliation_note');

            if ($addLogMoveVersion || $addLogTransitionId || $addReconciledAt || $addReconciliationNote) {
                Schema::table('whatsapp_notification_logs', function (Blueprint $table) use (
                    $addLogMoveVersion,
                    $addLogTransitionId,
                    $addReconciledAt,
                    $addReconciliationNote
                ) {
                    if ($addLogMoveVersion) {
                        $table->unsignedInteger('room_move_version')
                            ->nullable()
                            ->after('new_room_id');
                    }

                    if ($addLogTransitionId) {
                        $table->string('transition_id', 64)
                            ->nullable()
                            ->index()
                            ->after($addLogMoveVersion ? 'room_move_version' : 'new_room_id');
                    }

                    if ($addReconciledAt) {
                        $table->timestamp('reconciled_at')
                            ->nullable()
                            ->after('read_at');
                    }

                    if ($addReconciliationNote) {
                        $table->text('reconciliation_note')
                            ->nullable()
                            ->after($addReconciledAt ? 'reconciled_at' : 'read_at');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_notification_logs')) {
            if (Schema::hasColumn('whatsapp_notification_logs', 'transition_id')) {
                $indexes = collect(Schema::getIndexes('whatsapp_notification_logs'))
                    ->filter(fn (array $index): bool => in_array('transition_id', $index['columns'] ?? [], true) && !($index['primary'] ?? false))
                    ->pluck('name')
                    ->filter()
                    ->values();

                if ($indexes->isNotEmpty()) {
                    Schema::table('whatsapp_notification_logs', function (Blueprint $table) use ($indexes) {
                        foreach ($indexes as $indexName) {
                            $table->dropIndex($indexName);
                        }
                    });
                }
            }

            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('whatsapp_notification_logs', 'room_move_version') ? 'room_move_version' : null,
                Schema::hasColumn('whatsapp_notification_logs', 'transition_id') ? 'transition_id' : null,
                Schema::hasColumn('whatsapp_notification_logs', 'reconciled_at') ? 'reconciled_at' : null,
                Schema::hasColumn('whatsapp_notification_logs', 'reconciliation_note') ? 'reconciliation_note' : null,
            ]));

            if (!empty($columnsToDrop)) {
                Schema::table('whatsapp_notification_logs', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }

        if (Schema::hasTable('events')) {
            $eventColumnsToDrop = array_values(array_filter([
                Schema::hasColumn('events', 'room_move_version') ? 'room_move_version' : null,
                Schema::hasColumn('events', 'last_room_move_transition_id') ? 'last_room_move_transition_id' : null,
            ]));

            if (!empty($eventColumnsToDrop)) {
                Schema::table('events', function (Blueprint $table) use ($eventColumnsToDrop) {
                    $table->dropColumn($eventColumnsToDrop);
                });
            }
        }
    }
};

