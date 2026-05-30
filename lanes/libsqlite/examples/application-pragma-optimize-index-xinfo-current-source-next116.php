<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaOptimizeIndexXinfoCurrentSourceYield;
use PortLibs\LibSqlite\SQLitePragmaOptimizePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
        $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name COLLATE NOCASE DESC)', 2),
        $record('table', 'wp_postmeta', 'wp_postmeta', 4, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT, meta_value TEXT)', 3),
        $record('index', 'wp_postmeta_key', 'wp_postmeta', 5, 'CREATE INDEX wp_postmeta_key ON wp_postmeta(meta_key)', 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_name_temp', 'wp_options', 7, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name, length(option_value) COLLATE BINARY DESC)', 2),
    ],
);

$tables = [
    [
        'schema' => 'main',
        'name' => 'wp_options',
        'rowCount' => 12000,
        'statRowCount' => 8000,
        'touched' => true,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-wp-options-v41',
        'expectedSourceId' => 'main-wp-options-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_postmeta',
        'rowCount' => 240000,
        'statRowCount' => 240000,
        'touched' => false,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-wp-postmeta-v41',
        'expectedSourceId' => 'main-wp-postmeta-v41',
    ],
];

$page = SQLitePragmaOptimizeIndexXinfoCurrentSourceYield::page(
    $catalog,
    [
        'PRAGMA index_xinfo(wp_options_name_temp)',
        'PRAGMA main.index_xinfo(wp_options_name_main)',
        'PRAGMA index_xinfo(wp_postmeta_key)',
    ],
    new SQLitePragmaOptimizePlan(),
    'PRAGMA optimize',
    $tables,
    0,
    10,
);

$summary = [
    'scenario' => 'copied Application PRAGMA optimize joined to current PRAGMA index_xinfo metadata',
    'applicationUse' => 'Stream copied wp_options/wp_postmeta index metadata while preserving PRAGMA optimize decisions and current-source cursor tokens during import or repair without ext/sqlite.',
    'source_id' => $page['source_id'],
    'optimize' => $page['optimize'],
    'rows' => array_map(static fn (array $row): array => [
        'schema' => $row['schema'],
        'index' => $row['index'],
        'table' => $row['table'],
        'key_names' => $row['key_names'],
        'collations' => $row['collations'],
        'optimize_action' => $row['optimize_action'],
        'optimize_reason' => $row['optimize_reason'],
    ], $page['rows']),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['rows'][0]['schema'] === 'temp');
    assert($summary['rows'][1]['optimize_action'] === 'analyze');
    assert($summary['rows'][2]['optimize_reason'] === 'up-to-date');
    echo "application-pragma-optimize-index-xinfo-current-source-next116 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
