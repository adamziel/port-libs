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
    'real upstream fkey2 count changes dynamic cites count_changes section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'PRAGMA count_changes = 1'));
        $t->true(is_string($source) && str_contains($source, 'violate immediate FK constraints return'));
        $t->true(is_string($source) && str_contains($source, 'rows modified by FK actions are not counted'));
    },
];

for ($i = 1; $i <= 72; ++$i) {
    $parents = [
        ['b' => $i, 'c' => $i + 1, 'label' => 'parent_a_' . $i],
        ['b' => $i + 1, 'c' => $i + 2, 'label' => 'parent_b_' . $i],
        ['b' => $i + 2, 'c' => $i + 3, 'label' => 'parent_c_' . $i],
    ];
    $children = [
        ['id' => ($i * 10) + 1, 'e' => $i, 'f' => $i + 1, 'label' => 'child_a_' . $i],
        ['id' => ($i * 10) + 2, 'e' => $i + 1, 'f' => $i + 2, 'label' => 'child_b_' . $i],
    ];

    $immediate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::countChangesForeignKeyStatement(
        $parents,
        $children,
        [
            'operation' => 'insert-child',
            'deferred' => false,
            'rows' => [
                ['id' => ($i * 10) + 3, 'e' => $i + 500, 'f' => $i + 600, 'label' => 'bad_child_' . $i],
            ],
        ],
    );
    $case = 'fkey2-17 immediate count_changes failure dynamic ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-17.1.1..17.1.6',
        'status' => 'constraint-failed',
        'sqlite3_step_result' => 'SQLITE_CONSTRAINT',
        'finalize_result' => 'SQLITE_CONSTRAINT',
        'returned_count_rows' => [],
        'changes' => 0,
        'total_changes_delta' => 0,
        'violation_count' => 1,
        'violations.0.child_key' => [$i + 500, $i + 600],
        'dependencies.0' => 'sqlite-fkey2-count-changes-immediate-fk-fails-before-row-count',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($immediate, $path, $expected, $value): void {
            $t->same($expected, $value($immediate(), (string) $path));
        };
    }

    $deferred = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::countChangesForeignKeyStatement(
        $parents,
        $children,
        [
            'operation' => 'insert-child',
            'deferred' => true,
            'rows' => [
                ['id' => ($i * 10) + 3, 'e' => $i + 3, 'f' => $i + 4, 'label' => 'missing_a_' . $i],
                ['id' => ($i * 10) + 4, 'e' => $i + 4, 'f' => $i + 5, 'label' => 'missing_b_' . $i],
            ],
        ],
    );
    $case = 'fkey2-17 deferred count_changes failure dynamic ' . $i;
    foreach ([
        'status' => 'deferred-constraint-failed',
        'sqlite3_step_result' => 'SQLITE_ROW',
        'finalize_result' => 'SQLITE_CONSTRAINT',
        'returned_count_rows' => [2],
        'changes' => 2,
        'total_changes_delta' => 2,
        'violation_count' => 2,
        'violations.1.child_key' => [$i + 4, $i + 5],
        'dependencies.1' => 'sqlite-fkey2-count-changes-deferred-fk-returns-row-count-before-commit-fail',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($deferred, $path, $expected, $value): void {
            $t->same($expected, $value($deferred(), (string) $path));
        };
    }

    $action = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::countChangesForeignKeyStatement(
        $parents,
        $children,
        [
            'operation' => 'update-child',
            'deferred' => false,
            'on_update' => 'set null',
            'set' => ['e' => null, 'f' => null],
        ],
    );
    $case = 'fkey2-17 fk action count_changes exclusion dynamic ' . $i;
    foreach ([
        'status' => 'statement-ok',
        'sqlite3_step_result' => 'SQLITE_ROW',
        'finalize_result' => 'SQLITE_OK',
        'returned_count_rows' => [2],
        'changes' => 2,
        'fk_action_rows' => 2,
        'total_changes_delta' => 4,
        'foreign_key_action_rows_not_counted' => true,
        'child_pairs' => [[null, null], [null, null]],
        'dependencies.2' => 'sqlite-fkey2-count-changes-excludes-foreign-key-action-rows',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($action, $path, $expected, $value): void {
            $t->same($expected, $value($action(), (string) $path));
        };
    }
}

return $tests;
