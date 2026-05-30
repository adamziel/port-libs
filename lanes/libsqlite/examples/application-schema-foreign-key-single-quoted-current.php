<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$catalog = new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord('table', 'wp option names', 'wp option names', 2, "CREATE TABLE 'wp option names'('name key' TEXT PRIMARY KEY)", 1),
    new SQLiteSchemaRecord('table', 'wp legacy meta', 'wp legacy meta', 3, "CREATE TABLE 'wp legacy meta'('meta id' INTEGER PRIMARY KEY, 'option key' TEXT REFERENCES 'wp option names'('name key') ON DELETE CASCADE)", 2),
]);

$summary = [
    'scenario' => 'application-schema-foreign-key-single-quoted-current',
    'applicationUse' => 'Copied legacy Application export schemas can preserve PRAGMA foreign_key_list rows when sqlite_schema SQL uses single-quoted identifiers for option metadata tables.',
    'foreignKeys' => $catalog->execute("PRAGMA foreign_key_list('wp legacy meta')")['rows'],
];

if (in_array('--self-test', $argv, true)) {
    assert(count($summary['foreignKeys']) === 1);
    assert($summary['foreignKeys'][0]['table'] === 'wp option names');
    assert($summary['foreignKeys'][0]['from'] === 'option key');
    assert($summary['foreignKeys'][0]['to'] === 'name key');
    assert($summary['foreignKeys'][0]['on_delete'] === 'CASCADE');
    echo "application-schema-foreign-key-single-quoted-current self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
