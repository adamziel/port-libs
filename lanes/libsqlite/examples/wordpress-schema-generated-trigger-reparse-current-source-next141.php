<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$plan = SQLiteSchemaDdlReparsePlan::apply(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL, option_value_len INTEGER AS (length(option_value)) STORED)', 1),
        $record('index', 'wp_options_generated_lookup', 'wp_options', 3, 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, option_value_len) WHERE option_name_lc >= "a"', 2),
        $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE wp_option_audit(audit_id INTEGER PRIMARY KEY, option_id INTEGER, option_name TEXT, label TEXT)', 3),
        $record('view', 'wp_autoloaded_generated_options', 'wp_autoloaded_generated_options', 0, 'CREATE VIEW wp_autoloaded_generated_options AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"', 4),
    ],
    [
        'CREATE TRIGGER wp_options_generated_view_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT option_id, option_name_lc, "autoload" FROM wp_autoloaded_generated_options WHERE option_id = new.option_id; END',
    ],
    141,
    'main',
    [
        ['id' => 'stale-import-insert', 'schema_cookie' => 141, 'sql' => 'INSERT INTO wp_options(option_name, option_value) VALUES (?, ?)'],
        ['id' => 'fresh-audit-reader', 'schema_cookie' => 142, 'sql' => 'SELECT * FROM wp_option_audit'],
    ],
);

$summary = [
    'scenario' => 'wordpress-schema-generated-trigger-reparse-current-source-next141',
    'wordpressUse' => 'Copied wp_options imports often add audit triggers over compatibility views; the trigger reparse must keep generated-column dependencies from the view current source before stale inserts resume.',
    'status' => $plan['status'],
    'schemaCookieBefore' => $plan['before_schema_cookie'],
    'schemaCookieAfter' => $plan['after_schema_cookie'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'triggerViewReferences' => $plan['operations'][0]['view_references'],
    'triggerGeneratedColumns' => $plan['operations'][0]['generated_column_references'],
    'triggerGeneratedIndexes' => $plan['operations'][0]['generated_index_references'],
    'currentSourceReparse' => $plan['operations'][0]['current_source_reparse'],
    'dependencyClosure' => 'no new support component needed; reuses native sqlite_schema DDL reparse, trigger parsing, view dependency metadata, generated-column metadata, and index-term parsing',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'ok'
        || $summary['schemaCookieAfter'] !== 142
        || $summary['invalidatedPrepared'] !== ['stale-import-insert']
        || $summary['triggerViewReferences'] !== ['view:wp_autoloaded_generated_options']
        || $summary['triggerGeneratedColumns'] !== ['option_name_lc', 'option_value_len']
        || $summary['triggerGeneratedIndexes'] !== []
        || $summary['currentSourceReparse'] !== true
    ) {
        fwrite(STDERR, "wordpress-schema-generated-trigger-reparse-current-source-next141 self-test failed\n");
        exit(1);
    }

    echo "wordpress-schema-generated-trigger-reparse-current-source-next141 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
