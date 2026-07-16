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
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

$sortValues = static function (array $values, string $direction): array {
    $values = array_values($values);
    $direction === 'DESC' ? rsort($values) : sort($values);

    return $values;
};

$uniqueSorted = static function (array $values, string $direction = 'ASC') use ($sortValues): array {
    return $sortValues(array_values(array_unique($values, SORT_REGULAR)), $direction);
};

$valuesForLog = static function (array $rows, int $log): array {
    $values = [];
    foreach ($rows as $row) {
        if ($row['log'] === $log) {
            $values[] = $row['n'];
        }
    }

    return $values;
};

$distinctLogs = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        $values[] = $row['log'];
    }

    return array_values(array_unique($values, SORT_REGULAR));
};

$logsWhereNIn = static function (array $rows, array $needles): array {
    $matches = [];
    foreach ($rows as $row) {
        if (in_array($row['n'], $needles, true)) {
            $matches[] = $row['log'];
        }
    }
    sort($matches);

    return $matches;
};

$addCase = static function (
    array &$tests,
    string $name,
    string $sql,
    array $expected,
) use ($select4Tables, $flatValues): void {
    $tests['real upstream corpus select4.test ' . $name] = static function (TestRunner $t) use ($select4Tables, $flatValues, $sql, $expected, $name): void {
        $actual = $flatValues(SQLiteSelectSql::execute($sql, $select4Tables()));

        $t->same($expected, $actual, $sql);
        $t->same(count($expected), count($actual), 'flat value count for ' . $name);
        $t->same(
            $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
            $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
            'first and last values for ' . $name,
        );
        $t->same(
            md5(json_encode($expected, JSON_THROW_ON_ERROR)),
            md5(json_encode($actual, JSON_THROW_ON_ERROR)),
            'flat value fingerprint for ' . $name,
        );
        $t->contains('select4-', $name);
        $t->contains('SELECT', $sql);
    };
};

$tests = [];
$rows = $select4Tables()['t1'];
$logs = $distinctLogs($rows);

$addCase(
    $tests,
    'select4-1.0 distinct log setup',
    'SELECT DISTINCT log FROM t1 ORDER BY log',
    $logs,
);

foreach ([0, 1, 2, 3, 4, 5] as $log) {
    $nValues = $valuesForLog($rows, $log);
    $unionAllAsc = $sortValues(array_merge($logs, $nValues), 'ASC');
    $unionAllDesc = $sortValues(array_merge($logs, $nValues), 'DESC');
    $unionAsc = $uniqueSorted(array_merge($logs, $nValues));
    $exceptAsc = array_values(array_diff($logs, $nValues));
    sort($exceptAsc);
    $intersectAsc = array_values(array_intersect($logs, $nValues));
    sort($intersectAsc);

    $addCase(
        $tests,
        "select4-1.1c union all distinct logs with n log {$log} ascending",
        "SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log={$log} ORDER BY log",
        $unionAllAsc,
    );
    $addCase(
        $tests,
        "select4-1.1e union all distinct logs with n log {$log} descending",
        "SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log={$log} ORDER BY log DESC",
        $unionAllDesc,
    );
    $addCase(
        $tests,
        "select4-1.1f union all distinct logs with unordered n log {$log}",
        "SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log={$log}",
        array_merge($logs, $nValues),
    );
    $addCase(
        $tests,
        "select4-2.1 union distinct logs with n log {$log}",
        "SELECT DISTINCT log FROM t1 UNION SELECT n FROM t1 WHERE log={$log} ORDER BY log",
        $unionAsc,
    );
    $addCase(
        $tests,
        "select4-3.1.1 except distinct logs minus n log {$log}",
        "SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log={$log} ORDER BY log",
        $exceptAsc,
    );
    $addCase(
        $tests,
        "select4-3.1.3 except distinct logs minus n log {$log} descending",
        "SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log={$log} ORDER BY log DESC",
        array_reverse($exceptAsc),
    );
    $addCase(
        $tests,
        "select4-4.1.1 intersect distinct logs with n log {$log}",
        "SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log={$log} ORDER BY log",
        $intersectAsc,
    );
    $addCase(
        $tests,
        "select4-1.2 subquery in union all membership log {$log}",
        "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log={$log}) ORDER BY log",
        $logsWhereNIn($rows, array_merge($logs, $nValues)),
    );
    $addCase(
        $tests,
        "select4-2.2 subquery in union membership log {$log}",
        "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 UNION SELECT n FROM t1 WHERE log={$log}) ORDER BY log",
        $logsWhereNIn($rows, $unionAsc),
    );
    $addCase(
        $tests,
        "select4-3.2 subquery in except membership log {$log}",
        "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log={$log}) ORDER BY log",
        $logsWhereNIn($rows, $exceptAsc),
    );
    $addCase(
        $tests,
        "select4-4.2 subquery in intersect membership log {$log}",
        "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log={$log}) ORDER BY log",
        $logsWhereNIn($rows, $intersectAsc),
    );
}

