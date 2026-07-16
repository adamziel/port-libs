<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$plan = SQLiteSchemaDdlReparsePlan::apply(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL, option_value_len INTEGER AS (length(option_value)) VIRTUAL)', 1),
        $record('index', 'wp_options_generated_lookup', 'wp_options', 3, 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, option_value_len) WHERE option_name_lc >= "a"', 2),
        $record('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
    ],
    [
        'CREATE VIEW wp_options_generated_export AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE option_name_lc >= "a"',
        'CREATE VIEW wp_options_generated_star AS SELECT * FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"',
    ],
    132,
    'main',
    [
        ['id' => 'stale-generated-view-reader', 'schema_cookie' => 132, 'sql' => 'SELECT * FROM wp_options_generated_export'],
        ['id' => 'fresh-schema-reader', 'schema_cookie' => 134, 'sql' => 'SELECT * FROM wp_options_generated_star'],
    ],
);

$summary = [
    'scenario' => 'application-schema-generated-index-view-reparse-current-source-next132',
    'applicationUse' => 'Copied wp_options export views that force a generated-column expression index are marked as current-source reparse points before Application import diagnostics resume stale prepared statements.',
    'status' => $plan['status'],
    'schemaCookieBefore' => $plan['before_schema_cookie'],
    'schemaCookieAfter' => $plan['after_schema_cookie'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'generatedViewCurrentSource' => $plan['operations'][0]['current_source_reparse'],
    'generatedColumns' => $plan['operations'][0]['generated_column_references'],
    'generatedIndexes' => $plan['operations'][0]['generated_index_references'],
    'starExpansionRecords' => $plan['operations'][1]['star_expansion_records'],
    'dependencyClosure' => 'no new support component needed; reuses native sqlite_schema DDL reparse, PRAGMA catalog, generated-column metadata, and index-term parsing',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'ok'
        || $summary['schemaCookieAfter'] !== 134
        || $summary['invalidatedPrepared'] !== ['stale-generated-view-reader']
        || $summary['generatedColumns'] !== ['option_name_lc', 'option_value_len']
        || $summary['generatedIndexes'] !== ['wp_options_generated_lookup']
        || $summary['starExpansionRecords'] !== ['view:wp_options_generated_star']
    ) {
        fwrite(STDERR, "application-schema-generated-index-view-reparse-current-source-next132 self-test failed\n");
        exit(1);
    }

    echo "application-schema-generated-index-view-reparse-current-source-next132 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
