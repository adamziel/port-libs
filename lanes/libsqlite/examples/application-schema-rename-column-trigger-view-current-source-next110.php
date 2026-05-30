<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes', CHECK(length(option_name) > 0))", 1),
    new SQLiteSchemaRecord('index', 'wp_options_name_active', 'wp_options', 4, "CREATE INDEX wp_options_name_active ON wp_options(option_name COLLATE nocase, option_id) WHERE option_name IS NOT NULL AND autoload = 'yes'", 2),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW autoloaded_options AS SELECT option_id, option_name AS option_name FROM wp_options WHERE autoload = 'yes' ORDER BY lower(option_name)", 3),
    new SQLiteSchemaRecord('trigger', 'wp_options_au', 'wp_options', 0, "CREATE TRIGGER wp_options_au AFTER UPDATE OF option_name ON wp_options WHEN old.option_name <> new.option_name BEGIN INSERT INTO wp_option_audit(name, old_name) VALUES(new.option_name, old.option_name); END", 4),
    new SQLiteSchemaRecord('view', 'postmeta_names', 'postmeta_names', 0, 'CREATE VIEW postmeta_names AS SELECT option_name FROM wp_postmeta', 5),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $schema,
    ['ALTER TABLE wp_options RENAME COLUMN option_name TO option_key'],
    110,
    'main',
    [
        ['id' => 'autoloaded-options-current-source', 'schema_cookie' => 110, 'sql' => 'SELECT option_name FROM autoloaded_options'],
        ['id' => 'stale-trigger-before-rename', 'schema_cookie' => 109, 'sql' => 'UPDATE wp_options SET option_name = ?'],
    ],
);

$byName = [];
foreach ($plan['records'] as $record) {
    $byName[$record->name] = $record->sql;
}

$preview = [
    'scenario' => 'application-schema-rename-column-trigger-view-current-source-next110',
    'operation' => $plan['operations'][0],
    'schemaCookieBefore' => $plan['before_schema_cookie'],
    'schemaCookieAfter' => $plan['after_schema_cookie'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'tableColumn' => $plan['pragma_samples']['table_xinfo:wp_options']['rows'][1]['name'],
    'rewrittenIndexSql' => $byName['wp_options_name_active'],
    'rewrittenViewSql' => $byName['autoloaded_options'],
    'rewrittenTriggerSql' => $byName['wp_options_au'],
    'unrelatedViewSql' => $byName['postmeta_names'],
    'applicationUse' => 'Preview a copied Application wp_options ALTER TABLE RENAME COLUMN migration using the current sqlite_schema source: dependent trigger/view/index SQL is rewritten, stale prepared statements are invalidated, unrelated wp_postmeta views remain untouched, and no ext/sqlite dependency is required.',
];

if (($preview['tableColumn'] ?? null) !== 'option_key') {
    fwrite(STDERR, "application-schema-rename-column-trigger-view-current-source-next110 self-test failed\n");
    exit(1);
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
