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
    $rows[] = ['x' => $x, 'y' => $y, 'z' => $x + $y];
}

$tables = [
    'items' => $rows,
    'copy_items' => array_map(
        static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['z']],
        $rows,
    ),
];

$execute = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);
$column = static fn (string $sql, string $field): array => array_column($execute($sql), $field);
$flatten = static fn (array $rows): array => array_merge(...array_map(static fn (array $row): array => array_values($row), $rows));

$countByY = [];
$maxByY = [];
$minByY = [];
$sumByY = [];
foreach ($rows as $row) {
    $y = $row['y'];
    $countByY[$y] = ($countByY[$y] ?? 0) + 1;
    $maxByY[$y] = max($maxByY[$y] ?? $row['x'], $row['x']);
    $minByY[$y] = min($minByY[$y] ?? $row['x'], $row['x']);
    $sumByY[$y] = ($sumByY[$y] ?? 0) + $row['x'];
}
ksort($countByY);

$tests = [];

foreach (range(0, 20) as $limit) {
    $expected = count(array_filter($rows, static fn (array $row): bool => $row['x'] > $limit));
    $tests["real upstream select6-1.2 derived count x greater than {$limit}"] = static function (TestRunner $t) use ($column, $limit, $expected): void {
        $sql = "SELECT count(*) AS rows_seen FROM (SELECT y FROM items WHERE x > {$limit})";
        $t->same([$expected], $column($sql, 'rows_seen'));
    };
}

foreach (range(1, 20) as $limit) {
    $expected = [];
    foreach ($rows as $row) {
        if ($row['x'] <= $limit) {
            $expected[$row['y']] = true;
        }
    }
    $expected = count($expected);
    $tests["real upstream select6-1.3 derived distinct y through {$limit}"] = static function (TestRunner $t) use ($column, $limit, $expected): void {
        $sql = "SELECT count(*) AS distinct_y FROM (SELECT DISTINCT y FROM items WHERE x <= {$limit})";
        $t->same([$expected], $column($sql, 'distinct_y'));
    };
}

foreach (range(1, 20) as $limit) {
    $expected = [];
    foreach ($rows as $row) {
        if ($row['x'] <= $limit) {
            $expected[$row['y']] = true;
        }
    }
    $expected = count($expected);
    $tests["real upstream select6-1.4 nested derived distinct star through {$limit}"] = static function (TestRunner $t) use ($column, $limit, $expected): void {
        $sql = "SELECT count(*) AS distinct_rows FROM (SELECT DISTINCT * FROM (SELECT y FROM items WHERE x <= {$limit}))";
        $t->same([$expected], $column($sql, 'distinct_rows'));
    };
}

foreach (range(1, 20) as $limit) {
    $expected = [];
    foreach ($rows as $row) {
        if ($row['x'] <= $limit) {
            $expected[$row['y']] = true;
        }
    }
    $expected = count($expected);
    $tests["real upstream select6-1.5 nested select star over distinct through {$limit}"] = static function (TestRunner $t) use ($column, $limit, $expected): void {
        $sql = "SELECT count(*) AS distinct_rows FROM (SELECT * FROM (SELECT DISTINCT y FROM items WHERE x <= {$limit}))";
        $t->same([$expected], $column($sql, 'distinct_rows'));
    };
}

foreach (range(1, 5) as $maxY) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($y <= $maxY) {
            array_push($expected, $count, $y, $maxByY[$y], $y);
        }
    }
    $tests["real upstream select6-1.6 joined grouped derived tables through y {$maxY}"] = static function (TestRunner $t) use ($flatten, $tables, $maxY, $expected): void {
        $sql = "
            SELECT *
            FROM (SELECT count(*) AS item_count, y FROM items GROUP BY y) AS a,
                 (SELECT max(x) AS max_x, y FROM items GROUP BY y) AS b
            WHERE a.y = b.y AND a.y <= {$maxY}
            ORDER BY a.y
        ";
        $t->same($expected, $flatten(SQLiteSelectSql::execute($sql, $tables)));
    };
}

