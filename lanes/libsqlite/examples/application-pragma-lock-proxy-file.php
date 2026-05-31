<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaLockProxyFileState;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$state = new SQLitePragmaLockProxyFileState();
$database = '/tmp/application-lock-proxy.sqlite';
$first = $state->open($database, 1)['connection'];
$second = $state->open($database, 2, true)['connection'];

$state->pragma($first, "PRAGMA lock_proxy_file='/tmp/application-lock-proxy.lock'");
$firstSelect = $state->selectSchema($first, [['type' => 'table', 'name' => 'app_settings']]);
$secondSelect = $state->selectSchema($second, [['type' => 'table', 'name' => 'app_settings']]);

$payload = [
    'applicationUse' => 'Model PRAGMA lock_proxy_file preflight for a generic SQLite application database without requiring the SQLite extension.',
    'firstConnection' => $firstSelect,
    'secondConnection' => $secondSelect,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($firstSelect['status'] !== 'ok') {
        fwrite(STDERR, "first lock proxy select failed\n");
        exit(1);
    }
    if ($secondSelect['error'] !== 'database is locked') {
        fwrite(STDERR, "second host did not observe proxy lock\n");
        exit(1);
    }

    echo "application-pragma-lock-proxy-file self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
