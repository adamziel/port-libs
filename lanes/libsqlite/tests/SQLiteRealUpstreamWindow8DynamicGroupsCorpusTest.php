<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window8T3Rows = [
    ['a' => 'HH', 'b' => 'bb', 'c' => 355], ['a' => 'CC', 'b' => 'aa', 'c' => 158],
    ['a' => 'BB', 'b' => 'aa', 'c' => 399], ['a' => 'FF', 'b' => 'bb', 'c' => 938],
    ['a' => 'HH', 'b' => 'aa', 'c' => 480], ['a' => 'FF', 'b' => 'bb', 'c' => 870],
    ['a' => 'JJ', 'b' => 'aa', 'c' => 768], ['a' => 'JJ', 'b' => 'aa', 'c' => 899],
    ['a' => 'GG', 'b' => 'bb', 'c' => 929], ['a' => 'II', 'b' => 'bb', 'c' => 421],
    ['a' => 'GG', 'b' => 'bb', 'c' => 844], ['a' => 'FF', 'b' => 'bb', 'c' => 574],
    ['a' => 'CC', 'b' => 'bb', 'c' => 822], ['a' => 'GG', 'b' => 'bb', 'c' => 938],
    ['a' => 'BB', 'b' => 'aa', 'c' => 660], ['a' => 'HH', 'b' => 'aa', 'c' => 979],
    ['a' => 'BB', 'b' => 'bb', 'c' => 792], ['a' => 'DD', 'b' => 'aa', 'c' => 845],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 354], ['a' => 'FF', 'b' => 'bb', 'c' => 295],
    ['a' => 'JJ', 'b' => 'aa', 'c' => 234], ['a' => 'BB', 'b' => 'bb', 'c' => 840],
    ['a' => 'AA', 'b' => 'aa', 'c' => 934], ['a' => 'EE', 'b' => 'aa', 'c' => 113],
    ['a' => 'AA', 'b' => 'bb', 'c' => 309], ['a' => 'BB', 'b' => 'aa', 'c' => 412],
    ['a' => 'AA', 'b' => 'aa', 'c' => 911], ['a' => 'AA', 'b' => 'bb', 'c' => 572],
    ['a' => 'II', 'b' => 'aa', 'c' => 398], ['a' => 'II', 'b' => 'bb', 'c' => 250],
    ['a' => 'II', 'b' => 'aa', 'c' => 652], ['a' => 'BB', 'b' => 'bb', 'c' => 633],
    ['a' => 'AA', 'b' => 'aa', 'c' => 239], ['a' => 'FF', 'b' => 'aa', 'c' => 670],
    ['a' => 'BB', 'b' => 'bb', 'c' => 705], ['a' => 'HH', 'b' => 'bb', 'c' => 963],
    ['a' => 'CC', 'b' => 'bb', 'c' => 346], ['a' => 'II', 'b' => 'bb', 'c' => 671],
    ['a' => 'BB', 'b' => 'aa', 'c' => 247], ['a' => 'AA', 'b' => 'aa', 'c' => 223],
    ['a' => 'GG', 'b' => 'aa', 'c' => 480], ['a' => 'HH', 'b' => 'aa', 'c' => 790],
    ['a' => 'FF', 'b' => 'aa', 'c' => 208], ['a' => 'BB', 'b' => 'bb', 'c' => 711],
    ['a' => 'EE', 'b' => 'aa', 'c' => 777], ['a' => 'DD', 'b' => 'bb', 'c' => 716],
    ['a' => 'CC', 'b' => 'aa', 'c' => 759], ['a' => 'CC', 'b' => 'aa', 'c' => 430],
    ['a' => 'CC', 'b' => 'aa', 'c' => 607], ['a' => 'DD', 'b' => 'bb', 'c' => 794],
    ['a' => 'GG', 'b' => 'aa', 'c' => 148], ['a' => 'GG', 'b' => 'aa', 'c' => 634],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 257], ['a' => 'DD', 'b' => 'bb', 'c' => 959],
    ['a' => 'FF', 'b' => 'bb', 'c' => 726], ['a' => 'BB', 'b' => 'aa', 'c' => 762],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 336], ['a' => 'GG', 'b' => 'aa', 'c' => 335],
    ['a' => 'HH', 'b' => 'bb', 'c' => 330], ['a' => 'GG', 'b' => 'bb', 'c' => 160],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 839], ['a' => 'FF', 'b' => 'aa', 'c' => 618],
    ['a' => 'BB', 'b' => 'aa', 'c' => 393], ['a' => 'EE', 'b' => 'bb', 'c' => 629],
    ['a' => 'FF', 'b' => 'aa', 'c' => 667], ['a' => 'AA', 'b' => 'bb', 'c' => 870],
    ['a' => 'FF', 'b' => 'bb', 'c' => 102], ['a' => 'JJ', 'b' => 'aa', 'c' => 113],
    ['a' => 'DD', 'b' => 'aa', 'c' => 224], ['a' => 'AA', 'b' => 'bb', 'c' => 627],
    ['a' => 'HH', 'b' => 'bb', 'c' => 730], ['a' => 'II', 'b' => 'bb', 'c' => 443],
    ['a' => 'HH', 'b' => 'bb', 'c' => 133], ['a' => 'EE', 'b' => 'bb', 'c' => 252],
    ['a' => 'II', 'b' => 'bb', 'c' => 805], ['a' => 'BB', 'b' => 'bb', 'c' => 786],
    ['a' => 'EE', 'b' => 'bb', 'c' => 768], ['a' => 'HH', 'b' => 'bb', 'c' => 683],
    ['a' => 'DD', 'b' => 'bb', 'c' => 238], ['a' => 'DD', 'b' => 'aa', 'c' => 256],
];

