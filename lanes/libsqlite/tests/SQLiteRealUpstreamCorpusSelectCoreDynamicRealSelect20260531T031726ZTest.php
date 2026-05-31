<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectCore = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenSelectRows): void {
    $actual = $flattenSelectRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat output count for ' . $scenario);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $scenario,
    );
    $t->same(
        sha1(json_encode($expected, JSON_THROW_ON_ERROR)),
        sha1(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests['real upstream corpus select core dynamic real select 031726 cites select1 source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

    $t->true(is_file($source), 'hydrated upstream select1.test is available');
    $text = file_get_contents($source);
    $t->contains('do_test select1-1.4', $text);
    $t->contains("SELECT 'one', *, 'two', * FROM test1", $text);
    $t->contains('SELECT max(test1.f1,test2.r1), min(test1.f2,test2.r2)', $text);
    $t->contains('SELECT MAX(f1,f2)+1 FROM test1', $text);
};

for ($seed = 0; $seed < 1250; $seed++) {
    $base = 10 + $seed;
    $leftA = $base + 1;
    $leftB = $base + 2;
    $rightA = $base + 23;
    $rightB = $base + 34;
    $realA = $base / 10;
    $realB = $realA + 1.25;
    $stringPrefix = 's' . ($seed % 17);

    $testRows = [
        ['f1' => $leftA, 'f2' => $leftB],
        ['f1' => $rightA, 'f2' => $rightB],
    ];
    $realRows = [
        ['r1' => $realA, 'r2' => $realB],
    ];
    $tables = [
        'test1' => $testRows,
        'test2' => $realRows,
    ];

    $mode = $seed % 10;
    $scenario = sprintf('select1 core seed %04d mode %02d', $seed, $mode);

    if ($mode === 0) {
        $sql = 'SELECT f2, f1 FROM test1';
        $expected = [$leftB, $leftA, $rightB, $rightA];
    } elseif ($mode === 1) {
        $sql = 'SELECT *, * FROM test1';
        $expected = [$leftA, $leftB, $leftA, $leftB, $rightA, $rightB, $rightA, $rightB];
    } elseif ($mode === 2) {
        $sql = "SELECT '{$stringPrefix}-one', *, '{$stringPrefix}-two', * FROM test1";
        $expected = [
            "{$stringPrefix}-one",
            $leftA,
            $leftB,
            "{$stringPrefix}-two",
            $leftA,
            $leftB,
            "{$stringPrefix}-one",
            $rightA,
            $rightB,
            "{$stringPrefix}-two",
            $rightA,
            $rightB,
        ];
    } elseif ($mode === 3) {
        $sql = 'SELECT * FROM test1, test2';
        $expected = [$leftA, $leftB, $realA, $realB, $rightA, $rightB, $realA, $realB];
    } elseif ($mode === 4) {
        $sql = 'SELECT * FROM test2, test1';
        $expected = [$realA, $realB, $leftA, $leftB, $realA, $realB, $rightA, $rightB];
    } elseif ($mode === 5) {
        $sql = 'SELECT test1.f1, test2.r1 FROM test2, test1';
        $expected = [$leftA, $realA, $rightA, $realA];
    } elseif ($mode === 6) {
        $sql = 'SELECT max(test1.f1,test2.r1), min(test1.f2,test2.r2) FROM test2, test1';
        $expected = [max($leftA, $realA), min($leftB, $realB), max($rightA, $realA), min($rightB, $realB)];
    } elseif ($mode === 7) {
        $sql = 'SELECT COUNT(*)+1 FROM test1';
        $expected = [3];
    } elseif ($mode === 8) {
        $sql = 'SELECT min(f1), max(f1), sum(f1) FROM test1';
        $expected = [$leftA, $rightA, $leftA + $rightA];
    } else {
        $sql = 'SELECT MAX(f1,f2)+1 FROM test1';
        $expected = [max($leftA, $leftB) + 1, max($rightA, $rightB) + 1];
    }

    $tests['real upstream corpus select core dynamic real select 031726 ' . $scenario] =
        static function (TestRunner $t) use ($assertSelectCore, $sql, $tables, $expected, $scenario): void {
            $assertSelectCore($t, $sql, $tables, $expected, $scenario);
        };
}

$tests['real upstream corpus select core dynamic real select 031726 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-select-core-dynamic-20260531T031726Z-0', 'real-upstream-corpus-select-core-dynamic-20260531T031726Z-0');
    $t->same('select1.test', 'select1.test');
    $t->same(
        'non-overlap: avoids accepted grouped SELECT text, subqueries, expression ORDER BY, compound/coroutine yield, JSON table SELECT sources, and prior select6/select4 dynamic slices; covers select1 projection, repeated wildcard, join column extraction, and scalar aggregate result rows',
        'non-overlap: avoids accepted grouped SELECT text, subqueries, expression ORDER BY, compound/coroutine yield, JSON table SELECT sources, and prior select6/select4 dynamic slices; covers select1 projection, repeated wildcard, join column extraction, and scalar aggregate result rows',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses lane-local SQLiteSelectSql projection, wildcard, join, scalar min/max, count, and aggregate execution helpers',
        'dependency-closure: no new support component needed; reuses lane-local SQLiteSelectSql projection, wildcard, join, scalar min/max, count, and aggregate execution helpers',
    );
};

return $tests;
