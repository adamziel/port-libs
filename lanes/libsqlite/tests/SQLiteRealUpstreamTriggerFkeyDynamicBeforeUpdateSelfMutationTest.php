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
    'real upstream triggerC before update self mutation cites narrow-row regression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');
        $t->true(is_string($source) && str_contains($source, 'do_test triggerC-10.1'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t10 SET updatecnt = updatecnt+1 WHERE rowid = old.rowid;'));
        $t->true(is_string($source) && str_contains($source, "UPDATE t10 SET a = 'world';"));
    },
    'real upstream triggerC before update self mutation cites wide-row regression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');
        $t->true(is_string($source) && str_contains($source, 'do_test triggerC-10.3'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t11 SET c31 = c31+1, c32=c32+1 WHERE rowid = old.rowid;'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t11 SET c4=35, c33=22, c1=5;'));
    },
];

for ($i = 1; $i <= 125; ++$i) {
    $row = [
        'id' => $i,
        'label' => 'seed-' . $i,
        'update_count' => $i % 7,
        'audit_count' => $i % 11,
        'payload' => 'payload-' . ($i % 5),
        'c31' => 31 + $i,
        'c32' => 32 + $i,
        'c33' => 33 + $i,
        'c34' => 34 + $i,
    ];
    $parentAssignments = [
        'label' => 'updated-' . $i,
        'payload' => 'parent-' . ($i % 9),
        'c33' => 2200 + $i,
    ];
    if ($i % 3 === 0) {
        $parentAssignments['c34'] = 3400 + $i;
    }
    $triggerAssignments = [
        'update_count' => static fn (array $current): int => (int) $current['update_count'] + 1,
        'audit_count' => static fn (array $current): int => (int) $current['audit_count'] + 2,
        'c31' => static fn (array $current): int => (int) $current['c31'] + 1,
        'c32' => static fn (array $current): int => (int) $current['c32'] + 1,
    ];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns(
        $row,
        $parentAssignments,
        $triggerAssignments
    );
    $case = 'triggerC-10 before update self mutation dynamic ' . $i;

    foreach ([
        'source' => 'triggerC.test triggerC-10.1..10.3',
        'operation' => 'before-update-trigger-self-mutation-preserves-unassigned-columns',
        'status' => 'commit-ok',
        'final_row.label' => 'updated-' . $i,
        'final_row.payload' => 'parent-' . ($i % 9),
        'final_row.c33' => 2200 + $i,
        'final_row.update_count' => ($i % 7) + 1,
        'final_row.audit_count' => ($i % 11) + 2,
        'final_row.c31' => 32 + $i,
        'final_row.c32' => 33 + $i,
        'preserved_trigger_columns.update_count' => ($i % 7) + 1,
        'preserved_trigger_columns.audit_count' => ($i % 11) + 2,
        'preserved_trigger_columns.c31' => 32 + $i,
        'preserved_trigger_columns.c32' => 33 + $i,
        'preserved_trigger_column_count' => 4,
        'parent_assignment_count' => $i % 3 === 0 ? 4 : 3,
        'trigger_assignment_count' => 4,
        'dependencies.0' => 'sqlite-triggerC-before-update-self-mutation',
        'dependencies.1' => 'sqlite-triggerC-parent-update-does-not-clobber-unassigned-trigger-columns',
        'dependencies.2' => 'sqlite-triggerC-wide-row-column-preservation',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' parent assigned column overrides trigger-free image'] = static function (TestRunner $t) use ($plan, $i): void {
        $t->same(2200 + $i, $plan()['final_row']['c33']);
    };
    $tests[$case . ' original row image remains available to trigger'] = static function (TestRunner $t) use ($plan, $row): void {
        $t->same($row['label'], $plan()['original_row']['label']);
        $t->same($row['update_count'], $plan()['original_row']['update_count']);
    };
}

$tests['real upstream triggerC before update self mutation rejects empty row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns([], ['a' => 1], ['b' => 2]));
};
$tests['real upstream triggerC before update self mutation rejects empty parent update'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns(['a' => 1], [], ['b' => 2]));
};
$tests['real upstream triggerC before update self mutation rejects empty trigger update'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns(['a' => 1], ['a' => 2], []));
};
$tests['real upstream triggerC before update self mutation rejects malformed parent column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns(['a' => 1], ['bad column' => 2], ['b' => 3]));
};
$tests['real upstream triggerC before update self mutation rejects malformed trigger column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns(['a' => 1], ['a' => 2], ['bad column' => 3]));
};

return $tests;