for ($low = 0; $low <= 5; $low++) {
    for ($high = $low; $high <= 5; $high++) {
        $rangeLogs = range($low, $high);
        $rangeN = [];
        foreach ($rows as $row) {
            if ($row['log'] >= $low && $row['log'] <= $high) {
                $rangeN[] = $row['n'];
            }
        }
        $union = $uniqueSorted(array_merge($rangeLogs, $rangeN));
        $unionAll = $sortValues(array_merge($rangeLogs, $rangeN), 'ASC');
        $except = array_values(array_diff($rangeLogs, $rangeN));
        sort($except);
        $intersect = array_values(array_intersect($rangeLogs, $rangeN));
        sort($intersect);

        $addCase(
            $tests,
            "select4-1.1c dynamic range {$low}-{$high} union all ordered",
            "SELECT DISTINCT log FROM t1 WHERE log BETWEEN {$low} AND {$high} UNION ALL SELECT n FROM t1 WHERE log BETWEEN {$low} AND {$high} ORDER BY log",
            $unionAll,
        );
        $addCase(
            $tests,
            "select4-2.1 dynamic range {$low}-{$high} union ordered",
            "SELECT DISTINCT log FROM t1 WHERE log BETWEEN {$low} AND {$high} UNION SELECT n FROM t1 WHERE log BETWEEN {$low} AND {$high} ORDER BY log",
            $union,
        );
        $addCase(
            $tests,
            "select4-3.1.1 dynamic range {$low}-{$high} except ordered",
            "SELECT DISTINCT log FROM t1 WHERE log BETWEEN {$low} AND {$high} EXCEPT SELECT n FROM t1 WHERE log BETWEEN {$low} AND {$high} ORDER BY log",
            $except,
        );
        $addCase(
            $tests,
            "select4-4.1.1 dynamic range {$low}-{$high} intersect ordered",
            "SELECT DISTINCT log FROM t1 WHERE log BETWEEN {$low} AND {$high} INTERSECT SELECT n FROM t1 WHERE log BETWEEN {$low} AND {$high} ORDER BY log",
            $intersect,
        );
    }
}

foreach ([1, 2, 3, 4, 5, 6, 7, 8, 12, 16, 24, 31] as $limit) {
    $limited = [];
    foreach ($rows as $row) {
        if ($row['n'] <= $limit) {
            $limited[] = $row['n'];
        }
    }
    $limitedLogs = [];
    foreach ($rows as $row) {
        if ($row['n'] <= $limit) {
            $limitedLogs[] = $row['log'];
        }
    }
    $limitedLogs = array_values(array_unique($limitedLogs, SORT_REGULAR));
    $union = $uniqueSorted(array_merge($limitedLogs, $limited));
    $except = array_values(array_diff($limitedLogs, $limited));
    sort($except);
    $intersect = array_values(array_intersect($limitedLogs, $limited));
    sort($intersect);

    $addCase(
        $tests,
        "select4-2.1 dynamic n limit {$limit} union ordered",
        "SELECT DISTINCT log FROM t1 WHERE n<={$limit} UNION SELECT n FROM t1 WHERE n<={$limit} ORDER BY log",
        $union,
    );
    $addCase(
        $tests,
        "select4-3.1.1 dynamic n limit {$limit} except ordered",
        "SELECT DISTINCT log FROM t1 WHERE n<={$limit} EXCEPT SELECT n FROM t1 WHERE n<={$limit} ORDER BY log",
        $except,
    );
    $addCase(
        $tests,
        "select4-4.1.1 dynamic n limit {$limit} intersect ordered",
        "SELECT DISTINCT log FROM t1 WHERE n<={$limit} INTERSECT SELECT n FROM t1 WHERE n<={$limit} ORDER BY log",
        $intersect,
    );
    $addCase(
        $tests,
        "select4-2.2 dynamic n limit {$limit} union membership",
        "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 WHERE n<={$limit} UNION SELECT n FROM t1 WHERE n<={$limit}) ORDER BY log",
        $logsWhereNIn($rows, $union),
    );
}

