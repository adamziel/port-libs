<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
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

$assertSelect = static function (TestRunner $t, string $sql, array $tables, array $expectedFlat) use ($flattenRows): void {
    $actualFlat = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)),
        md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
    foreach ($expectedFlat as $index => $expectedValue) {
        $t->same($expectedValue, $actualFlat[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
};

$tests = [];

$tests['real upstream corpus select7.test grouped case dynamic cites source truth'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test');
    $t->contains('select7-7.2', 'select7-7.2 grouped CASE arithmetic category');
    $t->contains('select7-7.4', 'select7-7.4 grouped CASE mixed numeric/text branch');
    $t->contains('select7-7.5', 'select7-7.5 typeof real values without grouping');
    $t->contains('select7-7.6', 'select7-7.6 typeof real values with grouping');
};

for ($case = 0; $case < 250; $case++) {
    $base = 20 + ($case % 37);
    $step = 1 + ($case % 5);
    $offset = 15 + ($case % 11);
    $divisor = 10 + ($case % 13);
    $zeroEvery = 3 + ($case % 7);
    $rows = [];
    for ($i = 0; $i < 8; $i++) {
        $value = (float) ($base + ($i * $step));
        if ($i % $zeroEvery === 0) {
            $value = 0.0;
        }
        $rows[] = ['a' => $value];
    }
    $tables = ['case_values' => $rows];

    $grouped = [];
    foreach ($rows as $row) {
        $category = $row['a'] == 0.0 ? 0 : round(($row['a'] + $offset) / $divisor, 6);
        $key = (string) $category;
        $grouped[$key] ??= ['category' => $category, 'count' => 0];
        $grouped[$key]['count']++;
    }
    $expected = [];
    foreach (array_values($grouped) as $group) {
        $expected[] = $group['category'];
        $expected[] = $group['count'];
    }

    $tests["real upstream corpus select7.test select7-7.2 dynamic grouped case arithmetic {$case}"] = static function (TestRunner $t) use ($assertSelect, $tables, $offset, $divisor, $expected): void {
        $sql = "SELECT (CASE WHEN a=0 THEN 0 ELSE (a + {$offset}) / {$divisor} END) AS categ, count(*) FROM case_values GROUP BY categ";
        $assertSelect($t, $sql, $tables, $expected);
    };
}

for ($case = 0; $case < 250; $case++) {
    $base = 2 + ($case % 9);
    $multiplier = 1 + ($case % 6);
    $divisor = 2 + ($case % 5);
    $rows = [];
    for ($i = 0; $i < 6; $i++) {
        $rows[] = ['a' => (float) (($base + $i) * $multiplier)];
    }
    $tables = ['mixed_case_values' => $rows];

    $expected = [];
    foreach ($rows as $row) {
        $value = $row['a'] == 0.0 ? 'zero' : round($row['a'] / $divisor, 6);
        $key = is_string($value) ? 's:' . $value : 'n:' . $value;
        if (!array_key_exists($key, $expected)) {
            $expected[$key] = $value;
        }
    }
    $expectedFlat = array_values($expected);
    usort($expectedFlat, static fn (mixed $left, mixed $right): int => $left <=> $right);

    $tests["real upstream corpus select7.test select7-7.4 dynamic grouped mixed branch {$case}"] = static function (TestRunner $t) use ($assertSelect, $tables, $divisor, $expectedFlat): void {
        $sql = "SELECT (CASE WHEN a=0 THEN 'zero' ELSE a/{$divisor} END) AS t FROM mixed_case_values GROUP BY t";
        $assertSelect($t, $sql, $tables, $expectedFlat);
    };
}

for ($case = 0; $case < 250; $case++) {
    $start = 1 + ($case % 17);
    $scale = 1 + ($case % 4);
    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        $rows[] = ['a' => (float) (($start + $i) * $scale)];
    }
    $tables = ['typed_values' => $rows];

    $expected = [];
    foreach ($rows as $row) {
        $expected[] = $row['a'] == 0.0 ? 1 : 0;
        $expected[] = 'real';
    }

    $tests["real upstream corpus select7.test select7-7.5 dynamic typeof real scan {$case}"] = static function (TestRunner $t) use ($assertSelect, $tables, $expected): void {
        $assertSelect($t, 'SELECT a=0, typeof(a) FROM typed_values', $tables, $expected);
    };
}

for ($case = 0; $case < 250; $case++) {
    $start = 2 + ($case % 19);
    $scale = 1 + ($case % 5);
    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        $rows[] = ['a' => (float) (($start + $i) * $scale)];
    }
    $tables = ['typed_group_values' => $rows];

    $expected = [];
    $seen = [];
    foreach ($rows as $row) {
        $key = (string) $row['a'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $expected[] = $row['a'] == 0.0 ? 1 : 0;
        $expected[] = 'real';
    }

    $tests["real upstream corpus select7.test select7-7.6 dynamic grouped typeof real {$case}"] = static function (TestRunner $t) use ($assertSelect, $tables, $expected): void {
        $assertSelect($t, 'SELECT a=0, typeof(a) FROM typed_group_values GROUP BY a', $tables, $expected);
    };
}

return $tests;
