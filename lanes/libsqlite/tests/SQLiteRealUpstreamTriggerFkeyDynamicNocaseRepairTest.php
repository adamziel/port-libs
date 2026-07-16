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
    'real upstream fkey2 nocase repair cites trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-12.2.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tt1 AFTER DELETE ON t1'));
    },
    'real upstream fkey2 nocase repair cites restrict block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t2(y REFERENCES t1 ON DELETE RESTRICT)'));
        $t->true(is_string($source) && str_contains($source, 'catchsql { DELETE FROM t1 }'));
    },
    'real upstream fkey2 replace cites composite parent block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-13.1.1'));
        $t->true(is_string($source) && str_contains($source, 'REPLACE INTO pp VALUES(1, 4, 5)'));
        $t->true(is_string($source) && str_contains($source, 'REPLACE INTO pp(rowid, a, b, c) VALUES(2, 2, 2, 3)'));
    },
];

$keys = [
    ['A', 'B'],
    ['Alpha', 'Beta'],
    ['MIXED', 'Case'],
    ['north', 'SOUTH'],
    ['Tenant', 'Setting'],
];

for ($i = 1; $i <= 80; ++$i) {
    $pair = $keys[$i % count($keys)];
    $suffix = (string) $i;
    $parents = [
        ['id' => 'p' . $i . 'a', 'key' => $pair[0] . $suffix],
        ['id' => 'p' . $i . 'b', 'key' => $pair[1] . $suffix],
    ];
    $children = [
        ['id' => 'c' . $i . 'a', 'parent_key' => strtolower($pair[0]) . $suffix],
        ['id' => 'c' . $i . 'b', 'parent_key' => strtolower($pair[1]) . $suffix],
    ];
    if ($i % 6 === 0) {
        $parents[] = ['id' => 'p' . $i . 'c', 'key' => 'Unreferenced' . $suffix];
    }
    if ($i % 7 === 0) {
        $children[] = ['id' => 'c' . $i . 'c', 'parent_key' => strtoupper($pair[0]) . $suffix];
    }

    $repair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::nocaseDeleteTriggerRepair($parents, $children, 'no action');
    $restrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::nocaseDeleteTriggerRepair($parents, $children, 'restrict');
    $expectedReinserted = array_values(array_unique(array_map(
        static fn (array $child): string => strtolower((string) $child['parent_key']),
        $children
    )));
    $expectedReinserted = array_values(array_filter(
        array_column($parents, 'key'),
        static fn (string $key): bool => in_array(strtolower($key), $expectedReinserted, true)
    ));
    $case = 'real upstream fkey2 nocase after delete repair dynamic ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
        'operation' => 'nocase-parent-delete-trigger-repair',
        'status' => 'commit-ok',
        'delete_action' => 'no action',
        'trigger_reinserted_keys' => $expectedReinserted,
        'parent_keys' => $expectedReinserted,
        'child_keys' => array_column($children, 'parent_key'),
        'violation_count' => 0,
        'restrict_failed_before_trigger_repair' => false,
        'dependencies.0' => 'sqlite-fkey2-after-delete-trigger-can-repair-no-action-fk',
        'dependencies.1' => 'sqlite-fkey2-nocase-parent-key-match',
    ] as $path => $expected) {
        $tests[$case . ' repaired ' . $path] = static function (TestRunner $t) use ($repair, $path, $expected, $value): void {
            $t->same($expected, $value($repair(), (string) $path));
        };
    }

    foreach ([
        'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
        'operation' => 'nocase-parent-delete-trigger-repair',
        'status' => 'constraint-failed',
        'delete_action' => 'restrict',
        'trigger_reinserted_keys' => [],
        'parent_keys' => array_column($parents, 'key'),
        'child_keys' => array_column($children, 'parent_key'),
        'violation_count' => 0,
        'restrict_failed_before_trigger_repair' => true,
        'dependencies.0' => 'sqlite-fkey2-restrict-is-immediate-before-after-trigger-repair',
        'dependencies.1' => 'sqlite-fkey2-nocase-parent-key-match',
    ] as $path => $expected) {
        $tests[$case . ' restrict ' . $path] = static function (TestRunner $t) use ($restrict, $path, $expected, $value): void {
            $t->same($expected, $value($restrict(), (string) $path));
        };
    }

    $tests[$case . ' repaired children keep original case variants'] = static function (TestRunner $t) use ($repair, $children): void {
        $t->same(array_column($children, 'parent_key'), $repair()['child_keys']);
    };
    $tests[$case . ' repaired parent set excludes unreferenced deletes'] = static function (TestRunner $t) use ($repair): void {
        foreach ($repair()['parent_keys'] as $key) {
            $t->same(false, str_starts_with((string) $key, 'Unreferenced'));
        }
    };
    $tests[$case . ' restrict preserves parent set before trigger program'] = static function (TestRunner $t) use ($restrict, $parents): void {
        $t->same(array_column($parents, 'key'), $restrict()['parent_keys']);
    };
}

