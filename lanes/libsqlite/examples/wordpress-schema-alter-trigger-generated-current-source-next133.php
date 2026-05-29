<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT NOT NULL, autoload TEXT DEFAULT "yes", option_slug TEXT AS (lower(option_name)) STORED)', 1),
    $record('trigger', 'wp_options_generated_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_generated_au AFTER UPDATE OF option_value_len ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, old_slug, value_len) VALUES(new.option_id, old.option_slug, new.option_value_len); END', 2),
    $record('trigger', 'wp_options_plain_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_plain_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END', 3),
];

$plan = SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan::plan(
    $records,
    ['ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 0)'],
    133,
    'main',
    [
        ['id' => 'wp-option-update-trigger', 'schema_cookie' => 133, 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_name = ?'],
    ],
    [
        'wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ],
    ],
);

$summary = [
    'scenario' => 'wordpress-schema-alter-trigger-generated-current-source-next133',
    'wordpressUse' => 'After a copied WordPress wp_options migration adds a generated column used by triggers, stale prepared updates must reparse from the current sqlite_schema source before firing trigger bytecode.',
    'schemaCookie' => [$plan['schema_cookie_before'], $plan['schema_cookie_after']],
    'generatedAdded' => $plan['generated_added'],
    'reparseTriggers' => $plan['reparse_triggers'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'currentSourceRequired' => $plan['current_source_required'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['schemaCookie'] === [133, 134]);
    assert($summary['generatedAdded'] === ['option_value_len']);
    assert($summary['reparseTriggers'] === ['wp_options_generated_au']);
    assert($summary['invalidatedPrepared'] === ['wp-option-update-trigger']);
    echo "wordpress-schema-alter-trigger-generated-current-source-next133 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
