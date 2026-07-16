<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexTableInfoAnalysis;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL DEFAULT '', option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
        $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name COLLATE NOCASE DESC)', 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(option_name TEXT NOT NULL, option_value TEXT DEFAULT '{}', autoload TEXT, option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL)", 1),
        $record('index', 'wp_options_name_temp', 'wp_options', 5, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name, length(option_value) DESC)', 2),
    ],
);
$catalog->attach('archive', '/srv/archive.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 6, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT NOT NULL, meta_value TEXT)', 1),
    $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 7, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)', 2),
]);

$sql = [
    'PRAGMA table_info(wp_options)',
    'PRAGMA table_xinfo(wp_options)',
    'PRAGMA index_xinfo(wp_options_name_temp)',
    'pragma_table_info("wp_sitemeta")',
    'pragma_index_xinfo("wp_sitemeta_key")',
];

$first = SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($catalog, $sql, 0, 3);
$second = SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($catalog, $sql, 3, 3, [
    'source_id' => $first['source_id'],
    'next_offset' => $first['next_offset'],
]);

$output = [
    'scenario' => 'application-pragma-index-xinfo-tableinfo-analysis-current-source-next108',
    'applicationUse' => 'Resume copied Application SQLite schema analysis for wp_options temp import tables and attached site metadata only while PRAGMA table_info/table_xinfo/index_xinfo current-source rows are unchanged.',
    'source_id' => $first['source_id'],
    'first_page_pragmas' => array_column($first['analyses'], 'pragma'),
    'second_page_pragmas' => array_column($second['analyses'], 'pragma'),
    'temp_table_visible_columns' => $first['analyses'][0]['visible_columns'],
    'temp_table_generated_columns' => $first['analyses'][1]['generated_columns'],
    'temp_index_expression_columns' => $first['analyses'][2]['expression_columns'],
    'attached_sitemeta_columns' => $second['analyses'][0]['row_names'],
    'attached_sitemeta_index_columns' => $second['analyses'][1]['row_names'],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $output['temp_table_visible_columns'] !== 3
        || $output['temp_table_generated_columns'] !== 1
        || $output['temp_index_expression_columns'] !== 1
        || $output['attached_sitemeta_columns'] !== ['meta_id', 'meta_key', 'meta_value']
        || $output['attached_sitemeta_index_columns'] !== ['meta_key', null]
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-tableinfo-analysis-current-source-next108 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-tableinfo-analysis-current-source-next108 self-test passed\n");
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT) . "\n";
