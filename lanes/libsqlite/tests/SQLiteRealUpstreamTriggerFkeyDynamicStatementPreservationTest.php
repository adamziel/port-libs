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

$tests = [
    'real upstream trigger1 statement preservation cites delete trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.10'));
        $t->true(is_string($source) && str_contains($source, 'delete from t1 WHERE a=old.a+2;'));
        $t->true(is_string($source) && str_contains($source, 'delete from t1 where a=1 OR a=3;'));
    },
    'real upstream trigger1 statement preservation cites update trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.11'));
        $t->true(is_string($source) && str_contains($source, "update t1 set b='x-' || b where a=1 OR a=3;"));
    },
];

for ($i = 1; $i <= 125; ++$i) {
    $base = $i * 10;
    $rows = [
        ['id' => $base + 1, 'a' => 1, 'b' => 2, 'c' => 3],
        ['id' => $base + 2, 'a' => 2, 'b' => 3, 'c' => 4],
        ['id' => $base + 3, 'a' => 3, 'b' => 4, 'c' => 5],
        ['id' => $base + 4, 'a' => 4, 'b' => 5, 'c' => 6],
    ];
    $deleteTrigger = [
        'event' => 'delete',
        'match_column' => 'a',
        'match_value' => 1,
        'delete_column' => 'a',
        'delete_value' => $i % 2 === 0 ? 2 : 3,
        'name' => 'after_delete_preserve_statement_' . $i,
    ];
    $updateTrigger = [
        'event' => 'update',
        'match_column' => 'a',
        'match_value' => 1,
        'delete_column' => 'a',
        'delete_value' => $i % 2 === 0 ? 2 : 3,
        'name' => 'after_update_preserve_statement_' . $i,
    ];
    $updateAssignments = [
        'a' => static fn (array $old): int => (int) $old['a'] + 10,
        'c' => static fn (array $old): int => (int) $old['c'] + $i,
    ];
    $deletePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deleteWithAfterTrigger(
        $rows,
        'a',
        1,
        $deleteTrigger
    );
    $updatePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::updateWithAfterTrigger(
        $rows,
        'a',
        1,
        $updateAssignments,
        $updateTrigger
    );
    $deletedByTrigger = $i % 2 === 0 ? $base + 2 : $base + 3;
    $remainingAfterDelete = $i % 2 === 0
        ? [$base + 3, $base + 4]
        : [$base + 2, $base + 4];
    $remainingAfterUpdate = $i % 2 === 0
        ? [$base + 1, $base + 3, $base + 4]
        : [$base + 1, $base + 2, $base + 4];
    $case = 'trigger1 statement preservation dynamic ' . $i;

    foreach ([
        'source' => 'trigger1.test trigger1-1.10',
        'operation' => 'delete-statement-with-after-delete-trigger',
        'status' => 'commit-ok',
        'trigger' => 'after_delete_preserve_statement_' . $i,
        'outer_deleted_ids' => [$base + 1],
        'trigger_deleted_ids' => [$deletedByTrigger],
        'remaining_ids' => $remainingAfterDelete,
        'outer_delete_count' => 1,
        'trigger_delete_count' => 1,
        'total_changes' => 2,
        'statement_delete_preserved' => true,
        'dependencies.0' => 'sqlite-trigger1-delete-trigger-does-not-corrupt-outer-delete',
        'dependencies.1' => 'sqlite-row-trigger-old-row-scope',
    ] as $path => $expected) {
        $tests[$case . ' delete ' . $path] = static function (TestRunner $t) use ($deletePlan, $path, $expected, $value): void {
            $t->same($expected, $value($deletePlan(), (string) $path));
        };
    }

    foreach ([
        'source' => 'trigger1.test trigger1-1.11',
        'operation' => 'update-statement-with-after-update-trigger',
        'status' => 'commit-ok',
        'trigger' => 'after_update_preserve_statement_' . $i,
        'outer_updated_ids' => [$base + 1],
        'trigger_deleted_ids' => [$deletedByTrigger],
        'remaining_ids' => $remainingAfterUpdate,
        'updated_rows.0.a' => 11,
        'updated_rows.0.c' => 3 + $i,
        'outer_update_count' => 1,
        'trigger_delete_count' => 1,
        'total_changes' => 2,
        'statement_update_preserved' => true,
        'dependencies.0' => 'sqlite-trigger1-update-trigger-does-not-corrupt-outer-update',
        'dependencies.1' => 'sqlite-row-trigger-old-row-scope',
    ] as $path => $expected) {
        $tests[$case . ' update ' . $path] = static function (TestRunner $t) use ($updatePlan, $path, $expected, $value): void {
            $t->same($expected, $value($updatePlan(), (string) $path));
        };
    }
}

return $tests;
