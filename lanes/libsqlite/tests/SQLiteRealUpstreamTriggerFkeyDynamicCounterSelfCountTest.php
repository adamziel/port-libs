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

$parents = [
    ['id' => 1, 'label' => 'one'],
    ['id' => 2, 'label' => 'two'],
];
$children = [
    ['id' => 'neung', 'parent_id' => 1],
    ['id' => 'song', 'parent_id' => 2],
];
$childrenWithViolation = [
    ['id' => 'neung', 'parent_id' => 1],
    ['id' => 'song', 'parent_id' => 2],
    ['id' => 'see', 'parent_id' => 4],
];

$tests = [
    'real upstream fkey2 counter scan cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-15.1.1'));
        $t->true(is_string($source) && str_contains($source, 'execsqlS { INSERT INTO pp VALUES(3'));
    },
    'real upstream fkey2 self reference cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-16.1.$tn.1'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE self SET a = 17, b = 17'));
    },
    'real upstream fkey2 count changes cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-17.1.1'));
        $t->true(is_string($source) && str_contains($source, 'rows modified by FK actions are not counted'));
    },
];

for ($i = 1; $i <= 120; ++$i) {
    $operation = ['insert-parent', 'delete-child', 'delete-parent'][$i % 3];
    $outstanding = $i % 2 === 0;
    $rows = $outstanding ? $childrenWithViolation : $children;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredCounterScanPlan(
        $parents,
        $rows,
        $operation,
        $outstanding
    );
    $expectedSearches = match ($operation) {
        'insert-parent' => $outstanding ? 2 : 0,
        'delete-child' => $outstanding ? 2 : 1,
        default => 1,
    };
    $case = 'real upstream fkey2 deferred counter scan dynamic ' . $i;

    foreach ([
        'source' => 'fkey2.test fkey2-15.1.1..15.1.7',
        'operation' => 'deferred-counter-scan-avoidance',
        'statement' => $operation,
        'status' => 'commit-ok',
        'outstanding_deferred_violation' => $outstanding,
        'deferred_violation_count' => $outstanding ? 1 : 0,
        'fk_lookup_count' => $expectedSearches,
        'skipped_unnecessary_fk_scan' => $expectedSearches === 0,
        'dependencies.0' => 'sqlite-fkey2-zero-deferred-counter-skips-parent-probe',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 120; ++$i) {
    $schema = ['integer-primary-key', 'primary-key', 'unique-parent'][$i % 3];
    $newKey = 17 + $i;
    $newParent = $i % 4 === 0 ? $newKey + 1 : $newKey;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingRowPlan($schema, 13, 13, $newKey, $newParent);
    $valid = $newKey === $newParent;
    $case = 'real upstream fkey2 self referencing row dynamic ' . $i;

    foreach ([
        'source' => 'fkey2.test fkey2-16.1.1..16.1.8',
        'operation' => 'self-referencing-row-update',
        'schema_kind' => $schema,
        'old_row_valid' => true,
        'status' => $valid ? 'commit-ok' : 'constraint-failed',
        'self_reference_preserved' => $valid,
        'delete_self_reference_status' => 'commit-ok',
        'dependencies.1' => 'sqlite-fkey2-self-referencing-row-may-be-updated-when-key-and-reference-move-together',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 120; ++$i) {
    $statement = ['insert-child-violation', 'update-parent-cascade', 'delete-parent-cascade'][$i % 3];
    $deferred = $i % 2 === 0;
    $action = $statement !== 'insert-child-violation' && $i % 5 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::countChangesForeignKeyPlan($statement, $deferred, $action);
    $violation = $statement === 'insert-child-violation';
    $case = 'real upstream fkey2 count changes dynamic ' . $i;

    foreach ([
        'source' => $violation ? 'fkey2.test fkey2-17.1.1..17.1.14' : 'fkey2.test fkey2-17.2.1..17.2.10',
        'statement' => $statement,
        'status' => $violation ? ($deferred ? 'row-then-constraint' : 'constraint-immediate') : 'commit-ok',
        'deferred' => $deferred,
        'changes' => $violation ? ($deferred ? 1 : 0) : 1,
        'total_changes_delta' => $violation ? ($deferred ? 1 : 0) : ($action ? 2 : 1),
        'count_changes_returned_rows' => $violation ? ($deferred ? [1] : []) : [1],
        'dependencies.0' => $violation
            ? 'sqlite-fkey2-count-changes-immediate-fk-fails-before-row-count'
            : 'sqlite-fkey2-count-changes-excludes-fk-action-rows',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