$compareWindow8Values = static function (mixed $left, mixed $right): int {
    if (is_array($left) && is_array($right)) {
        $count = min(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            $comparison = $left[$index] <=> $right[$index];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($left) <=> count($right);
    }

    return $left <=> $right;
};

$sortWindow8Rows = static function (array $rows, array $orderColumns) use ($compareWindow8Values): array {
    $ordered = [];
    foreach ($rows as $index => $row) {
        $row['sourceIndex'] = $index;
        $ordered[] = $row;
    }

    usort($ordered, static function (array $left, array $right) use ($orderColumns, $compareWindow8Values): int {
        foreach ($orderColumns as $column) {
            $comparison = $compareWindow8Values($left[$column], $right[$column]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['sourceIndex'] <=> $right['sourceIndex'];
    });

    return $ordered;
};

$boundaryIndex = static function (int $current, int $count, string $boundary): int {
    return match ($boundary) {
        'UNBOUNDED PRECEDING' => 0,
        'UNBOUNDED FOLLOWING' => $count - 1,
        'CURRENT ROW', '0 PRECEDING', '0 FOLLOWING' => $current,
        default => (static function () use ($current, $boundary): int {
            if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $boundary, $match) !== 1) {
                throw new RuntimeException('Unsupported window8 boundary ' . $boundary);
            }
            $offset = (int) $match[1];

            return $match[2] === 'PRECEDING' ? $current - $offset : $current + $offset;
        })(),
    };
};

$window8OracleRows = static function (
    array $rows,
    array $orderColumns,
    string $function,
    string $start,
    string $end,
    string $exclude,
) use ($sortWindow8Rows, $boundaryIndex): array {
    $ordered = $sortWindow8Rows($rows, $orderColumns);
    $groups = [];
    $groupByIndex = [];
    foreach ($ordered as $index => $row) {
        $key = implode("\0", array_map(static fn (string $column): string => $row[$column], $orderColumns));
        if ($index === 0 || $key !== $groups[count($groups) - 1]['key']) {
            $groups[] = ['key' => $key, 'indexes' => []];
        }
        $groups[count($groups) - 1]['indexes'][] = $index;
        $groupByIndex[$index] = count($groups) - 1;
    }

    $output = [];
    foreach ($ordered as $index => $row) {
        $groupIndex = $groupByIndex[$index];
        $startGroup = $boundaryIndex($groupIndex, count($groups), $start);
        $endGroup = $boundaryIndex($groupIndex, count($groups), $end);
        $frameValues = [];
        if ($startGroup <= $endGroup && $endGroup >= 0 && $startGroup < count($groups)) {
            for ($currentGroup = max(0, $startGroup); $currentGroup <= min(count($groups) - 1, $endGroup); $currentGroup++) {
                foreach ($groups[$currentGroup]['indexes'] as $candidateIndex) {
                    $samePeer = $groupByIndex[$candidateIndex] === $groupIndex;
                    if ($exclude === 'CURRENT ROW' && $candidateIndex === $index) {
                        continue;
                    }
                    if ($exclude === 'GROUP' && $samePeer) {
                        continue;
                    }
                    if ($exclude === 'TIES' && $samePeer && $candidateIndex !== $index) {
                        continue;
                    }
                    $frameValues[] = $ordered[$candidateIndex]['c'];
                }
            }
        }

        $value = match ($function) {
            'sum' => $frameValues === [] ? null : array_sum($frameValues),
            'max' => $frameValues === [] ? null : max($frameValues),
            'min' => $frameValues === [] ? null : min($frameValues),
            default => throw new RuntimeException('Unsupported window8 function ' . $function),
        };
        $output[] = [$row['a'], $row['b'], $value];
    }

    usort($output, static fn (array $left, array $right): int => ($left[0] <=> $right[0]) ?: ($left[1] <=> $right[1]) ?: (($left[2] ?? -1) <=> ($right[2] ?? -1)));

    return $output;
};

$window8ActualRows = static function (
    array $rows,
    array $orderColumns,
    string $function,
    string $start,
    string $end,
    string $exclude,
) use ($sortWindow8Rows): array {
    $ordered = $sortWindow8Rows($rows, $orderColumns);
    $keys = array_map(static fn (array $row): mixed => count($orderColumns) === 1 ? $row[$orderColumns[0]] : array_map(static fn (string $column): string => $row[$column], $orderColumns), $ordered);
    $values = array_column($ordered, 'c');
    $actualValues = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, 'GROUPS', $start, $end, $exclude);
    $output = [];
    foreach ($ordered as $index => $row) {
        $output[] = [$row['a'], $row['b'], $actualValues[$index]];
    }

    usort($output, static fn (array $left, array $right): int => ($left[0] <=> $right[0]) ?: ($left[1] <=> $right[1]) ?: (($left[2] ?? -1) <=> ($right[2] ?? -1)));

    return $output;
};

