<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppNotificationLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MetaCloudApiDriver
{
    public const DRIVER_NAME = 'meta_cloud_api';
    public const DEFAULT_TEMPLATE_NAME = 'notifikasi_pemindahan_ruangan';
    public const DEFAULT_TEMPLATE_LANGUAGE = 'id';
    public const TEMPLATE_CATEGORY = 'UTILITY';

    public function graphBaseUrl(): string
    {
        return rtrim(trim((string) config('services.whatsapp.graph_base_url', 'https://graph.facebook.com')), '/');
    }

    public function graphVersion(): string
    {
        return trim((string) config('services.whatsapp.graph_version', 'v21.0'), '/');
    }

    public function phoneNumberId(): string
    {
        return trim((string) config('services.whatsapp.phone_number_id', ''));
    }

    public function accessToken(): string
    {
        return trim((string) config('services.whatsapp.access_token', ''));
    }

    public function templateName(): string
    {
        return trim((string) config('services.whatsapp.template_name', ''));
    }

    public function templateLanguage(): string
    {
        return trim((string) config('services.whatsapp.template_language', ''));
    }

    public function connectTimeout(): int
    {
        return max(1, (int) config('services.whatsapp.connect_timeout', 5));
    }

    public function timeout(): int
    {
        return max(2, (int) config('services.whatsapp.timeout', 10));
    }

    public function hasCompleteTemplateConfiguration(): bool
    {
        $templateName = $this->templateName();
        $templateLanguage = $this->templateLanguage();

        if ($templateName === '' || $templateLanguage === '') {
            return false;
        }

        return !preg_match('/[\r\n\t\x00-\x1F\x7F]/', $templateName . $templateLanguage);
    }

    public function hasValidGraphEndpoint(): bool
    {
        $baseUrl = $this->graphBaseUrl();
        $version = $this->graphVersion();
        $phoneNumberId = $this->phoneNumberId();

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        $host = trim((string) parse_url($baseUrl, PHP_URL_HOST));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        if ($version === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $version)) {
            return false;
        }

        return $phoneNumberId !== '' && (bool) preg_match('/^[A-Za-z0-9_-]+$/', $phoneNumberId);
    }

    public function hasCompleteLiveConfiguration(): bool
    {
        return $this->hasValidGraphEndpoint()
            && $this->accessToken() !== ''
            && $this->hasCompleteTemplateConfiguration();
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.whatsapp.enabled', false)
            && trim((string) config('services.whatsapp.driver', self::DRIVER_NAME)) === self::DRIVER_NAME
            && $this->hasCompleteLiveConfiguration();
    }

    public function buildEndpoint(): string
    {
        if (!$this->hasValidGraphEndpoint()) {
            throw new \InvalidArgumentException('Konfigurasi endpoint Meta WhatsApp Cloud API tidak valid atau belum lengkap.');
        }

        return sprintf(
            '%s/%s/%s/messages',
            $this->graphBaseUrl(),
            $this->graphVersion(),
            $this->phoneNumberId()
        );
    }

    /**
     * Membersihkan nilai parameter template Meta agar bebas newline, tab,
     * karakter kontrol, dan spasi beruntun (>4 spasi), serta memiliki fallback non-kosong.
     */
    public function sanitizeParameter(?string $value, string $fallback, int $maxLength = 300): string
    {
        $normalized = $this->normalizeSingleLineText((string) ($value ?? ''));

        if ($normalized === '') {
            $normalized = $this->normalizeSingleLineText($fallback);
        }

        if ($normalized === '') {
            $normalized = '-';
        }

        if ($maxLength > 3 && mb_strlen($normalized, 'UTF-8') > $maxLength) {
            $truncated = rtrim(mb_substr($normalized, 0, $maxLength - 3, 'UTF-8'));

            return $truncated . '...';
        }

        return $normalized;
    }

    /**
     * Menyusun tepat 6 parameter terurut untuk template UTILITY `notifikasi_pemindahan_ruangan`:
     * {{1}} Nama agenda
     * {{2}} Tanggal agenda
     * {{3}} Jam agenda (WIB)
     * {{4}} Ruangan lama
     * {{5}} Ruangan baru
     * {{6}} Alasan pemindahan
     *
     * @return array<int, string>
     */
    public function buildRoomMoveParameters(
        string $eventName,
        string $dateRange,
        string $timeRange,
        string $oldRoomName,
        string $newRoomName,
        ?string $reason = null
    ): array {
        return [
            $this->sanitizeParameter($eventName, 'Agenda Sekolah', 200),
            $this->sanitizeParameter($dateRange, '-', 120),
            $this->sanitizeParameter($timeRange, '-', 120),
            $this->sanitizeParameter($oldRoomName, 'Ruangan sebelumnya', 120),
            $this->sanitizeParameter($newRoomName, 'Ruangan baru', 120),
            $this->sanitizeParameter($reason, 'Penyesuaian jadwal dan penggunaan ruangan oleh Admin.', 300),
        ];
    }

    /**
     * Membangun payload resmi request POST /messages Meta WhatsApp Cloud API.
     *
     * @param array<int, string> $parameters
     * @return array<string, mixed>
     */
    public function buildTemplatePayload(
        string $recipientPhone,
        array $parameters,
        ?string $templateName = null,
        ?string $templateLanguage = null
    ): array {
        $resolvedTemplate = trim((string) ($templateName ?? $this->templateName()));
        $resolvedLanguage = trim((string) ($templateLanguage ?? $this->templateLanguage()));

        $bodyParameters = array_map(
            static fn (string $text): array => [
                'type' => 'text',
                'text' => $text,
            ],
            array_values($parameters)
        );

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientPhone,
            'type' => 'template',
            'template' => [
                'name' => $resolvedTemplate,
                'language' => [
                    'code' => $resolvedLanguage,
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $bodyParameters,
                    ],
                ],
            ],
        ];
    }

    /**
     * Mengirim pesan template resmi ke Graph API Meta secara sinkron dengan timeout singkat.
     *
     * @param array<int, string> $parameters
     * @return array{
     *     status: string,
     *     http_status: ?int,
     *     meta_message_id: ?string,
     *     error_code: ?string,
     *     error_message: ?string,
     *     failure_category: ?string,
     *     retryable: bool,
     *     retry_after_seconds: ?int
     * }
     */
    public function sendTemplateMessage(string $recipientPhone, array $parameters): array
    {
        if (!$this->hasCompleteLiveConfiguration()) {
            return [
                'status' => WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                'http_status' => null,
                'meta_message_id' => null,
                'error_code' => 'META_CONFIG_INCOMPLETE',
                'error_message' => 'Konfigurasi Meta WhatsApp Cloud API belum lengkap.',
                'failure_category' => 'unconfigured',
                'retryable' => false,
                'retry_after_seconds' => null,
            ];
        }

        $endpoint = $this->buildEndpoint();
        $token = $this->accessToken();
        $payload = $this->buildTemplatePayload($recipientPhone, $parameters);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->post($endpoint, $payload);

            $httpStatus = $response->status();
            $body = $response->json();

            if ($response->successful()) {
                $messageId = is_array($body)
                    ? trim((string) data_get($body, 'messages.0.id', ''))
                    : '';

                if ($messageId === '') {
                    return [
                        'status' => WhatsAppNotificationLog::STATUS_FAILED,
                        'http_status' => $httpStatus,
                        'meta_message_id' => null,
                        'error_code' => 'INVALID_META_RESPONSE',
                        'error_message' => 'Respons sukses Meta Cloud API tidak menyertakan messages.0.id.',
                        'failure_category' => 'invalid_response',
                        'retryable' => false,
                        'retry_after_seconds' => null,
                    ];
                }

                return [
                    'status' => WhatsAppNotificationLog::STATUS_ACCEPTED,
                    'http_status' => $httpStatus,
                    'meta_message_id' => substr($messageId, 0, 128),
                    'error_code' => null,
                    'error_message' => null,
                    'failure_category' => null,
                    'retryable' => false,
                    'retry_after_seconds' => null,
                ];
            }

            $rawErrorCode = is_array($body) ? data_get($body, 'error.code') : null;
            $rawErrorSubcode = is_array($body) ? data_get($body, 'error.error_subcode') : null;
            $metaCodeSuffix = $rawErrorCode !== null && $rawErrorCode !== ''
                ? (string) $rawErrorCode . ($rawErrorSubcode ? '/' . (string) $rawErrorSubcode : '')
                : null;

            $rawErrorMessage = is_array($body)
                ? (string) (data_get($body, 'error.message') ?? data_get($body, 'error.error_user_msg') ?? '')
                : '';

            if (trim($rawErrorMessage) === '') {
                $rawErrorMessage = 'Permintaan ditolak oleh Meta Cloud API dengan status HTTP ' . $httpStatus . '.';
            }

            $sanitizedMessage = $this->redactSensitiveData($rawErrorMessage, $token, $recipientPhone);

            if ($httpStatus === 429) {
                $retryAfterHeader = trim((string) $response->header('Retry-After'));
                $retryAfterSeconds = ctype_digit($retryAfterHeader)
                    ? max(60, min(3600, (int) $retryAfterHeader))
                    : 300;
                $errorCode = 'RATE_LIMITED' . ($metaCodeSuffix ? ':' . $metaCodeSuffix : '');

                return [
                    'status' => WhatsAppNotificationLog::STATUS_FAILED,
                    'http_status' => $httpStatus,
                    'meta_message_id' => null,
                    'error_code' => substr($errorCode, 0, 64),
                    'error_message' => $sanitizedMessage,
                    'failure_category' => 'rate_limited',
                    'retryable' => true,
                    'retry_after_seconds' => $retryAfterSeconds,
                ];
            }

            if ($httpStatus >= 500) {
                $errorCode = 'HTTP_5XX_AMBIGUOUS' . ($metaCodeSuffix ? ':' . $metaCodeSuffix : '');

                return [
                    'status' => WhatsAppNotificationLog::STATUS_FAILED,
                    'http_status' => $httpStatus,
                    'meta_message_id' => null,
                    'error_code' => substr($errorCode, 0, 64),
                    'error_message' => $sanitizedMessage,
                    'failure_category' => 'server_error_ambiguous',
                    'retryable' => false,
                    'retry_after_seconds' => null,
                ];
            }

            // HTTP 400, 401, dan 4xx non-429 lainnya adalah kegagalan permanen data/konfigurasi.
            $errorCode = 'PERMANENT_HTTP_' . $httpStatus . ($metaCodeSuffix ? ':' . $metaCodeSuffix : '');

            return [
                'status' => WhatsAppNotificationLog::STATUS_FAILED,
                'http_status' => $httpStatus,
                'meta_message_id' => null,
                'error_code' => substr($errorCode, 0, 64),
                'error_message' => $sanitizedMessage,
                'failure_category' => 'permanent',
                'retryable' => false,
                'retry_after_seconds' => null,
            ];
        } catch (ConnectionException $exception) {
            if ($this->isProvablePreSendConnectionFailure($exception)) {
                return [
                    'status' => WhatsAppNotificationLog::STATUS_CONNECTION_FAILED,
                    'http_status' => null,
                    'meta_message_id' => null,
                    'error_code' => 'CONNECTION_FAILED',
                    'error_message' => $this->redactSensitiveData(
                        'Koneksi ke Meta Cloud API gagal sebelum request terkirim: ' . $exception->getMessage(),
                        $token,
                        $recipientPhone
                    ),
                    'failure_category' => 'connection_failed',
                    'retryable' => true,
                    'retry_after_seconds' => 300,
                ];
            }

            return [
                'status' => WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT,
                'http_status' => null,
                'meta_message_id' => null,
                'error_code' => 'TIMEOUT_AMBIGUOUS',
                'error_message' => $this->redactSensitiveData(
                    'Timeout/koneksi terputus dengan hasil penerimaan Meta belum pasti: ' . $exception->getMessage(),
                    $token,
                    $recipientPhone
                ),
                'failure_category' => 'ambiguous_timeout',
                'retryable' => false,
                'retry_after_seconds' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => WhatsAppNotificationLog::STATUS_FAILED,
                'http_status' => null,
                'meta_message_id' => null,
                'error_code' => 'CLIENT_EXCEPTION',
                'error_message' => $this->redactSensitiveData(
                    'Kegagalan internal pengiriman Meta Cloud API: ' . $exception->getMessage(),
                    $token,
                    $recipientPhone
                ),
                'failure_category' => 'client_exception',
                'retryable' => false,
                'retry_after_seconds' => null,
            ];
        }
    }

    private function isProvablePreSendConnectionFailure(ConnectionException $exception): bool
    {
        $message = $exception->getMessage();

        if (preg_match('/(?:cURL error 28\b|timed out|timeout|Operation timed out|Empty reply|Recv failure|Send failure|SSL)/i', $message) === 1) {
            return false;
        }

        return preg_match('/(?:cURL error 6\b|Could not resolve host|Could not resolve proxy|cURL error 7\b|Connection refused|Network is unreachable|No route to host)/i', $message) === 1;
    }

    public function redactSensitiveData(string $text, string $token = '', string $phone = ''): string
    {
        $sanitized = $text;

        if ($token !== '') {
            $sanitized = str_replace($token, '[REDACTED_TOKEN]', $sanitized);
        }

        if ($phone !== '') {
            $sanitized = str_replace([$phone, '+' . $phone], '[REDACTED_PHONE]', $sanitized);
            if (str_starts_with($phone, '628')) {
                $local = '0' . substr($phone, 2);
                $sanitized = str_replace($local, '[REDACTED_PHONE]', $sanitized);
            }
        }

        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED_TOKEN]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(?:\+?628|08)[0-9\-\s]{6,15}/', '[REDACTED_PHONE]', $sanitized) ?? $sanitized;
        $sanitized = $this->normalizeSingleLineText($sanitized);

        if (mb_strlen($sanitized, 'UTF-8') > 500) {
            return rtrim(mb_substr($sanitized, 0, 497, 'UTF-8')) . '...';
        }

        return $sanitized !== '' ? $sanitized : 'Terjadi kegagalan yang tidak diketahui.';
    }

    private function normalizeSingleLineText(string $text): string
    {
        $cleaned = preg_replace('/[\r\n\t\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $text) ?? $text;
        $collapsed = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;

        return trim($collapsed);
    }
}

