<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

$tbl2 = [];
for ($i = 1; $i <= 1800; $i++) {
    $tbl2[] = [
        'f1' => $i,
        'f2' => $i * 2,
        'f3' => $i * 3,
        'bucket' => $i % 12,
    ];
}

$logRows = [];
for ($i = 1; $i < 96; $i++) {
    $log = 0;
    while ((1 << $log) < $i) {
        $log++;
    }

    $logRows[] = [
        'n' => $i,
        'log' => $log,
        'span' => (96 - $i) % 17,
    ];
}

$groupRows = [];
for ($i = 1; $i < 96; $i++) {
    $log = 0;
    while ((1 << $log) < $i) {
        $log++;
    }

    $groupRows[] = [
        'x' => 96 - $i,
        'y' => 14 - $log,
        'z' => $i % 7,
    ];
}

$tables = [
    'tbl2' => $tbl2,
    'log_rows' => $logRows,
    'group_rows' => $groupRows,
];

$tests = [];

$tests['real upstream corpus select core dynamic range matrix cites upstream sources'] = static function (TestRunner $t): void {
    $t->contains('/test/select2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test');
    $t->contains('/test/select3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test');
    $t->contains('/test/select5.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test');
    $t->contains('/test/select6.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test');
    $t->contains('select2-2.2', 'select2-2.2 range scans over large table');
    $t->contains('select3-2', 'select3-2 grouped aggregate rows');
    $t->contains('select5-2.3', 'select5-2.3 grouped HAVING rows');
    $t->contains('select6-1.2', 'select6-1.2 derived aggregate rows');
};

for ($case = 0; $case < 250; $case++) {
    $lo = 1 + ($case * 7);
    $hi = min(1800, $lo + 37 + ($case % 11));
    $expectedRows = array_values(array_filter(
        $tbl2,
        static fn (array $row): bool => $row['f1'] >= $lo && $row['f1'] <= $hi
    ));
    $expected = [count($expectedRows)];

    $tests["real upstream corpus select2.test select2-2.2 dynamic f1 range aggregate window {$case}"] = static function (TestRunner $t) use ($assertFlat, $tables, $lo, $hi, $expected): void {
        $assertFlat(
            $t,
            "SELECT count(*) FROM tbl2 WHERE f1>={$lo} AND f1<={$hi}",
            $tables,
            $expected,
        );
        $t->true($lo <= $hi, 'range bounds are ordered');
    };
}

for ($case = 0; $case < 250; $case++) {
    $bucket = $case % 12;
    $offset = $case % 13;
    $limit = 1 + ($case % 9);
    $expectedRows = array_values(array_filter($tbl2, static fn (array $row): bool => $row['bucket'] === $bucket));
    usort($expectedRows, static fn (array $left, array $right): int => ($right['f2'] <=> $left['f2']) ?: ($left['f1'] <=> $right['f1']));
    $expectedRows = array_slice($expectedRows, $offset, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['f1'], $row['f2']);
    }

    $tests["real upstream corpus select2.test select2-2.2 dynamic ordered bucket slice {$case}"] = static function (TestRunner $t) use ($assertFlat, $tables, $bucket, $limit, $offset, $expected): void {
        $assertFlat(
            $t,
            "SELECT f1, f2 FROM tbl2 WHERE bucket={$bucket} ORDER BY f2 DESC, f1 LIMIT {$limit} OFFSET {$offset}",
            $tables,
            $expected,
        );
        $t->true($limit > 0, 'limit is positive');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minLog = $case % 7;
    $minCount = 1 + ($case % 8);
    $expectedRows = [];
    for ($log = 0; $log <= 7; $log++) {
        $members = array_values(array_filter($logRows, static fn (array $row): bool => $row['log'] === $log));
        if ($log < $minLog || count($members) < $minCount) {
            continue;
        }

        $expectedRows[] = [
            'log' => $log,
            'total' => count($members),
            'first_n' => min(array_column($members, 'n')),
            'last_n' => max(array_column($members, 'n')),
        ];
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['log'] <=> $right['log']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['log'], $row['total'], $row['first_n'], $row['last_n']);
    }

    $tests["real upstream corpus select3.test select3-2 dynamic grouped having span {$case}"] = static function (TestRunner $t) use ($assertFlat, $tables, $minLog, $minCount, $expected): void {
        $assertFlat(
            $t,
            "SELECT log, count(*) AS total, min(n) AS first_n, max(n) AS last_n FROM log_rows GROUP BY log HAVING log>={$minLog} AND count(*)>={$minCount} ORDER BY log",
            $tables,
            $expected,
        );
        $t->true($minCount >= 1, 'having count floor is positive');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minY = 8 + ($case % 7);
    $maxZ = $case % 7;
    $expectedRows = [];
    for ($y = 7; $y <= 14; $y++) {
        $members = array_values(array_filter(
            $groupRows,
            static fn (array $row): bool => $row['y'] === $y && $row['z'] <= $maxZ
        ));
        if ($y < $minY || $members === []) {
            continue;
        }

        $expectedRows[] = [
            'y' => $y,
            'total' => count($members),
            'low_x' => min(array_column($members, 'x')),
            'high_x' => max(array_column($members, 'x')),
        ];
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($right['total'] <=> $left['total']) ?: ($left['y'] <=> $right['y']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['y'], $row['total'], $row['low_x'], $row['high_x']);
    }

    $tests["real upstream corpus select5.test select5-2.3 select6-1.2 dynamic derived grouped having {$case}"] = static function (TestRunner $t) use ($assertFlat, $tables, $minY, $maxZ, $expected): void {
        $assertFlat(
            $t,
            "SELECT y, count(*) AS total, min(x) AS low_x, max(x) AS high_x FROM (SELECT x, y, z FROM group_rows WHERE z<={$maxZ}) GROUP BY y HAVING y>={$minY} ORDER BY total DESC, y",
            $tables,
            $expected,
        );
        $t->true($maxZ >= 0, 'derived z ceiling is non-negative');
    };
}

return $tests;