foreach (range(1, 5) as $maxY) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($y <= $maxY) {
            array_push($expected, $y, $count, $maxByY[$y], $count);
        }
    }
    $tests["real upstream select6-1.7 qualified aliases from joined derived tables through y {$maxY}"] = static function (TestRunner $t) use ($flatten, $tables, $maxY, $expected): void {
        $sql = "
            SELECT a.y, a.item_count, max_x, item_count
            FROM (SELECT count(*) AS item_count, y FROM items GROUP BY y) AS a,
                 (SELECT max(x) AS max_x, y FROM items GROUP BY y) AS b
            WHERE a.y = b.y AND a.y <= {$maxY}
            ORDER BY a.y
        ";
        $t->same($expected, $flatten(SQLiteSelectSql::execute($sql, $tables)));
    };
}

foreach (range(1, 5) as $minY) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($y >= $minY) {
            array_push($expected, $y, $count, $maxByY[$y]);
        }
    }
    $tests["real upstream select6-1.8 renamed derived columns from y {$minY}"] = static function (TestRunner $t) use ($flatten, $tables, $minY, $expected): void {
        $sql = "
            SELECT q, p, r
            FROM (SELECT count(*) AS p, y AS q FROM items GROUP BY y) AS a,
                 (SELECT max(x) AS r, y AS s FROM items GROUP BY y) AS b
            WHERE q = s AND q >= {$minY}
            ORDER BY s
        ";
        $t->same($expected, $flatten(SQLiteSelectSql::execute($sql, $tables)));
    };
}

foreach (range(1, 5) as $minY) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($y >= $minY) {
            array_push($expected, $y, $count, $maxByY[$y], $minByY[$y] + $y);
        }
    }
    $tests["real upstream select6-1.9 derived arithmetic column from y {$minY}"] = static function (TestRunner $t) use ($flatten, $tables, $minY, $expected): void {
        $sql = "
            SELECT q, p, r, min_plus_y
            FROM (SELECT count(*) AS p, y AS q FROM items GROUP BY y) AS a,
                 (SELECT max(x) AS r, y AS s, min(x)+y AS min_plus_y FROM items GROUP BY y) AS b
            WHERE q = s AND q >= {$minY}
            ORDER BY s
        ";
        $t->same($expected, $flatten(SQLiteSelectSql::execute($sql, $tables)));
    };
}

foreach (range(1, 20) as $target) {
    $expected = array_values(array_filter($rows, static fn (array $row): bool => $row['x'] === $target));
    $tests["real upstream select6-3.1 nested subquery equality x {$target}"] = static function (TestRunner $t) use ($execute, $target, $expected): void {
        $sql = "SELECT * FROM (SELECT * FROM (SELECT x, y, z FROM items WHERE x = {$target}))";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(0, 19) as $limit) {
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['x'] + $row['y']], $rows),
        static fn (array $row): bool => $row['a'] > $limit && $row['b'] === 4,
    ));
    $tests["real upstream select6-4.1 derived where alias x greater than {$limit}"] = static function (TestRunner $t) use ($execute, $limit, $expected): void {
        $sql = "
            SELECT a, b, c
            FROM (SELECT x AS a, y AS b, x+y AS c FROM items WHERE y = 4)
            WHERE a > {$limit}
            ORDER BY a
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 20) as $limit) {
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['x'] + $row['y']], $rows),
        static fn (array $row): bool => $row['a'] < $limit && $row['b'] >= 3,
    ));
    $tests["real upstream select6-4.2 derived where composed aliases limit {$limit}"] = static function (TestRunner $t) use ($execute, $limit, $expected): void {
        $sql = "
            SELECT a, b, c
            FROM (SELECT x AS a, y AS b, x+y AS c FROM items WHERE y >= 3)
            WHERE a < {$limit}
            ORDER BY a
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 20) as $limit) {
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['a'], 'b' => $row['b'], 'c' => $row['a'] + $row['b']], $tables['copy_items']),
        static fn (array $row): bool => $row['a'] <= $limit && $row['b'] >= 2,
    ));
    $tests["real upstream select6-2.1 integer primary key copy derived through {$limit}"] = static function (TestRunner $t) use ($execute, $limit, $expected): void {
        $sql = "
            SELECT a, b, c
            FROM (SELECT a, b, a+b AS c FROM copy_items WHERE a <= {$limit})
            WHERE b >= 2
            ORDER BY a
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 5) as $y) {
    foreach (range(0, 10) as $offset) {
        $threshold = $minByY[$y] + $offset;
        $expected = count(array_filter($rows, static fn (array $row): bool => $row['y'] === $y && $row['x'] >= $threshold));
        $tests["real upstream select6 dynamic derived count y {$y} threshold {$threshold}"] = static function (TestRunner $t) use ($column, $y, $threshold, $expected): void {
            $sql = "SELECT count(*) AS rows_seen FROM (SELECT x FROM items WHERE y = {$y} AND x >= {$threshold})";
            $t->same([$expected], $column($sql, 'rows_seen'));
        };
    }
}

