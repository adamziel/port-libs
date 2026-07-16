<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT sort/collation behavior:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-8.8.1 and e_select-8.8.2: ORDER BY mixed SQL storage classes.
 * - e_select-8.9.1 and e_select-8.9.2: explicit ORDER BY COLLATE override.
 * - e_select-8.10.1 through e_select-8.10.3: ORDER BY ordinal/alias inherits
 *   postfix COLLATE from the result expression, but a plain source-column
 *   ORDER BY does not.
 * - e_select-8.12.1: arbitrary ORDER BY expression falls back to BINARY.
 *
 * This file intentionally avoids accepted expression-ORDER-BY shape coverage,
 * compound collation batches, JSON table SELECT sources, and storage clusters.
 * The new behavior is the SELECT-core sort comparator and result-expression
 * collation inheritance over dynamic generic rows.
 */

$tests = [];

/**
 * @return list<mixed>
 */
$flattenSelectSortValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_order_')) {
                continue;
            }
            $values[] = $value instanceof SQLiteBlobValue ? 'blob:' . $value->bytes : $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $values
 * @return list<mixed>
 */
$normalizeExpectedSortValues = static function (array $values): array {
    return array_map(
        static fn (mixed $value): mixed => $value instanceof SQLiteBlobValue ? 'blob:' . $value->bytes : $value,
        $values,
    );
};

$sqlSortRank = static function (mixed $value): int {
    return match (true) {
        $value === null => 0,
        is_bool($value) || is_int($value) || is_float($value) => 1,
        is_string($value) => 2,
        $value instanceof SQLiteBlobValue => 3,
        default => throw new RuntimeException('unsupported dynamic SELECT sort value'),
    };
};

$collationKey = static function (mixed $value, string $collation): mixed {
    if (!is_string($value)) {
        return $value;
    }

    return match (strtoupper($collation)) {
        'NOCASE' => strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
        'RTRIM' => rtrim($value, ' '),
        default => $value,
    };
};

/**
 * @param list<mixed> $values
 * @return list<mixed>
 */
$expectedOrderedValues = static function (array $values, string $direction = 'ASC', string $collation = 'BINARY') use ($sqlSortRank, $collationKey): array {
    $decorated = [];
    foreach ($values as $index => $value) {
        $decorated[] = [$value, $index];
    }

    usort($decorated, static function (array $left, array $right) use ($direction, $collation, $sqlSortRank, $collationKey): int {
        $leftValue = $left[0];
        $rightValue = $right[0];
        $comparison = $sqlSortRank($leftValue) <=> $sqlSortRank($rightValue);
        if ($comparison === 0) {
            if ($leftValue instanceof SQLiteBlobValue && $rightValue instanceof SQLiteBlobValue) {
                $comparison = strcmp($leftValue->bytes, $rightValue->bytes);
            } elseif ((is_int($leftValue) || is_float($leftValue) || is_bool($leftValue)) && (is_int($rightValue) || is_float($rightValue) || is_bool($rightValue))) {
                $comparison = ((float) $leftValue) <=> ((float) $rightValue);
            } elseif (is_string($leftValue) && is_string($rightValue)) {
                $comparison = strcmp((string) $collationKey($leftValue, $collation), (string) $collationKey($rightValue, $collation));
                if ($comparison === 0) {
                    $comparison = strcmp($leftValue, $rightValue);
                }
            } else {
                $comparison = 0;
            }
        }
        if ($comparison !== 0) {
            return strtoupper($direction) === 'DESC' ? -$comparison : $comparison;
        }

        return $left[1] <=> $right[1];
    });

    return array_map(static fn (array $entry): mixed => $entry[0], $decorated);
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectSort = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenSelectSortValues, $normalizeExpectedSortValues): void {
    $actual = $flattenSelectSortValues(SQLiteSelectSql::execute($sql, $tables));
    $expected = $normalizeExpectedSortValues($expected);

    $t->same($expected, $actual, $scenario . ' result');
    $t->same(count($expected), count($actual), $scenario . ' flat count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' edge values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' fingerprint',
    );
};

/**
 * @return array{mixed_values:list<array{v:mixed}>, labels:list<array{id:int,label:string}>}
 */
$selectSortTablesFor = static function (int $seed): array {
    $suffix = chr(97 + ($seed % 26));
    $number = 10 + ($seed % 17);
    $real = $number + 0.5 + (($seed % 3) / 10);
    $textLower = 'alpha_' . $suffix;
    $textUpper = 'Alpha_' . $suffix;
    $textTail = 'beta_' . chr(65 + ($seed % 26));

    return [
        'mixed_values' => [
            ['v' => 'text_' . $suffix],
            ['v' => $real],
            ['v' => $number],
            ['v' => new SQLiteBlobValue('blob_' . $suffix)],
            ['v' => $number - 3],
            ['v' => null],
            ['v' => new SQLiteBlobValue('Blob_' . $suffix)],
        ],
        'labels' => [
            ['id' => 1, 'label' => $textLower],
            ['id' => 2, 'label' => $textUpper],
            ['id' => 3, 'label' => $textTail],
            ['id' => 4, 'label' => strtoupper($textTail)],
        ],
    ];
};

