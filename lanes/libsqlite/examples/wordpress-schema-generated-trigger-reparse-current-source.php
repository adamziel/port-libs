<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaGeneratedTriggerReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$current = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT, option_slug TEXT AS (lower(option_name)) VIRTUAL)", 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id INTEGER, option_slug TEXT, option_value_len INTEGER, bucket TEXT)', 2),
    new SQLiteSchemaRecord('trigger', 'wp_options_audit_update', 'wp_options', 0, "CREATE TRIGGER wp_options_audit_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_slug, option_value_len, bucket) VALUES(new.option_id, new.option_slug, new.option_value_len, old.option_bucket); END", 3),
];

$next = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT, option_slug TEXT AS (lower(option_name)) STORED, option_value_len INTEGER AS (length(option_value)) VIRTUAL, option_bucket TEXT AS (CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END) VIRTUAL)", 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id INTEGER, option_slug TEXT, option_value_len INTEGER, bucket TEXT)', 2),
    new SQLiteSchemaRecord('trigger', 'wp_options_audit_update', 'wp_options', 0, "CREATE TRIGGER wp_options_audit_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_slug, option_value_len, bucket) VALUES(new.option_id, new.option_slug, new.option_value_len, old.option_bucket); END", 3),
];

$plan = SQLiteSchemaGeneratedTriggerReparsePlan::currentNext($current, $next, 'wp_options_audit_update', [
    'schema_version_before' => 106,
    'schema_version_after' => 107,
]);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'reparse-required' || $plan['generatedAdded'] !== ['option_value_len', 'option_bucket']) {
        fwrite(STDERR, "wordpress-schema-generated-trigger-reparse-current-source self-test failed\n");
        exit(1);
    }
    echo "wordpress-schema-generated-trigger-reparse-current-source self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'copied wp_options generated-column trigger reparse current source',
    'wordpressUse' => 'Detect when migration DDL adds generated columns used by an existing wp_options audit trigger so the prepared trigger source is reparsed before the next import write.',
    'status' => $plan['status'],
    'requiresReparse' => $plan['requiresReparse'],
    'schemaVersions' => [$plan['schema_version_before'], $plan['schema_version_after']],
    'generatedAdded' => $plan['generatedAdded'],
    'generatedReferences' => $plan['generatedReferences'],
    'missingBefore' => [
        'new' => $plan['current']['missingNew'],
        'old' => $plan['current']['missingOld'],
    ],
    'missingAfter' => [
        'new' => $plan['next']['missingNew'],
        'old' => $plan['next']['missingOld'],
    ],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
