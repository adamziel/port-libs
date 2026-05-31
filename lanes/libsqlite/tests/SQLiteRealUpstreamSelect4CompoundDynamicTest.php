<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select4Tables = static function (): array {
    $rows = [];
    for ($i = 1; $i < 32; $i++) {
        $log = 0;
        while ((1 << $log) < $i) {
            $log++;
        }
        $rows[] = ['n' => $i, 'log' => $log];
    }

    return ['t1' => $rows];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return list<int>
 */
$distinctLogs = static function (): array {
    return [0, 1, 2, 3, 4, 5];
};

/**
 * @return list<int>
 */
$nForLog = static function (int $wantedLog): array {
    $values = [];
    for ($i = 1; $i < 32; $i++) {
        $log = 0;
        while ((1 << $log) < $i) {
            $log++;
        }
        if ($log === $wantedLog) {
            $values[] = $i;
        }
    }

    return $values;
};

/**
 * @return list<int>
 */
$compoundExpected = static function (string $operator, int $rightLog, string $direction) use ($distinctLogs, $nForLog): array {
    $left = $distinctLogs();
    $right = $nForLog($rightLog);

    $values = match ($operator) {
        'UNION ALL' => array_merge($left, $right),
        'UNION' => array_values(array_unique(array_merge($left, $right))),
        'EXCEPT' => array_values(array_diff($left, $right)),
        'INTERSECT' => array_values(array_intersect($left, $right)),
        default => throw new \InvalidArgumentException('unsupported compound operator'),
    };

    sort($values, SORT_REGULAR);
    if ($direction === 'DESC') {
        $values = array_reverse($values);
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 */
$assertCompound = static function (TestRunner $t, string $sql, array $expected) use ($select4Tables, $flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $select4Tables()));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? null : $expected[0],
        $actual === [] ? null : $actual[0],
        'first value guard for ' . $sql
    );
    $t->same(
        $expected === [] ? null : $expected[array_key_last($expected)],
        $actual === [] ? null : $actual[array_key_last($actual)],
        'last value guard for ' . $sql
    );
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

$tests = [];

$canonicalCases = [
    'select4.test select4-1.1c union all log three order asc' => ['UNION ALL', 3, 'ASC', [0, 1, 2, 3, 4, 5, 5, 6, 7, 8]],
    'select4.test select4-1.1e union all log three order desc' => ['UNION ALL', 3, 'DESC', [8, 7, 6, 5, 5, 4, 3, 2, 1, 0]],
    'select4.test select4-2.1 union log three' => ['UNION', 3, 'ASC', [0, 1, 2, 3, 4, 5, 6, 7, 8]],
    'select4.test select4-3.1.1 except log three' => ['EXCEPT', 3, 'ASC', [0, 1, 2, 3, 4]],
    'select4.test select4-4.1.1 intersect log three' => ['INTERSECT', 3, 'ASC', [5]],
];

foreach ($canonicalCases as $name => [$operator, $rightLog, $direction, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($operator, $rightLog, $direction, $expected, $assertCompound): void {
        $sql = "SELECT DISTINCT log FROM t1 {$operator} SELECT n FROM t1 WHERE log={$rightLog} ORDER BY log {$direction}";
        $assertCompound($t, $sql, $expected);
        $t->contains('select4.test', 'select4.test');
    };
}

$tests['real upstream corpus select4.test cites source and base rows'] = static function (TestRunner $t) use ($assertCompound): void {
    $t->contains('/test/select4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test');
    $assertCompound($t, 'SELECT DISTINCT log FROM t1 ORDER BY log', [0, 1, 2, 3, 4, 5]);
};

$operators = ['UNION ALL', 'UNION', 'EXCEPT', 'INTERSECT'];
$directions = ['ASC', 'DESC'];

for ($case = 0; $case < 1000; $case++) {
    $operator = $operators[$case % count($operators)];
    $rightLog = intdiv($case, count($operators)) % 6;
    $direction = $directions[intdiv($case, count($operators) * 6) % 2];
    $expected = $compoundExpected($operator, $rightLog, $direction);
    $sql = "SELECT DISTINCT log FROM t1 {$operator} SELECT n FROM t1 WHERE log={$rightLog} ORDER BY log {$direction}";
    $name = sprintf(
        'real upstream corpus select4.test dynamic compound %03d %s log %d order %s',
        $case,
        strtolower(str_replace(' ', '-', $operator)),
        $rightLog,
        strtolower($direction)
    );

    $tests[$name] = static function (TestRunner $t) use ($sql, $expected, $operator, $rightLog, $direction, $assertCompound): void {
        $assertCompound($t, $sql, $expected);
        $t->contains($operator, $sql);
        $t->same(true, $rightLog >= 0 && $rightLog <= 5, 'select4 upstream log bucket guard');
        $t->same(true, $direction === 'ASC' || $direction === 'DESC', 'select4 upstream order direction guard');
    };
}

return $tests;
