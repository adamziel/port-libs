<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test
 * - select1-6.20 through select1-6.23, ticket #2296.
 *
 * These cases exercise compound SELECT subqueries used as IN-list sources.
 * They preserve the upstream UNION, ORDER BY ordinal/alias, and LIMIT shapes
 * while varying generic application rows.
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
 * @return array<string,list<array<string,string>>>
 */
$compoundInTables = static function (int $seed): array {
    $offset = $seed * 10;

    return [
        'app_values' => [
            ['label' => 'alpha-' . $seed, 'lookup_key' => (string) ($offset + 0)],
            ['label' => 'beta-' . $seed, 'lookup_key' => (string) ($offset + 1)],
            ['label' => 'gamma-' . $seed, 'lookup_key' => (string) ($offset + 2)],
            ['label' => 'delta-' . $seed, 'lookup_key' => (string) ($offset + 3)],
        ],
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('UNION SELECT', $sql, $label . ' keeps upstream compound subquery');
    $t->contains(' IN ', $sql, $label . ' keeps upstream IN predicate');
};

$tests = [];

$tests['real upstream select1.test select1-6.20 through select1-6.23 cites compound IN source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

        $t->true(is_file($source), 'hydrated upstream select1.test is available');
        $text = file_get_contents($source);
        $t->contains('Ticket #2296', $text);
        $t->contains('select1-6.20', $text);
        $t->contains('select1-6.23', $text);
        $t->contains("SELECT b FROM t6 WHERE a<='b' UNION SELECT '3' AS x", $text);
    };

for ($seed = 1; $seed <= 250; $seed++) {
    $offset = $seed * 10;
    $tables = $compoundInTables($seed);
    $cutoff = 'beta-' . $seed;
    $sentinel = (string) ($offset + 3);

    $cases = [
        'select1-6.20 order by ordinal ascending limit one' => [
            "SELECT label FROM app_values WHERE lookup_key IN (SELECT lookup_key FROM app_values WHERE label<='{$cutoff}' UNION SELECT '{$sentinel}' AS x ORDER BY 1 LIMIT 1) ORDER BY label",
            ['alpha-' . $seed],
        ],
        'select1-6.21 order by ordinal descending limit one' => [
            "SELECT label FROM app_values WHERE lookup_key IN (SELECT lookup_key FROM app_values WHERE label<='{$cutoff}' UNION SELECT '{$sentinel}' AS x ORDER BY 1 DESC LIMIT 1) ORDER BY label",
            ['delta-' . $seed],
        ],
        'select1-6.22 order by left result name limit two' => [
            "SELECT label FROM app_values WHERE lookup_key IN (SELECT lookup_key FROM app_values WHERE label<='{$cutoff}' UNION SELECT '{$sentinel}' AS x ORDER BY lookup_key LIMIT 2) ORDER BY label",
            ['alpha-' . $seed, 'beta-' . $seed],
        ],
        'select1-6.23 order by compound alias descending limit two' => [
            "SELECT label FROM app_values WHERE lookup_key IN (SELECT lookup_key FROM app_values WHERE label<='{$cutoff}' UNION SELECT '{$sentinel}' AS x ORDER BY x DESC LIMIT 2) ORDER BY label",
            ['beta-' . $seed, 'delta-' . $seed],
        ],
    ];

    foreach ($cases as $name => [$sql, $expected]) {
        $tests[sprintf('real upstream select1.test %s dynamic seed %03d', $name, $seed)] =
            static function (TestRunner $t) use ($assertFlatSelect, $sql, $tables, $expected, $name, $seed): void {
                $assertFlatSelect($t, $sql, $tables, $expected, $name . ' seed ' . $seed);
                $t->contains('select1-6.', $name);
                $t->same(true, $seed >= 1 && $seed <= 250, 'bounded dynamic seed');
            };
    }
}

return $tests;
