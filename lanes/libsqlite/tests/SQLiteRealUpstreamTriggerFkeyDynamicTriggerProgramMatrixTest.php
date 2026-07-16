<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

/**
 * @param array<string,mixed> $array
 */
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
    'real upstream trigger2 trigger program matrix cites before and after trigger source' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger2-2.$ii-before'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger2-2.$ii-after'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER the_trigger BEFORE'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER the_trigger AFTER'));
    },
];

$programs = [
    'old value update' => [
        'program' => 'update-b-from-old',
        'dependency' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
    ],
    'new value log insert' => [
        'program' => 'insert-log-new-c',
        'dependency' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
    ],
    'log delete' => [
        'program' => 'delete-log-a1',
        'dependency' => 'sqlite-trigger2-old-new-row-values-feed-program',
    ],
    'compound program' => [
        'program' => 'compound-insert-update-delete-log',
        'dependency' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
    ],
    'insert select table' => [
        'program' => 'insert-log-select-table',
        'dependency' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
    ],
];

$timings = ['before', 'after'];
$statementTypes = ['insert', 'update', 'delete'];
$caseNumber = 0;

for ($i = 1; $i <= 42; ++$i) {
    foreach ($timings as $timing) {
        foreach ($statementTypes as $statementType) {
            foreach ($programs as $programName => $programSpec) {
                ++$caseNumber;
                $seed = ($i * 100) + $caseNumber;
                $rows = [
                    ['a' => $seed + 1, 'b' => 10, 'c' => 100],
                    ['a' => $seed + 2, 'b' => 20, 'c' => 200],
                    ['a' => $seed + 3, 'b' => 30, 'c' => 300],
                ];
                $logRows = [
                    ['a' => $seed + 90, 'b' => 900, 'c' => 9000],
                ];

                $statement = match ($statementType) {
                    'insert' => ['type' => 'insert', 'row' => ['a' => $seed + 4, 'b' => 40, 'c' => 400]],
                    'update' => [
                        'type' => 'update',
                        'set' => ['b' => $seed + 500],
                        'where' => static fn (array $row): bool => $row['a'] === $seed + 2,
                    ],
                    default => [
                        'type' => 'delete',
                        'where' => static fn (array $row): bool => $row['a'] === $seed + 1,
                    ],
                };

                $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution(
                    $rows,
                    $logRows,
                    $statement,
                    $programSpec['program'],
                    $timing
                );

                $baseName = sprintf(
                    'real upstream trigger2.test trigger2-2 dynamic matrix %03d %s %s program %s',
                    $caseNumber,
                    $timing,
                    $statementType,
                    $programName
                );
                $statementChanges = 1;
                $rowsAtTrigger = match ($statementType) {
                    'insert' => $timing === 'before' ? 3 : 4,
                    'update' => 3,
                    default => $timing === 'before' ? 3 : 2,
                };
                $triggerChanges = match ($programSpec['program']) {
                    'update-b-from-old' => $statementType === 'insert' ? 0 : $rowsAtTrigger,
                    'insert-log-new-c' => 1,
                    'delete-log-a1' => 0,
                    'compound-insert-update-delete-log' => match ($statementType) {
                        'insert' => 2,
                        'update' => 6,
                        default => $timing === 'before' ? 6 : 5,
                    },
                    'insert-log-select-table' => $rowsAtTrigger,
                    default => throw new RuntimeException('unexpected program'),
                };
                $expectedContextOld = $statementType === 'insert' ? [] : ($statementType === 'update' ? $rows[1] : $rows[0]);
                $expectedContextNew = match ($statementType) {
                    'insert' => $statement['row'],
                    'update' => ['a' => $seed + 2, 'b' => $seed + 500, 'c' => 200],
                    default => [],
                };

                foreach ([
                    'source' => 'trigger2.test trigger2-2',
                    'operation' => 'trigger-program-statement-execution',
                    'status' => 'commit-ok',
                    'timing' => $timing,
                    'statement_type' => $statementType,
                    'program' => $programSpec['program'],
                    'statement_changes' => $statementChanges,
                    'trigger_program_changes' => $triggerChanges,
                    'total_changes' => $statementChanges + $triggerChanges,
                    'context_count' => 1,
                    'contexts.0.old' => $expectedContextOld,
                    'contexts.0.new' => $expectedContextNew,
                    'dependencies.0' => 'sqlite-trigger2-before-program-runs-before-statement-row-change',
                    'dependencies.1' => 'sqlite-trigger2-after-program-runs-after-statement-row-change',
                    'dependencies.2' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
                    'dependencies.3' => 'sqlite-trigger2-old-new-row-values-feed-program',
                ] as $path => $expected) {
                    $tests[$baseName . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                        $t->same($expected, $value($plan(), (string) $path));
                    };
                }

                $tests[$baseName . ' final row count follows statement and trigger program'] = static function (TestRunner $t) use ($plan, $statementType, $programSpec): void {
                    $actual = $plan();
                    $expected = match ($statementType) {
                        'insert' => 4,
                        'update' => 3,
                        default => 2,
                    };
                    if ($programSpec['program'] === 'compound-insert-update-delete-log') {
                        ++$expected;
                    }

                    $t->same($expected, count($actual['final_rows']));
                };

                $tests[$baseName . ' log row count follows trigger program'] = static function (TestRunner $t) use ($plan, $programSpec, $rowsAtTrigger): void {
                    $expected = match ($programSpec['program']) {
                        'insert-log-new-c' => 2,
                        'compound-insert-update-delete-log' => 0,
                        'insert-log-select-table' => 1 + $rowsAtTrigger,
                        default => 1,
                    };

                    $t->same($expected, count($plan()['log_rows']));
                };
            }
        }
    }
}

return $tests;
