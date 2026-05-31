<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

// Upstream source: SQLite test/window1.test 1.1-1.5, 4.1-4.10,
// test/window2.test 1.1-1.3, 2.1-2.25, test/window4.test 1.1-1.19,
// test/window6.test 1.1-1.6, 5.0-5.5, 8.1-9.4, test/window7.test
// 1.2-1.8.2, and test/windowE.test 4.1-5.2. The table/column names are
// made generic for the libsqlite port while preserving the upstream row
// shapes.
$basicRows = [
    ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
    ['a' => 5, 'b' => 6, 'c' => 7, 'd' => 8],
    ['a' => 9, 'b' => 10, 'c' => 11, 'd' => 12],
];

$eventRows = [
    ['a' => 0, 'b' => 0, 'c' => 0],
    ['a' => 1, 'b' => 1, 'c' => 1],
    ['a' => 2, 'b' => 0, 'c' => 2],
    ['a' => 3, 'b' => 1, 'c' => 0],
    ['a' => 4, 'b' => 0, 'c' => 1],
    ['a' => 5, 'b' => 1, 'c' => 2],
    ['a' => 6, 'b' => 0, 'c' => 0],
];

$textRows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$reservedRows = [
    ['x' => 1, 'over' => 2, 'window' => 2],
    ['x' => 3, 'over' => 4, 'window' => 4],
    ['x' => 5, 'over' => 6, 'window' => 6],
];

$sampleRows = [
    ['id' => 1, 'counter' => 1, 'value' => 10.0],
    ['id' => 2, 'counter' => 1, 'value' => 20.0],
    ['id' => 3, 'counter' => 2, 'value' => 1.0],
    ['id' => 4, 'counter' => 2, 'value' => 3.0],
    ['id' => 5, 'counter' => 3, 'value' => 100.0],
];

$wideNumberRows = [
    ['id' => 1, 'x' => -1],
    ['id' => 2, 'x' => 9223372036854775807],
    ['id' => 3, 'x' => 1],
    ['id' => 4, 'x' => 0.5],
];

$ntileRows = array_map(
    static fn (string $letter): array => ['letter' => $letter],
    range('a', 'j')
);

$peerFrameRows = [];
for ($b = 1; $b <= 100; $b++) {
    $peerFrameRows[] = ['a' => $b % 10, 'b' => $b];
}

$queryBasic = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_basic' => $basicRows]);
$queryEvents = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_events' => $eventRows]);
$queryText = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_text' => $textRows]);
$queryReserved = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_reserved' => $reservedRows]);
$querySample = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_sample' => $sampleRows]);
$queryWideNumbers = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_wide_numbers' => $wideNumberRows]);
$queryNtile = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_ntile_letters' => $ntileRows]);
$queryPeerFrames = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['app_peer_frames' => $peerFrameRows]);
$column = static fn (array $rows, string $name): array => array_column($rows, $name);
$pairs = static fn (array $rows, string $left, string $right): array => array_map(
    static fn (array $row): array => [$row[$left], $row[$right]],
    $rows
);
$ntileBucketForRow = static function (int $rowNumber, int $rowCount, int $bucketCount): int {
    if ($bucketCount >= $rowCount) {
        return $rowNumber;
    }

    $largeBucketSize = intdiv($rowCount + $bucketCount - 1, $bucketCount);
    $largeBucketCount = $rowCount % $bucketCount;
    if ($largeBucketCount === 0) {
        $largeBucketCount = $bucketCount;
    }
    $largeRows = $largeBucketSize * $largeBucketCount;
    if ($rowNumber <= $largeRows) {
        return intdiv($rowNumber - 1, $largeBucketSize) + 1;
    }

    return $largeBucketCount + intdiv($rowNumber - $largeRows - 1, $largeBucketSize - 1) + 1;
};
$peerGroupSums = [];
foreach ($peerFrameRows as $row) {
    $peerGroupSums[$row['a']] = ($peerGroupSums[$row['a']] ?? 0) + $row['b'];
}
ksort($peerGroupSums);
$peerFrameExpected = static function (callable $bounds) use ($peerGroupSums): array {
    $expected = [];
    foreach (array_keys($peerGroupSums) as $a) {
        [$first, $last] = $bounds($a);
        $sum = 0;
        foreach ($peerGroupSums as $group => $groupSum) {
            if ($group >= $first && $group <= $last) {
                $sum += $groupSum;
            }
        }
        for ($i = 0; $i < 10; $i++) {
            $expected[] = [$a, $sum];
        }
    }

    return $expected;
};

