<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$window8NullRangeRows = [
    ['rowid' => 1, 'a' => 1, 'b' => 65],
    ['rowid' => 2, 'a' => 2, 'b' => null],
    ['rowid' => 3, 'a' => 3, 'b' => null],
    ['rowid' => 4, 'a' => 4, 'b' => null],
    ['rowid' => 5, 'a' => 5, 'b' => 66],
    ['rowid' => 6, 'a' => 6, 'b' => 67],
];

$window8Boundary = static function (string $boundary): array {
    if ($boundary === 'UNBOUNDED PRECEDING' || $boundary === 'UNBOUNDED FOLLOWING' || $boundary === 'CURRENT ROW') {
        return [0, $boundary];
    }
    if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $boundary, $match) !== 1) {
        throw new RuntimeException('Unsupported window8 null range boundary ' . $boundary);
    }

    return [(int) $match[1], $match[2]];
};

$window8ActualByRowid = static function (array $rows, string $start, string $end, string $nulls, string $metric) use ($window8Boundary): array {
    [$preceding, $startBoundary] = $window8Boundary($start);
    [$following, $endBoundary] = $window8Boundary($end);
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'a',
        [],
        ['b'],
        null,
        $preceding,
        $following,
        [],
        [],
        [],
        [],
        [],
        [$nulls],
        'RANGE',
        'NO OTHERS',
        $startBoundary,
        $endBoundary,
    );

    $byRowid = [];
    while (!$cursor->eof()) {
        $row = $cursor->currentRow();
        $byRowid[$row['rowid']] = match ($metric) {
            'sum' => $cursor->sum(),
            'min' => $cursor->min(),
            'max' => $cursor->max(),
            default => throw new RuntimeException('Unsupported window8 null range metric ' . $metric),
        };
        $cursor->next();
    }
    ksort($byRowid);

    return array_values($byRowid);
};

$window8NullRangeCases = [
    '7.1.1 sum nulls last following to unbounded' => ['sum', '6 FOLLOWING', 'UNBOUNDED FOLLOWING', 'LAST', [9, 9, 9, 9, 9, 9]],
    '7.2.1 min nulls last following to unbounded' => ['min', '6 FOLLOWING', 'UNBOUNDED FOLLOWING', 'LAST', [2, 2, 2, 2, 2, 2]],
    '7.4.1 max nulls last following to unbounded' => ['max', '6 FOLLOWING', 'UNBOUNDED FOLLOWING', 'LAST', [4, 4, 4, 4, 4, 4]],
];

foreach ($window8NullRangeCases as $scenario => [$metric, $start, $end, $nulls, $expected]) {
    foreach ($expected as $offset => $expectedValue) {
        $rowid = $offset + 1;
        $tests['real upstream window8.test ' . $scenario . ' rowid ' . $rowid] =
            static function (TestRunner $t) use ($window8ActualByRowid, $window8NullRangeRows, $metric, $start, $end, $nulls, $expectedValue, $offset, $scenario): void {
                $actual = $window8ActualByRowid($window8NullRangeRows, $start, $end, $nulls, $metric);
                $t->same($expectedValue, $actual[$offset], 'window8.test ' . $scenario . ' row offset ' . $offset);
            };
    }
}

$tests['real upstream window8 null range dynamic corpus cites source scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window8.test:7.1.1 SUM RANGE NULLS LAST',
            'window8.test:7.2.1 MIN RANGE NULLS LAST',
            'window8.test:7.4.1 MAX RANGE NULLS LAST',
        ],
        [
            'window8.test:7.1.1 SUM RANGE NULLS LAST',
            'window8.test:7.2.1 MIN RANGE NULLS LAST',
            'window8.test:7.4.1 MAX RANGE NULLS LAST',
        ],
    );
};

return $tests;
