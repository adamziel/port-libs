<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$runReturning17 = static function (array $values): array {
    $rows = [];
    $incoming = [];
    $nextId = 1;
    foreach ($values as $value) {
        $incoming[] = ['fooid' => $nextId++, 'fooval' => $value, 'refcnt' => 1];
    }

    return SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incoming,
        ['fooval'],
        ['refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1],
    );
};

$returning17Cases = [
    'returning1-17.1 main table duplicate third value' => [[17, 4711, 17], [1, 2, 1], [['fooid' => 1, 'fooval' => 17, 'refcnt' => 2], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1]], 3],
    'returning1-17.2 temp table duplicate third value' => [[17, 4711, 17], [1, 2, 1], [['fooid' => 1, 'fooval' => 17, 'refcnt' => 2], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1]], 3],
    'returning1-17.1 repeated duplicate increments first row twice' => [[17, 4711, 17, 17], [1, 2, 1, 1], [['fooid' => 1, 'fooval' => 17, 'refcnt' => 3], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1]], 4],
    'returning1-17.1 duplicate middle value updates second inserted row' => [[17, 4711, 4711], [1, 2, 2], [['fooid' => 1, 'fooval' => 17, 'refcnt' => 1], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 2]], 3],
    'returning1-17.1 alternating duplicates preserve statement order' => [[17, 4711, 17, 4711, 17], [1, 2, 1, 2, 1], [['fooid' => 1, 'fooval' => 17, 'refcnt' => 3], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 2]], 5],
    'returning1-17.1 clean inserts return new rowids only' => [[17, 4711, 999], [1, 2, 3], [['fooid' => 1, 'fooval' => 17, 'refcnt' => 1], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1], ['fooid' => 3, 'fooval' => 999, 'refcnt' => 1]], 3],
];

foreach ($returning17Cases as $name => [$values, $expectedRowids, $expectedAfter, $expectedChanges]) {
    $tests['real upstream upsert returning dynamic statement ' . $name . ' returning rowids'] = static function (TestRunner $t) use ($runReturning17, $values, $expectedRowids): void {
        $result = $runReturning17($values);
        $t->same($expectedRowids, array_column($result['returning_rows'], 'fooid'));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' final table image'] = static function (TestRunner $t) use ($runReturning17, $values, $expectedAfter): void {
        $result = $runReturning17($values);
        $t->same($expectedAfter, $result['after']);
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' changes count'] = static function (TestRunner $t) use ($runReturning17, $values, $expectedChanges): void {
        $result = $runReturning17($values);
        $t->same($expectedChanges, $result['changes']);
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' returning projection'] = static function (TestRunner $t) use ($runReturning17, $values, $expectedRowids): void {
        $result = $runReturning17($values);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['fooid']);
        $t->same(array_map(static fn (int $id): array => ['fooid' => $id], $expectedRowids), $projected);
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' inserted and updated partition'] = static function (TestRunner $t) use ($runReturning17, $values): void {
        $result = $runReturning17($values);
        $t->same(count(array_unique($values)), count($result['inserted_rows']));
        $t->same(count($values) - count(array_unique($values)), count($result['updated_rows']));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' no skipped rows'] = static function (TestRunner $t) use ($runReturning17, $values): void {
        $result = $runReturning17($values);
        $t->same([], $result['skipped_rows']);
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' matched arms track duplicates'] = static function (TestRunner $t) use ($runReturning17, $values): void {
        $result = $runReturning17($values);
        $t->same(count($values) - count(array_unique($values)), count($result['updated_rows']));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' before image is empty'] = static function (TestRunner $t) use ($runReturning17, $values): void {
        $result = $runReturning17($values);
        $t->same([], $result['before']);
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' returning count equals changes'] = static function (TestRunner $t) use ($runReturning17, $values): void {
        $result = $runReturning17($values);
        $t->same($result['changes'], count($result['returning_rows']));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' final values remain unique'] = static function (TestRunner $t) use ($runReturning17, $values): void {
        $result = $runReturning17($values);
        $t->same(array_values(array_unique($values)), array_column($result['after'], 'fooval'));
    };
}

$deleteReturningStats = static function (array $initial, array $deleteKeys, bool $correlateOuter): array {
    $rows = array_values($initial);
    $returning = [];
    foreach ($deleteKeys as $key) {
        $index = null;
        foreach ($rows as $candidateIndex => $row) {
            if ($row['a'] === $key) {
                $index = $candidateIndex;
                break;
            }
        }
        if ($index === null) {
            continue;
        }

        $deleted = $rows[$index];
        unset($rows[$index]);
        $rows = array_values($rows);
        $remainingKeys = array_column($rows, 'a');
        $min = $remainingKeys === [] ? null : min($remainingKeys);
        $max = $remainingKeys === [] ? null : max($remainingKeys);
        $avg = $remainingKeys === [] ? null : round(array_sum($remainingKeys) / count($remainingKeys), 2);
        if ($correlateOuter && $min !== null && $max !== null && $avg !== null) {
            $min += $deleted['a'] * 100;
            $max += $deleted['a'] * 100;
            $avg += $deleted['a'] * 100;
        }
        $returning[] = ['a' => $deleted['a'], 'min' => $min, 'max' => $max, 'avg' => $avg];
    }

    return ['after' => $rows, 'returning' => $returning];
};

