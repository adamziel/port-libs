<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$datasets = [
    'ascending integers' => [
        'values' => [1, 2, 3, 4, 5, 6],
        'keys' => [1, 2, 3, 4, 5, 6],
        'filters' => [1, 0, 1, 1, 0, 1],
    ],
    'peer groups' => [
        'values' => [10, 11, 12, 13, 14, 15, 16],
        'keys' => [1, 1, 2, 3, 3, 3, 4],
        'filters' => [1, 1, 0, 1, 0, 1, 1],
    ],
    'negative keys' => [
        'values' => [-4, -1, 0, 1, 4],
        'keys' => [-2, -1, 0, 1, 2],
        'filters' => [1, 0, 1, 0, 1],
    ],
    'float keys' => [
        'values' => [3, 6, 9, 12, 15, 18],
        'keys' => [0.5, 1.5, 1.5, 2.5, 3.5, 5.5],
        'filters' => [0, 1, 1, 0, 1, 1],
    ],
    'sparse order' => [
        'values' => [7, 1, 5, 9, 3, 11, 13, 15],
        'keys' => [4, 8, 12, 16, 20, 24, 28, 32],
        'filters' => [1, 1, 1, 0, 1, 0, 1, 1],
    ],
    'mixed null values' => [
        'values' => [null, 2, null, 4, 6, null, 8],
        'keys' => [1, 2, 3, 4, 5, 6, 7],
        'filters' => [1, 0, 1, 1, 1, 0, 1],
    ],
];

$helpers = [
    'nth-value-by-row',
    'first-value-frame',
    'aggregate-count-frame',
    'aggregate-sum-filtered-frame',
    'custom-count-frame',
    'group-concat-separator-frame',
    'aggregate-row-summary-frame',
];

$runFrameHelper = static function (string $helper, array $dataset, string $unit, string $start, string $end): mixed {
    $values = $dataset['values'];
    $keys = $dataset['keys'];
    $filters = $dataset['filters'];
    $stringValues = array_map(static fn (mixed $value): string => $value === null ? 'n' : 'v' . (string) $value, $values);
    $separators = array_map(static fn (int $index): string => $index % 2 === 0 ? '.' : '|', array_keys($values));

    return match ($helper) {
        'nth-value-by-row' => SQLiteWindowFunction::nthValueByRow(
            $values,
            array_fill(0, count($values), 1),
            $keys,
            $unit,
            $start,
            $end,
        ),
        'first-value-frame' => SQLiteWindowFunction::valueFrameBetweenValues(
            'first_value',
            $values,
            $keys,
            $unit,
            $start,
            $end,
        ),
        'aggregate-count-frame' => SQLiteWindowFunction::aggregateFrameBetweenValues(
            'count',
            $values,
            $keys,
            $unit,
            $start,
            $end,
        ),
        'aggregate-sum-filtered-frame' => SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $values,
            $keys,
            $unit,
            $start,
            $end,
            'NO OTHERS',
            $filters,
        ),
        'custom-count-frame' => SQLiteWindowFunction::customFrameBetweenValues(
            $values,
            $keys,
            $unit,
            $start,
            $end,
            static fn (array $frame): int => count($frame),
        ),
        'group-concat-separator-frame' => SQLiteWindowFunction::groupConcatFrameBetweenSeparators(
            $stringValues,
            $separators,
            $keys,
            $unit,
            $start,
            $end,
        ),
        'aggregate-row-summary-frame' => SQLiteWindowFunction::aggregateFrameBetweenRows(
            $values,
            $keys,
            $unit,
            $start,
            $end,
        ),
        default => throw new RuntimeException('Unknown window helper ' . $helper),
    };
};

$invalidFrameSpecs = [
    'window6.test 9.5 range starts at unbounded following' => ['UNBOUNDED FOLLOWING', 'UNBOUNDED FOLLOWING'],
    'window6.test 9.5 range unbounded following to current row' => ['UNBOUNDED FOLLOWING', 'CURRENT ROW'],
    'window6.test 9.5 range unbounded following to offset following' => ['UNBOUNDED FOLLOWING', '2 FOLLOWING'],
    'window6.test 9.6 range ends at unbounded preceding' => ['UNBOUNDED PRECEDING', 'UNBOUNDED PRECEDING'],
    'window6.test 9.7.1 current row cannot end at preceding' => ['CURRENT ROW', '4 PRECEDING'],
    'window6.test 9.7.3 following cannot end at current row' => ['4 FOLLOWING', 'CURRENT ROW'],
    'window6.test 9.7.4 following cannot end at preceding' => ['4 FOLLOWING', '2 PRECEDING'],
    'window6.test 9.7.4 following offset cannot end at preceding offset' => ['1 FOLLOWING', '1 PRECEDING'],
];

