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

$fkey2Source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests = [
    'real upstream fkey2 integer primary key child cites section header' => static function (TestRunner $t) use ($fkey2Source): void {
        $source = file_get_contents($fkey2Source);
        $t->true(is_string($source) && str_contains($source, 'Test that it is possible to use an INTEGER PRIMARY KEY as the child key'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t2(c INTEGER PRIMARY KEY REFERENCES t1, b);'));
    },
    'real upstream fkey2 integer primary key child cites rowid update boundary' => static function (TestRunner $t) use ($fkey2Source): void {
        $source = file_get_contents($fkey2Source);
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-7.9'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t2 SET rowid = 3'));
    },
];

$scenarios = [
    'fkey2-7.2 insert child missing parent fails' => static function (int $base): array {
        return [
            'parents' => [],
            'children' => [],
            'statement' => ['action' => 'insert-child', 'row' => ['c' => $base, 'b' => 'A-' . $base]],
            'expected' => [
                'action' => 'insert-child',
                'status' => 'constraint-failed',
                'error' => 'FOREIGN KEY constraint failed',
                'statement_rolled_back' => true,
                'affected_count' => 1,
                'violation_count' => 1,
                'failed_child_key' => $base,
                'child_keys_after' => [],
                'child_rowids_after' => [],
                'parent_keys_after' => [],
            ],
        ];
    },
    'fkey2-7.3 insert child after parent rows succeeds' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base, 'b' => 'parent-' . $base],
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [],
            'statement' => ['action' => 'insert-child', 'row' => ['c' => $base, 'b' => 'A-' . $base]],
            'expected' => [
                'action' => 'insert-child',
                'status' => 'commit-ok',
                'error' => null,
                'statement_rolled_back' => false,
                'affected_count' => 1,
                'violation_count' => 0,
                'failed_child_key' => null,
                'child_keys_after' => [$base],
                'child_rowids_after' => [$base],
                'parent_keys_after' => [$base, $base + 1],
            ],
        ];
    },
    'fkey2-7.4 update child integer primary key to existing parent succeeds' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base, 'b' => 'parent-' . $base],
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [
                ['c' => $base, 'b' => 'A-' . $base],
            ],
            'statement' => ['action' => 'update-child-key', 'where' => ['c' => $base], 'value' => $base + 1],
            'expected' => [
                'action' => 'update-child-key',
                'status' => 'commit-ok',
                'error' => null,
                'statement_rolled_back' => false,
                'affected_count' => 1,
                'violation_count' => 0,
                'failed_child_key' => null,
                'child_keys_after' => [$base + 1],
                'child_rowids_after' => [$base + 1],
                'parent_keys_after' => [$base, $base + 1],
            ],
        ];
    },
    'fkey2-7.5 update child integer primary key to missing parent fails' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base, 'b' => 'parent-' . $base],
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [
                ['c' => $base + 1, 'b' => 'A-' . ($base + 1)],
            ],
            'statement' => ['action' => 'update-child-key', 'where' => ['c' => $base + 1], 'value' => $base + 2],
            'expected' => [
                'action' => 'update-child-key',
                'status' => 'constraint-failed',
                'error' => 'FOREIGN KEY constraint failed',
                'statement_rolled_back' => true,
                'affected_count' => 1,
                'violation_count' => 1,
                'failed_child_key' => $base + 2,
                'child_keys_after' => [$base + 1],
                'child_rowids_after' => [$base + 1],
                'parent_keys_after' => [$base, $base + 1],
            ],
        ];
    },
    'fkey2-7.6 delete referenced parent fails' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base, 'b' => 'parent-' . $base],
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [
                ['c' => $base + 1, 'b' => 'A-' . ($base + 1)],
            ],
            'statement' => ['action' => 'delete-parent', 'parent_key' => $base + 1],
            'expected' => [
                'action' => 'delete-parent',
                'status' => 'constraint-failed',
                'error' => 'FOREIGN KEY constraint failed',
                'statement_rolled_back' => true,
                'affected_count' => 1,
                'violation_count' => 1,
                'failed_child_key' => $base + 1,
                'child_keys_after' => [$base + 1],
                'child_rowids_after' => [$base + 1],
                'parent_keys_after' => [$base, $base + 1],
            ],
        ];
    },
    'fkey2-7.7 delete unreferenced parent succeeds' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base, 'b' => 'parent-' . $base],
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [
                ['c' => $base + 1, 'b' => 'A-' . ($base + 1)],
            ],
            'statement' => ['action' => 'delete-parent', 'parent_key' => $base],
            'expected' => [
                'action' => 'delete-parent',
                'status' => 'commit-ok',
                'error' => null,
                'statement_rolled_back' => false,
                'affected_count' => 1,
                'violation_count' => 0,
                'failed_child_key' => null,
                'child_keys_after' => [$base + 1],
                'child_rowids_after' => [$base + 1],
                'parent_keys_after' => [$base + 1],
            ],
        ];
    },
    'fkey2-7.8 update referenced parent key fails' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [
                ['c' => $base + 1, 'b' => 'A-' . ($base + 1)],
            ],
            'statement' => ['action' => 'update-parent-key', 'parent_key' => $base + 1, 'value' => $base + 2],
            'expected' => [
                'action' => 'update-parent-key',
                'status' => 'constraint-failed',
                'error' => 'FOREIGN KEY constraint failed',
                'statement_rolled_back' => true,
                'affected_count' => 1,
                'violation_count' => 1,
                'failed_child_key' => $base + 1,
                'child_keys_after' => [$base + 1],
                'child_rowids_after' => [$base + 1],
                'parent_keys_after' => [$base + 1],
            ],
        ];
    },
    'fkey2-7.9 update child rowid alias to missing parent fails' => static function (int $base): array {
        return [
            'parents' => [
                ['a' => $base + 1, 'b' => 'parent-' . ($base + 1)],
            ],
            'children' => [
                ['c' => $base + 1, 'b' => 'A-' . ($base + 1)],
            ],
            'statement' => ['action' => 'update-child-rowid', 'where' => ['c' => $base + 1], 'value' => $base + 2],
            'expected' => [
                'action' => 'update-child-rowid',
                'status' => 'constraint-failed',
                'error' => 'FOREIGN KEY constraint failed',
                'statement_rolled_back' => true,
                'affected_count' => 1,
                'violation_count' => 1,
                'failed_child_key' => $base + 2,
                'child_keys_after' => [$base + 1],
                'child_rowids_after' => [$base + 1],
                'parent_keys_after' => [$base + 1],
            ],
        ];
    },
];

