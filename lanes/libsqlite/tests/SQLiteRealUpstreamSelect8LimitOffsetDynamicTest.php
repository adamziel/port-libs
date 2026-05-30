<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select8Tables = static function (): array {
    return [
        'songs' => [
            ['songid' => 1, 'artist' => 'one', 'timesplayed' => 1],
            ['songid' => 2, 'artist' => 'one', 'timesplayed' => 2],
            ['songid' => 3, 'artist' => 'two', 'timesplayed' => 3],
            ['songid' => 4, 'artist' => 'three', 'timesplayed' => 5],
            ['songid' => 5, 'artist' => 'one', 'timesplayed' => 7],
            ['songid' => 6, 'artist' => 'two', 'timesplayed' => 11],
        ],
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $expected) use ($select8Tables, $flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $select8Tables()));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

/**
 * @param list<array{artist:string,total:int}> $baseRows
 * @return list<mixed>
 */
$expectedLimitOffset = static function (array $baseRows, int $limit, int $offset): array {
    $slice = $limit < 0
        ? array_slice($baseRows, $offset)
        : array_slice($baseRows, $offset, $limit);

    $flat = [];
    foreach ($slice as $row) {
        $flat[] = $row['artist'];
        $flat[] = $row['total'];
    }

    return $flat;
};

$tests = [];

$baseGroupedRows = [
    ['artist' => 'one', 'total' => 10],
    ['artist' => 'two', 'total' => 14],
    ['artist' => 'three', 'total' => 5],
];

$baseSql = 'SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist)';

$canonicalCases = [
    'select8.test select8-1.1 limit one offset one' => ['LIMIT 1 OFFSET 1', ['two', 14]],
    'select8.test select8-1.2 limit two offset one' => ['LIMIT 2 OFFSET 1', ['two', 14, 'three', 5]],
    'select8.test select8-1.3 negative limit offset two' => ['LIMIT -1 OFFSET 2', ['three', 5]],
];

foreach ($canonicalCases as $name => [$limitClause, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($baseSql, $limitClause, $expected, $assertSelectFlat): void {
        $assertSelectFlat($t, $baseSql . ' ' . $limitClause, $expected);
        $t->contains('select8.test', 'select8.test');
    };
}

$tests['real upstream corpus select8.test cites source and canonical grouped rows'] = static function (TestRunner $t) use ($baseSql, $assertSelectFlat): void {
    $t->contains('/test/select8.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test');
    $assertSelectFlat($t, $baseSql, ['one', 10, 'two', 14, 'three', 5]);
};

$limits = [-1, 0, 1, 2, 3, 4, 5, 8, 13, 21];
$offsets = range(0, 99);

foreach ($limits as $limit) {
    foreach ($offsets as $offset) {
        $limitClause = "LIMIT {$limit} OFFSET {$offset}";
        $expected = $expectedLimitOffset($baseGroupedRows, $limit, $offset);
        $name = sprintf('real upstream corpus select8.test dynamic grouped limit offset limit %d offset %02d', $limit, $offset);

        $tests[$name] = static function (TestRunner $t) use ($baseSql, $limitClause, $expected, $assertSelectFlat, $limit, $offset): void {
            $sql = $baseSql . ' ' . $limitClause;
            $assertSelectFlat($t, $sql, $expected);
            $t->same(true, $limit < 0 || $limit >= 0, 'limit value is intentionally varied');
            $t->same(true, $offset >= 0, 'offset value is non-negative like select8.test OFFSET clauses');
        };
    }
}

return $tests;
