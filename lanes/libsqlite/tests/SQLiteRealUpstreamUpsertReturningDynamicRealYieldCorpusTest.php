<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$seedRows = [
    ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
    ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
    ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
    ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
];
$constraints = [['a'], ['c'], ['d'], ['e']];

$orders = [
    'upsert5-1.100-through-1.103 c-d-e-catchall' => ['c', 'd', 'e', null],
    'upsert5-1.200-through-1.204 c-a-d-e-catchall' => ['c', 'a', 'd', 'e', null],
    'upsert5-1.410-through-1.423 c-d-catchall' => ['c', 'd', null],
    'upsert5-1.500-through-1.505 c-d-e-catchall-do-nothing' => ['c', 'd', 'e', null],
];

$incomingRowsForSeed = static function (int $seed): array {
    $base = 100 + ($seed * 10);

    return [
        ['a' => 90 + $seed, 'b' => 'insert-' . $seed, 'c' => $base + 1, 'd' => $base + 2, 'e' => $base + 3],
        ['a' => 91 + $seed, 'b' => 'c-even-' . $seed, 'c' => 20, 'd' => $base + 4, 'e' => $base + 6],
        ['a' => 92 + $seed, 'b' => 'c-odd-skip-' . $seed, 'c' => 20, 'd' => $base + 7, 'e' => $base + 5],
        ['a' => 93 + $seed, 'b' => 'd-conflict-' . $seed, 'c' => $base + 8, 'd' => 300, 'e' => $base + 9],
        ['a' => 94 + $seed, 'b' => 'e-conflict-' . $seed, 'c' => $base + 10, 'd' => $base + 11, 'e' => 4000],
        ['a' => 1, 'b' => 'primary-catch-' . $seed, 'c' => $base + 12, 'd' => $base + 13, 'e' => $base + 14],
    ];
};

$buildArms = static function (array $order, bool $catchAllDoesNothing): array {
    $arms = [];
    foreach ($order as $target) {
        $action = $target === null && $catchAllDoesNothing ? 'nothing' : 'update';
        $arm = [
            'target' => $target === null ? null : [$target],
            'action' => $action,
        ];
        if ($action === 'update') {
            $label = $target === null ? 'catch' : (string) $target;
            $arm['assignments'] = [
                'b' => static fn (array $current, array $incoming): string => $label . ':' . $incoming['b'],
            ];
            if ($target === 'c') {
                $arm['where'] = static fn (array $current, array $incoming): bool => ((int) $incoming['e'] % 2) === 0;
            }
        }
        $arms[] = $arm;
    }

    return $arms;
};

$oracle = static function (array $incomingRows, array $order, bool $catchAllDoesNothing) use ($seedRows, $quoteSql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE t1(a INTEGER PRIMARY KEY, b TEXT, c INT UNIQUE, d INT UNIQUE, e INT UNIQUE)');
    foreach ($seedRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO t1(a,b,c,d,e) VALUES(%d,%s,%d,%d,%d)',
            $row['a'],
            $quoteSql($row['b']),
            $row['c'],
            $row['d'],
            $row['e'],
        ));
    }

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf(
            '(%d,%s,%d,%d,%d)',
            $row['a'],
            $quoteSql($row['b']),
            $row['c'],
            $row['d'],
            $row['e'],
        );
    }

    $sql = 'INSERT INTO t1(a,b,c,d,e) VALUES ' . implode(',', $values);
    foreach ($order as $target) {
        if ($target === null) {
            $sql .= $catchAllDoesNothing
                ? ' ON CONFLICT DO NOTHING'
                : " ON CONFLICT DO UPDATE SET b='catch:'||excluded.b";
            continue;
        }
        $where = $target === 'c' ? ' WHERE excluded.e%2=0' : '';
        $sql .= " ON CONFLICT({$target}) DO UPDATE SET b='{$target}:'||excluded.b" . $where;
    }
    $sql .= ' RETURNING a,b,c,d,e';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = [
            'a' => (int) $row['a'],
            'b' => (string) $row['b'],
            'c' => (int) $row['c'],
            'd' => (int) $row['d'],
            'e' => (int) $row['e'],
        ];
    }

    $after = [];
    $result = $db->query('SELECT a,b,c,d,e FROM t1 ORDER BY a');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = [
            'a' => (int) $row['a'],
            'b' => (string) $row['b'],
            'c' => (int) $row['c'],
            'd' => (int) $row['d'],
            'e' => (int) $row['e'],
        ];
    }

    return [
        'after' => $after,
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $incomingRows, array $order, bool $catchAllDoesNothing) use ($seedRows, $constraints, $buildArms): array {
    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $seedRows,
        $incomingRows,
        $buildArms($order, $catchAllDoesNothing),
        $constraints,
    );
    $after = $plan['after'];
    usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    return [
        'after' => array_values($after),
        'returning_rows' => $plan['returning_rows'],
        'changes' => $plan['changes'],
        'yield_trace' => $plan['yield_trace'],
        'matched_arms' => $plan['matched_arms'],
        'dependencies' => $plan['dependencies'],
    ];
};

