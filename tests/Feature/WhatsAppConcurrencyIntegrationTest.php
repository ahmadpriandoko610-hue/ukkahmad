<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use App\Models\WhatsAppNotificationLog;
use App\Services\WhatsApp\MetaCloudApiDriver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class WhatsAppConcurrencyIntegrationTest extends TestCase
{
    private const FAKE_TOKEN = 'EAAG-TEST-CONCURRENCY-SECRET-TOKEN';
    private const FAKE_PHONE_ID = '987654321012345';

    /**
     * H5 & H6 (Integrasi Nyata MySQL InnoDB Multi-Proses):
     * 1. Dua proses OS paralel memindahkan agenda yang sama -> InnoDB lockForUpdate() memaksa hanya 1 pemindahan sah,
     *    1 ScheduleNotification, 1 log WhatsApp, dan 1 HTTP request Meta (tanpa race condition).
     * 2. Dua proses OS paralel memanggil notifyRoomChanged() untuk transition_id yang sama -> pre-HTTP claim `pending`
     *    dengan UNIQUE(idempotency_key) memblokir proses kedua saat proses pertama masih menunggu HTTP tanpa menahan lock DB.
     */
    public function test_real_mysql_innodb_multiprocess_row_locking_and_pre_http_pending_claim(): void
    {
        $mysqlEnv = $this->detectSafeTemporaryMysqlEnvironment();
        if ($mysqlEnv === null) {
            $this->markTestSkipped('Server MySQL lokal tidak terjangkau untuk pengujian integrasi InnoDB multi-proses.');
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rapi_wa_mysql_' . bin2hex(random_bytes(4));
        mkdir($tempDir, 0777, true);

        $tempDb = $mysqlEnv['database'];
        $originalDefaultConnection = config('database.default');
        $originalMysqlConfig = config('database.connections.mysql');

        try {
            $this->createTemporaryMysqlDatabase($mysqlEnv);

            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $mysqlEnv['host'],
                'database.connections.mysql.port' => $mysqlEnv['port'],
                'database.connections.mysql.database' => $tempDb,
                'database.connections.mysql.username' => $mysqlEnv['username'],
                'database.connections.mysql.password' => $mysqlEnv['password'],
            ]);
            DB::purge('mysql');
            DB::reconnect('mysql');

            Artisan::call('migrate:fresh', [
                '--database' => 'mysql',
                '--force' => true,
            ]);

            $seed = $this->seedFixtureData();
            $workerScript = $tempDir . DIRECTORY_SEPARATOR . 'worker.php';
            file_put_contents($workerScript, $this->buildChildWorkerScript());

            // SKENARIO A: Dua proses OS simultan memindahkan agenda yang sama dari current_room_id = roomA
            $barrierMove = $tempDir . DIRECTORY_SEPARATOR . 'go_move.flag';
            $counterMove = $tempDir . DIRECTORY_SEPARATOR . 'http_move.count';
            file_put_contents($counterMove, '0');

            $p1 = $this->spawnWorkerProcess($workerScript, $mysqlEnv, [
                'action' => 'move_controller',
                'barrier_file' => $barrierMove,
                'http_counter_file' => $counterMove,
                'admin_id' => $seed['admin']->id,
                'event_id' => $seed['event']->id,
                'target_room_id' => $seed['roomB']->id,
                'current_room_id' => $seed['roomA']->id,
                'move_reason' => 'Admin 1 pindah paralel ke Lab 1',
            ]);

            $p2 = $this->spawnWorkerProcess($workerScript, $mysqlEnv, [
                'action' => 'move_controller',
                'barrier_file' => $barrierMove,
                'http_counter_file' => $counterMove,
                'admin_id' => $seed['admin2']->id,
                'event_id' => $seed['event']->id,
                'target_room_id' => $seed['roomC']->id,
                'current_room_id' => $seed['roomA']->id,
                'move_reason' => 'Admin 2 pindah paralel ke Lab 2',
            ]);

            usleep(250_000);
            file_put_contents($barrierMove, 'go');

            $r1 = $this->collectWorkerResult($p1);
            $r2 = $this->collectWorkerResult($p2);

            $this->assertSame(0, $r1['exit_code'], 'Worker 1 gagal: ' . $r1['stderr']);
            $this->assertSame(0, $r2['exit_code'], 'Worker 2 gagal: ' . $r2['stderr']);

            $outcomes = [$r1['data']['outcome'] ?? null, $r2['data']['outcome'] ?? null];
            sort($outcomes);
            $this->assertSame(['rejected_or_failed', 'success'], $outcomes);
            $this->assertSame(1, (int) trim((string) file_get_contents($counterMove)));
            $this->assertSame(1, (int) $seed['event']->fresh()->room_move_version);
            $this->assertSame(1, ScheduleNotification::where('event_id', $seed['event']->id)->count());
            $this->assertSame(1, WhatsAppNotificationLog::where('event_id', $seed['event']->id)->count());

            // SKENARIO B: InnoDB FOR UPDATE aktif menahan proses ke-2 hingga transaksi ke-1 commit, lalu stale ditolak
            $eventLocked = Event::create([
                'event_name' => 'Agenda Uji InnoDB Row Lock',
                'date' => '2026-11-28',
                'end_date' => '2026-11-28',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $seed['guru']->id,
                'room_id' => $seed['roomA']->id,
                'status' => 'Disetujui',
                'approved_by' => $seed['admin']->id,
            ]);

            $lockHeldSignal = $tempDir . DIRECTORY_SEPARATOR . 'lock_held.flag';
            $counterLock = $tempDir . DIRECTORY_SEPARATOR . 'http_lock.count';
            file_put_contents($counterLock, '0');

            $lockHolder = $this->spawnWorkerProcess($workerScript, $mysqlEnv, [
                'action' => 'hold_row_lock_then_update',
                'signal_file' => $lockHeldSignal,
                'event_id' => $eventLocked->id,
                'target_room_id' => $seed['roomB']->id,
                'hold_ms' => 1000,
            ]);

            $contender = $this->spawnWorkerProcess($workerScript, $mysqlEnv, [
                'action' => 'move_controller',
                'barrier_file' => $lockHeldSignal,
                'http_counter_file' => $counterLock,
                'admin_id' => $seed['admin2']->id,
                'event_id' => $eventLocked->id,
                'target_room_id' => $seed['roomC']->id,
                'current_room_id' => $seed['roomA']->id,
                'move_reason' => 'Admin menabrak lock InnoDB',
            ]);

            $waitDeadline = microtime(true) + 10.0;
            while (!file_exists($lockHeldSignal) && microtime(true) < $waitDeadline) {
                usleep(5_000);
            }
            $this->assertFileExists($lockHeldSignal, 'Lock holder MySQL tidak memberi sinyal tepat waktu.');

            $holderRes = $this->collectWorkerResult($lockHolder);
            $contenderRes = $this->collectWorkerResult($contender);

            $this->assertSame(0, $holderRes['exit_code'], $holderRes['stderr']);
            $this->assertSame(0, $contenderRes['exit_code'], $contenderRes['stderr']);
            $this->assertSame('rejected_or_failed', $contenderRes['data']['outcome'] ?? null);
            $this->assertGreaterThanOrEqual(250.0, (float) ($contenderRes['data']['elapsed_ms'] ?? 0));
            $this->assertSame($seed['roomB']->id, (int) $eventLocked->fresh()->room_id);
            $this->assertSame(0, (int) trim((string) file_get_contents($counterLock)));
            $this->assertSame(0, ScheduleNotification::where('event_id', $eventLocked->id)->count());

            // SKENARIO C: Dua proses paralel memanggil notifyRoomChanged() dengan transition_id SAMA saat klaim masih pending
            $eventPreHttp = Event::create([
                'event_name' => 'Agenda Uji Pre-HTTP Pending MySQL',
                'date' => '2026-11-29',
                'end_date' => '2026-11-29',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $seed['guru']->id,
                'room_id' => $seed['roomB']->id,
                'move_reason' => 'Perbaikan panel listrik',
                'room_move_version' => 1,
                'last_room_move_transition_id' => 'mysql-shared-transition-01',
                'status' => 'Disetujui',
                'approved_by' => $seed['admin']->id,
            ]);

            $pendingInFlightSignal = $tempDir . DIRECTORY_SEPARATOR . 'pending_inflight.json';
            $counterNotify = $tempDir . DIRECTORY_SEPARATOR . 'http_notify.count';
            file_put_contents($counterNotify, '0');

            $notifyProc1 = $this->spawnWorkerProcess($workerScript, $mysqlEnv, [
                'action' => 'notify_room_changed',
                'http_counter_file' => $counterNotify,
                'in_flight_signal_file' => $pendingInFlightSignal,
                'http_sleep_ms' => 800,
                'event_id' => $eventPreHttp->id,
                'old_room_id' => $seed['roomA']->id,
                'new_room_id' => $seed['roomB']->id,
                'move_reason' => 'Perbaikan panel listrik',
                'transition_id' => 'mysql-shared-transition-01',
            ]);

            $notifyProc2 = $this->spawnWorkerProcess($workerScript, $mysqlEnv, [
                'action' => 'notify_room_changed',
                'barrier_file' => $pendingInFlightSignal,
                'http_counter_file' => $counterNotify,
                'in_flight_signal_file' => null,
                'http_sleep_ms' => 0,
                'event_id' => $eventPreHttp->id,
                'old_room_id' => $seed['roomA']->id,
                'new_room_id' => $seed['roomB']->id,
                'move_reason' => 'Perbaikan panel listrik',
                'transition_id' => 'mysql-shared-transition-01',
            ]);

            $waitDeadline2 = microtime(true) + 10.0;
            while (!file_exists($pendingInFlightSignal) && microtime(true) < $waitDeadline2) {
                usleep(5_000);
            }
            $this->assertFileExists($pendingInFlightSignal, 'Proses 1 tidak mencatat klaim pending pra-HTTP.');

            $n2 = $this->collectWorkerResult($notifyProc2);
            $n1 = $this->collectWorkerResult($notifyProc1);
            $inFlightSnapshot = json_decode((string) file_get_contents($pendingInFlightSignal), true);
            $this->assertSame('pending', $inFlightSnapshot['db_status_during_http'] ?? null);

            $this->assertSame(0, $n1['exit_code'], $n1['stderr']);
            $this->assertSame(0, $n2['exit_code'], $n2['stderr']);
            $this->assertSame('accepted', $n1['data']['wa_status'] ?? null);
            $this->assertSame('skipped_duplicate', $n2['data']['wa_status'] ?? null);
            $this->assertSame(1, (int) trim((string) file_get_contents($counterNotify)));
            $this->assertSame(1, WhatsAppNotificationLog::where('event_id', $eventPreHttp->id)->count());
        } finally {
            DB::disconnect('mysql');
            config([
                'database.default' => $originalDefaultConnection,
                'database.connections.mysql' => $originalMysqlConfig,
            ]);
            DB::purge('mysql');
            $this->dropTemporaryMysqlDatabase($mysqlEnv);
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * Uji multi-proses paralel terhadap UNIQUE(idempotency_key) berbasis file SQLite nyata (lintas platform/CI).
     */
    public function test_multiprocess_parallel_unique_idempotency_key_claim_on_file_database(): void
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rapi_wa_sqlite_' . bin2hex(random_bytes(4));
        mkdir($tempDir, 0777, true);
        $sqlitePath = $tempDir . DIRECTORY_SEPARATOR . 'concurrency.sqlite';
        touch($sqlitePath);

        $dbEnv = [
            'driver' => 'sqlite',
            'host' => '',
            'port' => '',
            'database' => $sqlitePath,
            'username' => '',
            'password' => '',
        ];

        $originalDefaultConnection = config('database.default');
        $originalSqliteConfig = config('database.connections.sqlite');

        try {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $sqlitePath,
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            Artisan::call('migrate:fresh', [
                '--database' => 'sqlite',
                '--force' => true,
            ]);

            $seed = $this->seedFixtureData();
            $workerScript = $tempDir . DIRECTORY_SEPARATOR . 'worker.php';
            file_put_contents($workerScript, $this->buildChildWorkerScript());

            $barrier = $tempDir . DIRECTORY_SEPARATOR . 'go_sqlite.flag';
            $counter = $tempDir . DIRECTORY_SEPARATOR . 'http_sqlite.count';
            file_put_contents($counter, '0');

            $p1 = $this->spawnWorkerProcess($workerScript, $dbEnv, [
                'action' => 'notify_room_changed',
                'barrier_file' => $barrier,
                'http_counter_file' => $counter,
                'http_sleep_ms' => 150,
                'event_id' => $seed['event']->id,
                'old_room_id' => $seed['roomA']->id,
                'new_room_id' => $seed['roomB']->id,
                'move_reason' => 'Race SQLite parallel',
                'transition_id' => 'sqlite-parallel-tr-01',
            ]);

            $p2 = $this->spawnWorkerProcess($workerScript, $dbEnv, [
                'action' => 'notify_room_changed',
                'barrier_file' => $barrier,
                'http_counter_file' => $counter,
                'http_sleep_ms' => 150,
                'event_id' => $seed['event']->id,
                'old_room_id' => $seed['roomA']->id,
                'new_room_id' => $seed['roomB']->id,
                'move_reason' => 'Race SQLite parallel',
                'transition_id' => 'sqlite-parallel-tr-01',
            ]);

            usleep(250_000);
            file_put_contents($barrier, 'go');

            $r1 = $this->collectWorkerResult($p1);
            $r2 = $this->collectWorkerResult($p2);

            $this->assertSame(0, $r1['exit_code'], $r1['stderr']);
            $this->assertSame(0, $r2['exit_code'], $r2['stderr']);

            $statuses = [$r1['data']['wa_status'] ?? null, $r2['data']['wa_status'] ?? null];
            sort($statuses);
            $this->assertSame(['accepted', 'skipped_duplicate'], $statuses);
            $this->assertSame(1, (int) trim((string) file_get_contents($counter)));
            $this->assertSame(1, WhatsAppNotificationLog::where('event_id', $seed['event']->id)->count());
        } finally {
            DB::disconnect('sqlite');
            config([
                'database.default' => $originalDefaultConnection,
                'database.connections.sqlite' => $originalSqliteConfig,
            ]);
            DB::purge('sqlite');
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * @return array{driver: string, host: string, port: string, database: string, username: string, password: string}|null
     */
    private function detectSafeTemporaryMysqlEnvironment(): ?array
    {
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (string) config('database.connections.mysql.port', '3306');
        $username = (string) config('database.connections.mysql.username', 'root');
        $password = (string) config('database.connections.mysql.password', '');
        $tempDb = 'rapi_wa_concurrency_tmp_' . bin2hex(random_bytes(4));

        $forbidden = ['ukk_ahmad_rapi', 'laravel', 'mysql', 'information_schema', 'performance_schema', 'sys'];
        if (in_array(strtolower($tempDb), $forbidden, true)) {
            return null;
        }

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 2,
                ]
            );
            $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$tempDb]);
            if ($stmt->fetchColumn() !== false) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $tempDb,
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $mysqlEnv
     */
    private function createTemporaryMysqlDatabase(array $mysqlEnv): void
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $mysqlEnv['host'], $mysqlEnv['port']),
            $mysqlEnv['username'],
            $mysqlEnv['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('CREATE DATABASE `' . str_replace('`', '', $mysqlEnv['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $mysqlEnv
     */
    private function dropTemporaryMysqlDatabase(array $mysqlEnv): void
    {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $mysqlEnv['host'], $mysqlEnv['port']),
                $mysqlEnv['username'],
                $mysqlEnv['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '', $mysqlEnv['database']) . '`');
        } catch (\Throwable) {
            // Abaikan error pembersihan sekunder
        }
    }

    /**
     * @return array{admin: User, admin2: User, guru: User, roomA: Room, roomB: Room, roomC: Room, event: Event}
     */
    private function seedFixtureData(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Concurrency 1']);
        $admin2 = User::factory()->create(['role' => 'admin', 'name' => 'Admin Concurrency 2']);
        $pic = User::factory()->create(['role' => 'pic', 'name' => 'PIC Concurrency', 'phone' => '081111111111']);
        $guru = User::factory()->create(['role' => 'guru', 'name' => 'Guru Concurrency', 'phone' => '081234567890']);
        $guru->grantWhatsAppConsent('guru_self_service')->save();

        $roomA = Room::create(['room_name' => 'Aula Utama', 'location' => 'A', 'capacity' => 120, 'pic_id' => $pic->id, 'is_active' => true]);
        $roomB = Room::create(['room_name' => 'Lab RPL 1', 'location' => 'B', 'capacity' => 36, 'pic_id' => $pic->id, 'is_active' => true]);
        $roomC = Room::create(['room_name' => 'Lab RPL 2', 'location' => 'C', 'capacity' => 36, 'pic_id' => $pic->id, 'is_active' => true]);

        $event = Event::create([
            'event_name' => 'Agenda Uji Konkurensi',
            'date' => '2026-11-27',
            'end_date' => '2026-11-27',
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $guru->id,
            'room_id' => $roomA->id,
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);

        return compact('admin', 'admin2', 'guru', 'roomA', 'roomB', 'roomC', 'event');
    }

    /**
     * @param array{driver: string, host: string, port: string, database: string, username: string, password: string} $dbEnv
     * @param array<string, mixed> $job
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function spawnWorkerProcess(string $scriptPath, array $dbEnv, array $job): array
    {
        $jobJson = json_encode($job, JSON_THROW_ON_ERROR);
        $baseEnv = getenv();
        $env = array_merge(is_array($baseEnv) ? $baseEnv : [], $_ENV, [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'DB_CONNECTION' => $dbEnv['driver'],
            'DB_HOST' => $dbEnv['host'],
            'DB_PORT' => $dbEnv['port'],
            'DB_DATABASE' => $dbEnv['database'],
            'DB_USERNAME' => $dbEnv['username'],
            'DB_PASSWORD' => $dbEnv['password'],
            'WORKER_JOB_JSON' => $jobJson,
        ]);

        $cmd = [PHP_BINARY, $scriptPath];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertIsResource($process, 'Gagal menjalankan child process worker PHP.');
        fclose($pipes[0]);

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param array{process: resource, pipes: array<int, resource>} $handle
     * @return array{exit_code: int, stdout: string, stderr: string, data: array<string, mixed>}
     */
    private function collectWorkerResult(array $handle): array
    {
        $stdout = (string) stream_get_contents($handle['pipes'][1]);
        $stderr = (string) stream_get_contents($handle['pipes'][2]);
        fclose($handle['pipes'][1]);
        fclose($handle['pipes'][2]);
        $exitCode = proc_close($handle['process']);

        $decoded = json_decode(trim($stdout), true);

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }

    private function buildChildWorkerScript(): string
    {
        $basePathExport = var_export(base_path(), true);

        return str_replace('__BASE_PATH__', $basePathExport, <<<'PHP'
<?php
declare(strict_types=1);

$basePath = __BASE_PATH__;
require $basePath . '/vendor/autoload.php';
$app = require $basePath . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
    Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 5000;');
}

// Pastikan konfigurasi WhatsApp uji live aman dengan Http::fake() dan Http::preventStrayRequests()
config([
    'services.whatsapp.enabled' => true,
    'services.whatsapp.driver' => App\Services\WhatsApp\MetaCloudApiDriver::DRIVER_NAME,
    'services.whatsapp.dry_run' => false,
    'services.whatsapp.require_consent' => true,
    'services.whatsapp.test_allowlist_only' => false,
    'services.whatsapp.graph_base_url' => 'https://graph.facebook.com',
    'services.whatsapp.graph_version' => 'v21.0',
    'services.whatsapp.phone_number_id' => '987654321012345',
    'services.whatsapp.access_token' => 'EAAG-TEST-CONCURRENCY-SECRET-TOKEN',
    'services.whatsapp.template_name' => 'notifikasi_pemindahan_ruangan',
    'services.whatsapp.template_language' => 'id',
]);

$job = json_decode((string) getenv('WORKER_JOB_JSON'), true, 512, JSON_THROW_ON_ERROR);
$counterFile = $job['http_counter_file'] ?? null;
$inFlightFile = $job['in_flight_signal_file'] ?? null;
$httpSleepMs = (int) ($job['http_sleep_ms'] ?? 0);

Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake([
    'https://graph.facebook.com/*' => function () use ($counterFile, $inFlightFile, $httpSleepMs, $job) {
        if ($counterFile) {
            $fh = fopen($counterFile, 'c+');
            flock($fh, LOCK_EX);
            $current = (int) trim((string) stream_get_contents($fh));
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) ($current + 1));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        if ($inFlightFile && isset($job['transition_id'])) {
            $pendingStatus = App\Models\WhatsAppNotificationLog::where('transition_id', $job['transition_id'])->value('status');
            $tmpInFlight = $inFlightFile . '.tmp';
            file_put_contents($tmpInFlight, json_encode(['db_status_during_http' => $pendingStatus]));
            rename($tmpInFlight, $inFlightFile);
        }

        if ($httpSleepMs > 0) {
            usleep($httpSleepMs * 1000);
        }

        return Illuminate\Support\Facades\Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.proc.' . bin2hex(random_bytes(4))]],
        ], 200);
    },
]);

$barrierFile = $job['barrier_file'] ?? null;
if ($barrierFile) {
    $deadline = microtime(true) + 15.0;
    while (!file_exists($barrierFile) && microtime(true) < $deadline) {
        usleep(1000);
    }
}

$started = microtime(true);
$result = [];

if ($job['action'] === 'move_controller') {
    $admin = App\Models\User::findOrFail($job['admin_id']);
    Illuminate\Support\Facades\Auth::login($admin);
    $event = App\Models\Event::findOrFail($job['event_id']);

    $request = Illuminate\Http\Request::create('/events/' . $event->id . '/move', 'PUT', [
        'room_id' => $job['target_room_id'],
        'current_room_id' => $job['current_room_id'],
        'move_reason' => $job['move_reason'],
    ]);
    $session = $app['session.store'];
    $session->start();
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);

    try {
        /** @var Illuminate\Http\RedirectResponse $response */
        $response = $app->make(App\Http\Controllers\EventController::class)->move($request, $event);
        $hasSuccess = $session->has('success') && !$session->has('errors');
        $result = [
            'outcome' => $hasSuccess ? 'success' : 'rejected_or_failed',
            'response_url' => $response->getTargetUrl(),
        ];
    } catch (Illuminate\Validation\ValidationException $exception) {
        $result = [
            'outcome' => 'rejected_or_failed',
            'validation_errors' => $exception->errors(),
        ];
    }
} elseif ($job['action'] === 'hold_row_lock_then_update') {
    Illuminate\Support\Facades\DB::transaction(function () use ($job): void {
        $locked = App\Models\Event::query()->whereKey($job['event_id'])->lockForUpdate()->firstOrFail();
        file_put_contents($job['signal_file'], 'locked');
        usleep(((int) $job['hold_ms']) * 1000);
        $locked->update([
            'room_id' => $job['target_room_id'],
            'room_move_version' => ((int) ($locked->room_move_version ?? 0)) + 1,
            'last_room_move_transition_id' => (string) Illuminate\Support\Str::uuid(),
        ]);
    });
    $result = ['outcome' => 'lock_released'];
} elseif ($job['action'] === 'notify_room_changed') {
    $event = App\Models\Event::with('requester')->findOrFail($job['event_id']);
    $oldRoom = App\Models\Room::with('pic')->findOrFail($job['old_room_id']);
    $newRoom = App\Models\Room::with('pic')->findOrFail($job['new_room_id']);

    $wa = $app->make(App\Services\WhatsAppService::class)->notifyRoomChanged(
        $event,
        $oldRoom,
        $newRoom,
        $job['move_reason'],
        null,
        $job['transition_id']
    );

    $result = [
        'wa_status' => $wa['status'] ?? null,
        'log_id' => $wa['log_id'] ?? null,
    ];
}

$result['elapsed_ms'] = round((microtime(true) - $started) * 1000, 2);
echo json_encode($result, JSON_THROW_ON_ERROR);
PHP
        );
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