for ($i = 1; $i <= 120; ++$i) {
    $baseA = 1000 + $i;
    $baseB = 2000 + $i;
    $baseC = 3000 + $i;
    $parents = [
        ['rowid' => 1, 'a' => $baseA, 'b' => $baseB, 'c' => $baseC],
    ];
    $children = [
        ['d' => $baseB, 'e' => $baseC, 'f' => 1],
    ];
    if ($i % 4 === 0) {
        $children[] = ['d' => $baseB, 'e' => $baseC, 'f' => 2];
    }
    $expectedFailedViolationCount = count($children);

    $uniqueFail = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceCompositeParentForeignKey(
        $parents,
        $children,
        ['a' => $baseA, 'b' => $baseB + 2, 'c' => $baseC + 2, 'conflict' => 'unique-a']
    );
    $rowidFail = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceCompositeParentForeignKey(
        $parents,
        $children,
        ['rowid' => 1, 'a' => $baseA + 1, 'b' => $baseB + 2, 'c' => $baseC + 2, 'conflict' => 'rowid', 'transaction' => true]
    );
    $sameKeyRowid = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceCompositeParentForeignKey(
        $parents,
        $children,
        ['rowid' => 2, 'a' => $baseA + 1, 'b' => $baseB, 'c' => $baseC, 'conflict' => 'rowid']
    );
    $sameKeyUnique = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceCompositeParentForeignKey(
        $parents,
        $children,
        ['rowid' => 1, 'a' => $baseA, 'b' => $baseB, 'c' => $baseC, 'conflict' => 'unique-a']
    );

    $case = 'real upstream fkey2 replace composite parent dynamic ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-13.1.1..13.1.4',
        'operation' => 'replace-composite-parent-foreign-key',
        'status' => 'constraint-failed',
        'conflict_target' => 'unique-a',
        'deleted_parent_keys.0' => [$baseB, $baseC],
        'committed_parent_keys.0' => [$baseB, $baseC],
        'committed_child_keys.0' => [$baseB, $baseC],
        'violation_count' => $expectedFailedViolationCount,
        'violations.0.reason' => 'missing-composite-parent-after-replace-delete',
        'dependencies.0' => 'sqlite-fkey2-replace-runs-foreign-key-processing',
        'dependencies.1' => 'sqlite-fkey2-replace-failure-preserves-original-rows',
    ] as $path => $expected) {
        $tests[$case . ' unique conflict preserves rows ' . $path] = static function (TestRunner $t) use ($uniqueFail, $path, $expected, $value): void {
            $t->same($expected, $value($uniqueFail(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'conflict_target' => 'rowid',
        'transaction_open_after_failed_replace' => true,
        'deleted_rowids.0' => 1,
        'committed_parent_rows.0.rowid' => 1,
        'committed_parent_rows.0.a' => $baseA,
        'committed_parent_keys.0' => [$baseB, $baseC],
        'violation_count' => $expectedFailedViolationCount,
        'violations.0.child_key' => [$baseB, $baseC],
    ] as $path => $expected) {
        $tests[$case . ' rowid conflict failed statement leaves transaction open ' . $path] = static function (TestRunner $t) use ($rowidFail, $path, $expected, $value): void {
            $t->same($expected, $value($rowidFail(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'conflict_target' => 'rowid',
        'incoming_parent_key' => [$baseB, $baseC],
        'incoming_rowid' => 2,
        'deleted_rowids.0' => 1,
        'committed_parent_rows.0.rowid' => 2,
        'committed_parent_keys.0' => [$baseB, $baseC],
        'committed_child_keys.0' => [$baseB, $baseC],
        'violation_count' => 0,
        'dependencies.2' => 'sqlite-fkey2-replace-same-composite-parent-key-commits',
    ] as $path => $expected) {
        $tests[$case . ' same composite key rowid replace commits ' . $path] = static function (TestRunner $t) use ($sameKeyRowid, $path, $expected, $value): void {
            $t->same($expected, $value($sameKeyRowid(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'conflict_target' => 'unique-a',
        'incoming_rowid' => 1,
        'deleted_parent_keys.0' => [$baseB, $baseC],
        'committed_parent_keys.0' => [$baseB, $baseC],
        'committed_child_keys.0' => [$baseB, $baseC],
        'violation_count' => 0,
    ] as $path => $expected) {
        $tests[$case . ' same composite key unique replace commits ' . $path] = static function (TestRunner $t) use ($sameKeyUnique, $path, $expected, $value): void {
            $t->same($expected, $value($sameKeyUnique(), (string) $path));
        };
    }

    $tests[$case . ' failed unique replace preserves full parent row set'] = static function (TestRunner $t) use ($uniqueFail, $parents): void {
        $t->same($parents, $uniqueFail()['committed_parent_rows']);
    };
    $tests[$case . ' failed rowid replace preserves child row keys'] = static function (TestRunner $t) use ($rowidFail, $children): void {
        $t->same(array_values(array_map(static fn (array $row): array => [$row['d'], $row['e']], $children)), $rowidFail()['committed_child_keys']);
    };
}

return $tests;