foreach (range(1, 5) as $y) {
    foreach (range(1, 8) as $take) {
        $expected = [];
        foreach ($rows as $row) {
            if ($row['y'] === $y && count($expected) < $take) {
                $expected[] = ['x' => $row['x'], 'y' => $row['y']];
            }
        }
        $tests["real upstream select6 dynamic limited derived y {$y} take {$take}"] = static function (TestRunner $t) use ($execute, $y, $take, $expected): void {
            $sql = "SELECT * FROM (SELECT x, y FROM items WHERE y = {$y} ORDER BY x LIMIT {$take})";
            $t->same($expected, $execute($sql));
        };
    }
}

foreach (range(1, 5) as $y) {
    foreach (range(0, 7) as $skip) {
        $expectedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['y'] === $y));
        $expectedRows = array_slice($expectedRows, $skip, 2);
        $expected = array_map(static fn (array $row): int => $row['x'], $expectedRows);
        $tests["real upstream select6 dynamic derived limit offset y {$y} skip {$skip}"] = static function (TestRunner $t) use ($column, $y, $skip, $expected): void {
            $sql = "SELECT x FROM (SELECT x, y FROM items WHERE y = {$y} ORDER BY x LIMIT 2 OFFSET {$skip})";
            $t->same($expected, $column($sql, 'x'));
        };
    }
}

foreach (range(1, 20) as $limit) {
    $expected = [];
    foreach ($rows as $row) {
        if ($row['x'] <= $limit) {
            $expected[$row['y']] = true;
        }
    }
    $expected = array_keys($expected);
    sort($expected);
    $tests["real upstream select6 dynamic distinct derived values through {$limit}"] = static function (TestRunner $t) use ($column, $limit, $expected): void {
        $sql = "SELECT y FROM (SELECT DISTINCT y FROM items WHERE x <= {$limit}) ORDER BY y";
        $t->same($expected, $column($sql, 'y'));
    };
}

foreach (range(1, 5) as $maxY) {
    foreach (range(1, 5) as $minCount) {
        $expected = [];
        foreach ($countByY as $y => $count) {
            if ($y <= $maxY && $count >= $minCount) {
                $expected[] = ['q' => $y, 'p' => $count];
            }
        }
        $tests["real upstream select6 dynamic grouped derived having y {$maxY} count {$minCount}"] = static function (TestRunner $t) use ($execute, $maxY, $minCount, $expected): void {
            $sql = "
                SELECT q, p
                FROM (SELECT count(*) AS p, y AS q FROM items GROUP BY y HAVING count(*) >= {$minCount})
                WHERE q <= {$maxY}
                ORDER BY q
            ";
            $t->same($expected, $execute($sql));
        };
    }
}

foreach (range(1, 5) as $maxY) {
    foreach (range(0, 4) as $offset) {
        $expected = [];
        foreach ($countByY as $y => $count) {
            if ($y <= $maxY) {
                $expected[] = ['q' => $y, 'sum_x' => $sumByY[$y]];
            }
        }
        $expected = array_slice($expected, $offset, 3);
        $tests["real upstream select6 dynamic grouped derived sum y {$maxY} offset {$offset}"] = static function (TestRunner $t) use ($execute, $maxY, $offset, $expected): void {
            $sql = "
                SELECT q, sum_x
                FROM (SELECT sum(x) AS sum_x, y AS q FROM items GROUP BY y)
                WHERE q <= {$maxY}
                ORDER BY q
                LIMIT 3 OFFSET {$offset}
            ";
            $t->same($expected, $execute($sql));
        };
    }
}

