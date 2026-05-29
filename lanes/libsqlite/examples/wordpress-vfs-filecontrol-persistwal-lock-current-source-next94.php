<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsOpenLockFileControlCurrentSource.php';

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$first = SQLiteVfsOpenLockFileControlCurrentSource::planPersistWalLockFileControl([
    'open',
    'file_control(persist_wal, on)',
    'lock(reserved)',
    'file_control(persist_wal, on)',
    'close',
], [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix',
]);

$second = SQLiteVfsOpenLockFileControlCurrentSource::planPersistWalLockFileControl([
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix)',
    'file_control(persist_wal, off)',
    'lock(pending)',
    'file_control(persist_wal, off)',
], [
    'current' => $first['events'][4]['next'],
]);

$payload = [
    'scenario' => 'wordpress-vfs-filecontrol-persistwal-lock-current-source-next94',
    'wordpressUse' => 'Route copied wp_options xFileControl(PERSIST_WAL) through current-source lock rules: unlocked handles cannot flip WAL sidecar persistence, reserved/pending writers can, and the decoded file: URI source preserves the setting across close/reopen without ext/sqlite.',
    'path' => $second['events'][0]['source_key'],
    'unlockedPersistWalStatus' => $first['events'][1]['status'],
    'unlockedPersistWalReason' => $first['events'][1]['reason'],
    'reservedPersistWalStatus' => $first['events'][3]['status'],
    'reopenReusedControls' => $second['events'][0]['reused_controls'],
    'reopenPersistWal' => $second['events'][0]['next']['handles']['db-2']['controls']['persist_wal'],
    'reopenBlockedPersistWalStatus' => $second['events'][1]['status'],
    'pendingPersistWalStatus' => $second['events'][3]['status'],
    'finalPersistWal' => $second['events'][3]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal'],
    'dataVersion' => $second['events'][3]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version'],
    'dependencies' => $second['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($payload['unlockedPersistWalStatus'] === 'blocked');
    assert($payload['reservedPersistWalStatus'] === 'ok');
    assert($payload['reopenPersistWal'] === true);
    assert($payload['reopenBlockedPersistWalStatus'] === 'blocked');
    assert($payload['pendingPersistWalStatus'] === 'ok');
    assert($payload['finalPersistWal'] === false);
    echo "wordpress-vfs-filecontrol-persistwal-lock-current-source-next94 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