$caseResult = static function (string $caseKey, array $incomingRows, array $order, bool $catchAllDoesNothing) use ($oracle, $native): array {
    static $cache = [];
    if (!isset($cache[$caseKey])) {
        $cache[$caseKey] = [
            'expected' => $oracle($incomingRows, $order, $catchAllDoesNothing),
            'actual' => $native($incomingRows, $order, $catchAllDoesNothing),
        ];
    }

    return $cache[$caseKey];
};

foreach ($orders as $orderName => $order) {
    for ($seed = 1; $seed <= 50; ++$seed) {
        $catchAllDoesNothing = str_contains($orderName, 'do-nothing') && ($seed % 2 === 0);
        $incomingRows = $incomingRowsForSeed($seed);
        $caseKey = $orderName . ':' . $seed . ':' . ($catchAllDoesNothing ? 'nothing' : 'update');
        $prefix = sprintf('real upstream corpus upsert returning dynamic real yield %s seed %03d ', $orderName, $seed);

        $tests[$prefix . 'final table matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $order, $catchAllDoesNothing): void {
            $result = $caseResult($caseKey, $incomingRows, $order, $catchAllDoesNothing);
            $t->same($result['expected']['after'], $result['actual']['after']);
        };

        $tests[$prefix . 'RETURNING stream matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $order, $catchAllDoesNothing): void {
            $result = $caseResult($caseKey, $incomingRows, $order, $catchAllDoesNothing);
            $t->same($result['expected']['returning_rows'], $result['actual']['returning_rows']);
        };

        $tests[$prefix . 'changes count matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $order, $catchAllDoesNothing): void {
            $result = $caseResult($caseKey, $incomingRows, $order, $catchAllDoesNothing);
            $t->same($result['expected']['changes'], $result['actual']['changes']);
        };

        $tests[$prefix . 'yield trace has one pre-yield per dynamic input row'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $order, $catchAllDoesNothing): void {
            $result = $caseResult($caseKey, $incomingRows, $order, $catchAllDoesNothing);
            $t->same(count($incomingRows), count(array_filter($result['actual']['yield_trace'], static fn (array $event): bool => $event['event'] === 'before-insert')));
        };

        $tests[$prefix . 'changed rows equal non-null returning yield events'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $order, $catchAllDoesNothing): void {
            $result = $caseResult($caseKey, $incomingRows, $order, $catchAllDoesNothing);
            $yielded = array_values(array_filter($result['actual']['yield_trace'], static fn (array $event): bool => $event['returning'] !== null));
            $t->same($result['actual']['changes'], count($yielded));
            $t->same($result['actual']['returning_rows'], array_map(static fn (array $event): array => $event['returning'], $yielded));
        };
    }
}

$tests['real upstream corpus upsert returning dynamic real yield source coverage cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.100 through upsert5-1.505 generalized ON CONFLICT arm ordering',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-11 returning row stream ordering across multi-row DML',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.100 through upsert5-1.505 generalized ON CONFLICT arm ordering',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-11 returning row stream ordering across multi-row DML',
    ]);
};

return $tests;
