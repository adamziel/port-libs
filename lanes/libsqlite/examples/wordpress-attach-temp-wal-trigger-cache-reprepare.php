<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text)', 2),
    $record('trigger', 'options_after_update', 'wp_options', 0, "CREATE TRIGGER options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value) VALUES(new.option_id, 'main', old.option_value, new.option_value); END", 3),
], [
    $record('table', 'wp_option_audit', 'wp_option_audit', 10, 'CREATE TEMP TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text)', 4),
    $record('trigger', 'temp_options_after_update', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_after_update AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value) VALUES(new.option_id, 'temp', old.option_value, new.option_value); END", 5),
]);

$nextMain = [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, touched integer)', 20),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text, request_id text)', 21),
    $record('trigger', 'options_after_update', 'wp_options', 0, "CREATE TRIGGER options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value, request_id) VALUES(new.option_id, 'main-next', old.option_value, new.option_value, 'next'); END", 22),
];

$plan = SQLiteAttachWalTempViewCachePlan::triggerProgramCacheRepreparePlan(
    $catalog,
    ['options_after_update', 'temp_options_after_update'],
    ['main' => $nextMain],
    [
        'main' => ['schema_cookie' => 11, 'wal_schema_cookie' => 12],
        'temp' => ['schema_cookie' => 4],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-temp-wal-trigger-cache-reprepare');
    assert($plan['active_current_programs_kept'] === true);
    assert($plan['reprepare_triggers'] === ['options_after_update']);
    assert($plan['triggers']['options_after_update']['before']['trigger']['sql'] !== $plan['triggers']['options_after_update']['after']['trigger']['sql']);
    assert($plan['triggers']['temp_options_after_update']['next_requires_reprepare'] === false);
    echo "wordpress-attach-temp-wal-trigger-cache-reprepare self-test passed\n";
    return;
}

echo json_encode([
    'operation' => $plan['operation'],
    'changed_schemas' => $plan['changed_schemas'],
    'current_programs_kept' => $plan['active_current_programs_kept'],
    'reprepare_triggers' => $plan['reprepare_triggers'],
    'main_trigger_before_source' => $plan['triggers']['options_after_update']['before']['trigger']['schema'],
    'main_trigger_next_reprepare' => $plan['triggers']['options_after_update']['next_requires_reprepare'],
    'temp_trigger_next_reprepare' => $plan['triggers']['temp_options_after_update']['next_requires_reprepare'],
], JSON_PRETTY_PRINT) . PHP_EOL;
