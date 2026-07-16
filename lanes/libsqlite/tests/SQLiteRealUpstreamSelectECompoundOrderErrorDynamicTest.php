<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tests['real upstream selectE.test selectE-3.1 cites compound order placement error source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test';

    $t->true(is_file($source), 'hydrated upstream selectE.test is available');
    $text = file_get_contents($source);
    $t->contains('do_catchsql_test selectE-3.1', $text);
    $t->contains('ORDER BY clause should come after EXCEPT not before', $text);
};

for ($seed = 1; $seed <= 1000; $seed++) {
    $left = $seed;
    $middle = $seed + 1;
    $right = $seed + 2;
    $collation = $seed % 2 === 0 ? 'nocase' : 'binary';
    $orderTerm = $seed % 3 === 0 ? '1' : (string) (($seed % 5) + 1 - ($seed % 5));
    $operator = $seed % 4 === 0 ? 'UNION' : ($seed % 4 === 1 ? 'EXCEPT' : ($seed % 4 === 2 ? 'INTERSECT' : 'UNION ALL'));
    $expectedOperator = $operator === 'UNION ALL' ? 'UNION ALL' : $operator;

    $tests[sprintf('real upstream selectE.test selectE-3.1 rejects non-final compound order seed %04d', $seed)] =
        static function (TestRunner $t) use ($left, $middle, $right, $collation, $orderTerm, $operator, $expectedOperator): void {
            $sql = sprintf(
                'SELECT %d EXCEPT SELECT %d ORDER BY %s COLLATE %s %s SELECT %d',
                $left,
                $middle,
                $orderTerm,
                $collation,
                $operator,
                $right,
            );

            $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteSelectSql::execute($sql, []));
            try {
                SQLiteSelectSql::execute($sql, []);
            } catch (InvalidArgumentException $exception) {
                $t->contains('ORDER BY clause should come after ' . $expectedOperator . ' not before', $exception->getMessage());
                $t->contains('ORDER BY', $sql);
                $t->contains($operator, $sql);
            }
        };
}

return $tests;