$caseNumber = 0;
foreach ($invalidFrameSpecs as $source => [$start, $end]) {
    foreach (['ROWS', 'RANGE', 'GROUPS'] as $unit) {
        foreach ($helpers as $helper) {
            foreach ($datasets as $datasetName => $dataset) {
                $caseNumber++;
                $tests[sprintf(
                    'real upstream window6 frame syntax dynamic %04d %s %s %s %s',
                    $caseNumber,
                    $source,
                    strtolower($unit),
                    $helper,
                    $datasetName,
                )] = static function (TestRunner $t) use ($runFrameHelper, $helper, $dataset, $unit, $start, $end): void {
                    $t->throws(
                        InvalidArgumentException::class,
                        static fn () => $runFrameHelper($helper, $dataset, $unit, $start, $end),
                    );
                };
            }
        }
    }
}

$orderedRangeCase = 0;
foreach ($invalidFrameSpecs as $source => [$start, $end]) {
    foreach ($datasets as $datasetName => $dataset) {
        $orderedRangeCase++;
        $tests[sprintf(
            'real upstream window6 frame syntax ordered range dynamic %03d %s %s',
            $orderedRangeCase,
            $source,
            $datasetName,
        )] = static function (TestRunner $t) use ($dataset, $start, $end): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => SQLiteWindowFunction::aggregateOrderedRangeValues(
                    'count',
                    $dataset['values'],
                    $dataset['keys'],
                    'ASC',
                    'LAST',
                    $start,
                    $end,
                ),
            );
        };
    }
}

$invalidExpressionFrameSpecs = [
    'window6.test 9.8.1 start expression preceding rejected' => ['a PRECEDING', '2 FOLLOWING'],
    'window6.test 9.8.2 end expression following rejected' => ['2 PRECEDING', 'a FOLLOWING'],
];

foreach ($invalidExpressionFrameSpecs as $source => [$start, $end]) {
    foreach ($helpers as $helper) {
        foreach ($datasets as $datasetName => $dataset) {
            $tests[sprintf(
                'real upstream window6 expression frame syntax %s %s %s',
                $source,
                $helper,
                $datasetName,
            )] = static function (TestRunner $t) use ($runFrameHelper, $helper, $dataset, $start, $end): void {
                $t->throws(
                    InvalidArgumentException::class,
                    static fn () => $runFrameHelper($helper, $dataset, 'ROWS', $start, $end),
                );
            };
        }
    }
}

$emptyFrameDataset = [
    'values' => [5, 10, 15, 20, 25],
    'keys' => [1, 2, 3, 4, 5],
    'filters' => [1, 1, 1, 1, 1],
];

$acceptedEmptyFrames = [
    'window2.test 2.17 rows same-class preceding empty frame remains valid' => ['ROWS', '1 PRECEDING', '2 PRECEDING'],
    'window4.test generated rows zero preceding to one preceding remains valid' => ['ROWS', '0 PRECEDING', '1 PRECEDING'],
    'window1.test 13.8 range same-class preceding empty frame remains valid' => ['RANGE', '1 PRECEDING', '2 PRECEDING'],
    'windowB.test 3.11 range same-class following empty frame remains valid' => ['RANGE', '2 FOLLOWING', '0 FOLLOWING'],
    'window8.test generated groups same-class following empty frame remains valid' => ['GROUPS', '1 FOLLOWING', '0 FOLLOWING'],
];

foreach ($acceptedEmptyFrames as $source => [$unit, $start, $end]) {
    $tests['real upstream window frame syntax positive ' . $source] = static function (TestRunner $t) use ($emptyFrameDataset, $unit, $start, $end): void {
        $t->same(
            [0, 0, 0, 0, 0],
            SQLiteWindowFunction::aggregateFrameBetweenValues(
                'count',
                $emptyFrameDataset['values'],
                $emptyFrameDataset['keys'],
                $unit,
                $start,
                $end,
            ),
        );
    };
}

return $tests;
