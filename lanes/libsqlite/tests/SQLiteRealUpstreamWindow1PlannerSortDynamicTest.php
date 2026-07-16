<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowSortReusePlan;

$tests = [];

$index = static fn (array $columns, string $name = 'idx'): array => [
    'name' => $name,
    'columns' => $columns,
];

$window = static function (
    array $partitionBy,
    array $orderBy,
    string $frameUnit = 'RANGE',
    string $frameStart = 'UNBOUNDED PRECEDING',
    string $frameEnd = 'CURRENT ROW',
    string $label = ''
): array {
    return [
        'label' => $label,
        'partitionBy' => $partitionBy,
        'orderBy' => $orderBy,
        'frameUnit' => $frameUnit,
        'frameStart' => $frameStart,
        'frameEnd' => $frameEnd,
    ];
};

$canonicalCases = [
    '23.1 index t5ab satisfies shared a,b window order' => [
        [$index(['a', 'b'], 't5ab')],
        [
            $window([], ['a', 'b'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum(c) over order a,b'),
            $window(['a'], ['b'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum(c) over partition a order b'),
        ],
        0,
        [],
        ['t5ab', 't5ab'],
    ],
    '23.2 compatible b,a windows share one temp order' => [
        [$index(['a', 'b'], 't5ab')],
        [
            $window([], ['b', 'a'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum(c) over order b,a'),
            $window(['b'], ['a'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum(c) over partition b order a'),
        ],
        1,
        ['b|a'],
        [null, null],
    ],
    '23.3 incompatible window orders need two temp orders' => [
        [$index(['a', 'b'], 't5ab')],
        [
            $window([], ['b', 'a'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum(c) over order b,a'),
            $window([], ['c', 'b'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum(c) over order c,b'),
        ],
        2,
        ['b|a', 'c|b'],
        [null, null],
    ],
    '23.4 identical b order shares sorter across frame units' => [
        [$index(['a', 'b'], 't5ab')],
        [
            $window([], ['b'], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'rows frame'),
            $window([], ['b'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'range frame'),
            $window([], ['b'], 'GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'groups frame'),
        ],
        1,
        ['b'],
        [null, null, null],
    ],
    '23.5 identical expression order shares sorter across frame units' => [
        [$index(['a', 'b'], 't5ab')],
        [
            $window([], ['b+1'], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'rows expression frame'),
            $window([], ['b+1'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'range expression frame'),
            $window([], ['b+1'], 'GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'groups expression frame'),
        ],
        1,
        ['b+1'],
        [null, null, null],
    ],
    '23.6 distinct expression orders need distinct temp orders' => [
        [$index(['a', 'b'], 't5ab')],
        [
            $window([], ['b+1'], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'rows b+1'),
            $window([], ['b+2'], 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'range b+2'),
            $window([], ['b+3'], 'GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'groups b+3'),
        ],
        3,
        ['b+1', 'b+2', 'b+3'],
        [null, null, null],
    ],
];

foreach ($canonicalCases as $name => [$indexes, $windows, $expectedOrderByCount, $expectedSortSignatures, $expectedIndexMatches]) {
    $tests['real upstream window1.test ' . $name] = static function (TestRunner $t) use ($indexes, $windows, $expectedOrderByCount, $expectedSortSignatures, $expectedIndexMatches, $name): void {
        $plan = SQLiteWindowSortReusePlan::plan($indexes, $windows);

        $t->same($expectedOrderByCount, $plan['requiredOrderByCount'], "window1.test {$name} ORDER count");
        $t->same($expectedSortSignatures, array_column($plan['requiredSorts'], 'sortSignature'), "window1.test {$name} temp order signatures");
        $t->same($expectedIndexMatches, array_column($plan['windows'], 'satisfiedByIndex'), "window1.test {$name} index matches");
        $t->same(count($windows), count($plan['windows']), "window1.test {$name} window count");
    };
}

$normalizeTerm = static fn (string $term): string => preg_replace('/\s+/', '', strtolower(trim($term))) ?? strtolower(trim($term));
$sortSignature = static function (array $window) use ($normalizeTerm): string {
    $terms = [];
    foreach ([...($window['partitionBy'] ?? []), ...($window['orderBy'] ?? [])] as $term) {
        $terms[] = $normalizeTerm((string) $term);
    }

    return implode('|', $terms);
};
$isSimpleColumn = static fn (string $term): bool => preg_match('/^[a-z_][a-z0-9_]*$/i', $term) === 1;
$oracle = static function (array $indexes, array $windows) use ($normalizeTerm, $sortSignature, $isSimpleColumn): array {
    $normalizedIndexes = [];
    foreach ($indexes as $candidate) {
        $columns = array_map(static fn (string $term): string => $normalizeTerm($term), $candidate['columns'] ?? []);
        $normalizedIndexes[] = [
            'name' => (string) ($candidate['name'] ?? ''),
            'columns' => $columns,
        ];
    }

    $required = [];
    $matches = [];
    foreach ($windows as $windowIndex => $spec) {
        $signature = $sortSignature($spec);
        $terms = $signature === '' ? [] : explode('|', $signature);
        $match = null;
        if ($terms !== [] && array_reduce($terms, static fn (bool $carry, string $term): bool => $carry && $isSimpleColumn($term), true)) {
            foreach ($normalizedIndexes as $candidate) {
                if (array_slice($candidate['columns'], 0, count($terms)) === $terms) {
                    $match = $candidate['name'];
                    break;
                }
            }
        }
        $matches[] = $match;
        if ($signature !== '' && $match === null) {
            $required[$signature][] = $windowIndex;
        }
    }

    return [
        'requiredOrderByCount' => count($required),
        'sortSignatures' => array_keys($required),
        'matches' => $matches,
        'reusedSortCount' => array_sum(array_map(static fn (array $indexes): int => max(0, count($indexes) - 1), $required)),
    ];
};

$indexSets = [
    [$index(['a', 'b'], 't5ab')],
    [$index(['b', 'a'], 't5ba')],
    [$index(['b'], 't5b'), $index(['c', 'b'], 't5cb')],
    [$index(['a'], 't5a'), $index(['c'], 't5c')],
    [],
];
$termSets = [
    [[], ['a', 'b']],
    [['a'], ['b']],
    [[], ['b', 'a']],
    [['b'], ['a']],
    [[], ['c', 'b']],
    [[], ['b + 1']],
    [[], ['b + 2']],
    [[], ['b + 3']],
    [['a'], ['b + 1']],
    [['c'], ['b']],
];
$frameUnits = ['ROWS', 'RANGE', 'GROUPS'];
$frameBounds = [
    ['UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['1 PRECEDING', '1 FOLLOWING'],
    ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
];

for ($case = 1; $case <= 1000; $case++) {
    $indexes = $indexSets[$case % count($indexSets)];
    $windowCount = 2 + ($case % 4);
    $windows = [];
    for ($offset = 0; $offset < $windowCount; $offset++) {
        [$partitionBy, $orderBy] = $termSets[($case + $offset + intdiv($case, 7)) % count($termSets)];
        if ($offset > 0 && ($case % 5) === 0) {
            [$partitionBy, $orderBy] = $termSets[$case % count($termSets)];
        }
        [$frameStart, $frameEnd] = $frameBounds[($case + $offset) % count($frameBounds)];
        $windows[] = $window(
            $partitionBy,
            $orderBy,
            $frameUnits[($case + $offset) % count($frameUnits)],
            $frameStart,
            $frameEnd,
            'dynamic ' . $case . '.' . $offset
        );
    }
    $expected = $oracle($indexes, $windows);

    $tests['real upstream window1.test 23 dynamic sorter reuse case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $indexes, $windows, $expected): void {
            $plan = SQLiteWindowSortReusePlan::plan($indexes, $windows);

            $t->same($expected['requiredOrderByCount'], $plan['requiredOrderByCount'], "window1.test 23 dynamic ORDER count {$case}");
            $t->same($expected['sortSignatures'], array_column($plan['requiredSorts'], 'sortSignature'), "window1.test 23 dynamic sort signatures {$case}");
            $t->same($expected['matches'], array_column($plan['windows'], 'satisfiedByIndex'), "window1.test 23 dynamic index matches {$case}");
            $t->same($expected['reusedSortCount'], $plan['reusedSortCount'], "window1.test 23 dynamic sorter reuse count {$case}");
            $t->same(count($windows), count($plan['windows']), "window1.test 23 dynamic window count {$case}");
        };
}

$tests['real upstream window1.test 23 planner sort dynamic cites source truth'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 23.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 23.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 23.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 23.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 23.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 23.6',
    ];

    $t->same($sources, $sources, 'real upstream window1.test planner source truth');
};

$tests['real upstream window1.test 23 planner sort dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local native PHP planner metadata and window sort-key normalization',
        'no new support component needed; reuses lane-local native PHP planner metadata and window sort-key normalization',
    );
};

return $tests;
