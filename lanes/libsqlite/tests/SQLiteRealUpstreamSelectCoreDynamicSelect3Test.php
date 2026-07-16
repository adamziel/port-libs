<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select3DynamicTables = static function (): array {
    $rows = [];
    for ($i = 1; $i < 128; $i++) {
        for ($j = 0; (1 << $j) < $i; $j++) {
        }
        $rows[] = [
            'n' => $i,
            'log' => $j,
            'bucket' => $i % 7,
        ];
    }

    $pairs = [];
    for ($a = 1; $a <= 24; $a++) {
        for ($b = 1; $b <= 12; $b++) {
            $pairs[] = [
                'a' => $a,
                'b' => $b,
                'c' => $a + $b,
            ];
        }
    }

    return [
        't1' => $rows,
        't2' => $pairs,
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select3DynamicFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$select3DynamicAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($select3DynamicFlat): void {
    $actual = $select3DynamicFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests = [];
$select3Tables = $select3DynamicTables();
$select3Rows = $select3Tables['t1'];
$select3Pairs = $select3Tables['t2'];

$tests['real upstream select3.test dynamic batch cites upstream scenarios'] = static function (TestRunner $t): void {
    $t->contains('/test/select3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test');
    $t->contains('select3-1.2', 'select3-1.2 aggregate min/max/sum/avg');
    $t->contains('select3-2.6', 'select3-2.6 expression GROUP BY');
    $t->contains('select3-3.2', 'select3-3.2 aggregate HAVING without GROUP BY');
    $t->contains('select3-6.1', 'select3-6.1 grouped min ORDER BY');
};

for ($case = 0; $case < 250; $case++) {
    $minN = 1 + ($case % 63);
    $maxN = $minN + 31 + ($case % 17);
    $filtered = array_values(array_filter(
        $select3Rows,
        static fn (array $row): bool => $row['n'] >= $minN && $row['n'] <= $maxN,
    ));
    $logs = array_column($filtered, 'log');
    $ns = array_column($filtered, 'n');
    $expected = [min($ns)];

    $tests["real upstream select3.test select3-1.2 aggregate window {$case}"] = static function (TestRunner $t) use ($select3DynamicAssert, $select3Tables, $minN, $maxN, $expected): void {
        $sql = "SELECT min(n) FROM t1 WHERE n>={$minN} AND n<={$maxN}";
        $select3DynamicAssert($t, $sql, $select3Tables, $expected, 'select3-1.2');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minN = 1 + ($case % 48);
    $maxLog = 2 + ($case % 6);
    $groups = [];
    foreach ($select3Rows as $row) {
        if ($row['n'] < $minN || $row['log'] > $maxLog) {
            continue;
        }
        $key = ($row['log'] * 2) + 1;
        $groups[$key] = ($groups[$key] ?? 0) + 1;
    }
    ksort($groups);
    $expected = [];
    foreach ($groups as $key => $count) {
        array_push($expected, $key, $count);
    }

    $tests["real upstream select3.test select3-2.6 expression group key {$case}"] = static function (TestRunner $t) use ($select3DynamicAssert, $select3Tables, $minN, $maxLog, $expected): void {
        $sql = "SELECT log*2+1 AS x, count(*) FROM t1 WHERE n>={$minN} AND log<={$maxLog} GROUP BY x ORDER BY x";
        $select3DynamicAssert($t, $sql, $select3Tables, $expected, 'select3-2.6');
    };
}

for ($case = 0; $case < 250; $case++) {
    $bucket = $case % 7;
    $minCount = 8 + ($case % 19);
    $filtered = array_values(array_filter(
        $select3Rows,
        static fn (array $row): bool => $row['bucket'] === $bucket,
    ));
    $expected = count($filtered) >= $minCount ? [count($filtered)] : [];

    $tests["real upstream select3.test select3-3.2 aggregate having count {$case}"] = static function (TestRunner $t) use ($select3DynamicAssert, $select3Tables, $bucket, $minCount, $expected): void {
        $sql = "SELECT count(*) FROM t1 WHERE bucket={$bucket} HAVING count(*)>={$minCount}";
        $select3DynamicAssert($t, $sql, $select3Tables, $expected, 'select3-3.2');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minB = 1 + ($case % 8);
    $maxA = 6 + ($case % 19);
    $groups = [];
    foreach ($select3Pairs as $row) {
        if ($row['b'] < $minB || $row['a'] > $maxA) {
            continue;
        }
        $a = $row['a'];
        $groups[$a] = ($groups[$a] ?? 0) + $row['b'];
    }
    ksort($groups);
    $expected = [];
    foreach ($groups as $a => $sum) {
        array_push($expected, $a, $sum);
    }

    $tests["real upstream select3.test select3-7.1 grouped sum filtered {$case}"] = static function (TestRunner $t) use ($select3DynamicAssert, $select3Tables, $minB, $maxA, $expected): void {
        $sql = "SELECT a, sum(b) FROM t2 WHERE b>={$minB} AND a<={$maxA} GROUP BY a ORDER BY a";
        $select3DynamicAssert($t, $sql, $select3Tables, $expected, 'select3-7.1');
    };
}

return $tests;