foreach (range(1, 20) as $limit) {
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['z']], $rows),
        static fn (array $row): bool => $row['a'] <= $limit && $row['c'] > 10,
    ));
    $tests["real upstream select6 dynamic derived expression filter through {$limit}"] = static function (TestRunner $t) use ($execute, $limit, $expected): void {
        $sql = "
            SELECT a, b, c
            FROM (SELECT x AS a, y AS b, x+y AS c FROM items WHERE x <= {$limit})
            WHERE c > 10
            ORDER BY a
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 20) as $limit) {
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['z']], $rows),
        static fn (array $row): bool => $row['a'] <= $limit && $row['c'] <= 10,
    ));
    $tests["real upstream select6 dynamic derived expression inverse filter through {$limit}"] = static function (TestRunner $t) use ($execute, $limit, $expected): void {
        $sql = "
            SELECT a, b, c
            FROM (SELECT x AS a, y AS b, x+y AS c FROM items WHERE x <= {$limit})
            WHERE c <= 10
            ORDER BY a
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 5) as $y) {
    foreach (range(1, 5) as $maxGroup) {
        $expected = [];
        foreach ($countByY as $groupY => $count) {
            if ($groupY <= $maxGroup && $groupY !== $y) {
                $expected[] = ['q' => $groupY, 'p' => $count];
            }
        }
        $tests["real upstream select6 dynamic derived inequality y {$y} max {$maxGroup}"] = static function (TestRunner $t) use ($execute, $y, $maxGroup, $expected): void {
            $sql = "
                SELECT q, p
                FROM (SELECT count(*) AS p, y AS q FROM items GROUP BY y)
                WHERE q != {$y} AND q <= {$maxGroup}
                ORDER BY q
            ";
            $t->same($expected, $execute($sql));
        };
    }
}

foreach (range(1, 15) as $low) {
    $high = $low + 5;
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['x' => $row['x'], 'y' => $row['y']], $rows),
        static fn (array $row): bool => $row['x'] >= $low && $row['x'] <= $high,
    ));
    $tests["real upstream select6 dynamic nested range {$low} {$high}"] = static function (TestRunner $t) use ($execute, $low, $high, $expected): void {
        $sql = "
            SELECT x, y
            FROM (SELECT * FROM (SELECT x, y FROM items WHERE x >= {$low}))
            WHERE x <= {$high}
            ORDER BY x
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 10) as $threshold) {
    $expected = [];
    foreach ($countByY as $y => $count) {
        if ($maxByY[$y] > $threshold) {
            $expected[] = ['q' => $y, 'p' => $count, 'r' => $maxByY[$y]];
        }
    }
    $tests["real upstream select6 dynamic joined grouped max threshold {$threshold}"] = static function (TestRunner $t) use ($execute, $threshold, $expected): void {
        $sql = "
            SELECT q, p, r
            FROM (SELECT count(*) AS p, y AS q FROM items GROUP BY y) AS a,
                 (SELECT max(x) AS r, y AS s FROM items GROUP BY y) AS b
            WHERE q = s AND r > {$threshold}
            ORDER BY q
        ";
        $t->same($expected, $execute($sql));
    };
}

foreach (range(1, 5) as $maxY) {
    foreach (range(1, 3) as $take) {
        $expected = [];
        foreach ($countByY as $y => $count) {
            if ($y <= $maxY) {
                $expected[] = ['q' => $y, 'p' => $count, 'r' => $maxByY[$y], 'm' => $minByY[$y] + $y];
            }
        }
        $expected = array_slice($expected, 0, $take);
        $tests["real upstream select6 dynamic joined grouped limit y {$maxY} take {$take}"] = static function (TestRunner $t) use ($execute, $maxY, $take, $expected): void {
            $sql = "
                SELECT q, p, r, m
                FROM (SELECT count(*) AS p, y AS q FROM items GROUP BY y) AS a,
                     (SELECT max(x) AS r, y AS s, min(x)+y AS m FROM items GROUP BY y) AS b
                WHERE q = s AND q <= {$maxY}
                ORDER BY q
                LIMIT {$take}
            ";
            $t->same($expected, $execute($sql));
        };
    }
}

foreach (range(1, 20) as $limit) {
    foreach (range(1, 2) as $take) {
        $expectedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['x'] <= $limit));
        usort($expectedRows, static fn (array $a, array $b): int => $b['x'] <=> $a['x']);
        $expectedRows = array_slice($expectedRows, 0, $take);
        $expected = array_map(static fn (array $row): int => $row['x'], $expectedRows);
        $tests["real upstream select6 dynamic nested order desc limit {$limit} take {$take}"] = static function (TestRunner $t) use ($column, $limit, $take, $expected): void {
            $sql = "
                SELECT x
                FROM (SELECT x, y FROM items WHERE x <= {$limit} ORDER BY x DESC LIMIT {$take})
                ORDER BY x DESC
            ";
            $t->same($expected, $column($sql, 'x'));
        };
    }
}

return $tests;