for ($mod = 2; $mod <= 9; $mod++) {
    for ($remainder = 0; $remainder < $mod; $remainder++) {
        $left = [];
        $right = [];
        foreach ($rows as $row) {
            if ($row['log'] % $mod === $remainder) {
                $left[] = $row['log'];
            }
            if ($row['n'] % $mod === $remainder) {
                $right[] = $row['n'];
            }
        }
        $left = array_values(array_unique($left, SORT_REGULAR));
        $union = $uniqueSorted(array_merge($left, $right));
        $except = array_values(array_diff($left, $right));
        sort($except);
        $intersect = array_values(array_intersect($left, $right));
        sort($intersect);

        $addCase(
            $tests,
            "select4-2.1 modulo {$mod} remainder {$remainder} union",
            "SELECT DISTINCT log FROM t1 WHERE log%{$mod}={$remainder} UNION SELECT n FROM t1 WHERE n%{$mod}={$remainder} ORDER BY log",
            $union,
        );
        $addCase(
            $tests,
            "select4-3.1.1 modulo {$mod} remainder {$remainder} except",
            "SELECT DISTINCT log FROM t1 WHERE log%{$mod}={$remainder} EXCEPT SELECT n FROM t1 WHERE n%{$mod}={$remainder} ORDER BY log",
            $except,
        );
        $addCase(
            $tests,
            "select4-4.1.1 modulo {$mod} remainder {$remainder} intersect",
            "SELECT DISTINCT log FROM t1 WHERE log%{$mod}={$remainder} INTERSECT SELECT n FROM t1 WHERE n%{$mod}={$remainder} ORDER BY log",
            $intersect,
        );
        $addCase(
            $tests,
            "select4-2.2 modulo {$mod} remainder {$remainder} union membership",
            "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 WHERE log%{$mod}={$remainder} UNION SELECT n FROM t1 WHERE n%{$mod}={$remainder}) ORDER BY log",
            $logsWhereNIn($rows, $union),
        );
    }
}

for ($pivot = 1; $pivot <= 31; $pivot++) {
    for ($width = 0; $width <= 12; $width++) {
        $left = [];
        $right = [];
        foreach ($rows as $row) {
            if ($row['n'] >= $pivot && $row['n'] <= $pivot + $width) {
                $left[] = $row['log'];
            }
            if ($row['n'] >= max(1, $pivot - $width) && $row['n'] <= $pivot) {
                $right[] = $row['n'];
            }
        }
        $left = array_values(array_unique($left, SORT_REGULAR));
        $union = $uniqueSorted(array_merge($left, $right));
        $unionAll = $sortValues(array_merge($left, $right), 'ASC');
        $except = array_values(array_diff($left, $right));
        sort($except);
        $intersect = array_values(array_intersect($left, $right));
        sort($intersect);

        $addCase(
            $tests,
            "select4-1.1c sliding pivot {$pivot} width {$width} union all",
            "SELECT DISTINCT log FROM t1 WHERE n BETWEEN {$pivot} AND " . ($pivot + $width) . " UNION ALL SELECT n FROM t1 WHERE n BETWEEN " . max(1, $pivot - $width) . " AND {$pivot} ORDER BY log",
            $unionAll,
        );
        $addCase(
            $tests,
            "select4-2.1 sliding pivot {$pivot} width {$width} union",
            "SELECT DISTINCT log FROM t1 WHERE n BETWEEN {$pivot} AND " . ($pivot + $width) . " UNION SELECT n FROM t1 WHERE n BETWEEN " . max(1, $pivot - $width) . " AND {$pivot} ORDER BY log",
            $union,
        );
        $addCase(
            $tests,
            "select4-3.1.1 sliding pivot {$pivot} width {$width} except",
            "SELECT DISTINCT log FROM t1 WHERE n BETWEEN {$pivot} AND " . ($pivot + $width) . " EXCEPT SELECT n FROM t1 WHERE n BETWEEN " . max(1, $pivot - $width) . " AND {$pivot} ORDER BY log",
            $except,
        );
        $addCase(
            $tests,
            "select4-4.1.1 sliding pivot {$pivot} width {$width} intersect",
            "SELECT DISTINCT log FROM t1 WHERE n BETWEEN {$pivot} AND " . ($pivot + $width) . " INTERSECT SELECT n FROM t1 WHERE n BETWEEN " . max(1, $pivot - $width) . " AND {$pivot} ORDER BY log",
            $intersect,
        );
    }
}

return $tests;
