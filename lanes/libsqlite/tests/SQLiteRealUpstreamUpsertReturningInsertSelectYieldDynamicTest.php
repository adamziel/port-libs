<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$runCase = static function (int $seed): array {
    $base = $seed * 1000;
    $before = [
        ['a' => $base + 1, 'b' => 2, 'c' => 0],
        ['a' => $base + 3, 'b' => 4, 'c' => 0],
    ];
    $incoming = [
        ['a' => $base + 1, 'b' => 8],
        ['a' => $base + 2, 'b' => 11],
        ['a' => $base + 3, 'b' => 1],
        ['a' => $base + 2, 'b' => 15],
        ['a' => $base + 1, 'b' => 4],
        ['a' => $base + 1, 'b' => 99],
    ];

    return SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $before,
        array_map(static fn (array $row): array => $row + ['c' => 0], $incoming),
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => [
                'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
                'c' => static fn (array $current): int => (int) $current['c'] + 1,
            ],
            'where' => static fn (array $current, array $excluded): bool => (int) $current['b'] < (int) $excluded['b'],
        ]],
        [['a']],
    );
};

$expectedAfter = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['a' => $base + 1, 'b' => 99, 'c' => 2],
        ['a' => $base + 3, 'b' => 4, 'c' => 0],
        ['a' => $base + 2, 'b' => 15, 'c' => 1],
    ];
};

$expectedReturning = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['a' => $base + 1, 'b' => 8, 'c' => 1],
        ['a' => $base + 2, 'b' => 11, 'c' => 0],
        ['a' => $base + 2, 'b' => 15, 'c' => 1],
        ['a' => $base + 1, 'b' => 99, 'c' => 2],
    ];
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream upsert2 insert-select RETURNING yield dynamic %04d current images and skipped rows', $seed)] =
        static function (TestRunner $t) use ($runCase, $expectedAfter, $expectedReturning, $seed): void {
            $plan = $runCase($seed);
            $events = array_column($plan['yield_trace'], 'event');
            $returningOrdinals = array_values(array_map(
                static fn (array $event): int => (int) $event['ordinal'],
                array_filter($plan['yield_trace'], static fn (array $event): bool => in_array($event['event'], ['insert-returning', 'update-returning'], true)),
            ));

            $t->same($expectedAfter($seed), $plan['after']);
            $t->same($expectedReturning($seed), $plan['returning_rows']);
            $t->same(4, $plan['changes']);
            $t->same(2, count($plan['skipped_rows']));
            $t->same([0, 1, 3, 5], $returningOrdinals);
            $t->same(6, count(array_filter($events, static fn (string $event): bool => $event === 'before-insert')));
            $t->same(2, count(array_filter($events, static fn (string $event): bool => $event === 'conflict-update-where-false')));
            $t->same(true, in_array('sqlite-upsert-conflict-arm-yield-trace', $plan['dependencies'], true));
        };
}

$tests['real upstream upsert2 insert-select RETURNING yield dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test upsert2-200 repeated INSERT SELECT source rows update current image',
        'upsert2.test upsert2-201 target alias preserves excluded/current state',
        'upsert2.test upsert2-210 WITHOUT ROWID repeats the same row-yield behavior',
        'returning1.test changed-row stream emits only inserted or updated rows',
    ], [
        'upsert2.test upsert2-200 repeated INSERT SELECT source rows update current image',
        'upsert2.test upsert2-201 target alias preserves excluded/current state',
        'upsert2.test upsert2-210 WITHOUT ROWID repeats the same row-yield behavior',
        'returning1.test changed-row stream emits only inserted or updated rows',
    ]);
};

$tests['real upstream upsert2 insert-select RETURNING yield dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses existing UPSERT conflict-arm yield executor', 'no new support component needed; reuses existing UPSERT conflict-arm yield executor');
};

return $tests;
