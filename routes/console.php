<?php

use App\Models\WhatsAppNotificationLog;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:reconcile {--log_id= : ID log tertentu yang akan direkonsiliasi} {--status= : Status akhir hasil verifikasi (accepted|delivered|read|failed)} {--message_id= : Meta wamid hasil verifikasi dari Meta WhatsApp Manager} {--note= : Catatan audit rekonsiliasi} {--pending-minutes=5 : Ambang menit untuk menganggap pending tertinggal}', function () {
    $service = app(WhatsAppService::class);
    $logId = $this->option('log_id');
    $resolvedStatus = $this->option('status');

    if ($logId !== null) {
        if ($resolvedStatus === null) {
            $this->error('Opsi --status wajib diisi saat menyertakan --log_id (accepted|delivered|read|failed).');

            return 1;
        }

        $log = WhatsAppNotificationLog::find((int) $logId);
        if (!$log) {
            $this->error("Log #{$logId} tidak ditemukan.");

            return 1;
        }

        $updated = $service->reconcileNotificationLog(
            $log,
            (string) $resolvedStatus,
            $this->option('note') ? (string) $this->option('note') : null,
            $this->option('message_id') ? (string) $this->option('message_id') : null
        );

        $this->info("Log #{$updated->id} berhasil direkonsiliasi menjadi status: {$updated->status}");

        return 0;
    }

    $minutes = max(0, (int) $this->option('pending-minutes'));
    $logs = $service->findLogsNeedingReconciliation($minutes);

    if ($logs->isEmpty()) {
        $this->info('Tidak ada log notifikasi WhatsApp yang membutuhkan rekonsiliasi.');

        return 0;
    }

    $this->table(
        ['ID', 'Event ID', 'Transition ID', 'Status', 'HTTP', 'Phone Masked', 'Last Attempt'],
        $logs->map(fn (WhatsAppNotificationLog $log) => [
            $log->id,
            $log->event_id,
            $log->transition_id ?: '-',
            $log->status,
            $log->http_status ?? '-',
            $log->recipient_phone_masked ?: '-',
            $log->last_attempt_at?->toDateTimeString() ?: '-',
        ])->all()
    );

    return 0;
})->purpose('Memeriksa dan merekonsiliasi log WhatsApp berstatus pending, ambiguous_timeout, atau HTTP 5xx.');