for ($i = 1; $i <= 90; ++$i) {
    $base = $i * 10;
    foreach ($scenarios as $scenarioName => $scenario) {
        $case = $scenario($base);
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2IntegerPrimaryKeyChildPlan(
            $case['parents'],
            $case['children'],
            $case['statement']
        );
        $expected = $case['expected'];
        $label = sprintf('real upstream fkey2 integer primary key child dynamic %03d %s', $i, $scenarioName);

        foreach ([
            'source' => 'fkey2.test fkey2-7.1..7.9',
            'operation' => 'integer-primary-key-child-foreign-key',
            'action' => $expected['action'],
            'status' => $expected['status'],
            'error' => $expected['error'],
            'child_key_column' => 'c',
            'child_rowid_alias' => 'rowid',
            'rowid_alias_matches_child_key' => true,
            'foreign_key_checked' => true,
            'statement_rolled_back' => $expected['statement_rolled_back'],
            'affected_count' => $expected['affected_count'],
            'violation_count' => $expected['violation_count'],
            'failed_child_key' => $expected['failed_child_key'],
            'child_keys_after' => $expected['child_keys_after'],
            'child_rowids_after' => $expected['child_rowids_after'],
            'parent_keys_after' => $expected['parent_keys_after'],
            'dependencies.0' => 'sqlite-fkey2-child-integer-primary-key-is-foreign-key',
            'dependencies.1' => 'sqlite-fkey2-child-rowid-update-checks-parent-key',
            'dependencies.2' => 'sqlite-fkey2-parent-delete-and-update-check-child-ipk',
        ] as $path => $expectedValue) {
            $tests[$label . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expectedValue, $value): void {
                $t->same($expectedValue, $value($plan(), (string) $path));
            };
        }

        $tests[$label . ' committed rows reflect statement rollback boundary'] = static function (TestRunner $t) use ($plan): void {
            $result = $plan();
            if ($result['statement_rolled_back']) {
                $t->same($result['parents_before'], $result['parents_after']);
                $t->same($result['children_before'], $result['children_after']);
                return;
            }

            $t->same($result['attempted_parents'], $result['parents_after']);
            $t->same($result['attempted_children'], $result['children_after']);
        };
    }
}

$tests['real upstream fkey2 integer primary key child rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2IntegerPrimaryKeyChildPlan([], [], ['action' => 'vacuum']));
};

$tests['real upstream fkey2 integer primary key child rejects broken rowid alias'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2IntegerPrimaryKeyChildPlan(
        [['a' => 1, 'b' => 'parent']],
        [['c' => 1, 'rowid' => 2, 'b' => 'child']],
        ['action' => 'delete-parent', 'parent_key' => 1]
    ));
};

$tests['real upstream fkey2 integer primary key child records non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::fkey2IntegerPrimaryKeyChildPlan(
        [['a' => 1, 'b' => 'parent']],
        [['c' => 1, 'b' => 'child']],
        ['action' => 'update-child-rowid', 'where' => ['c' => 1], 'value' => 2]
    );
    $t->same('fkey2.test fkey2-7.1..7.9', $plan['source']);
    $t->true(str_contains($plan['non_overlap'], 'not fkey2-5 incremental blob'));
    $t->true(str_contains($plan['non_overlap'], 'not fkey2-8 pragma toggles'));
    $t->same('no new support component needed; reuses SQLiteDynamicTriggerForeignKeyPlan against hydrated upstream fkey2.test', $plan['dependency_closure']);
};

return $tests;