$cases = [
    'window1 1.1 whole partition sum' => [
        static fn (): mixed => $column($queryBasic('SELECT sum(b) OVER () AS total_b FROM app_basic'), 'total_b'),
        [18, 18, 18],
    ],
    'window1 1.2 projection plus whole partition sum' => [
        static fn (): mixed => $pairs($queryBasic('SELECT a, sum(b) OVER () AS total_b FROM app_basic'), 'a', 'total_b'),
        [[1, 18], [5, 18], [9, 18]],
    ],
    'window1 1.3 expression plus whole partition sum' => [
        static fn (): mixed => $pairs($queryBasic('SELECT a, sum(b) OVER () AS total_b FROM app_basic'), 'a', 'total_b'),
        [[1, 18], [5, 18], [9, 18]],
    ],
    'window1 1.5 partition by unique column' => [
        static fn (): mixed => $pairs($queryBasic('SELECT a, sum(b) OVER (PARTITION BY c) AS total_b FROM app_basic'), 'a', 'total_b'),
        [[1, 2], [5, 6], [9, 10]],
    ],
    'window1 4.1 partition sum emits partition order' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER (PARTITION BY b) AS total_a FROM app_events ORDER BY a'), 'a', 'total_a'),
        [[0, 12], [1, 9], [2, 12], [3, 9], [4, 12], [5, 9], [6, 12]],
    ],
    'window1 4.2 partition sum reordered after window' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER (PARTITION BY b) AS total_a FROM app_events ORDER BY a'), 'a', 'total_a'),
        [[0, 12], [1, 9], [2, 12], [3, 9], [4, 12], [5, 9], [6, 12]],
    ],
    'window1 4.3 unpartitioned sum reordered after window' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER () AS total_a FROM app_events ORDER BY a'), 'a', 'total_a'),
        [[0, 21], [1, 21], [2, 21], [3, 21], [4, 21], [5, 21], [6, 21]],
    ],
    'window1 4.4 running sum order by a' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER (ORDER BY a) AS running_a FROM app_events'), 'a', 'running_a'),
        [[0, 0], [1, 1], [2, 3], [3, 6], [4, 10], [5, 15], [6, 21]],
    ],
    'window1 4.5 partition b running sum' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER (PARTITION BY b ORDER BY a) AS running_a FROM app_events ORDER BY a'), 'a', 'running_a'),
        [[0, 0], [1, 1], [2, 2], [3, 4], [4, 6], [5, 9], [6, 12]],
    ],
    'window1 4.6 partition c running sum' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER (PARTITION BY c ORDER BY a) AS running_a FROM app_events ORDER BY a'), 'a', 'running_a'),
        [[0, 0], [1, 1], [2, 2], [3, 3], [4, 5], [5, 7], [6, 9]],
    ],
    'window1 4.7 descending partition b running sum' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, sum(a) OVER (PARTITION BY b ORDER BY a DESC) AS running_a FROM app_events ORDER BY a'), 'a', 'running_a'),
        [[0, 12], [1, 9], [2, 12], [3, 8], [4, 10], [5, 5], [6, 6]],
    ],
    'window1 4.9 running sum and avg share order' => [
        static fn (): mixed => array_map(static fn (array $row): array => [$row['a'], $row['running_a'], $row['avg_a']], $queryEvents('SELECT a, sum(a) OVER (ORDER BY a) AS running_a, avg(a) OVER (ORDER BY a) AS avg_a FROM app_events ORDER BY a')),
        [[0, 0, 0.0], [1, 1, 0.5], [2, 3, 1.0], [3, 6, 1.5], [4, 10, 2.0], [5, 15, 2.5], [6, 21, 3.0]],
    ],
    'window1 4.10 count star descending' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, count(*) OVER (ORDER BY a DESC) AS running_count FROM app_events ORDER BY a DESC'), 'a', 'running_count'),
        [[6, 1], [5, 2], [4, 3], [3, 4], [2, 5], [1, 6], [0, 7]],
    ],
    'window1 4.10 group concat descending' => [
        static fn (): mixed => $pairs($queryEvents('SELECT a, group_concat(a) OVER (ORDER BY a DESC) AS trace FROM app_events ORDER BY a DESC'), 'a', 'trace'),
        [[6, '6'], [5, '6,5'], [4, '6,5,4'], [3, '6,5,4,3'], [2, '6,5,4,3,2'], [1, '6,5,4,3,2,1'], [0, '6,5,4,3,2,1,0']],
    ],
    'window2 1.1 text partition order' => [
        static fn (): mixed => $pairs($queryText('SELECT c, sum(d) OVER (PARTITION BY b ORDER BY c) AS running_d FROM app_text ORDER BY c'), 'c', 'running_d'),
        [['five', 5], ['four', 4], ['one', 6], ['six', 10], ['three', 9], ['two', 12]],
    ],
    'window2 1.2 whole partition text table' => [
        static fn (): mixed => $column($queryText('SELECT sum(d) OVER () AS total_d FROM app_text'), 'total_d'),
        [21, 21, 21, 21, 21, 21],
    ],
    'window2 1.3 partition without order' => [
        static fn (): mixed => $column($queryText('SELECT sum(d) OVER (PARTITION BY b) AS total_d FROM app_text ORDER BY a'), 'total_d'),
        [9, 12, 9, 12, 9, 12],
    ],
    'window2 2.1 rows unbounded-like preceding one following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1000 PRECEDING AND 1 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 3], [2, 6], [3, 10], [4, 15], [5, 21], [6, 21]],
    ],
    'window2 2.2 rows wide preceding and following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1000 PRECEDING AND 1000 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 21], [2, 21], [3, 21], [4, 21], [5, 21], [6, 21]],
    ],
    'window2 2.3 one preceding wide following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1000 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 21], [2, 21], [3, 20], [4, 18], [5, 15], [6, 11]],
    ],
    'window2 2.4 one preceding one following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 3], [2, 6], [3, 9], [4, 12], [5, 15], [6, 11]],
    ],
    'window2 2.5 one preceding current row' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 0 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 1], [2, 3], [3, 5], [4, 7], [5, 9], [6, 11]],
    ],
    'window2 2.6 partition one preceding one following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (PARTITION BY b ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS s FROM app_text ORDER BY a'), 'a', 's'),
        [[1, 4], [2, 6], [3, 9], [4, 12], [5, 8], [6, 10]],
    ],
    'window2 2.7 partition current row only' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (PARTITION BY b ORDER BY d ROWS BETWEEN 0 PRECEDING AND 0 FOLLOWING) AS s FROM app_text ORDER BY a'), 'a', 's'),
        [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5], [6, 6]],
    ],
    'window2 2.8 current row two following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 6], [2, 9], [3, 12], [4, 15], [5, 11], [6, 6]],
    ],
    'window2 2.10 duplicate current row two following' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS s FROM app_text'), 'a', 's'),
        [[1, 6], [2, 9], [3, 12], [4, 15], [5, 11], [6, 6]],
    ],
    'window2 2.11 two preceding current row' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS s FROM app_text'), 'a', 's'),
        [[1, 1], [2, 3], [3, 6], [4, 9], [5, 12], [6, 15]],
    ],
    'window2 2.15 partition one preceding current row' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (PARTITION BY b ORDER BY d ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS s FROM app_text ORDER BY a'), 'a', 's'),
        [[1, 1], [2, 2], [3, 4], [4, 6], [5, 8], [6, 10]],
    ],
    'window rank row_number order by d' => [
        static fn (): mixed => $pairs($queryText('SELECT a, row_number() OVER (ORDER BY d) AS rn FROM app_text'), 'a', 'rn'),
        [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5], [6, 6]],
    ],
    'window rank rank order by partition' => [
        static fn (): mixed => $pairs($queryText('SELECT a, rank() OVER (PARTITION BY b ORDER BY d) AS r FROM app_text ORDER BY a'), 'a', 'r'),
        [[1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3]],
    ],
    'window rank dense rank order by partition' => [
        static fn (): mixed => $pairs($queryText('SELECT a, dense_rank() OVER (PARTITION BY b ORDER BY d) AS r FROM app_text ORDER BY a'), 'a', 'r'),
        [[1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3]],
    ],
    'window rank percent rank order by d' => [
        static fn (): mixed => $pairs($queryText('SELECT a, percent_rank() OVER (ORDER BY d) AS r FROM app_text'), 'a', 'r'),
        [[1, 0.0], [2, 0.2], [3, 0.4], [4, 0.6], [5, 0.8], [6, 1.0]],
    ],
    'window rank cume dist order by d' => [
        static fn (): mixed => $pairs($queryText('SELECT a, cume_dist() OVER (ORDER BY d) AS r FROM app_text'), 'a', 'r'),
        [[1, 1 / 6], [2, 2 / 6], [3, 3 / 6], [4, 4 / 6], [5, 5 / 6], [6, 1.0]],
    ],
    'window ntile three buckets' => [
        static fn (): mixed => $pairs($queryText('SELECT a, ntile(3) OVER (ORDER BY d) AS bucket FROM app_text'), 'a', 'bucket'),
        [[1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3]],
    ],
    'window lag default offset' => [
        static fn (): mixed => $pairs($queryText('SELECT a, lag(c) OVER (ORDER BY d) AS prev_c FROM app_text'), 'a', 'prev_c'),
        [[1, null], [2, 'one'], [3, 'two'], [4, 'three'], [5, 'four'], [6, 'five']],
    ],
    'window lead default offset' => [
        static fn (): mixed => $pairs($queryText('SELECT a, lead(c) OVER (ORDER BY d) AS next_c FROM app_text'), 'a', 'next_c'),
        [[1, 'two'], [2, 'three'], [3, 'four'], [4, 'five'], [5, 'six'], [6, null]],
    ],
    'window lag explicit default' => [
        static fn (): mixed => $pairs($queryText("SELECT a, lag(c, 2, 'none') OVER (ORDER BY d) AS prev_c FROM app_text"), 'a', 'prev_c'),
        [[1, 'none'], [2, 'none'], [3, 'one'], [4, 'two'], [5, 'three'], [6, 'four']],
    ],
    'window lead explicit default' => [
        static fn (): mixed => $pairs($queryText("SELECT a, lead(c, 2, 'none') OVER (ORDER BY d) AS next_c FROM app_text"), 'a', 'next_c'),
        [[1, 'three'], [2, 'four'], [3, 'five'], [4, 'six'], [5, 'none'], [6, 'none']],
    ],
    'window first value bounded frame' => [
        static fn (): mixed => $pairs($queryText('SELECT a, first_value(c) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS first_c FROM app_text'), 'a', 'first_c'),
        [[1, 'one'], [2, 'one'], [3, 'two'], [4, 'three'], [5, 'four'], [6, 'five']],
    ],
    'window last value bounded frame' => [
        static fn (): mixed => $pairs($queryText('SELECT a, last_value(c) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS last_c FROM app_text'), 'a', 'last_c'),
        [[1, 'two'], [2, 'three'], [3, 'four'], [4, 'five'], [5, 'six'], [6, 'six']],
    ],
    'window nth value bounded frame' => [
        static fn (): mixed => $pairs($queryText('SELECT a, nth_value(c, 2) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS nth_c FROM app_text'), 'a', 'nth_c'),
        [[1, 'two'], [2, 'two'], [3, 'three'], [4, 'four'], [5, 'five'], [6, 'six']],
    ],
    'window filter with bounded rows' => [
        static fn (): mixed => $pairs($queryText("SELECT a, sum(d) FILTER (WHERE b = 'even') OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS even_sum FROM app_text"), 'a', 'even_sum'),
        [[1, 2], [2, 2], [3, 6], [4, 4], [5, 10], [6, 6]],
    ],
    'window named window running sum' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER win AS running_d FROM app_text WINDOW win AS (ORDER BY d) ORDER BY a'), 'a', 'running_d'),
        [[1, 1], [2, 3], [3, 6], [4, 10], [5, 15], [6, 21]],
    ],
    'window named window partitioned running sum' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER win AS running_d FROM app_text WINDOW win AS (PARTITION BY b ORDER BY d) ORDER BY a'), 'a', 'running_d'),
        [[1, 1], [2, 2], [3, 4], [4, 6], [5, 9], [6, 12]],
    ],
    'window named window frame suffix' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (win ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS s FROM app_text WINDOW win AS (ORDER BY d)'), 'a', 's'),
        [[1, 3], [2, 6], [3, 9], [4, 12], [5, 15], [6, 11]],
    ],
    'window order desc first value' => [
        static fn (): mixed => $pairs($queryText('SELECT a, first_value(c) OVER (ORDER BY d DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS first_c FROM app_text ORDER BY a'), 'a', 'first_c'),
        [[1, 'one'], [2, 'two'], [3, 'three'], [4, 'four'], [5, 'five'], [6, 'six']],
    ],
    'window order desc last value' => [
        static fn (): mixed => $pairs($queryText('SELECT a, last_value(c) OVER (ORDER BY d DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS last_c FROM app_text ORDER BY a'), 'a', 'last_c'),
        [[1, 'one'], [2, 'one'], [3, 'two'], [4, 'three'], [5, 'four'], [6, 'five']],
    ],
    'window rank partition by parity cume dist' => [
        static fn (): mixed => $pairs($queryText('SELECT a, cume_dist() OVER (PARTITION BY b ORDER BY d) AS cd FROM app_text ORDER BY a'), 'a', 'cd'),
        [[1, 1 / 3], [2, 1 / 3], [3, 2 / 3], [4, 2 / 3], [5, 1.0], [6, 1.0]],
    ],
    'window rank partition by parity percent rank' => [
        static fn (): mixed => $pairs($queryText('SELECT a, percent_rank() OVER (PARTITION BY b ORDER BY d) AS pr FROM app_text ORDER BY a'), 'a', 'pr'),
        [[1, 0.0], [2, 0.0], [3, 0.5], [4, 0.5], [5, 1.0], [6, 1.0]],
    ],
    'window count argument ignores nulls' => [
        static fn (): mixed => $column(SQLiteSelectSql::execute('SELECT count(v) OVER (ORDER BY k ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS cnt FROM app_nullable', ['app_nullable' => [['k' => 1, 'v' => null], ['k' => 2, 'v' => 20], ['k' => 3, 'v' => null], ['k' => 4, 'v' => 40]]]), 'cnt'),
        [1, 1, 2, 1],
    ],
    'window total returns floats over empty-null frame' => [
        static fn (): mixed => $column(SQLiteSelectSql::execute('SELECT total(v) OVER (ORDER BY k ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS total_v FROM app_nullable', ['app_nullable' => [['k' => 1, 'v' => null], ['k' => 2, 'v' => 20], ['k' => 3, 'v' => null], ['k' => 4, 'v' => 40]]]), 'total_v'),
        [0.0, 20.0, 20.0, 40.0],
    ],
    'window min max bounded rows' => [
        static fn (): mixed => array_map(static fn (array $row): array => [$row['min_v'], $row['max_v']], SQLiteSelectSql::execute('SELECT min(v) OVER (ORDER BY k ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS min_v, max(v) OVER (ORDER BY k ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS max_v FROM app_nullable', ['app_nullable' => [['k' => 1, 'v' => null], ['k' => 2, 'v' => 20], ['k' => 3, 'v' => null], ['k' => 4, 'v' => 40]]])),
        [[20, 20], [20, 20], [20, 40], [40, 40]],
    ],
    'window exclude current bounded rows' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS s FROM app_text'), 'a', 's'),
        [[1, 2], [2, 4], [3, 6], [4, 8], [5, 10], [6, 5]],
    ],
    'window exclude group with unique order matches current row' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE GROUP) AS s FROM app_text'), 'a', 's'),
        [[1, 2], [2, 4], [3, 6], [4, 8], [5, 10], [6, 5]],
    ],
    'window exclude ties with unique order keeps frame' => [
        static fn (): mixed => $pairs($queryText('SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE TIES) AS s FROM app_text'), 'a', 's'),
        [[1, 3], [2, 6], [3, 9], [4, 12], [5, 15], [6, 11]],
    ],
    'window6 1.1 group concat keyword-like names' => [
        static fn (): mixed => $column($queryReserved('SELECT group_concat(x) OVER (ORDER BY window) AS trace FROM app_reserved'), 'trace'),
        ['1', '1,3', '1,3,5'],
    ],
    'window6 1.2 named window with keyword-like name' => [
        static fn (): mixed => $column($queryReserved('SELECT sum(x) OVER win AS running_x FROM app_reserved WINDOW win AS (ORDER BY window)'), 'running_x'),
        [1, 4, 9],
    ],
    'window6 1.3 qualified column through named window' => [
        static fn (): mixed => $column($queryReserved('SELECT sum(app_reserved.x) OVER win AS running_x FROM app_reserved WINDOW win AS (ORDER BY window)'), 'running_x'),
        [1, 4, 9],
    ],
    'window6 5.1 empty named window definition' => [
        static fn (): mixed => $column($queryReserved('SELECT sum(x) OVER over_name AS total_x FROM app_reserved WINDOW over_name AS ()'), 'total_x'),
        [9, 9, 9],
    ],
    'window6 5.5 following-only frame count' => [
        static fn (): mixed => $column($queryReserved('SELECT count(*) OVER win AS future_count FROM app_reserved WINDOW win AS (ORDER BY x ROWS BETWEEN 2 FOLLOWING AND 3 FOLLOWING)'), 'future_count'),
        [1, 0, 0],
    ],
    'window6 8.1 rank reused in final order by' => [
        static fn (): mixed => array_map(static fn (array $row): array => [$row['counter'], $row['value'], $row['rank']], $querySample('SELECT counter, value, rank() OVER w AS rank FROM app_sample WINDOW w AS (PARTITION BY counter ORDER BY value DESC) ORDER BY counter, rank() OVER w')),
        [[1, 20.0, 1], [1, 10.0, 2], [2, 3.0, 1], [2, 1.0, 2], [3, 100.0, 1]],
    ],
    'window6 8.2 rows two preceding shorthand' => [
        static fn (): mixed => array_map(static fn (array $row): array => [$row['counter'], $row['value'], $row['rolling']], $querySample('SELECT counter, value, sum(value) OVER (ORDER BY id ROWS 2 PRECEDING) AS rolling FROM app_sample ORDER BY id')),
        [[1, 10.0, 10.0], [1, 20.0, 30.0], [2, 1.0, 31.0], [2, 3.0, 24.0], [3, 100.0, 104.0]],
    ],
    'window6 8.3 explicit rows two preceding to current' => [
        static fn (): mixed => $column($querySample('SELECT sum(value) OVER (ORDER BY id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS rolling FROM app_sample ORDER BY id'), 'rolling'),
        [10.0, 30.0, 31.0, 24.0, 104.0],
    ],
    'windowE 4.1 total over unbounded following with integer max' => [
        static fn (): mixed => $pairs($queryWideNumbers('SELECT id, total(x) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total_x FROM app_wide_numbers'), 'id', 'total_x'),
        [[1, 9.223372036854776E+18], [2, 9.223372036854776E+18], [3, 1.5], [4, 0.5]],
    ],
    'windowE 5.1 id sum over current row two following' => [
        static fn (): mixed => $pairs($queryWideNumbers('SELECT id, sum(id) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS id_sum FROM app_wide_numbers'), 'id', 'id_sum'),
        [[1, 6], [2, 9], [3, 7], [4, 4]],
    ],
    'windowE 5.2 numeric sum over current row two following' => [
        static fn (): mixed => $pairs($queryWideNumbers('SELECT id, sum(x) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS x_sum FROM app_wide_numbers'), 'id', 'x_sum'),
        [[1, 9223372036854775807], [2, 9.223372036854776E+18], [3, 1.5], [4, 0.5]],
    ],
];

