<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();
            $table->foreignId('old_room_id')
                ->nullable()
                ->constrained('rooms')
                ->nullOnDelete();
            $table->foreignId('new_room_id')
                ->nullable()
                ->constrained('rooms')
                ->nullOnDelete();
            $table->foreignId('recipient_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('driver', 50);
            $table->string('template_name', 128)->nullable();
            $table->string('recipient_phone_masked', 25)->nullable();
            /*
             * Korelasi nomor penerima menggunakan HMAC-SHA256 berbasis APP_KEY
             * (bukan SHA-256 polos) agar nomor telepon tidak mudah ditebak lewat rainbow table
             * sekaligus menjaga jejak audit tanpa menyimpan nomor telepon lengkap.
             */
            $table->string('recipient_phone_hmac', 64)->nullable()->index();
            $table->boolean('is_dry_run')->default(true);
            $table->string('status', 40)->index();
            $table->string('meta_message_id', 128)->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_logs');
    }
};

