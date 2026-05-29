<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsUriTempLockingFileControlCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsUriTempLockingFileControlCurrentSourceNextPlan;

$plan = SQLiteVfsUriTempLockingFileControlCurrentSourceNextPlan::run([
    'open(main, file://localhost/srv/www/wp-content/database/wp.sqlite?mode=rw&cache=shared)',
    'file_control(reserve_bytes, 32)',
    'open(temp, file:/srv/www/wp-content/database/wp-temp-import.sqlite?mode=memory&cache=private)',
    'file_control(locking_mode, exclusive)',
    'lock(exclusive, wp-import)',
    'close(temp)',
    'open(temp, file:/srv/www/wp-content/database/wp-temp-import.sqlite?mode=memory&cache=private)',
    'lock(shared, wp-cron)',
]);

$summary = [
    'scenario' => 'wordpress-vfs-uri-temp-locking-filecontrol-current-source-next130',
    'wordpressUse' => 'Model a copied WordPress import that opens a URI main database plus a temp import database, keeps temp locks and file-control state handle-local, and deletes temp state on close without requiring ext/sqlite.',
    'dependency' => 'vfs-uri-temp-locking-filecontrol-current-source-next130',
    'mainReserveBytes' => $plan['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp.sqlite']['reserve_bytes'],
    'tempDeleted' => $plan['events'][5]['deleted_temp'],
    'reopenedTempOwner' => $plan['events'][6]['owner'],
    'finalCurrentSource' => $plan['next']['current_source'],
    'tempOpenCount' => $plan['next']['temp_open_count'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['mainReserveBytes'] === 32);
    assert($summary['tempDeleted'] === true);
    assert($summary['reopenedTempOwner'] === 'temp:temp:3');
    assert($summary['finalCurrentSource'] === 'temp');
    assert($summary['tempOpenCount'] === 1);
    echo "wordpress-vfs-uri-temp-locking-filecontrol-current-source-next130 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
