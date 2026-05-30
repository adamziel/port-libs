<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    $sql,
    1,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_option_dependency', 2, "CREATE TABLE wp_option_dependency(
        option_name TEXT PRIMARY KEY,
        parent_option TEXT REFERENCES wp_options(option_name) ON UPDATE CASCADE ON DELETE SET NULL
    )"),
    $record('wp_options', 3, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT)'),
]);

$plan = $catalog->foreignKeyListAfterSchemaReparse(
    "pragma_foreign_key_list('wp_option_dependency')",
    [
        $record('wp_option_dependency', 2, "CREATE TABLE wp_option_dependency(
            option_name TEXT PRIMARY KEY,
            parent_option TEXT REFERENCES wp_options(option_name) ON UPDATE CASCADE ON DELETE SET NULL,
            previous_option TEXT,
            fallback_option TEXT,
            FOREIGN KEY(previous_option, fallback_option)
                REFERENCES wp_option_dependency(option_name, parent_option)
                ON UPDATE SET DEFAULT
                ON DELETE CASCADE
                MATCH recursive
        )"),
        $record('wp_options', 3, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT)'),
    ],
);

if (
    $plan['current_cursor']->current()['table'] !== 'wp_options'
    || count($plan['next_recursive_rows']) !== 2
    || $plan['next_recursive_rows'][0]['table'] !== 'wp_option_dependency'
    || $plan['next_recursive_rows'][1]['from'] !== 'fallback_option'
) {
    fwrite(STDERR, "application-pragma-fkey-recursive-schema-reparse self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && ($_SERVER['argv'][1] ?? '') === '--self-test') {
    echo "application-pragma-fkey-recursive-schema-reparse self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-pragma-fkey-recursive-schema-reparse',
    'applicationUse' => 'Previews copied Application option-dependency schema reparses where PRAGMA foreign_key_list keeps the current cursor stable and the next cursor exposes recursive self-references without ext/sqlite.',
    'currentSchema' => $plan['current_schema'],
    'nextSchema' => $plan['next_schema'],
    'currentForeignKeyCount' => count($plan['current_rows']),
    'nextForeignKeyCount' => count($plan['next_rows']),
    'nextRecursiveForeignKeyCount' => count($plan['next_recursive_rows']),
    'nextRecursiveColumns' => array_column($plan['next_recursive_rows'], 'from'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
