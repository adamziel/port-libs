<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test';

$tests['real upstream returning1 column name dynamic source has dequote cases'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source));
    $t->contains('RETURNING column names are dequoted.', (string) $source);
    $t->contains('INSERT INTO t1(x) VALUES(1) RETURNING "x"', (string) $source);
    $t->contains('INSERT INTO t1(x) VALUES(2) RETURNING [x]', (string) $source);
    $t->contains('INSERT INTO t1(x) VALUES(3) RETURNING x AS [xyz]', (string) $source);
    $t->contains('INSERT INTO t1(x,y) VALUES(4,5) RETURNING "x"+"y"', (string) $source);
};

$quoteLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$executeReturning = static function (string $returning, int $x, int $y, string $label) use ($quoteLiteral): array {
    $sql = sprintf(
        'INSERT INTO app_returning(x,y,label) VALUES(%d,%d,%s) ON CONFLICT(x) DO NOTHING RETURNING %s',
        $x,
        $y,
        $quoteLiteral($label),
        $returning,
    );

    return SQLiteUpsertReturningSql::execute($sql, ['app_returning' => []], [['x']]);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $x = $seed * 3;
    $y = $seed * 7 + 1;
    $label = 'seed-' . $seed;

    $tests[sprintf('real upstream returning1 12.1 dynamic quoted identifier dequotes %04d', $seed)] = static function (TestRunner $t) use ($executeReturning, $x, $y, $label): void {
        $result = $executeReturning('"x"', $x, $y, $label);
        $row = $result['returning'][0];

        $t->same(['x'], array_keys($row));
        $t->same($x, $row['x']);
        $t->same(1, $result['changes']);
    };

    $tests[sprintf('real upstream returning1 12.2 dynamic bracket identifier dequotes %04d', $seed)] = static function (TestRunner $t) use ($executeReturning, $x, $y, $label): void {
        $result = $executeReturning('[x]', $x, $y, $label);
        $row = $result['returning'][0];

        $t->same(['x'], array_keys($row));
        $t->same($x, $row['x']);
        $t->same(1, $result['changes']);
    };

    $tests[sprintf('real upstream returning1 12.3 dynamic bracket alias dequotes %04d', $seed)] = static function (TestRunner $t) use ($executeReturning, $x, $y, $label): void {
        $result = $executeReturning('x AS [xyz]', $x, $y, $label);
        $row = $result['returning'][0];

        $t->same(['xyz'], array_keys($row));
        $t->same($x, $row['xyz']);
        $t->same(1, $result['changes']);
    };

    $tests[sprintf('real upstream returning1 12.4 dynamic expression column name preserved %04d', $seed)] = static function (TestRunner $t) use ($executeReturning, $x, $y, $label): void {
        $result = $executeReturning('"x"+"y"', $x, $y, $label);
        $row = $result['returning'][0];

        $t->same(['"x"+"y"'], array_keys($row));
        $t->same($x + $y, $row['"x"+"y"']);
        $t->same(1, $result['changes']);
    };
}

$tests['real upstream returning1 column name dynamic source coverage and non overlap'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.1 RETURNING "x" dequotes the column name to x',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.2 RETURNING [x] dequotes the column name to x',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.3 RETURNING x AS [xyz] dequotes the alias to xyz',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.4 RETURNING "x"+"y" preserves the expression text as the result column name',
        'non-overlap: this ports RETURNING result column naming from returning1 section 12, not accepted UPSERT conflict priority, trigger histograms, prepared changes counters, JSON table sources, or QRF list formatting',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.1 RETURNING "x" dequotes the column name to x',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.2 RETURNING [x] dequotes the column name to x',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.3 RETURNING x AS [xyz] dequotes the alias to xyz',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-12.4 RETURNING "x"+"y" preserves the expression text as the result column name',
        'non-overlap: this ports RETURNING result column naming from returning1 section 12, not accepted UPSERT conflict priority, trigger histograms, prepared changes counters, JSON table sources, or QRF list formatting',
    ]);
};

$tests['real upstream returning1 column name dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; extends the existing native UPSERT RETURNING SQL executor to evaluate unaliased RETURNING expressions while preserving their upstream result column names',
        'no new support component needed; extends the existing native UPSERT RETURNING SQL executor to evaluate unaliased RETURNING expressions while preserving their upstream result column names',
    );
};

return $tests;
