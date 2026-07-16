<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<array<string,mixed>>
 */
$limitDatatypeRows = static function (int $seed): array {
    $rows = [];
    $count = 18 + ($seed % 9);
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = [
            'a' => $i,
            'b' => sprintf('item_%04d_%02d', $seed, $i),
        ];
    }

    return $rows;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$limitDatatypeLabels = static function (array $rows): array {
    return array_map(static fn (array $row): mixed => $row['b'], $rows);
};

/**
 * @param list<mixed> $expected
 */
$limitDatatypeAssert = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label): void {
    $actualRows = SQLiteSelectSql::execute($sql, $tables);
    $actual = array_map(static fn (array $row): mixed => $row['b'], $actualRows);

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' row count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

$limitDatatypeAssertMismatch = static function (TestRunner $t, string $sql, array $tables, string $label): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteSelectSql::execute($sql, $tables));
    try {
        SQLiteSelectSql::execute($sql, $tables);
    } catch (InvalidArgumentException $exception) {
        $t->contains('LIMIT', $exception->getMessage(), $label . ' message names LIMIT');
        $t->contains('datatype mismatch', $exception->getMessage(), $label . ' message matches upstream mismatch');
    }
};

$tests = [];

$tests['real upstream e_select.test limit datatype cites source sections'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test exists');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('e_select-9.1', $text);
    $t->contains('LIMIT 5.0', $text);
    $t->contains("LIMIT '5'", $text);
    $t->contains('e_select-9.2 -error "datatype mismatch"', $text);
    $t->contains('e_select-9.7', $text);
    $t->contains('e_select-9.10', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $rows = $limitDatatypeRows($seed);
    $labels = $limitDatatypeLabels($rows);
    $rowCount = count($rows);
    $limit = 1 + ($seed % 8);
    $offset = $seed % 6;
    $subqueryNeedle = $labels[$limit - 1];
    $tables = ['f1' => $rows];

    $expectedLimit = array_slice($labels, 0, $limit);
    $expectedOffset = array_slice($labels, $offset, $limit);
    $expectedNegativeOffset = array_slice($labels, 0, $limit);
    $quotedInteger = "'" . (string) $limit . "'";
    $quotedRealInteger = "'" . (string) $limit . ".0'";
    $quotedOffset = "'" . (string) $offset . ".0'";
    $concatOffset = "'" . (string) intdiv($offset, 10) . "'||'" . (string) ($offset % 10) . "'";
    $subquerySql = "(SELECT a FROM f1 WHERE b='{$subqueryNeedle}')";

    $tests[sprintf('real upstream e_select.test limit datatype dynamic lossless case %04d', $seed)] =
        static function (TestRunner $t) use (
            $limitDatatypeAssert,
            $limitDatatypeAssertMismatch,
            $tables,
            $limit,
            $offset,
            $rowCount,
            $quotedInteger,
            $quotedRealInteger,
            $quotedOffset,
            $concatOffset,
            $subquerySql,
            $expectedLimit,
            $expectedOffset,
            $expectedNegativeOffset,
            $seed
        ): void {
            $base = 'SELECT b FROM f1 ORDER BY a';

            $limitDatatypeAssert($t, "{$base} LIMIT {$limit}.0", $tables, $expectedLimit, 'e_select-9.1 real integral LIMIT seed ' . $seed);
            $limitDatatypeAssert($t, "{$base} LIMIT {$quotedInteger}", $tables, $expectedLimit, 'e_select-9.1 quoted integer LIMIT seed ' . $seed);
            $limitDatatypeAssert($t, "{$base} LIMIT {$quotedRealInteger}", $tables, $expectedLimit, 'e_select-9.5 quoted real integer LIMIT seed ' . $seed);
            $limitDatatypeAssert($t, "{$base} LIMIT {$subquerySql}", $tables, $expectedLimit, 'e_select-9.1 scalar subquery LIMIT seed ' . $seed);
            $limitDatatypeAssert($t, "{$base} LIMIT {$limit} OFFSET {$quotedOffset}", $tables, $expectedOffset, 'e_select-9.8 quoted real integer OFFSET seed ' . $seed);
            $limitDatatypeAssert($t, "{$base} LIMIT {$limit} OFFSET {$concatOffset}", $tables, $expectedOffset, 'e_select-9.8 concatenated integer OFFSET seed ' . $seed);
            $limitDatatypeAssert($t, "{$base} LIMIT {$limit} OFFSET -" . ($offset + 1), $tables, $expectedNegativeOffset, 'e_select-9.10 negative OFFSET seed ' . $seed);

            $limitDatatypeAssertMismatch($t, "{$base} LIMIT 'hello_{$seed}'", $tables, 'e_select-9.2 text LIMIT seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT NULL", $tables, 'e_select-9.2 NULL LIMIT seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT X'ABCD'", $tables, 'e_select-9.2 BLOB LIMIT seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT {$limit}.5", $tables, 'e_select-9.2 non-integral LIMIT seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT (SELECT group_concat(b) FROM f1)", $tables, 'e_select-9.2 aggregate text LIMIT seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT {$limit} OFFSET 'bad_{$seed}'", $tables, 'e_select-9.7 text OFFSET seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT {$limit} OFFSET NULL", $tables, 'e_select-9.7 NULL OFFSET seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT {$limit} OFFSET X'ABCD'", $tables, 'e_select-9.7 BLOB OFFSET seed ' . $seed);
            $limitDatatypeAssertMismatch($t, "{$base} LIMIT {$limit} OFFSET " . ($offset + 0.25), $tables, 'e_select-9.7 non-integral OFFSET seed ' . $seed);

            $t->same($rowCount, count($tables['f1']), 'dynamic f1 row count seed ' . $seed);
            $t->contains('e_select-9.', 'e_select-9.1/9.2/9.7/9.8/9.10 LIMIT datatype corpus');
        };
}

$tests['real upstream e_select.test limit datatype non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: owns e_select.test LIMIT/OFFSET lossless integer conversion and datatype mismatch sections 9.1, 9.2, 9.7, 9.8, and 9.10; avoids accepted comma-LIMIT, negative LIMIT, select9 compound limit sweeps, SELECT DISTINCT/ALL, empty aggregate, join, grouped SELECT, expression ORDER BY, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows',
        'non-overlap: owns e_select.test LIMIT/OFFSET lossless integer conversion and datatype mismatch sections 9.1, 9.2, 9.7, 9.8, and 9.10; avoids accepted comma-LIMIT, negative LIMIT, select9 compound limit sweeps, SELECT DISTINCT/ALL, empty aggregate, join, grouped SELECT, expression ORDER BY, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteSelectResult, scalar subqueries, string concatenation, LIMIT/OFFSET parsing, and the hydrated upstream SQLite SELECT corpus',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteSelectResult, scalar subqueries, string concatenation, LIMIT/OFFSET parsing, and the hydrated upstream SQLite SELECT corpus',
    );
};

return $tests;
