<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBusyHandler;
use PortLibs\LibSqlite\SQLiteFileUri;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$database = SQLiteFileUri::parse('file:/srv/application/wp-content/database/site%20copy.sqlite?mode=rw&cache=shared&busy_timeout=35');
$busy = SQLiteBusyHandler::timeout(35);

$blockedOpen = $busy->lockedOperationPlan('open copied wp_options database', false);
$cancelledCheckpoint = SQLiteBusyHandler::withDelays(25, [5, 10, 10])->lockedOperationPlan(
    'checkpoint copied wp_options wal',
    false,
    static fn (int $attempt): bool => $attempt === 0
);

echo json_encode([
    'database' => [
        'path' => $database['path'],
        'mode' => $database['mode'],
        'cache' => $database['cache'],
        'unknownParameters' => $database['unknown_parameters'],
    ],
    'blockedOpen' => [
        'status' => $blockedOpen['status'],
        'timeoutMs' => $blockedOpen['timeout_ms'],
        'retryCount' => $blockedOpen['retry_count'],
        'totalSleepMs' => $blockedOpen['total_sleep_ms'],
        'sleeps' => array_column($blockedOpen['attempts'], 'sleep_ms'),
    ],
    'cancelledCheckpoint' => [
        'status' => $cancelledCheckpoint['status'],
        'retryCount' => $cancelledCheckpoint['retry_count'],
        'totalSleepMs' => $cancelledCheckpoint['total_sleep_ms'],
    ],
    'applicationUse' => 'Preview SQLite busy-handler retry and timeout behavior before copied Application database opens or WAL checkpoints on hosts without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
