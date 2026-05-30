<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$catalog = new SQLitePragmaSchemaCatalog(
    [
        new SQLiteSchemaRecord(
            'table',
            'wp_options',
            'wp_options',
            2,
            'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)',
            1,
        ),
    ],
    [
        ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'wp_option_summary', 'builtin' => 0, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0],
    ],
    [
        ['name' => 'json_each'],
        ['name' => 'json_tree'],
        ['name' => 'wp_options_vtab'],
    ],
    [
        ['seq' => 0, 'name' => 'binary'],
        ['seq' => 1, 'name' => 'nocase'],
        ['seq' => 2, 'name' => 'rtrim'],
        ['seq' => 3, 'name' => 'wp_slug'],
    ],
);

$functionRows = $catalog->executeTableValuedPragma('pragma_function_list()')['rows'];
$moduleRows = $catalog->execute('PRAGMA module_list')['rows'];
$collationRows = $catalog->executeTableValuedPragma('pragma_collation_list()')['rows'];

echo json_encode(
    [
        'status' => 'ok',
        'application_path' => 'copied wp_options metadata preflight',
        'functions' => array_values(array_filter(
            $functionRows,
            static fn (array $row): bool => in_array($row['name'], ['json_extract', 'lower', 'wp_option_summary'], true),
        )),
        'modules' => array_column($moduleRows, 'name'),
        'collations' => array_column($collationRows, 'name'),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
) . PHP_EOL;
