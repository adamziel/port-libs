<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream fkey8 replace corpus cites deferred counter section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE INTO p1 VALUES(2'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY constraint failed'));
    },
    'real upstream fkey8 replace corpus cites trigger replace section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER p3d AFTER DELETE ON p3'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM t2 WHERE a=1'));
    },
];

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

$parents = static fn (int $offset): array => [
    ['id' => 1 + $offset, 'label' => 'one-' . $offset],
    ['id' => 2 + $offset, 'label' => 'two-' . $offset],
];
$children = static fn (int $offset): array => [
    ['id' => 10 + $offset, 'parent_id' => 1 + $offset, 'label' => 'child-one-' . $offset],
    ['id' => 20 + $offset, 'parent_id' => 2 + $offset, 'label' => 'child-two-' . $offset],
];

for ($i = 1; $i <= 130; ++$i) {
    $offset = $i * 100;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceForeignKeyCounter(
        $parents($offset),
        $children($offset),
        ['kind' => 'replace-parent-after-delete', 'parent_id' => 1 + $offset, 'replace_parent_id' => 2 + $offset],
    );
    $case = 'real upstream fkey8 without rowid replace parent violation dynamic ' . $i;
    foreach ([
        'source' => 'fkey8.test fkey8-2.1.0..2.1.2',
        'operation' => 'without-rowid-replace-foreign-key-counter',
        'status' => 'constraint-failed',
        'kind' => 'replace-parent-after-delete',
        'without_rowid' => true,
        'deferred_counter_delta' => 1,
        'implicit_delete_count' => 1,
        'implicit_deletes.0.reason' => 'replace-conflict',
        'violations.0.missing_parent_id' => 1 + $offset,
        'rollback_parent_ids' => [1 + $offset, 2 + $offset],
        'rollback_child_parent_ids' => [1 + $offset, 2 + $offset],
        'dependencies.0' => 'sqlite-fkey8-without-rowid-replace-updates-deferred-counter',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 130; ++$i) {
    $offset = 20000 + ($i * 100);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceForeignKeyCounter(
        [],
        [['id' => 13 + $offset, 'parent_id' => 13 + $offset, 'label' => 'unmatched-' . $i]],
        ['kind' => 'replace-child-cycle', 'child_id' => 13 + $offset, 'replace_parent_id' => null],
    );
    $case = 'real upstream fkey8 replace child clears deferred violation dynamic ' . $i;
    foreach ([
        'source' => 'fkey8.test fkey8-2.2.0..2.2.1',
        'status' => 'commit-ok',
        'kind' => 'replace-child-cycle',
        'deferred_counter_delta' => 0,
        'implicit_delete_count' => 1,
        'implicit_deletes.0.table' => 'child',
        'child_parent_ids' => [null],
        'violations' => [],
        'rollback_parent_ids' => [],
        'dependencies.1' => 'sqlite-fkey8-replace-child-conflict-can-clear-deferred-counter',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 130; ++$i) {
    $offset = 40000 + ($i * 100);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceForeignKeyCounter(
        $parents($offset),
        $children($offset),
        ['kind' => 'delete-parent-trigger-replace', 'parent_id' => 1 + $offset, 'replace_parent_id' => 2 + $offset, 'trigger_replaces_parent' => true],
    );
    $case = 'real upstream fkey8 trigger replace preserves failure dynamic ' . $i;
    foreach ([
        'source' => 'fkey8.test fkey8-2.3.0..3.1',
        'status' => 'constraint-failed',
        'kind' => 'delete-parent-trigger-replace',
        'deferred_counter_delta' => 1,
        'implicit_delete_count' => 1,
        'implicit_deletes.0.reason' => 'trigger-replace-conflict',
        'trigger_effects.0.trigger' => 'after_parent_delete_replace',
        'trigger_effects.0.old_id' => 1 + $offset,
        'violations.0.child_id' => 10 + $offset,
        'violations.0.phase' => 'deferred-commit',
        'dependencies.2' => 'sqlite-fkey8-triggered-replace-delete-preserves-fk-failure',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream fkey8 replace counter rejects unsupported kind'] = static function (TestRunner $t) use ($parents, $children): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceForeignKeyCounter(
        $parents(0),
        $children(0),
        ['kind' => 'unsupported'],
    ));
};

return $tests;
