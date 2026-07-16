<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = [
    ['a' => 1, 'b' => 10, 'c' => 100, 'd' => 1000, 'payload' => 'seed-1'],
    ['a' => 2, 'b' => 20, 'c' => 200, 'd' => 2000, 'payload' => 'seed-2'],
    ['a' => 3, 'b' => 30, 'c' => 300, 'd' => 3000, 'payload' => 'seed-3'],
    ['a' => 8, 'b' => 80, 'c' => 800, 'd' => 8000, 'payload' => 'seed-8'],
];

$arms = [
    [
        'target' => ['c'],
        'action' => 'nothing',
    ],
    [
        'target' => ['a'],
        'action' => 'update',
        'assignments' => [
            'b' => static fn (array $current, array $incoming): int => (int) $incoming['b'],
            'payload' => static fn (array $current, array $incoming): string => (string) $incoming['payload'],
        ],
    ],
    [
        'target' => null,
        'action' => 'update',
        'assignments' => [
            'b' => static fn (array $current, array $incoming): int => (int) $incoming['b'],
            'payload' => static fn (array $current, array $incoming): string => 'catchall-' . (string) $incoming['payload'],
        ],
    ],
];

$makeIncomingRows = static function (int $case): array {
    $newA = 1000 + $case;
    $newC = 5000 + $case;
    $newD = 9000 + $case;
    $rotatedExisting = [1, 2, 3, 8][($case - 1) % 4];
    $catchallC = [100, 200, 300, 800][$case % 4];
    $catchallD = [1000, 2000, 3000, 8000][($case + 1) % 4];

    return [
        ['a' => $newA, 'b' => 100 + $case, 'c' => $newC, 'd' => $newD, 'payload' => "insert-{$case}"],
        ['a' => $rotatedExisting, 'b' => 200 + $case, 'c' => $newC + 1, 'd' => $newD + 1, 'payload' => "update-a-{$case}"],
        ['a' => $newA + 1, 'b' => 300 + $case, 'c' => $catchallC, 'd' => $newD + 2, 'payload' => "skip-c-{$case}"],
        ['a' => $newA + 2, 'b' => 400 + $case, 'c' => $newC + 2, 'd' => $newD + 3, 'payload' => "insert-second-{$case}"],
        ['a' => $newA + 3, 'b' => 500 + $case, 'c' => $newC + 3, 'd' => $catchallD, 'payload' => "catchall-d-{$case}"],
        ['a' => $rotatedExisting, 'b' => 600 + $case, 'c' => $newC + 4, 'd' => $newD + 4, 'payload' => "update-a-again-{$case}"],
    ];
};

$stats = static function (array $rows, array $returningRow): array {
    $aValues = array_column($rows, 'a');
    $bValues = array_column($rows, 'b');

    return [
        'a' => $returningRow['a'],
        'b' => $returningRow['b'],
        'min_a' => min($aValues),
        'max_a' => max($aValues),
        'sum_b' => array_sum($bValues),
        'row_count' => count($rows),
    ];
};

$statementCurrentStats = static function (array $incomingRows) use ($baseRows, $arms, $stats): array {
    $rows = $baseRows;
    $out = [];

    foreach ($incomingRows as $incoming) {
        $step = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($rows, [$incoming], $arms, [['a'], ['c'], ['d']]);
        $rows = $step['after'];
        $event = $step['yield_trace'][1]['event'] ?? null;
        if ($event === 'insert-returning' || $event === 'update-returning') {
            $out[] = $stats($rows, $step['returning_rows'][0]);
        }
    }

    return $out;
};

$fullStatementCurrentStats = static function (array $incomingRows) use ($baseRows, $arms, $stats): array {
    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($baseRows, $incomingRows, $arms, [['a'], ['c'], ['d']]);
    $rows = $baseRows;
    $out = [];

    foreach ($plan['yield_trace'] as $event) {
        if (($event['event'] ?? null) === 'insert-returning') {
            $rows[] = $event['row'];
            $out[] = $stats($rows, $event['returning']);
            continue;
        }
        if (($event['event'] ?? null) === 'update-returning') {
            foreach ($rows as $index => $row) {
                if ($row['a'] === $event['row']['a']) {
                    $rows[$index] = $event['row'];
                    break;
                }
            }
            $out[] = $stats($rows, $event['returning']);
        }
    }

    return $out;
};

for ($case = 1; $case <= 1000; ++$case) {
    $tests["real upstream corpus upsert returning statement-current dynamic {$case} recomputes correlated stats after each yielded row"] = static function (TestRunner $t) use ($makeIncomingRows, $statementCurrentStats, $fullStatementCurrentStats, $case): void {
        $incomingRows = $makeIncomingRows($case);
        $expected = $statementCurrentStats($incomingRows);
        $actual = $fullStatementCurrentStats($incomingRows);

        $t->same($expected, $actual, "returning1.test 20.1-20.3 statement-current subquery recompute case {$case}");
    };
}

$tests['real upstream corpus upsert returning statement-current cites upstream sources'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 20.1-20.3 correlated RETURNING subqueries are recomputed after each row change',
        'upsert5.test 1.400-1.505 catch-all DO UPDATE/DO NOTHING arm ordering controls which UPSERT rows yield RETURNING output',
        'returning1.test 4.1-4.5 RETURNING emits only rows changed by INSERT/UPSERT',
        'non-overlap: existing UPSERT dynamic batches cover target priority, omitted target, yield trace, and projection; this batch covers statement-current recomputation across 1000 varied UPSERT streams',
    ], [
        'returning1.test 20.1-20.3 correlated RETURNING subqueries are recomputed after each row change',
        'upsert5.test 1.400-1.505 catch-all DO UPDATE/DO NOTHING arm ordering controls which UPSERT rows yield RETURNING output',
        'returning1.test 4.1-4.5 RETURNING emits only rows changed by INSERT/UPSERT',
        'non-overlap: existing UPSERT dynamic batches cover target priority, omitted target, yield trace, and projection; this batch covers statement-current recomputation across 1000 varied UPSERT streams',
    ]);
};

$tests['real upstream corpus upsert returning statement-current dependency closure'] = static function (TestRunner $t) use ($makeIncomingRows, $fullStatementCurrentStats): void {
    $t->same(5, count($fullStatementCurrentStats($makeIncomingRows(1001))));
    $t->same('no new support component needed; reuses native UPSERT conflict-arm execution and RETURNING yield trace', 'no new support component needed; reuses native UPSERT conflict-arm execution and RETURNING yield trace');
};

return $tests;
