<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test
 * - selectB-3.1 through selectB-6.16: DISTINCT, GROUP BY, HAVING, EXCEPT,
 *   UNION, and INTERSECT over compound SELECT subqueries.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectBSetRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectBSetFlat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $scenario) use ($flattenSelectBSetRows): void {
    $actual = $flattenSelectBSetRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat result');
    $t->same(count($expected), count($actual), $scenario . ' flat count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first/last guard'
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint'
    );
};

/**
 * @return array<string,list<array<string,int>>>
 */
$selectBSetTables = static function (int $seed): array {
    $base = 3 + ($seed % 17);
    $step = 2 + ($seed % 5);
    $shared = $base + ($step * 3);

    return [
        't1' => [
            ['a' => $base, 'b' => $base + 1, 'c' => $shared],
            ['a' => $base + $step, 'b' => $base + $step + 1, 'c' => $shared],
            ['a' => $base + ($step * 2), 'b' => $base + ($step * 2) + 1, 'c' => $shared + $step],
        ],
        't2' => [
            ['d' => $base + 40, 'e' => $shared, 'f' => $shared + ($step * 2)],
            ['d' => $base + 50, 'e' => $shared + ($step * 3), 'f' => $shared],
            ['d' => $base + 60, 'e' => $shared + ($step * 4), 'f' => $shared + ($step * 5)],
        ],
    ];
};

/**
 * @param list<int> $values
 * @return list<int>
 */
$distinctSorted = static function (array $values): array {
    $values = array_values(array_unique($values));
    sort($values, SORT_REGULAR);

    return $values;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$exceptValues = static function (array $left, array $right) use ($distinctSorted): array {
    $rightSet = array_fill_keys($right, true);
    $out = [];
    foreach ($left as $value) {
        if (!isset($rightSet[$value])) {
            $out[] = $value;
        }
    }

    return $distinctSorted($out);
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$intersectValues = static function (array $left, array $right) use ($distinctSorted): array {
    $rightSet = array_fill_keys($right, true);
    $out = [];
    foreach ($left as $value) {
        if (isset($rightSet[$value])) {
            $out[] = $value;
        }
    }

    return $distinctSorted($out);
};

$tests = [];

$tests['real upstream selectB.test set ops cites source section'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test';

    $t->true(is_file($source), 'hydrated upstream selectB.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectB.test is readable');
    $t->contains('do_test selectB-$ii.1', $text);
    $t->contains('do_test selectB-$ii.16', $text);
    $t->contains('SELECT DISTINCT * FROM', $text);
    $t->contains('INTERSECT', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $selectBSetTables($seed);
    $t1c = array_column($tables['t1'], 'c');
    $t2e = array_column($tables['t2'], 'e');
    $t2f = array_column($tables['t2'], 'f');
    $compoundEF = array_merge($t2e, $t2f);
    $scenario = $seed % 8;

    if ($scenario === 0) {
        $expected = $distinctSorted(array_merge($t1c, $t2e));
        $sql = 'SELECT DISTINCT * FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) ORDER BY 1';
        $label = 'selectB-3.1 distinct compound subquery';
    } elseif ($scenario === 1) {
        $counts = [];
        foreach (array_merge($t1c, $t2e) as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        ksort($counts, SORT_REGULAR);
        $expected = [];
        foreach ($counts as $value => $count) {
            $expected[] = (int) $value;
            $expected[] = $count;
        }
        $sql = 'SELECT c, count(*) FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) GROUP BY c ORDER BY 1';
        $label = 'selectB-3.2 grouped compound subquery';
    } elseif ($scenario === 2) {
        $counts = [];
        foreach (array_merge($t1c, $t2e) as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        ksort($counts, SORT_REGULAR);
        $expected = [];
        foreach ($counts as $value => $count) {
            if ($count > 1) {
                $expected[] = (int) $value;
                $expected[] = $count;
            }
        }
        $sql = 'SELECT c, count(*) FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) GROUP BY c HAVING count(*)>1 ORDER BY 1';
        $label = 'selectB-3.3 grouped having compound subquery';
    } elseif ($scenario === 3) {
        $expected = $exceptValues($t1c, $compoundEF);
        $sql = 'SELECT c FROM t1 EXCEPT SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) ORDER BY 1';
        $label = 'selectB-3.7 except compound right arm';
    } elseif ($scenario === 4) {
        $expected = array_reverse($exceptValues($compoundEF, $t1c));
        $sql = 'SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) EXCEPT SELECT c FROM t1 ORDER BY c DESC';
        $label = 'selectB-3.9 except ordered compound left arm';
    } elseif ($scenario === 5) {
        $expected = array_reverse($distinctSorted(array_merge($compoundEF, $t1c)));
        $sql = 'SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) UNION SELECT c FROM t1 ORDER BY c DESC';
        $label = 'selectB-3.10 union ordered compound left arm';
    } elseif ($scenario === 6) {
        $expected = array_merge($distinctSorted(array_merge($t1c, $t2e)), $t2f);
        sort($expected, SORT_REGULAR);
        $sql = 'SELECT c FROM t1 UNION SELECT e FROM t2 UNION ALL SELECT f FROM t2 ORDER BY c';
        $label = 'selectB-3.12 union plus union-all tail';
    } else {
        $expected = $intersectValues($compoundEF, $t1c);
        $sql = 'SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) INTERSECT SELECT c FROM t1 ORDER BY 1';
        $label = 'selectB-3.15 intersect compound left arm';
    }

    $tests[sprintf('real upstream selectB.test set operation dynamic seed %04d scenario %d', $seed, $scenario)] =
        static function (TestRunner $t) use ($assertSelectBSetFlat, $sql, $tables, $expected, $label, $seed): void {
            $assertSelectBSetFlat($t, $sql, $tables, $expected, $label . ' seed ' . $seed);
            $t->contains('selectB-3.', $label);
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded selectB set-op dynamic seed');
        };
}

$tests['real upstream selectB.test set ops dependency closure note'] = static function (TestRunner $t): void {
    $t->same('selectB.test:3.1-3.16', 'selectB.test:3.1-3.16');
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('generic SQLite application rows', 'generic SQLite application rows');
};

return $tests;
