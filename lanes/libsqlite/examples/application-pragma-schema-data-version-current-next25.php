<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;

$snapshot = null;
if ($databasePath !== null && is_file($databasePath)) {
    $snapshot = SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromFile($databasePath));
}

$pragma = $snapshot instanceof SQLitePragmaSnapshot
    ? SQLitePragmaSchemaDataVersion::fromSnapshot($snapshot)
    : new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 12, 'data_version' => 5, 'change_counter' => 5]]);

$before = [
    'schema_version' => $pragma->execute('PRAGMA schema_version'),
    'data_version' => $pragma->execute('PRAGMA data_version'),
];
$schemaWrite = $pragma->execute('PRAGMA schema_version=13');
$externalCommit = $pragma->bumpDataVersion('main', 1, 'copied_wp_options_writer_commit');
$ignoredDataWrite = $pragma->execute('PRAGMA data_version=99');

echo json_encode([
    'path' => $databasePath ?? ':bounded-fixture:',
    'applicationUse' => 'Preview PRAGMA schema_version and data_version current/next rows for Application import tools that need to distinguish schema-cookie writes from another connection committing data.',
    'before' => [
        'schema_version' => $before['schema_version']['rows'][0]['schema_version'],
        'data_version' => $before['data_version']['rows'][0]['data_version'],
    ],
    'schemaWrite' => [
        'value' => $schemaWrite['value'],
        'changed' => $schemaWrite['changed'],
        'header' => $schemaWrite['header'],
    ],
    'externalCommit' => [
        'value' => $externalCommit['value'],
        'reason' => $externalCommit['reason'],
        'header' => $externalCommit['header'],
    ],
    'ignoredDataVersionWrite' => [
        'value' => $ignoredDataWrite['value'],
        'reason' => $ignoredDataWrite['reason'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
