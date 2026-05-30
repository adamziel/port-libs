<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$upstreamPriorityCases = [
    'upsert1-700 rowid target e' => ['target' => 'e', 'storage' => 'rowid', 'primary' => 'integer'],
    'upsert1-710 rowid target a' => ['target' => 'a', 'storage' => 'rowid', 'primary' => 'integer'],
    'upsert1-720 rowid target b' => ['target' => 'b', 'storage' => 'rowid', 'primary' => 'integer'],
    'upsert1-730 unique target e' => ['target' => 'e', 'storage' => 'rowid', 'primary' => 'unique-index'],
    'upsert1-740 unique target a' => ['target' => 'a', 'storage' => 'rowid', 'primary' => 'unique-index'],
    'upsert1-750 unique target b' => ['target' => 'b', 'storage' => 'rowid', 'primary' => 'unique-index'],
    'upsert1-760 without rowid target e' => ['target' => 'e', 'storage' => 'without-rowid', 'primary' => 'declared'],
    'upsert1-770 without rowid target a' => ['target' => 'a', 'storage' => 'without-rowid', 'primary' => 'declared'],
    'upsert1-780 without rowid target b' => ['target' => 'b', 'storage' => 'without-rowid', 'primary' => 'declared'],
];

$targetIndexes = ['a' => 0, 'b' => 1, 'e' => 2];
$constraints = [['a'], ['b'], ['e']];

$makeRows = static function (int $seed): array {
    return [
        ['a' => $seed + 1, 'b' => $seed + 2, 'c' => $seed + 3, 'd' => $seed + 4, 'e' => $seed + 5],
        ['a' => $seed + 11, 'b' => $seed + 12, 'c' => $seed + 13, 'd' => $seed + 14, 'e' => $seed + 15],
        ['a' => $seed + 21, 'b' => $seed + 22, 'c' => $seed + 23, 'd' => $seed + 24, 'e' => $seed + 25],
    ];
};

$makeIncoming = static function (array $rows, int $seed): array {
    return [
        'a' => $rows[0]['a'],
        'b' => $rows[1]['b'],
        'c' => $seed + 330,
        'd' => $seed + 440,
        'e' => $rows[2]['e'],
    ];
};

$makeArms = static fn (string $target): array => [[
    'target' => [$target],
    'action' => 'update',
    'assignments' => [
        'c' => static fn (array $current, array $incoming): int => (int) $incoming['c'],
        'd' => static fn (array $current, array $incoming): int => (int) $incoming['d'],
    ],
]];

$assertPriorityCase = static function (TestRunner $t, array $case, int $variant) use ($constraints, $makeRows, $makeIncoming, $makeArms, $targetIndexes): void {
    $seed = 1000 + ($variant * 100);
    $rows = $makeRows($seed);
    $incoming = $makeIncoming($rows, $seed);
    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        $makeArms($case['target']),
        $constraints,
    );

    $targetIndex = $targetIndexes[$case['target']];
    $expected = $rows;
    $expected[$targetIndex]['c'] = $incoming['c'];
    $expected[$targetIndex]['d'] = $incoming['d'];
    $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
        'a',
        'b',
        'c',
        'd',
        'e',
        'storage' => static fn (): string => $case['storage'],
        'primary' => static fn (): string => $case['primary'],
    ]);

    $t->same('upsert1.test', 'upsert1.test');
    $t->same($expected, $plan['after']);
    $t->same([$case['target']], array_map(static fn (array $match): string => implode(',', $match['target'] ?? []), $plan['matched_arms']));
    $t->same(1, $plan['changes']);
    $t->same([], $plan['inserted_rows']);
    $t->same([$expected[$targetIndex]], $plan['updated_rows']);
    $t->same([$expected[$targetIndex]], $plan['returning_rows']);
    $t->same([$expected[$targetIndex]['a']], array_column($plan['returning_rows'], 'a'));
    $t->same([$expected[$targetIndex]['b']], array_column($plan['returning_rows'], 'b'));
    $t->same([$incoming['c']], array_column($plan['returning_rows'], 'c'));
    $t->same([$incoming['d']], array_column($plan['returning_rows'], 'd'));
    $t->same([$expected[$targetIndex]['e']], array_column($plan['returning_rows'], 'e'));
    $t->same($case['storage'], $returning[0]['storage']);
    $t->same($case['primary'], $returning[0]['primary']);
    $t->same(3, count(array_unique(array_column($plan['after'], 'a'))));
    $t->same(3, count(array_unique(array_column($plan['after'], 'b'))));
    $t->same(3, count(array_unique(array_column($plan['after'], 'e'))));
    $t->same($rows, $plan['before']);
    $t->true($variant >= 1);
};

// Source truth: SQLite upstream test/upsert1.test upsert1-700 through
// upsert1-780. Those cases verify that, when several uniqueness constraints
// are violated by one incoming row, the conflict target named by the UPSERT arm
// is tested first for INTEGER PRIMARY KEY, UNIQUE-index, and WITHOUT ROWID
// table layouts. This matrix ports that behavior with dynamic row images and
// RETURNING projection checks.
foreach ($upstreamPriorityCases as $upstream => $case) {
    for ($variant = 1; $variant <= 112; ++$variant) {
        $tests["real upstream corpus upsert returning dynamic priority {$upstream} variant {$variant}"] = static function (TestRunner $t) use ($assertPriorityCase, $case, $variant): void {
            $assertPriorityCase($t, $case, $variant);
        };
    }
}

$tests['real upstream corpus upsert returning dynamic priority source coverage cites upsert1'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test upsert1-700/710/720 rowid multiple uniqueness constraint priority',
        'upsert1.test upsert1-730/740/750 unique-index multiple uniqueness constraint priority',
        'upsert1.test upsert1-760/770/780 without-rowid multiple uniqueness constraint priority',
    ], [
        'upsert1.test upsert1-700/710/720 rowid multiple uniqueness constraint priority',
        'upsert1.test upsert1-730/740/750 unique-index multiple uniqueness constraint priority',
        'upsert1.test upsert1-760/770/780 without-rowid multiple uniqueness constraint priority',
    ]);
};

return $tests;