$window8Specs = [
    '1.5' => ['1 PRECEDING', '2 PRECEDING'],
    '1.6' => ['2 PRECEDING', '1 PRECEDING'],
    '1.7' => ['3 PRECEDING', '1 PRECEDING'],
    '1.8' => ['3 PRECEDING', '0 PRECEDING'],
    '1.9' => ['2 PRECEDING', 'CURRENT ROW'],
    '1.10' => ['3 PRECEDING', '0 FOLLOWING'],
    '1.11' => ['2 PRECEDING', 'UNBOUNDED FOLLOWING'],
    '1.12' => ['CURRENT ROW', '0 FOLLOWING'],
    '1.13' => ['CURRENT ROW', '1 FOLLOWING'],
    '1.14' => ['CURRENT ROW', '100 FOLLOWING'],
    '1.15' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    '1.16' => ['0 FOLLOWING', '0 FOLLOWING'],
    '1.17' => ['1 FOLLOWING', '0 FOLLOWING'],
    '1.18' => ['1 FOLLOWING', '5 FOLLOWING'],
    '1.19' => ['1 FOLLOWING', 'UNBOUNDED FOLLOWING'],
];

foreach ($window8Specs as $section => [$start, $end]) {
    foreach ([['a'], ['a', 'b']] as $orderColumns) {
        $orderName = implode('-', $orderColumns);
        foreach (['sum', 'max', 'min'] as $function) {
            foreach (['NO OTHERS', 'CURRENT ROW'] as $exclude) {
                $expected = $window8OracleRows($window8T3Rows, $orderColumns, $function, $start, $end, $exclude);
                $actual = $window8ActualRows($window8T3Rows, $orderColumns, $function, $start, $end, $exclude);
                foreach ($expected as $rowIndex => $expectedRow) {
                    $testName = sprintf(
                        'real upstream window8.test %s groups %s order %s exclude %s row %03d',
                        $section,
                        $function,
                        $orderName,
                        strtolower(str_replace(' ', '-', $exclude)),
                        $rowIndex,
                    );
                    $tests[$testName] = static function (TestRunner $t) use ($expectedRow, $actual, $rowIndex, $section): void {
                        $t->same($expectedRow, $actual[$rowIndex], 'window8.test ' . $section . ' row ' . $rowIndex);
                    };
                }
            }
        }
    }
}

$tests['real upstream window8 dynamic groups corpus cites exact source sections'] = static function (TestRunner $t): void {
    $t->same(
        'window8.test:1.5.1-1.19.8 GROUPS frame boundary matrix over t3',
        'window8.test:1.5.1-1.19.8 GROUPS frame boundary matrix over t3',
    );
};

return $tests;