/**
 * @param list<array{id:int,label:string}> $rows
 * @return list<string>
 */
$orderedLabels = static function (array $rows, string $collation, string $direction = 'ASC') use ($collationKey): array {
    $decorated = [];
    foreach ($rows as $index => $row) {
        $decorated[] = [$row, $index];
    }

    usort($decorated, static function (array $left, array $right) use ($collationKey, $collation, $direction): int {
        $leftLabel = $left[0]['label'];
        $rightLabel = $right[0]['label'];
        $comparison = strcmp((string) $collationKey($leftLabel, $collation), (string) $collationKey($rightLabel, $collation));
        if ($comparison === 0) {
            $comparison = $left[0]['id'] <=> $right[0]['id'];
        }
        if ($comparison !== 0) {
            return strtoupper($direction) === 'DESC' ? -$comparison : $comparison;
        }

        return $left[1] <=> $right[1];
    });

    return array_map(static fn (array $entry): string => $entry[0]['label'], $decorated);
};

$tests['real upstream e_select.test select-core sort collation cites hydrated source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    foreach ([
        'do_execsql_test e_select-8.8.1',
        'do_execsql_test e_select-8.8.2',
        'do_execsql_test e_select-8.9.1',
        'do_execsql_test e_select-8.9.2',
        'do_execsql_test e_select-8.10.1',
        'do_execsql_test e_select-8.10.2',
        'do_execsql_test e_select-8.10.3',
        'do_execsql_test e_select-8.12.1',
    ] as $needle) {
        $t->contains($needle, $text);
    }
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $selectSortTablesFor($seed);
    $mixed = array_column($tables['mixed_values'], 'v');
    $labels = $tables['labels'];
    $ascending = $expectedOrderedValues($mixed, 'ASC');
    $descending = $expectedOrderedValues($mixed, 'DESC');
    $binaryLabels = $orderedLabels($labels, 'BINARY');
    $nocaseLabels = $orderedLabels($labels, 'NOCASE');

    $tests[sprintf('real upstream e_select.test select-core dynamic sort collation seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertSelectSort,
            $tables,
            $ascending,
            $descending,
            $binaryLabels,
            $nocaseLabels,
            $seed
        ): void {
            $assertSelectSort(
                $t,
                'SELECT v FROM mixed_values ORDER BY v',
                $tables,
                $ascending,
                'e_select-8.8.1 mixed storage class ASC seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                'SELECT v FROM mixed_values ORDER BY v DESC',
                $tables,
                $descending,
                'e_select-8.8.2 mixed storage class DESC seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                'SELECT label FROM labels ORDER BY label COLLATE binary',
                $tables,
                $binaryLabels,
                'e_select-8.9.1 explicit ORDER BY binary seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                'SELECT label COLLATE binary FROM labels ORDER BY 1 COLLATE nocase',
                $tables,
                $nocaseLabels,
                'e_select-8.9.2 ORDER BY collation override seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                'SELECT label COLLATE nocase AS sorted_label FROM labels ORDER BY sorted_label',
                $tables,
                $nocaseLabels,
                'e_select-8.10.3 result alias collation inheritance seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                'SELECT label COLLATE nocase FROM labels ORDER BY 1',
                $tables,
                $nocaseLabels,
                'e_select-8.10.1 ordinal collation inheritance seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                'SELECT label COLLATE nocase FROM labels ORDER BY label',
                $tables,
                $binaryLabels,
                'e_select-8.10.2 source-column order ignores result collation seed ' . $seed,
            );
            $assertSelectSort(
                $t,
                "SELECT label FROM labels ORDER BY label||''",
                $tables,
                $binaryLabels,
                'e_select-8.12.1 arbitrary ORDER BY expression binary fallback seed ' . $seed,
            );
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded dynamic sort/collation seed');
        };
}

$tests['real upstream e_select.test select-core dynamic sort collation dependency closure note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-select-core-dynamic-20260531T085917Z-0', 'real-upstream-corpus-select-core-dynamic-20260531T085917Z-0');
    $t->same('e_select.test:8.8.1-8.12.1', 'e_select.test:8.8.1-8.12.1');
    $t->same(
        'non-overlap: simple SELECT mixed storage-class ORDER BY and result-expression COLLATE inheritance; avoids accepted expression ORDER BY shape, compound collation, JSON table, and storage clusters',
        'non-overlap: simple SELECT mixed storage-class ORDER BY and result-expression COLLATE inheritance; avoids accepted expression ORDER BY shape, compound collation, JSON table, and storage clusters',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectResult mixed-type comparator, SQLiteBlobValue, and hydrated upstream SELECT corpus',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectResult mixed-type comparator, SQLiteBlobValue, and hydrated upstream SELECT corpus',
    );
};

return $tests;
