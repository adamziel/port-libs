<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @return list<array{id:int,bucket:string,kind:string,label:string,score:int}>
 */
$windowDynamicRows = static function (int $variant): array {
    $base = [
        [1, 'odd', 'one', 1],
        [2, 'even', 'two', 2],
        [3, 'odd', 'three', 3],
        [4, 'even', 'four', 4],
        [5, 'odd', 'five', 5],
        [6, 'even', 'six', 6],
    ];

    $rows = [];
    foreach ($base as [$id, $bucket, $label, $score]) {
        $rows[] = [
            'id' => $id,
            'bucket' => $bucket,
            'kind' => $id % 3 === 0 ? 'third' : ($id % 3 === 1 ? 'first' : 'second'),
            'label' => $label . '_' . $variant,
            'score' => $score + $variant,
        ];
    }

    return $rows;
};

/**
 * @param list<array{id:int,bucket:string,kind:string,label:string,score:int}> $rows
 * @return list<int|string|null>
 */
$windowRowsOracle = static function (array $rows, ?string $partitionColumn, string $direction, int $preceding, int $following, string $function): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitionKey = $partitionColumn === null ? '__all__' : $row[$partitionColumn];
        $partitions[$partitionKey][] = $row;
    }

    $byId = [];
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static function (array $left, array $right) use ($direction): int {
            $comparison = $left['score'] <=> $right['score'];
            if ($direction === 'DESC') {
                $comparison *= -1;
            }
            return $comparison !== 0 ? $comparison : ($left['id'] <=> $right['id']);
        });

        $count = count($partitionRows);
        foreach ($partitionRows as $offset => $row) {
            $start = max(0, $offset - $preceding);
            $end = min($count - 1, $offset + $following);
            $frame = array_slice($partitionRows, $start, $end - $start + 1);

            $byId[$row['id']] = match ($function) {
                'sum' => array_sum(array_column($frame, 'score')),
                'count' => count($frame),
                'group_concat' => implode(',', array_column($frame, 'label')),
                default => null,
            };
        }
    }

    ksort($byId, SORT_NUMERIC);

    return array_values($byId);
};

$partitionSpecs = [
    'all rows' => null,
    'partition by parity' => 'bucket',
    'partition by modulo kind' => 'kind',
];
$frameSpecs = [
    'one preceding one following' => [1, 1],
    'two preceding current row' => [2, 0],
    'current row two following' => [0, 2],
];
$functionSpecs = [
    'sum score' => ['sum', 'sum(score)'],
    'count star' => ['count', 'count(*)'],
    'group concat labels' => ['group_concat', 'group_concat(label)'],
];

foreach (range(1, 10) as $variant) {
    foreach ($partitionSpecs as $partitionName => $partitionColumn) {
        foreach (['ASC', 'DESC'] as $direction) {
            foreach ($frameSpecs as $frameName => [$preceding, $following]) {
                foreach ($functionSpecs as $functionName => [$function, $expression]) {
                    $name = "real upstream window dynamic rows window2 {$variant} {$partitionName} {$direction} {$frameName} {$functionName}";
                    $tests[$name] = static function (TestRunner $t) use (
                        $windowDynamicRows,
                        $windowRowsOracle,
                        $variant,
                        $partitionColumn,
                        $direction,
                        $preceding,
                        $following,
                        $function,
                        $expression
                    ): void {
                        $rows = $windowDynamicRows($variant);
                        $partitionSql = $partitionColumn === null ? '' : "PARTITION BY {$partitionColumn} ";
                        $sql = "SELECT id, {$expression} OVER ({$partitionSql}ORDER BY score {$direction} ROWS BETWEEN {$preceding} PRECEDING AND {$following} FOLLOWING) AS metric FROM entries ORDER BY id";

                        $actual = SQLiteSelectSql::execute($sql, ['entries' => $rows]);
                        $plan = SQLiteSelectSql::plan($sql, ['entries' => $rows]);

                        $t->same(array_column($rows, 'id'), array_column($actual, 'id'));
                        $t->same($windowRowsOracle($rows, $partitionColumn, $direction, $preceding, $following, $function), array_column($actual, 'metric'));
                        $t->same('ROWS', $plan['select'][1]['frame']['unit']);
                    };
                }
            }
        }
    }
}

$tests['real upstream window dynamic rows window1 lead offset default'] = static function (TestRunner $t): void {
    $rows = [
        ['id' => 1, 'x' => 1, 'y' => 2],
        ['id' => 2, 'x' => 3, 'y' => 4],
        ['id' => 3, 'x' => 5, 'y' => 6],
        ['id' => 4, 'x' => 7, 'y' => 8],
        ['id' => 5, 'x' => 9, 'y' => 10],
    ];

    $actual = SQLiteSelectSql::execute("SELECT lead(y) OVER win AS one, lead(y, 2) OVER win AS two, lead(y, 3, 'default') OVER win AS three FROM entries WINDOW win AS (ORDER BY x) ORDER BY id", ['entries' => $rows]);

    $t->same([4, 6, 8, 10, null], array_column($actual, 'one'));
    $t->same([6, 8, 10, null, null], array_column($actual, 'two'));
    $t->same([8, 10, 'default', 'default', 'default'], array_column($actual, 'three'));
};

$tests['real upstream window dynamic rows window1 row number named frame ignores frame'] = static function (TestRunner $t): void {
    $rows = [
        ['id' => 1, 'x' => 1],
        ['id' => 2, 'x' => 3],
        ['id' => 3, 'x' => 5],
        ['id' => 4, 'x' => 7],
        ['id' => 5, 'x' => 9],
    ];

    $actual = SQLiteSelectSql::execute('SELECT row_number() OVER win AS row_number, lead(x) OVER win AS next_x FROM entries WINDOW win AS (ORDER BY x ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) ORDER BY id', ['entries' => $rows]);

    $t->same([1, 2, 3, 4, 5], array_column($actual, 'row_number'));
    $t->same([3, 5, 7, 9, null], array_column($actual, 'next_x'));
};

return $tests;