$window6IdentifierMaps = [
    'plain identifiers' => ['table' => 'app_w6_plain', 'x' => 'x', 'y' => 'y', 'w' => 'w', 'alias' => 'alias'],
    'over table name' => ['table' => 'over_table', 'x' => 'x', 'y' => 'y', 'w' => 'w', 'alias' => 'alias'],
    'window table name' => ['table' => 'window_table', 'x' => 'x', 'y' => 'y', 'w' => 'w', 'alias' => 'alias'],
    'following value column' => ['table' => 'app_w6_following', 'x' => 'following_value', 'y' => 'y', 'w' => 'w', 'alias' => 'alias'],
    'preceding order column' => ['table' => 'app_w6_preceding', 'x' => 'x', 'y' => 'preceding_value', 'w' => 'w', 'alias' => 'alias'],
    'current named window' => ['table' => 'app_w6_current', 'x' => 'x', 'y' => 'y', 'w' => 'current_window', 'alias' => 'alias'],
];

foreach ($window6IdentifierMaps as $name => $map) {
    $rows = [];
    foreach ([1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd', 5 => 'e'] as $x => $y) {
        $rows[] = [$map['x'] => $x, $map['y'] => $y];
    }
    $tables = [$map['table'] => $rows];
    $table = $map['table'];
    $x = $map['x'];
    $y = $map['y'];
    $w = $map['w'];
    $alias = $map['alias'];

    $tests['real upstream window functions dynamic window6 1.' . $name . ' group concat mapped identifiers'] = static function (TestRunner $t) use ($tables, $table, $x, $y): void {
        $actual = array_column(SQLiteSelectSql::execute("SELECT group_concat({$x}, '.') OVER (ORDER BY {$y}) AS trace FROM {$table}", $tables), 'trace');
        $t->same(['1', '1.2', '1.2.3', '1.2.3.4', '1.2.3.4.5'], $actual);
    };

    $tests['real upstream window functions dynamic window6 1.' . $name . ' named running sum mapped identifiers'] = static function (TestRunner $t) use ($tables, $table, $x, $y, $w): void {
        $actual = array_column(SQLiteSelectSql::execute("SELECT sum({$x}) OVER {$w} AS running_x FROM {$table} WINDOW {$w} AS (ORDER BY {$y})", $tables), 'running_x');
        $t->same([1, 3, 6, 10, 15], $actual);
    };

    $tests['real upstream window functions dynamic window6 1.' . $name . ' qualified named running sum'] = static function (TestRunner $t) use ($tables, $table, $x, $y, $w): void {
        $actual = array_column(SQLiteSelectSql::execute("SELECT sum({$table}.{$x}) OVER {$w} AS running_x FROM {$table} WINDOW {$w} AS (ORDER BY {$y})", $tables), 'running_x');
        $t->same([1, 3, 6, 10, 15], $actual);
    };

    $tests['real upstream window functions dynamic window6 1.' . $name . ' ordinary aggregate alias'] = static function (TestRunner $t) use ($tables, $table, $x, $alias): void {
        $actual = array_column(SQLiteSelectSql::execute("SELECT sum({$x}) AS {$alias} FROM {$table}", $tables), $alias);
        $t->same([15], $actual);
    };
}

$ntileLetters = range('a', 'j');
foreach (range(1, 19) as $bucketCount) {
    $tests['real upstream window functions dynamic window4 1.' . $bucketCount . ' ntile bucket distribution'] = static function (TestRunner $t) use ($bucketCount, $ntileLetters, $ntileBucketForRow, $queryNtile): void {
        $actual = $queryNtile("SELECT letter, ntile({$bucketCount}) OVER (ORDER BY letter) AS bucket FROM app_ntile_letters");

        foreach ($ntileLetters as $index => $letter) {
            $t->same(
                [$letter, $ntileBucketForRow($index + 1, count($ntileLetters), $bucketCount)],
                [$actual[$index]['letter'], $actual[$index]['bucket']],
                "window4 ntile({$bucketCount}) row {$letter}"
            );
        }
    };
}

$peerFrameCases = [
    'window7 1.2 groups current row peer sum' => [
        'SELECT a, sum(b) OVER (ORDER BY a GROUPS BETWEEN CURRENT ROW AND CURRENT ROW) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [$a, $a]),
    ],
    'window7 1.3 groups zero preceding zero following' => [
        'SELECT a, sum(b) OVER (ORDER BY a GROUPS BETWEEN 0 PRECEDING AND 0 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [$a, $a]),
    ],
    'window7 1.4 groups two preceding two following' => [
        'SELECT a, sum(b) OVER (ORDER BY a GROUPS BETWEEN 2 PRECEDING AND 2 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [max(0, $a - 2), min(9, $a + 2)]),
    ],
    'window7 1.5 range zero preceding zero following' => [
        'SELECT a, sum(b) OVER (ORDER BY a RANGE BETWEEN 0 PRECEDING AND 0 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [$a, $a]),
    ],
    'window7 1.6 range two preceding two following' => [
        'SELECT a, sum(b) OVER (ORDER BY a RANGE BETWEEN 2 PRECEDING AND 2 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [max(0, $a - 2), min(9, $a + 2)]),
    ],
    'window7 1.7 range two preceding one following' => [
        'SELECT a, sum(b) OVER (ORDER BY a RANGE BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [max(0, $a - 2), min(9, $a + 1)]),
    ],
    'window7 1.8.1 range zero preceding one following' => [
        'SELECT a, sum(b) OVER (ORDER BY a RANGE BETWEEN 0 PRECEDING AND 1 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [$a, min(9, $a + 1)]),
    ],
    'window7 1.8.2 descending range zero preceding one following' => [
        'SELECT a, sum(b) OVER (ORDER BY a DESC RANGE BETWEEN 0 PRECEDING AND 1 FOLLOWING) AS peer_sum FROM app_peer_frames ORDER BY a',
        static fn () => $peerFrameExpected(static fn (int $a): array => [$a, min(9, $a + 1)]),
    ],
];

foreach ($peerFrameCases as $name => [$sql, $expectedCallback]) {
    $tests['real upstream window functions dynamic ' . $name] = static function (TestRunner $t) use ($queryPeerFrames, $sql, $expectedCallback): void {
        $actual = $queryPeerFrames($sql);
        foreach ($expectedCallback() as $index => $expected) {
            $t->same($expected, [$actual[$index]['a'], $actual[$index]['peer_sum']], $sql . ' row ' . $index);
        }
    };
}

foreach ($cases as $name => [$callback, $expected]) {
    $tests['real upstream window functions dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['real upstream window functions dynamic rejects invalid ntile zero from window1 5.1'] = static function (TestRunner $t) use ($queryText): void {
    $t->throws(InvalidArgumentException::class, static fn () => $queryText('SELECT ntile(0) OVER (ORDER BY a) AS bucket FROM app_text'));
};

$tests['real upstream window functions dynamic rejects invalid ntile negative from window1 5.2'] = static function (TestRunner $t) use ($queryText): void {
    $t->throws(InvalidArgumentException::class, static fn () => $queryText('SELECT ntile(-1) OVER (ORDER BY a) AS bucket FROM app_text'));
};

$tests['real upstream window functions dynamic records frame plan from window2 bounded rows'] = static function (TestRunner $t) use ($textRows): void {
    $plan = SQLiteSelectSql::plan('SELECT sum(d) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS s FROM app_text', ['app_text' => $textRows]);

    $t->same([
        'unit' => 'ROWS',
        'preceding' => 1,
        'following' => 1,
        'exclude' => 'NO OTHERS',
        'startBoundary' => '1 PRECEDING',
        'endBoundary' => '1 FOLLOWING',
    ], $plan['select'][0]['frame']);
};

$tests['real upstream window functions dynamic records filter plan from window1 filtered sum'] = static function (TestRunner $t) use ($textRows): void {
    $plan = SQLiteSelectSql::plan("SELECT sum(d) FILTER (WHERE b = 'even') OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS even_sum FROM app_text", ['app_text' => $textRows]);

    $t->same('even', $plan['select'][0]['filter']['right']['value']);
    $t->same('even_sum', $plan['select'][0]['alias']);
};

return $tests;
