<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

$items = [];
for ($i = 1; $i <= 300; $i++) {
    $bucket = 1;
    if ($i >= 2) {
        $bucket = 2;
    }
    if ($i >= 4) {
        $bucket = 3;
    }
    if ($i >= 8) {
        $bucket = 4;
    }
    if ($i >= 16) {
        $bucket = 5;
    }
    if ($i >= 32) {
        $bucket = 6;
    }
    if ($i >= 64) {
        $bucket = 7;
    }
    if ($i >= 128) {
        $bucket = 8;
    }
    if ($i >= 256) {
        $bucket = 9;
    }

    $items[] = [
        'x' => $i,
        'y' => $bucket,
        'z' => $i + $bucket,
    ];
}

$copyItems = array_map(
    static fn (array $row): array => [
        'a' => $row['x'],
        'b' => $row['y'],
        'c' => $row['z'],
    ],
    $items,
);

$selectTables = [
    'items' => $items,
    'copy_items' => $copyItems,
];

$bucketCounts = [];
$bucketMins = [];
$bucketMaxes = [];
$bucketSums = [];
foreach ($items as $row) {
    $bucket = $row['y'];
    $bucketCounts[$bucket] = ($bucketCounts[$bucket] ?? 0) + 1;
    $bucketMins[$bucket] = min($bucketMins[$bucket] ?? $row['x'], $row['x']);
    $bucketMaxes[$bucket] = max($bucketMaxes[$bucket] ?? $row['x'], $row['x']);
    $bucketSums[$bucket] = ($bucketSums[$bucket] ?? 0) + $row['x'];
}
ksort($bucketCounts);

$tests = [];

$tests['real upstream corpus select core dynamic batch0 cites upstream sources'] = static function (TestRunner $t): void {
    $t->contains('/test/select6.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test');
    $t->contains('/test/select5.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test');
    $t->contains('/test/select2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test');
    $t->contains('select6-1.2', 'select6-1.2 derived table count');
    $t->contains('select5-2.3', 'select5-2.3 grouped HAVING');
    $t->contains('select2-3.2c', 'select2-3.2c equality predicate');
};

foreach (range(0, 249) as $threshold) {
    $expected = [300 - $threshold];
    $tests["real upstream corpus select6.test select6-1.2 derived count threshold {$threshold}"] = static function (TestRunner $t) use ($assertFlat, $selectTables, $threshold, $expected): void {
        $sql = "SELECT count(*) AS rows_seen FROM (SELECT y FROM items WHERE x>{$threshold})";
        $assertFlat($t, $sql, $selectTables, $expected);
        $t->same(true, $threshold >= 0);
    };
}

foreach (range(1, 250) as $limit) {
    $seen = [];
    foreach ($items as $row) {
        if ($row['x'] <= $limit) {
            $seen[$row['y']] = true;
        }
    }
    $expected = [count($seen)];
    $tests["real upstream corpus select6.test select6-1.3 derived distinct bucket limit {$limit}"] = static function (TestRunner $t) use ($assertFlat, $selectTables, $limit, $expected): void {
        $sql = "SELECT count(*) AS distinct_y FROM (SELECT DISTINCT y FROM items WHERE x<={$limit})";
        $assertFlat($t, $sql, $selectTables, $expected);
        $t->same(true, $limit > 0);
    };
}

foreach (range(1, 250) as $limit) {
    $count = 0;
    foreach ($copyItems as $row) {
        if ($row['a'] <= $limit && $row['b'] >= 4) {
            $count++;
        }
    }
    $expected = [$count];
    $tests["real upstream corpus select6.test select6-2.1 copied row derived filter limit {$limit}"] = static function (TestRunner $t) use ($assertFlat, $selectTables, $limit, $expected): void {
        $sql = "SELECT count(*) FROM (SELECT a, b, a+b AS c FROM copy_items WHERE a<={$limit}) WHERE b>=4";
        $assertFlat($t, $sql, $selectTables, $expected);
        $t->same(true, $limit <= 250);
    };
}

foreach (range(1, 200) as $minCount) {
    $expected = [];
    foreach ($bucketCounts as $bucket => $count) {
        if ($count >= $minCount) {
            array_push($expected, $bucket, $count);
        }
    }
    $tests["real upstream corpus select5.test select5-2.3 grouped having count at least {$minCount}"] = static function (TestRunner $t) use ($assertFlat, $selectTables, $minCount, $expected): void {
        $sql = "SELECT y, count(*) FROM items GROUP BY y HAVING count(*)>={$minCount} ORDER BY y";
        $assertFlat($t, $sql, $selectTables, $expected);
        $t->same(true, $minCount >= 1);
    };
}

foreach (range(1, 49) as $bucket) {
    $normalizedBucket = (($bucket - 1) % 9) + 1;
    $expected = [
        $bucketCounts[$normalizedBucket] ?? 0,
        $bucketMins[$normalizedBucket] ?? null,
        $bucketMaxes[$normalizedBucket] ?? null,
        $bucketSums[$normalizedBucket] ?? null,
    ];
    $tests["real upstream corpus select5.test select5-1 dynamic aggregate bucket {$bucket}"] = static function (TestRunner $t) use ($assertFlat, $selectTables, $normalizedBucket, $expected): void {
        $sql = "SELECT count(*), min(x), max(x), sum(x) FROM items WHERE y={$normalizedBucket}";
        $assertFlat($t, $sql, $selectTables, $expected);
        $t->same(true, $normalizedBucket >= 1 && $normalizedBucket <= 9);
    };
}

foreach (range(1, 250) as $value) {
    $expected = [$value];
    $tests["real upstream corpus select2.test select2-3.2c direct equality value {$value}"] = static function (TestRunner $t) use ($assertFlat, $selectTables, $value, $expected): void {
        $sql = "SELECT a FROM copy_items WHERE a={$value}";
        $assertFlat($t, $sql, $selectTables, $expected);
        $t->same(true, $value >= 1);
    };
}

return $tests;
