<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$plan = SQLiteVfsOpenLockFileControlCurrentSource::planUriOpenLock([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared)',
    'file_control(chunk_size, 12288)',
    'file_control(persist_wal, 1)',
    'lock(shared)',
    'close',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=ro&immutable=1)',
    'lock(shared)',
]);

$summary = [
    'scenario' => 'wordpress-vfs-open-uri-lock-current-source-next86',
    'status' => $plan['status'],
    'decodedPath' => $plan['events'][0]['path'],
    'reopenedSourceKey' => $plan['events'][5]['source_key'],
    'reopenedControls' => $plan['events'][5]['next']['handles']['db-2']['controls'],
    'immutableLockStatus' => $plan['events'][6]['status'],
    'immutableLockReason' => $plan['events'][6]['reason'],
    'wordpressUse' => 'Track copied wp_options database file: URI open/reopen source identity across percent-encoded paths and localhost authority while immutable archive opens skip byte-range locks.',
    'dependencies' => $plan['dependencies'],
];

if (
    $summary['decodedPath'] !== '/srv/www/wp-content/database/wp copy.sqlite'
    || $summary['reopenedSourceKey'] !== '/srv/www/wp-content/database/wp copy.sqlite'
    || ($summary['reopenedControls']['chunk_size'] ?? null) !== 12288
    || $summary['immutableLockStatus'] !== 'blocked'
) {
    fwrite(STDERR, "wordpress-vfs-open-uri-lock-current-source-next86 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
