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
        $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE wp_option_audit(audit_id INTEGER PRIMARY KEY, option_name TEXT, label TEXT)', 3),
        $record('view', 'wp_options_generated_base', 'wp_options_generated_base', 0, 'CREATE VIEW wp_options_generated_base AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE option_name_lc >= "a"', 4),
    ],
    [
        'CREATE VIEW wp_options_generated_export AS SELECT option_id, option_name_lc FROM wp_options_generated_base WHERE option_value_len > 0',
        'CREATE TRIGGER wp_options_generated_export_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) SELECT option_name_lc, "nested" FROM wp_options_generated_export WHERE option_id = new.option_id; END',
    ],
    138,
    'main',
    [
        ['id' => 'stale-generated-export-reader', 'schema_cookie' => 138, 'sql' => 'SELECT * FROM wp_options_generated_export'],
        ['id' => 'fresh-audit-reader', 'schema_cookie' => 140, 'sql' => 'SELECT * FROM wp_option_audit'],
    ],
);

$summary = [
    'scenario' => 'application-schema-generated-view-index-reparse-current-source-next138',
    'applicationUse' => 'Copied wp_options export views layered on generated-column index views keep their current-source reparse metadata when plugin import triggers read the nested view.',
    'status' => $plan['status'],
    'schemaCookieBefore' => $plan['before_schema_cookie'],
    'schemaCookieAfter' => $plan['after_schema_cookie'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'nestedViewSourceViews' => $plan['operations'][0]['source_views'],
    'nestedViewGeneratedColumns' => $plan['operations'][0]['generated_column_references'],
    'nestedViewGeneratedIndexes' => $plan['operations'][0]['generated_index_references'],
    'triggerViewReferences' => $plan['operations'][1]['view_references'],
    'triggerGeneratedIndexes' => $plan['operations'][1]['generated_index_references'],
    'dependencyClosure' => 'no new support component needed; reuses native sqlite_schema DDL reparse, view dependency metadata, generated-column metadata, trigger parsing, and index-term parsing',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'ok'
        || $summary['schemaCookieAfter'] !== 140
        || $summary['invalidatedPrepared'] !== ['stale-generated-export-reader']
        || $summary['nestedViewSourceViews'] !== ['wp_options_generated_base']
        || $summary['nestedViewGeneratedColumns'] !== ['option_name_lc', 'option_value_len']
        || $summary['nestedViewGeneratedIndexes'] !== ['wp_options_generated_lookup']
        || $summary['triggerViewReferences'] !== ['view:wp_options_generated_export']
        || $summary['triggerGeneratedIndexes'] !== ['wp_options_generated_lookup']
    ) {
        fwrite(STDERR, "application-schema-generated-view-index-reparse-current-source-next138 self-test failed\n");
        exit(1);
    }

    echo "application-schema-generated-view-index-reparse-current-source-next138 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
