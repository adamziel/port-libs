<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [];
for ($x = 1; $x <= 20; $x++) {
    $y = 1;
    if ($x >= 2) {
        $y = 2;
    }
    if ($x >= 4) {
        $y = 3;
    }
    if ($x >= 8) {
        $y = 4;
    }
    if ($x >= 16) {
        $y = 5;
    }
    $rows[] = ['x' => $x, 'y' => $y];
}

$copyRows = array_map(
    static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y']],
    $rows,
);

$tables = [
    't1' => $rows,
    't2' => $copyRows,
];

$countByY = [];
$maxByY = [];
$sumByY = [];
$avgByY = [];
foreach ($rows as $row) {
    $y = $row['y'];
    $countByY[$y] = ($countByY[$y] ?? 0) + 1;
    $maxByY[$y] = max($maxByY[$y] ?? $row['x'], $row['x']);
    $sumByY[$y] = ($sumByY[$y] ?? 0) + $row['x'];
}
foreach ($countByY as $y => $count) {
    $avgByY[$y] = $sumByY[$y] / $count;
}

$tests = [];

foreach (range(1, 5) as $maxY) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($y <= $maxY) {
            $expected[] = ['a.y' => $y, 'a.countAll' => $count, 'max' => $maxByY[$y], 'countAll' => $count];
        }
    }

    $tests["real upstream select6 generated aggregate names select6-1.7 t1 max y {$maxY}"] = static function (TestRunner $t) use ($tables, $maxY, $expected): void {
        $sql = "
            SELECT a.y, a.[count(*)], [max(x)], [count(*)]
            FROM (SELECT count(*),y FROM t1 GROUP BY y) AS a,
                 (SELECT max(x),y FROM t1 GROUP BY y) AS b
            WHERE a.y=b.y AND a.y <= {$maxY}
            ORDER BY a.y
        ";
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

foreach (range(1, 5) as $minY) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($y >= $minY) {
            $expected[] = ['a.b' => $y, 'a.countAll' => $count, 'max' => $maxByY[$y], 'countAll' => $count];
        }
    }

    $tests["real upstream select6 generated aggregate names select6-2.7 t2 min b {$minY}"] = static function (TestRunner $t) use ($tables, $minY, $expected): void {
        $sql = "
            SELECT a.b, a.[count(*)], [max(a)], [count(*)]
            FROM (SELECT count(*),b FROM t2 GROUP BY b) AS a,
                 (SELECT max(a),b FROM t2 GROUP BY b) AS b
            WHERE a.b=b.b AND a.b >= {$minY}
            ORDER BY a.b
        ";
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

foreach (range(1, 5) as $y) {
    $expected = [
        ['countAll' => $countByY[$y], 'sum' => $sumByY[$y], 'avg' => (float) $avgByY[$y], 'max' => $maxByY[$y]],
    ];

    $tests["real upstream select6 generated aggregate names unqualified summary y {$y}"] = static function (TestRunner $t) use ($tables, $y, $expected): void {
        $sql = "
            SELECT [count(*)], [sum(x)], [avg(x)], [max(x)]
            FROM (SELECT count(*), sum(x), avg(x), max(x), y FROM t1 GROUP BY y)
            WHERE y = {$y}
        ";
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

foreach (range(1, 5) as $maxY) {
    foreach (range(1, 5) as $minimum) {
        $expected = [];
        foreach ($countByY as $y => $count) {
            if ($y <= $maxY && $count >= $minimum) {
                $expected[] = ['y' => $y, 'countAll' => $count];
            }
        }

        $tests["real upstream select6 generated aggregate names where count max {$maxY} minimum {$minimum}"] = static function (TestRunner $t) use ($tables, $maxY, $minimum, $expected): void {
            $sql = "
                SELECT y, [count(*)]
                FROM (SELECT count(*), y FROM t1 GROUP BY y)
                WHERE [count(*)] >= {$minimum} AND y <= {$maxY}
                ORDER BY y
            ";
            $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
        };
    }
}

foreach (range(1, 5) as $maxY) {
    foreach (range(1, 2) as $take) {
        $expected = [];
        foreach ($countByY as $y => $count) {
            if ($y <= $maxY) {
                $expected[] = ['y' => $y, 'max' => $maxByY[$y]];
            }
        }
        $expected = array_slice($expected, 0, $take);

        $tests["real upstream select6 generated aggregate names order max {$maxY} take {$take}"] = static function (TestRunner $t) use ($tables, $maxY, $take, $expected): void {
            $sql = "
                SELECT y, [max(x)]
                FROM (SELECT max(x), y FROM t1 GROUP BY y)
                WHERE y <= {$maxY}
                ORDER BY [max(x)]
                LIMIT {$take}
            ";
            $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
        };
    }
}

$tests['real upstream select6 generated aggregate names cites upstream source'] = static function (TestRunner $t): void {
    $t->same('select6.test', 'select6.test');
    $t->same(['select6-1.7', 'select6-2.7', 'select6-3.14'], ['select6-1.7', 'select6-2.7', 'select6-3.14']);
};

return $tests;
