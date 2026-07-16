<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger2 program corpus cites trigger program section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'Trigger program execution tests.'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger2-2.$ii-before'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger2-2.$ii-after'));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO log select * from tbl'));
    },
];

$programs = [
    'update-b-from-old',
    'insert-log-new-c',
    'delete-log-a1',
    'compound-insert-update-delete-log',
    'insert-log-select-table',
];
$statementTypes = ['insert', 'update', 'delete'];
$timings = ['before', 'after'];

for ($seed = 1; $seed <= 34; ++$seed) {
    foreach ($programs as $programIndex => $program) {
        foreach ($statementTypes as $typeIndex => $type) {
            foreach ($timings as $timing) {
                $rows = [
                    ['a' => $seed, 'b' => $seed + 10, 'c' => $seed + 20],
                    ['a' => $seed + 1, 'b' => $seed + 11, 'c' => $seed + 21],
                ];
                $logRows = [
                    ['a' => 1, 'b' => 2, 'c' => 3],
                    ['a' => 10 + $seed, 'b' => 20 + $seed, 'c' => 30 + $seed],
                ];
                $incoming = ['a' => 1000 + $seed, 'b' => 2000 + $seed, 'c' => 3000 + $seed + $programIndex];
                $statement = match ($type) {
                    'insert' => ['type' => 'insert', 'row' => $incoming],
                    'update' => [
                        'type' => 'update',
                        'set' => ['b' => 7000 + $seed + $typeIndex, 'c' => 8000 + $seed],
                        'where' => static fn (array $row): bool => $row['a'] === $seed,
                    ],
                    default => [
                        'type' => 'delete',
                        'where' => static fn (array $row): bool => $row['a'] === $seed,
                    ],
                };

                $case = sprintf('trigger2-2 program %s %s %s seed %02d', $program, $type, $timing, $seed);
                $tests[$case] = static function (TestRunner $t) use ($rows, $logRows, $statement, $program, $timing, $type, $incoming): void {
                    $actual = SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution($rows, $logRows, $statement, $program, $timing);

                    $t->same('trigger2.test trigger2-2', $actual['source']);
                    $t->same('trigger-program-statement-execution', $actual['operation']);
                    $t->same('commit-ok', $actual['status']);
                    $t->same($timing, $actual['timing']);
                    $t->same($type, $actual['statement_type']);
                    $t->same($program, $actual['program']);
                    $t->same(1, $actual['statement_changes']);
                    $t->same(1, $actual['context_count']);
                    $t->same($actual['statement_changes'] + $actual['trigger_program_changes'], $actual['total_changes']);
                    $t->same('sqlite-trigger2-before-program-runs-before-statement-row-change', $actual['dependencies'][0]);
                    $t->same('sqlite-trigger2-after-program-runs-after-statement-row-change', $actual['dependencies'][1]);
                    $t->same('sqlite-trigger2-trigger-program-can-update-insert-delete-select', $actual['dependencies'][2]);
                    $t->same('sqlite-trigger2-old-new-row-values-feed-program', $actual['dependencies'][3]);

                    if ($type === 'insert') {
                        $t->same($incoming, $actual['contexts'][0]['new']);
                        $t->true(in_array($incoming, $actual['final_rows'], true));
                    } elseif ($type === 'update') {
                        $t->same($rows[0], $actual['contexts'][0]['old']);
                        $t->same($statement['set']['b'], $actual['contexts'][0]['new']['b']);
                    } else {
                        $t->same($rows[0], $actual['contexts'][0]['old']);
                        $t->same(false, in_array($rows[0], $actual['final_rows'], true));
                    }

                    if ($program === 'insert-log-new-c') {
                        $t->true(in_array(['a' => (int) ($actual['contexts'][0]['new']['c'] ?? 0), 'b' => 2, 'c' => 3], $actual['log_rows'], true));
                    } elseif ($program === 'delete-log-a1') {
                        $t->same(false, in_array(['a' => 1, 'b' => 2, 'c' => 3], $actual['log_rows'], true));
                    } elseif ($program === 'insert-log-select-table') {
                        $t->true($actual['trigger_program_changes'] >= 1);
                    } else {
                        $t->true($actual['trigger_program_changes'] >= 0);
                    }
                };
            }
        }
    }
}

return $tests;
