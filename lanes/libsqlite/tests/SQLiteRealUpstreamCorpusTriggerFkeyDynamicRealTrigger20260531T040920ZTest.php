<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger2 execution model cites row trigger sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger2-1.1.*: ON UPDATE trigger execution model.'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-1.2.*: DELETE trigger execution model.'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-1.3.*: INSERT trigger execution model.'));
    },
    'real upstream trigger2 execution model cites trigger program sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger2-3.1: UPDATE OF triggers'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-3.2: WHEN clause'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-5'));
    },
];

$value = static function (array $row, string $path): mixed {
    $cursor = $row;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

for ($i = 1; $i <= 170; ++$i) {
    $initialRows = [
        ['a' => $i, 'b' => $i + 1],
        ['a' => $i + 2, 'b' => $i + 3],
    ];
    $insertRows = [
        ['a' => $i + 20, 'b' => $i + 21],
        ['a' => $i + 30, 'b' => $i + 31],
    ];
    $rowOrder = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::rowTriggerExecutionOrder($initialRows, $insertRows);
    foreach ([
        'source' => 'trigger2.test trigger2-1.1..1.3',
        'operation' => 'row-trigger-before-after-execution-order',
        'status' => 'commit-ok',
        'update_log_count' => 4,
        'conditional_update_log_count' => 1,
        'delete_log_count' => 4,
        'insert_log_count' => 4,
        'update_log.0.old_a' => $i,
        'update_log.0.db_sum_a' => ($i * 2) + 2,
        'update_log.1.db_sum_a' => ($i * 11) + 2,
        'delete_log.0.old_a' => $i * 10,
        'insert_log.0.new_a' => $i + 20,
        'insert_log.1.db_sum_a' => $i + 20,
        'dependencies.0' => 'sqlite-trigger2-before-trigger-sees-prestatement-rowset',
        'dependencies.1' => 'sqlite-trigger2-after-trigger-sees-current-row-change',
        'dependencies.2' => 'sqlite-trigger2-when-clause-uses-old-row-image',
    ] as $path => $expected) {
        $tests[sprintf('trigger2-1 row trigger execution order dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($rowOrder, $value, $path, $expected): void {
            $t->same($expected, $value($rowOrder(), (string) $path));
        };
    }

    $baseRows = [
        ['a' => $i, 'b' => $i + 10, 'c' => $i + 20],
        ['a' => $i + 1, 'b' => $i + 11, 'c' => $i + 21],
    ];
    $logRows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 10, 'b' => 20, 'c' => 30],
    ];
    $statement = match ($i % 3) {
        0 => ['type' => 'insert', 'row' => ['a' => $i + 100, 'b' => $i + 101, 'c' => $i + 102]],
        1 => ['type' => 'update', 'set' => ['b' => $i + 200], 'where' => static fn (array $row): bool => $row['a'] === $i],
        default => ['type' => 'delete', 'where' => static fn (array $row): bool => $row['a'] === $i],
    };
    $program = ['update-b-from-old', 'insert-log-new-c', 'delete-log-a1', 'insert-log-select-table'][$i % 4];
    $timing = $i % 2 === 0 ? 'before' : 'after';
    $programPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution($baseRows, $logRows, $statement, $program, $timing);
    foreach ([
        'source' => 'trigger2.test trigger2-2',
        'operation' => 'trigger-program-statement-execution',
        'status' => 'commit-ok',
        'timing' => $timing,
        'statement_type' => $statement['type'],
        'program' => $program,
        'statement_changes' => 1,
        'context_count' => 1,
        'dependencies.2' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
        'dependencies.3' => 'sqlite-trigger2-old-new-row-values-feed-program',
    ] as $path => $expected) {
        $tests[sprintf('trigger2-2 program statement execution dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($programPlan, $value, $path, $expected): void {
            $t->same($expected, $value($programPlan(), (string) $path));
        };
    }

    $selectiveRows = [
        ['a' => $i, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => $i + 1, 'b' => 0, 'c' => 0, 'd' => 0],
    ];
    $updates = [
        ['columns' => ['b'], 'where' => static fn (array $row): bool => true],
        ['columns' => ['c'], 'where' => static fn (array $row): bool => $row['a'] === $i],
        ['columns' => ['d'], 'where' => static fn (array $row): bool => $row['a'] === $i + 1],
    ];
    $whenRows = [
        ['a' => $i % 4 === 0 ? 5 : 25, 'b' => 1, 'c' => 1, 'd' => 1],
        ['a' => 35, 'b' => 2, 'c' => 2, 'd' => 2],
    ];
    $selectivePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selectiveTriggerExecution($selectiveRows, $updates, $whenRows, true);
    foreach ([
        'source' => 'trigger2.test trigger2-3.1..3.2',
        'operation' => 'selective-update-of-and-when-trigger-execution',
        'status' => 'commit-ok',
        'update_of_log_count' => 2,
        'when_log.0.preinsert_count' => 0,
        'inserted_rows.1.a' => 35,
        'dependencies.0' => 'sqlite-trigger2-update-of-fires-only-for-named-columns',
        'dependencies.1' => 'sqlite-trigger2-when-new-row-predicate',
        'dependencies.2' => 'sqlite-trigger2-when-subquery-sees-preinsert-table',
    ] as $path => $expected) {
        $tests[sprintf('trigger2-3 update-of when dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($selectivePlan, $value, $path, $expected): void {
            $t->same($expected, $value($selectivePlan(), (string) $path));
        };
    }

    $countPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger2CountChangesExcludesTriggerProgram(
        [
            ['id' => $i, 'a' => 1, 'b' => 2, 'c' => 3],
            ['id' => $i + 10, 'a' => 9, 'b' => 8, 'c' => 7],
        ],
        ['id' => $i + 1000, 'a' => $i + 4, 'b' => $i + 5, 'c' => $i + 6]
    );
    foreach ([
        'source' => 'trigger2.test trigger2-5',
        'operation' => 'trigger-program-count-changes-boundary',
        'status' => 'commit-ok',
        'direct_statement_changes' => 1,
        'db_changes_result' => 1,
        'count_changes_excludes_trigger_program' => true,
        'total_changes_includes_trigger_program' => true,
        'final_row_ids.0' => $i + 1000,
        'dependencies.0' => 'sqlite-trigger2-db-changes-excludes-trigger-program-work',
        'dependencies.2' => 'sqlite-trigger2-trigger-program-delete-all-does-not-cancel-direct-insert',
    ] as $path => $expected) {
        $tests[sprintf('trigger2-5 count changes dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($countPlan, $value, $path, $expected): void {
            $t->same($expected, $value($countPlan(), (string) $path));
        };
    }
}

$tests['real upstream trigger2 execution rejects unsupported program timing'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution([], [], ['type' => 'insert', 'row' => ['a' => 1, 'b' => 2, 'c' => 3]], 'insert-log-new-c', 'around'));
};

$tests['real upstream trigger2 execution rejects malformed count changes seed'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger2CountChangesExcludesTriggerProgram([['id' => 1, 'a' => 1, 'b' => 2]], ['id' => 2, 'a' => 3, 'b' => 4, 'c' => 5]));
};

return $tests;