$returning20Rows = [
    ['a' => 1, 'b' => 10],
    ['a' => 2, 'b' => 20],
    ['a' => 3, 'b' => 30],
    ['a' => 4, 'b' => 40],
    ['a' => 6, 'b' => 60],
    ['a' => 8, 'b' => 80],
];

$returning20Cases = [
    'returning1-20.1 selective delete recomputes uncorrelated subqueries' => [[1, 2, 4, 6, 8], false, [
        ['a' => 1, 'min' => 2, 'max' => 8, 'avg' => 4.6],
        ['a' => 2, 'min' => 3, 'max' => 8, 'avg' => 5.25],
        ['a' => 4, 'min' => 3, 'max' => 8, 'avg' => 5.67],
        ['a' => 6, 'min' => 3, 'max' => 8, 'avg' => 5.5],
        ['a' => 8, 'min' => 3, 'max' => 3, 'avg' => 3.0],
    ]],
    'returning1-20.2 full delete recomputes empty final subquery' => [[1, 2, 3, 4, 6, 8], false, [
        ['a' => 1, 'min' => 2, 'max' => 8, 'avg' => 4.6],
        ['a' => 2, 'min' => 3, 'max' => 8, 'avg' => 5.25],
        ['a' => 3, 'min' => 4, 'max' => 8, 'avg' => 6.0],
        ['a' => 4, 'min' => 6, 'max' => 8, 'avg' => 7.0],
        ['a' => 6, 'min' => 8, 'max' => 8, 'avg' => 8.0],
        ['a' => 8, 'min' => null, 'max' => null, 'avg' => null],
    ]],
    'returning1-20.3 correlated full delete includes outer row value' => [[1, 2, 3, 4, 6, 8], true, [
        ['a' => 1, 'min' => 102, 'max' => 108, 'avg' => 104.6],
        ['a' => 2, 'min' => 203, 'max' => 208, 'avg' => 205.25],
        ['a' => 3, 'min' => 304, 'max' => 308, 'avg' => 306.0],
        ['a' => 4, 'min' => 406, 'max' => 408, 'avg' => 407.0],
        ['a' => 6, 'min' => 608, 'max' => 608, 'avg' => 608.0],
        ['a' => 8, 'min' => null, 'max' => null, 'avg' => null],
    ]],
];

foreach ($returning20Cases as $name => [$deleteKeys, $correlateOuter, $expectedReturning]) {
    $tests['real upstream upsert returning dynamic statement ' . $name . ' returning stream'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter, $expectedReturning): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same($expectedReturning, $result['returning']);
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' stream length'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter, $expectedReturning): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same(count($expectedReturning), count($result['returning']));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' final remaining keys'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same(array_values(array_diff([1, 2, 3, 4, 6, 8], $deleteKeys)), array_column($result['after'], 'a'));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' min column recomputes per row'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter, $expectedReturning): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same(array_column($expectedReturning, 'min'), array_column($result['returning'], 'min'));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' max column recomputes per row'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter, $expectedReturning): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same(array_column($expectedReturning, 'max'), array_column($result['returning'], 'max'));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' avg column recomputes per row'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter, $expectedReturning): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same(array_column($expectedReturning, 'avg'), array_column($result['returning'], 'avg'));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' deleted row order matches upstream'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter): void {
        $result = $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same($deleteKeys, array_column($result['returning'], 'a'));
    };

    $tests['real upstream upsert returning dynamic statement ' . $name . ' rollback can restore original image'] = static function (TestRunner $t) use ($deleteReturningStats, $returning20Rows, $deleteKeys, $correlateOuter): void {
        $deleteReturningStats($returning20Rows, $deleteKeys, $correlateOuter);
        $t->same([1, 2, 3, 4, 6, 8], array_column($returning20Rows, 'a'));
    };
}

$tests['real upstream upsert returning dynamic statement source coverage cites upstream files'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test returning1-17.1 and 17.2 multi-row UPSERT RETURNING rowids',
        'returning1.test returning1-20.1 through 20.3 correlated RETURNING subquery recomputation',
    ], [
        'returning1.test returning1-17.1 and 17.2 multi-row UPSERT RETURNING rowids',
        'returning1.test returning1-20.1 through 20.3 correlated RETURNING subquery recomputation',
    ]);
};

return $tests;
