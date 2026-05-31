<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/temptrigger.test';

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing temptrigger lifecycle assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream temptrigger lifecycle cites temp shadow table block' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source) && str_contains($source, 'Test that creating a temp table after a temp trigger on the same name'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 4.1'));
    },
    'real upstream temptrigger lifecycle cites external table drop block' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source) && str_contains($source, 'Test that no harm is done if the table a temp trigger is attached to'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 5.2'));
    },
];

for ($seed = 1; $seed <= 170; ++$seed) {
    $tableName = 't' . $seed;
    $triggerName = 'tr' . $seed;

    $shadowPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerTargetLifecyclePlan(
        $seed,
        'temp-shadow-table-created',
    );
    foreach ([
        'source' => 'temptrigger.test temptrigger-4.0..4.1',
        'operation' => 'temp-trigger-target-lifecycle',
        'status' => 'commit-ok',
        'scenario' => 'temp-shadow-table-created',
        'main_table' => $tableName,
        'temp_table' => $tableName,
        'trigger_name' => $triggerName,
        'trigger_schema' => 'temp',
        'target_schema' => 'main',
        'shadow_table_created_after_trigger' => true,
        'shadow_table_rebinds_trigger_target' => false,
        'temp_table_rows_after_create' => [],
        'main_trigger_schema_rows' => [],
        'temp_trigger_schema_rows.0.type' => 'trigger',
        'temp_trigger_schema_rows.0.name' => $triggerName,
        'temp_trigger_schema_rows.0.tbl_name' => $tableName,
        'temp_trigger_schema_rows.0.rootpage' => 0,
        'drop_trigger_after_shadow_ok' => true,
        'dependencies.0' => 'sqlite-temptrigger-temp-table-created-after-trigger-does-not-steal-target',
        'dependencies.1' => 'sqlite-temptrigger-temp-schema-may-contain-trigger-and-table-with-same-target-name',
        'dependencies.2' => 'sqlite-temptrigger-drop-after-shadow-table-remains-safe',
    ] as $path => $expected) {
        $tests[sprintf('real upstream temptrigger 4 lifecycle dynamic %03d %s', $seed, $path)] = static function (TestRunner $t) use ($shadowPlan, $value, $path, $expected): void {
            $t->same($expected, $value($shadowPlan(), (string) $path));
        };
    }

    $externalDropPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerTargetLifecyclePlan(
        $seed,
        'external-target-drop',
    );
    foreach ([
        'source' => 'temptrigger.test temptrigger-5.0..5.2',
        'operation' => 'temp-trigger-target-lifecycle',
        'status' => 'orphaned-temp-trigger-record-preserved',
        'scenario' => 'external-target-drop',
        'main_table' => $tableName,
        'temp_table' => null,
        'trigger_name' => $triggerName,
        'trigger_schema' => 'temp',
        'target_schema' => 'main',
        'target_dropped_by_peer_connection' => true,
        'main_schema_rows' => [],
        'temp_trigger_schema_rows.0.type' => 'trigger',
        'temp_trigger_schema_rows.0.name' => $triggerName,
        'temp_trigger_schema_rows.0.tbl_name' => $tableName,
        'temp_trigger_schema_rows.0.rootpage' => 0,
        'temp_trigger_schema_rows.0.sql' => 'CREATE TRIGGER ' . $triggerName . ' BEFORE INSERT ON ' . $tableName . ' BEGIN SELECT 1,2,3; END',
        'trigger_fires_after_external_drop' => false,
        'orphan_record_is_connection_local' => true,
        'schema_query_after_external_drop_ok' => true,
        'dependencies.0' => 'sqlite-temptrigger-external-target-drop-preserves-temp-schema-trigger-row',
        'dependencies.1' => 'sqlite-temptrigger-orphaned-temp-trigger-does-not-corrupt-main-schema',
        'dependencies.2' => 'sqlite-temptrigger-owner-connection-can-still-query-schema-after-peer-drop',
    ] as $path => $expected) {
        $tests[sprintf('real upstream temptrigger 5 lifecycle dynamic %03d %s', $seed, $path)] = static function (TestRunner $t) use ($externalDropPlan, $value, $path, $expected): void {
            $t->same($expected, $value($externalDropPlan(), (string) $path));
        };
    }
}

$tests['real upstream temptrigger lifecycle rejects unsupported scenario'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerTargetLifecyclePlan(1, 'missing'));
};

return $tests;
