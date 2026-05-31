<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataStoreDirectory;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$state = new SQLitePragmaDataStoreDirectory();
$initial = $state->execute('PRAGMA data_store_directory');
$assigned = $state->execute("PRAGMA data_store_directory = '/tmp/application-sqlite-data'");
$relative = $state->databaseList('app.sqlite');
$absolute = $state->databaseList('/var/lib/app.sqlite');
$cleared = $state->execute("PRAGMA data_store_directory = ''");
$afterClear = $state->databaseList('app.sqlite');

$payload = [
    'applicationUse' => 'Model PRAGMA data_store_directory routing for generic SQLite application database opens without requiring the SQLite extension.',
    'initial' => $initial,
    'assigned' => $assigned,
    'relativeDatabaseList' => $relative,
    'absoluteDatabaseList' => $absolute,
    'cleared' => $cleared,
    'afterClearDatabaseList' => $afterClear,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($initial['rows'] !== []) {
        fwrite(STDERR, "initial data_store_directory should be empty\n");
        exit(1);
    }
    if ($relative['rows'][0]['file'] !== '/tmp/application-sqlite-data/app.sqlite') {
        fwrite(STDERR, "relative database path did not use data_store_directory\n");
        exit(1);
    }
    if ($absolute['rows'][0]['file'] !== '/var/lib/app.sqlite') {
        fwrite(STDERR, "absolute database path should bypass data_store_directory\n");
        exit(1);
    }
    if ($cleared['directory'] !== null || $afterClear['rows'][0]['file'] !== 'app.sqlite') {
        fwrite(STDERR, "cleared data_store_directory should restore relative database paths\n");
        exit(1);
    }

    echo "application-pragma-data-store-directory self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
