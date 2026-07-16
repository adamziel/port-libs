<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test
 * - select4-2.5: an ORDER BY scalar subquery may reference the visible result
 *   alias from a constant SELECT.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select4ScalarOrderFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_order_')) {
                continue;
            }
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 */
$assertSelect4ScalarOrder = static function (TestRunner $t, string $sql, array $expected, string $label) use ($select4ScalarOrderFlat): void {
    $actualRows = SQLiteSelectSql::execute($sql, []);
    $actual = $select4ScalarOrderFlat($actualRows);

    $t->same($expected, $actual, $label . ' flat rows');
    $t->same(count($expected), count($actual), $label . ' value count');
    $t->same($expected[0] ?? null, $actual[0] ?? null, $label . ' first value');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

$tests = [];

$tests['real upstream select4.test select4-2.5 cites scalar subquery order source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';

    $t->true(is_file($source), 'hydrated upstream select4.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream select4.test is readable');
    $t->contains('do_execsql_test select4-2.5', $text);
    $t->contains('SELECT 123 AS x ORDER BY (SELECT x ORDER BY 1)', $text);
};

$tests['real upstream select4.test select4-2.5 canonical scalar subquery order alias'] =
    static function (TestRunner $t) use ($assertSelect4ScalarOrder): void {
        $assertSelect4ScalarOrder(
            $t,
            'SELECT 123 AS x ORDER BY (SELECT x ORDER BY 1)',
            [123],
            'select4-2.5 canonical',
        );
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $value = 1000 + $seed;
    $offset = $seed % 17;
    $multiplier = 2 + ($seed % 5);
    $alias = 'alias_' . $seed;
    $secondaryAlias = 'rank_' . $seed;

    $tests[sprintf('real upstream select4.test select4-2.5 dynamic alias scalar order value %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect4ScalarOrder, $value, $alias, $seed): void {
            $assertSelect4ScalarOrder(
                $t,
                "SELECT {$value} AS {$alias} ORDER BY (SELECT {$alias} ORDER BY 1)",
                [$value],
                'select4-2.5 direct alias seed ' . $seed,
            );
        };

    $tests[sprintf('real upstream select4.test select4-2.5 dynamic expression alias scalar order %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect4ScalarOrder, $value, $offset, $alias, $seed): void {
            $expected = $value + $offset;
            $assertSelect4ScalarOrder(
                $t,
                "SELECT {$value}+{$offset} AS {$alias} ORDER BY (SELECT {$alias} ORDER BY 1)",
                [$expected],
                'select4-2.5 expression alias seed ' . $seed,
            );
        };

    $tests[sprintf('real upstream select4.test select4-2.5 dynamic multi alias scalar order %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect4ScalarOrder, $value, $offset, $multiplier, $alias, $secondaryAlias, $seed): void {
            $first = $value + $offset;
            $second = $first * $multiplier;
            $assertSelect4ScalarOrder(
                $t,
                "SELECT {$value}+{$offset} AS {$alias}, ({$value}+{$offset})*{$multiplier} AS {$secondaryAlias} ORDER BY (SELECT {$secondaryAlias} ORDER BY 1), (SELECT {$alias} ORDER BY 1)",
                [$first, $second],
                'select4-2.5 multi alias seed ' . $seed,
            );
        };
}

return $tests;
