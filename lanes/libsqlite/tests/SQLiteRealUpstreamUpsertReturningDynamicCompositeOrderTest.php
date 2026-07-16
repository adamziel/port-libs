<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$makeCompositeRows = static fn (int $variant): array => [
    ['k' => $variant, 'v' => 'alpha-' . $variant, 'c' => 0],
    ['k' => $variant + 1, 'v' => 'beta-' . $variant, 'c' => 0],
    ['k' => $variant + 2, 'v' => 'gamma-' . $variant, 'c' => 0],
];

$runCompositeInsert = static function (int $variant, array $target) use ($makeCompositeRows): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $makeCompositeRows($variant),
        [
            ['k' => $variant, 'v' => 'alpha-' . $variant, 'c' => 8],
            ['k' => $variant + 9, 'v' => 'new-' . $variant, 'c' => 3],
            ['k' => $variant + 1, 'v' => 'beta-' . $variant, 'c' => 4],
        ],
        [[
            'target' => $target,
            'action' => 'nothing',
        ]],
        [['k', 'v']],
    );
};

$runExcludedAliasBatch = static function (int $variant): array {
    $values = [
        ['a' => $variant, 'b' => $variant + 1, 'c' => 0],
        ['a' => $variant, 'b' => $variant + 1, 'c' => 0],
        ['a' => $variant + 2, 'b' => $variant + 3, 'c' => 0],
        ['a' => $variant, 'b' => $variant + 1, 'c' => 0],
        ['a' => $variant + 4, 'b' => $variant + 5, 'c' => 0],
        ['a' => $variant + 2, 'b' => $variant + 3, 'c' => 0],
    ];

    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [],
        $values,
        [[
            'target' => ['b', 'a'],
            'action' => 'update',
            'assignments' => ['c' => static fn (array $current): int => (int) $current['c'] + 1],
        ]],
        [['a', 'b']],
    );
};

$runBaseWhereBatch = static function (int $variant, int $firstReplacement, int $secondReplacement): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [
            ['a' => $variant, 'b' => $variant + 1, 'c' => 2],
            ['a' => $variant + 2, 'b' => $variant + 3, 'c' => 1],
            ['a' => $variant + 4, 'b' => $variant + 5, 'c' => 0],
        ],
        [
            ['a' => $variant, 'b' => $variant + 1, 'c' => $firstReplacement],
            ['a' => $variant, 'b' => $variant + 1, 'c' => $secondReplacement],
        ],
        [[
            'target' => ['b', 'a'],
            'action' => 'update',
            'assignments' => ['c' => static fn (array $current, array $incoming): int => (int) $incoming['c'] + 1],
            'where' => static fn (array $current, array $incoming): bool => (int) $current['c'] < (int) $incoming['c'],
        ]],
        [['a', 'b']],
    );
};

$runReturningMixedBatch = static function (int $variant, array $sourceKeys): array {
    $rows = [];
    $returningIds = [];
    $nextId = 1;

    foreach ($sourceKeys as $sourceKey) {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows,
            [['fooid' => $nextId, 'fooval' => $sourceKey, 'refcnt' => 1]],
            [[
                'target' => null,
                'action' => 'update',
                'assignments' => ['refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1],
            ]],
            [['fooval']],
        );
        $rows = $plan['after'];
        $returningIds[] = $plan['returning_rows'][0]['fooid'];
        if ($plan['inserted_rows'] !== []) {
            ++$nextId;
        }
    }

    return ['rows' => $rows, 'returning_ids' => $returningIds];
};

for ($variant = 1; $variant <= 150; ++$variant) {
    $prefix = 'real upstream upsert3 composite target order variant ' . $variant;

    $tests[$prefix . ' accepts declared composite order and skips duplicates'] = static function (TestRunner $t) use ($runCompositeInsert, $variant): void {
        $result = $runCompositeInsert($variant, ['k', 'v']);

        $t->same(1, count($result['inserted_rows']));
        $t->same(2, count($result['skipped_rows']));
        $t->same([$variant + 9], array_column($result['inserted_rows'], 'k'));
    };

    $tests[$prefix . ' accepts reversed conflict target order'] = static function (TestRunner $t) use ($runCompositeInsert, $variant): void {
        $result = $runCompositeInsert($variant, ['v', 'k']);

        $t->same(4, count($result['after']));
        $t->same([], $result['updated_rows']);
        $t->same([$variant, $variant + 1], array_column($result['skipped_rows'], 'k'));
    };

    $tests[$prefix . ' rejects partial k target like upsert3-110'] = static function (TestRunner $t) use ($makeCompositeRows, $variant): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $makeCompositeRows($variant),
            [['k' => $variant, 'v' => 'alpha-' . $variant, 'c' => 0]],
            [['target' => ['k'], 'action' => 'nothing']],
            [['k', 'v']],
        ));
    };

    $tests[$prefix . ' rejects partial v target like upsert3-120'] = static function (TestRunner $t) use ($makeCompositeRows, $variant): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $makeCompositeRows($variant),
            [['k' => $variant, 'v' => 'alpha-' . $variant, 'c' => 0]],
            [['target' => ['v'], 'action' => 'nothing']],
            [['k', 'v']],
        ));
    };

    $tests[$prefix . ' applies excluded alias counter sequence'] = static function (TestRunner $t) use ($runExcludedAliasBatch, $variant): void {
        $result = $runExcludedAliasBatch($variant);
        $byKey = [];
        foreach ($result['after'] as $row) {
            $byKey[$row['a']] = $row['c'];
        }
        ksort($byKey);

        $t->same([$variant, $variant + 2, $variant + 4], array_keys($byKey));
        $t->same([2, 1, 0], array_values($byKey));
        $t->same(6, $result['changes']);
    };

    $tests[$prefix . ' applies base alias WHERE before excluded update'] = static function (TestRunner $t) use ($runBaseWhereBatch, $variant): void {
        $result = $runBaseWhereBatch($variant, 8 + ($variant % 3), 3);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b', 'c']);

        $t->same([['a' => $variant, 'b' => $variant + 1, 'c' => 9 + ($variant % 3)]], $projected);
        $t->same(1, $result['changes']);
        $t->same(1, count($result['skipped_rows']));
    };

    $tests[$prefix . ' preserves RETURNING order for mixed insert and update'] = static function (TestRunner $t) use ($runReturningMixedBatch, $variant): void {
        $sourceKeys = [17 + $variant, 4711 + $variant, 17 + $variant, 99 + $variant, 4711 + $variant];
        $result = $runReturningMixedBatch($variant, $sourceKeys);

        $t->same([1, 2, 1, 3, 2], $result['returning_ids']);
        $t->same([2, 2, 1], array_column($result['rows'], 'refcnt'));
    };

    $tests[$prefix . ' projects returning expression aliases after mixed batch'] = static function (TestRunner $t) use ($runReturningMixedBatch, $variant): void {
        $sourceKeys = [7 + $variant, 8 + $variant, 7 + $variant, 7 + $variant];
        $result = $runReturningMixedBatch($variant, $sourceKeys);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['rows'], [
            'id' => 'fooid',
            'value' => 'fooval',
            'tag' => static fn (array $row): string => $row['fooid'] . ':' . $row['refcnt'],
        ]);

        $t->same([
            ['id' => 1, 'value' => 7 + $variant, 'tag' => '1:3'],
            ['id' => 2, 'value' => 8 + $variant, 'tag' => '2:1'],
        ], $projected);
    };
}

return $tests;
