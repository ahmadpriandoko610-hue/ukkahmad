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
    Schema::create('complaints', function (Blueprint $table) {
        $table->id();

        $table->foreignId('room_id')
            ->constrained('rooms')
            ->cascadeOnDelete();

        $table->foreignId('reporter_id')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->text('description');

        $table->string('photo_path')->nullable();

        $table->enum('status_repair', [
            'Belum Diperbaiki',
            'Menunggu Spare Part',
            'Sudah Diperbaiki'
        ])->default('Belum Diperbaiki');

        $table->foreignId('handled_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->timestamp('reported_at')->useCurrent();

        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
