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
    'real upstream trigger3 raise action cites table trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test');
        $t->true(is_string($source) && str_contains($source, "RAISE(ABORT,    'Trigger abort')"));
        $t->true(is_string($source) && str_contains($source, "RAISE(FAIL,     'Trigger fail')"));
        $t->true(is_string($source) && str_contains($source, "RAISE(ROLLBACK, 'Trigger rollback')"));
        $t->true(is_string($source) && str_contains($source, 'WHEN (new.a = 4) THEN RAISE(IGNORE)'));
    },
    'real upstream trigger3 raise action cites mutation and view blocks' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test');
        $t->true(is_string($source) && str_contains($source, 'RAISE(IGNORE) for UPDATE and DELETE'));
        $t->true(is_string($source) && str_contains($source, 'RAISE(IGNORE) works correctly for nested triggers'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tbl_view_insert INSTEAD OF INSERT ON tbl_view'));
    },
];

for ($i = 1; $i <= 120; ++$i) {
    $baseRows = [['a' => 5 + $i, 'b' => 5, 'c' => 6]];
    $statementRows = [
        ['a' => 6 + $i, 'b' => 5, 'c' => 6],
        ['a' => 1, 'b' => 5, 'c' => 6],
        ['a' => 2, 'b' => 5, 'c' => 6],
        ['a' => 3, 'b' => 5, 'c' => 6],
        ['a' => 4, 'b' => 5, 'c' => 6],
    ];

    $abort = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan($baseRows, $statementRows, 1, true);
    $fail = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan($baseRows, $statementRows, 2, true);
    $rollback = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan($baseRows, $statementRows, 3, true);
    $rollbackAutocommit = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan($baseRows, $statementRows, 3, false);
    $ignore = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan($baseRows, $statementRows, 4, true);
    $case = 'trigger3 table raise boundary dynamic ' . $i;

    foreach ([
        'source' => 'trigger3.test trigger3-1.1..4.2',
        'operation' => 'table-trigger-raise-action-boundary',
        'status' => 'constraint-trigger',
        'raise_action' => 'abort',
        'raise_message' => 'Trigger abort',
        'inside_transaction' => true,
        'rolled_back' => false,
        'statement_aborted' => true,
        'error_code' => 'SQLITE_CONSTRAINT_TRIGGER',
        'final_a_values' => [5 + $i, 6 + $i, 1],
        'dependencies.0' => 'sqlite-trigger3-raise-abort-fail-rollback-boundaries',
    ] as $path => $expected) {
        $tests[$case . ' abort ' . $path] = static function (TestRunner $t) use ($abort, $path, $expected, $value): void {
            $t->same($expected, $value($abort(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-trigger',
        'raise_action' => 'fail',
        'raise_message' => 'Trigger fail',
        'rolled_back' => false,
        'final_a_values' => [5 + $i, 6 + $i, 1, 2],
        'changes' => 3,
    ] as $path => $expected) {
        $tests[$case . ' fail ' . $path] = static function (TestRunner $t) use ($fail, $path, $expected, $value): void {
            $t->same($expected, $value($fail(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-trigger',
        'raise_action' => 'rollback',
        'raise_message' => 'Trigger rollback',
        'rolled_back' => true,
        'final_a_values' => [5 + $i],
        'changes' => 4,
    ] as $path => $expected) {
        $tests[$case . ' rollback transaction ' . $path] = static function (TestRunner $t) use ($rollback, $path, $expected, $value): void {
            $t->same($expected, $value($rollback(), (string) $path));
        };
    }

    foreach ([
        'raise_action' => 'rollback',
        'inside_transaction' => false,
        'rolled_back' => false,
        'final_a_values' => [5 + $i, 6 + $i, 1, 2, 3],
    ] as $path => $expected) {
        $tests[$case . ' rollback autocommit behaves like fail ' . $path] = static function (TestRunner $t) use ($rollbackAutocommit, $path, $expected, $value): void {
            $t->same($expected, $value($rollbackAutocommit(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'raise_action' => 'ignore',
        'raise_message' => null,
        'rolled_back' => false,
        'statement_aborted' => false,
        'inserted_a_values' => [6 + $i, 1, 2, 3],
        'final_a_values' => [5 + $i, 6 + $i, 1, 2, 3],
        'dependencies.1' => 'sqlite-trigger3-raise-ignore-skips-current-row',
    ] as $path => $expected) {
        $tests[$case . ' ignore ' . $path] = static function (TestRunner $t) use ($ignore, $path, $expected, $value): void {
            $t->same($expected, $value($ignore(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 90; ++$i) {
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4 + $i, 'b' => 5, 'c' => 6],
    ];
    $update = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseIgnoreMutationPlan($rows, 'update');
    $delete = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseIgnoreMutationPlan($rows, 'delete');
    $nested = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseIgnoreMutationPlan($rows, 'nested-insert');
    $case = 'trigger3 raise ignore mutation dynamic ' . $i;

    foreach ([
        'source' => 'trigger3.test trigger3-5.1..5.2',
        'operation' => 'raise-ignore-update',
        'status' => 'commit-ok',
        'ignored_a_values' => [1],
        'mutated_a_values' => [4 + $i],
        'final_rows.0.c' => 3,
        'final_rows.1.c' => 10,
        'dependencies.0' => 'sqlite-trigger3-raise-ignore-update-delete-row-skip',
    ] as $path => $expected) {
        $tests[$case . ' update ' . $path] = static function (TestRunner $t) use ($update, $path, $expected, $value): void {
            $t->same($expected, $value($update(), (string) $path));
        };
    }

    foreach ([
        'operation' => 'raise-ignore-delete',
        'ignored_a_values' => [1],
        'mutated_a_values' => [4 + $i],
        'final_a_values' => [1],
    ] as $path => $expected) {
        $tests[$case . ' delete ' . $path] = static function (TestRunner $t) use ($delete, $path, $expected, $value): void {
            $t->same($expected, $value($delete(), (string) $path));
        };
    }

    foreach ([
        'source' => 'trigger3.test trigger3-6',
        'operation' => 'raise-ignore-nested-insert',
        'nested_row_count' => 2,
        'final_a_values' => [1, 4 + $i],
        'final_rows.1.c' => 10,
        'dependencies.1' => 'sqlite-trigger3-nested-trigger-ignore-resumes-outer-program',
    ] as $path => $expected) {
        $tests[$case . ' nested ' . $path] = static function (TestRunner $t) use ($nested, $path, $expected, $value): void {
            $t->same($expected, $value($nested(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 90; ++$i) {
    $row = [['a' => ($i % 3) + 1, 'b' => 2, 'c' => 3]];
    $viewRollback = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan([], $row, 1, true, true);
    $viewIgnore = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan([], $row, 2, true, true);
    $viewAbort = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionBoundaryPlan([], $row, 3, true, true);
    $case = 'trigger3 view trigger raise dynamic ' . $i;

    foreach ([
        'source' => 'trigger3.test trigger3-7.1..7.3',
        'operation' => 'view-trigger-raise-action-boundary',
        'raise_action' => 'rollback',
        'raise_message' => 'View rollback',
        'view_trigger' => true,
        'rolled_back' => true,
        'error_code' => 'SQLITE_CONSTRAINT_TRIGGER',
        'dependencies.2' => 'sqlite-trigger3-view-trigger-raise-actions',
    ] as $path => $expected) {
        $tests[$case . ' view rollback ' . $path] = static function (TestRunner $t) use ($viewRollback, $path, $expected, $value): void {
            $t->same($expected, $value($viewRollback(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'raise_action' => 'ignore',
        'raise_message' => null,
        'view_trigger' => true,
        'final_a_values' => [],
    ] as $path => $expected) {
        $tests[$case . ' view ignore ' . $path] = static function (TestRunner $t) use ($viewIgnore, $path, $expected, $value): void {
            $t->same($expected, $value($viewIgnore(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-trigger',
        'raise_action' => 'abort',
        'raise_message' => 'View abort',
        'view_trigger' => true,
        'statement_aborted' => true,
    ] as $path => $expected) {
        $tests[$case . ' view abort ' . $path] = static function (TestRunner $t) use ($viewAbort, $path, $expected, $value): void {
            $t->same($expected, $value($viewAbort(), (string) $path));
        };
    }
}

$tests['trigger3 raise ignore rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::raiseIgnoreMutationPlan([], 'replace'));
};

return $tests;
