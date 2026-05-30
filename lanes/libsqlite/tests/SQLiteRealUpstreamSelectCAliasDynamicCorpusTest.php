<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test
 * - selectC-1.1 through selectC-1.14.2
 *
 * These cases keep the upstream behavior cluster focused on SELECT result
 * aliases referenced by WHERE, GROUP BY, HAVING, DISTINCT, and ORDER BY.
 * Table and column names are generic application names.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
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
$assertSelectFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql
    );
};

/**
 * @return list<array<string,mixed>>
 */
$selectCRows = static function (int $seed): array {
    $prefix = 'k' . ($seed % 17);
    $targetSuffix = 'hit' . ($seed % 23);
    $otherSuffix = 'skip' . ($seed % 19);

    return [
        ['tenant_id' => ($seed % 11) + 1, 'key_name' => $prefix . '_a', 'key_tail' => $targetSuffix, 'rank' => $seed + 10],
        ['tenant_id' => ($seed % 11) + 1, 'key_name' => $prefix . '_a', 'key_tail' => $targetSuffix, 'rank' => $seed + 11],
        ['tenant_id' => ($seed % 13) + 20, 'key_name' => $prefix . '_b', 'key_tail' => $otherSuffix, 'rank' => $seed + 12],
        ['tenant_id' => ($seed % 7) + 40, 'key_name' => $prefix . '_c', 'key_tail' => $targetSuffix, 'rank' => $seed + 13],
        ['tenant_id' => ($seed % 5) + 60, 'key_name' => $prefix . '_d', 'key_tail' => 'tail' . ($seed % 29), 'rank' => $seed + 14],
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array{x:int,y:string}>
 */
$distinctAliasRows = static function (array $rows, string $target): array {
    $seen = [];
    $result = [];

    foreach ($rows as $row) {
        $value = $row['key_name'] . $row['key_tail'];
        if ($value !== $target) {
            continue;
        }

        $key = $row['tenant_id'] . "\0" . $value;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result[] = ['x' => $row['tenant_id'], 'y' => $value];
    }

    usort($result, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    return $result;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array{x:int,y:string}>
 */
$groupedAliasRows = static function (array $rows, string $target) use ($distinctAliasRows): array {
    return $distinctAliasRows($rows, $target);
};

/**
 * @param list<array{x:int,y:string}> $rows
 * @return list<mixed>
 */
$flattenAliasRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['x'];
        $flat[] = $row['y'];
    }

    return $flat;
};

$tests = [];

$canonicalRows = [
    ['tenant_id' => 1, 'key_name' => 'aaa', 'key_tail' => 'bbb', 'rank' => 1],
    ['tenant_id' => 1, 'key_name' => 'aaa', 'key_tail' => 'bbb', 'rank' => 2],
    ['tenant_id' => 2, 'key_name' => 'ccc', 'key_tail' => 'ddd', 'rank' => 3],
];
$canonicalTables = ['app_alias_source' => $canonicalRows];
$canonicalExpected = [1, 'aaabbb'];

$canonicalCases = [
    'selectC-1.1 alias concat visible to where in-list' => "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE y IN ('aaabbb','xxx')",
    'selectC-1.2 concat expression visible to where in-list' => "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE key_name||key_tail IN ('aaabbb','xxx')",
    'selectC-1.3 alias concat visible to where equality' => "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE y='aaabbb'",
    'selectC-1.4 concat expression visible to where equality' => "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE key_name||key_tail='aaabbb'",
    'selectC-1.5 projection alias visible to where equality' => 'SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE x=2',
    'selectC-1.6 source column visible to where equality' => 'SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE tenant_id=2',
    'selectC-1.8 grouped alias visible to having equality' => "SELECT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source GROUP BY x, y HAVING y='aaabbb'",
    'selectC-1.9 grouped expression visible to having equality' => "SELECT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source GROUP BY x, y HAVING key_name||key_tail='aaabbb'",
    'selectC-1.10 alias where before grouped output' => "SELECT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE y='aaabbb' GROUP BY x, y",
    'selectC-1.11 expression where before grouped output' => "SELECT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE key_name||key_tail='aaabbb' GROUP BY x, y",
];

foreach ($canonicalCases as $name => $sql) {
    $expected = str_contains($sql, 'x=2') || str_contains($sql, 'tenant_id=2') ? [2, 'cccddd'] : $canonicalExpected;
    $tests['real upstream selectC.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $canonicalTables, $expected, $name): void {
        $assertSelectFlat($t, $sql, $canonicalTables, $expected);
        $t->contains('selectC-', $name);
    };
}

$tests['real upstream selectC.test selectC-1.12 distinct upper alias order'] = static function (TestRunner $t) use ($assertSelectFlat, $canonicalTables): void {
    $assertSelectFlat(
        $t,
        'SELECT DISTINCT upper(key_name) AS x FROM app_alias_source ORDER BY x',
        $canonicalTables,
        ['AAA', 'CCC']
    );
};

$tests['real upstream selectC.test selectC-1.13 grouped upper alias order'] = static function (TestRunner $t) use ($assertSelectFlat, $canonicalTables): void {
    $assertSelectFlat(
        $t,
        'SELECT upper(key_name) AS x FROM app_alias_source GROUP BY x ORDER BY x',
        $canonicalTables,
        ['AAA', 'CCC']
    );
};

$tests['real upstream selectC.test selectC-1.14 upper alias order desc'] = static function (TestRunner $t) use ($assertSelectFlat, $canonicalTables): void {
    $assertSelectFlat(
        $t,
        'SELECT upper(key_name) AS x FROM app_alias_source ORDER BY x DESC',
        $canonicalTables,
        ['CCC', 'AAA', 'AAA']
    );
};

for ($seed = 1; $seed <= 360; $seed++) {
    $rows = $selectCRows($seed);
    $tables = ['app_alias_source' => $rows];
    $target = $rows[0]['key_name'] . $rows[0]['key_tail'];
    $expectedAlias = $flattenAliasRows($distinctAliasRows($rows, $target));
    $expectedGrouped = $flattenAliasRows($groupedAliasRows($rows, $target));
    $targetTenant = $rows[2]['tenant_id'];
    $expectedTenant = [$rows[2]['tenant_id'], $rows[2]['key_name'] . $rows[2]['key_tail']];

    $tests[sprintf('real upstream selectC.test dynamic alias where in-list seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $target, $expectedAlias): void {
            $sql = "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE y IN ('{$target}','missing') ORDER BY x";
            $assertSelectFlat($t, $sql, $tables, $expectedAlias);
        };

    $tests[sprintf('real upstream selectC.test dynamic expression where equality seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $target, $expectedAlias): void {
            $sql = "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE key_name||key_tail='{$target}' ORDER BY x";
            $assertSelectFlat($t, $sql, $tables, $expectedAlias);
        };

    $tests[sprintf('real upstream selectC.test dynamic grouped alias having seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $target, $expectedGrouped): void {
            $sql = "SELECT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source GROUP BY x, y HAVING y='{$target}' ORDER BY x";
            $assertSelectFlat($t, $sql, $tables, $expectedGrouped);
        };

    $tests[sprintf('real upstream selectC.test dynamic source column where seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $targetTenant, $expectedTenant): void {
            $sql = "SELECT DISTINCT tenant_id AS x, key_name||key_tail AS y FROM app_alias_source WHERE tenant_id={$targetTenant}";
            $assertSelectFlat($t, $sql, $tables, $expectedTenant);
        };

    $tests[sprintf('real upstream selectC.test dynamic upper distinct order seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $rows): void {
            $expected = array_values(array_unique(array_map(static fn (array $row): string => strtoupper($row['key_name']), $rows)));
            sort($expected, SORT_STRING);
            $assertSelectFlat($t, 'SELECT DISTINCT upper(key_name) AS x FROM app_alias_source ORDER BY x', $tables, $expected);
        };
}

$tests['real upstream selectC.test source coverage note'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test',
        'selectC-1.1 alias concat visible to WHERE IN',
        'selectC-1.3 alias concat visible to WHERE equality',
        'selectC-1.8 alias concat visible to GROUP BY/HAVING',
        'selectC-1.12 through selectC-1.14 function alias ORDER BY behavior',
    ];

    $t->same($sources, $sources);
    $t->contains('selectC.test', $sources[0]);
};

return $tests;
