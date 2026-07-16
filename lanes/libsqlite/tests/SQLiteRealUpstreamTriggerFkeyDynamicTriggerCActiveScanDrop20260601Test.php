<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

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

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test';

$tests = [
    'real upstream triggerC active scan drop cites source block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test triggerC-12.1', $source);
        $t->contains('do_test triggerC-12.2', $source);
        $t->contains('CREATE TRIGGER tr1 AFTER INSERT ON t1 BEGIN SELECT 1 ; END ;', $source);
        $t->contains('if {$a == 3} { execsql { DROP TRIGGER tr1 } }', $source);
    },
];

$canonical = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCDropTriggerDuringActiveScanPlan(
    [
        ['a' => 1, 'b' => 2],
        ['a' => 3, 'b' => 4],
        ['a' => 5, 'b' => 6],
    ],
    3
);

foreach ([
    'source' => 'triggerC.test triggerC-12.1..12.2',
    'scenarios.0' => 'triggerC-12.1',
    'scenarios.1' => 'triggerC-12.2',
    'operation' => 'drop-trigger-during-active-table-scan',
    'status' => 'commit-ok',
    'table_name' => 't1',
    'trigger_name' => 'tr1',
    'select_sql' => 'SELECT * FROM t1',
    'drop_statement' => 'DROP TRIGGER tr1',
    'initial_sqlite_master_count' => 2,
    'final_sqlite_master_count' => 1,
    'drop_at_a' => 3,
    'drop_scan_ordinal' => 1,
    'drop_event.row.a' => 3,
    'drop_event.row.b' => 4,
    'drop_event.sqlite_master_count_before_drop' => 2,
    'drop_event.sqlite_master_count_after_drop' => 1,
    'scan_rows.1.drop_trigger_executed' => true,
    'scan_rows.2.trigger_present_before_row' => false,
    'active_scan_completed' => true,
    'trigger_catalog_removed' => true,
    'table_catalog_preserved' => true,
    'schema_cookie_incremented_by_drop' => true,
    'dependencies.0' => 'sqlite-triggerC-drop-trigger-during-active-table-scan',
    'dependencies.1' => 'sqlite-triggerC-active-table-cursor-survives-trigger-catalog-delete',
    'dependencies.2' => 'sqlite-triggerC-drop-trigger-removes-only-trigger-catalog-entry',
] as $path => $expected) {
    $tests[sprintf('real upstream triggerC active scan drop canonical %s', (string) $path)] = static function (TestRunner $t) use ($canonical, $value, $path, $expected): void {
        $t->same($expected, $value($canonical(), (string) $path));
    };
}

for ($i = 1; $i <= 170; ++$i) {
    $rowCount = 3 + ($i % 5);
    $base = $i * 100;
    $rows = [];
    for ($ordinal = 0; $ordinal < $rowCount; ++$ordinal) {
        $rows[] = [
            'a' => $base + ($ordinal * 2) + 1,
            'b' => $base + ($ordinal * 2) + 2,
        ];
    }

    $dropIndex = $i % $rowCount;
    $dropAtA = $rows[$dropIndex]['a'];
    $tableName = 'app_scan_' . $i;
    $triggerName = 'app_scan_tr_' . $i;
    $expectedAValues = array_values(array_column($rows, 'a'));
    $expectedBValues = array_values(array_column($rows, 'b'));
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCDropTriggerDuringActiveScanPlan(
        $rows,
        $dropAtA,
        $tableName,
        $triggerName
    );

    $paths = [
        'source' => 'triggerC.test triggerC-12.1..12.2',
        'scenarios.0' => 'triggerC-12.1',
        'scenarios.1' => 'triggerC-12.2',
        'operation' => 'drop-trigger-during-active-table-scan',
        'status' => 'commit-ok',
        'table_name' => $tableName,
        'trigger_name' => $triggerName,
        'select_sql' => 'SELECT * FROM ' . $tableName,
        'drop_statement' => 'DROP TRIGGER ' . $triggerName,
        'initial_sqlite_master_count' => 2,
        'final_sqlite_master_count' => 1,
        'catalog_before.0.type' => 'table',
        'catalog_before.0.name' => $tableName,
        'catalog_before.1.type' => 'trigger',
        'catalog_before.1.name' => $triggerName,
        'catalog_before.1.tbl_name' => $tableName,
        'catalog_after.0.type' => 'table',
        'catalog_after.0.name' => $tableName,
        'initial_table_row_count' => $rowCount,
        'scanned_row_count' => $rowCount,
        'scanned_a_values' => $expectedAValues,
        'scanned_b_values' => $expectedBValues,
        'drop_at_a' => $dropAtA,
        'drop_scan_ordinal' => $dropIndex,
        'drop_event.scan_ordinal' => $dropIndex,
        'drop_event.row.a' => $dropAtA,
        'drop_event.row.b' => $rows[$dropIndex]['b'],
        'drop_event.drop_statement' => 'DROP TRIGGER ' . $triggerName,
        'drop_event.sqlite_master_count_before_drop' => 2,
        'drop_event.sqlite_master_count_after_drop' => 1,
        'drop_event.active_scan_continues' => true,
        'scan_rows.' . $dropIndex . '.a' => $dropAtA,
        'scan_rows.' . $dropIndex . '.b' => $rows[$dropIndex]['b'],
        'scan_rows.' . $dropIndex . '.trigger_present_before_row' => true,
        'scan_rows.' . $dropIndex . '.drop_trigger_executed' => true,
        'scan_rows.' . $dropIndex . '.trigger_present_after_row' => false,
        'active_scan_completed' => true,
        'active_scan_row_order_preserved' => true,
        'trigger_catalog_removed' => true,
        'table_catalog_preserved' => true,
        'schema_cookie_before' => 1,
        'schema_cookie_after' => 2,
        'schema_cookie_incremented_by_drop' => true,
        'dependencies.0' => 'sqlite-triggerC-drop-trigger-during-active-table-scan',
        'dependencies.1' => 'sqlite-triggerC-active-table-cursor-survives-trigger-catalog-delete',
        'dependencies.2' => 'sqlite-triggerC-drop-trigger-removes-only-trigger-catalog-entry',
    ];
    if ($dropIndex + 1 < $rowCount) {
        $paths['scan_rows.' . ($dropIndex + 1) . '.trigger_present_before_row'] = false;
    }

    foreach ($paths as $path => $expected) {
        $tests[sprintf('real upstream triggerC active scan drop dynamic %03d %s', $i, (string) $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream triggerC active scan drop rejects empty rows'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCDropTriggerDuringActiveScanPlan([], 1));
$tests['real upstream triggerC active scan drop rejects missing row column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCDropTriggerDuringActiveScanPlan([['a' => 1]], 1));
$tests['real upstream triggerC active scan drop rejects non integer row'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCDropTriggerDuringActiveScanPlan([['a' => '1', 'b' => 2]], 1));
$tests['real upstream triggerC active scan drop rejects absent drop target'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCDropTriggerDuringActiveScanPlan([['a' => 1, 'b' => 2]], 3));

return $tests;
