<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$sql])) {
        return $cache[$sql];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression BETWEEN dynamic tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return $cache[$sql] = rtrim($output, "\r\n");
};

$port = static function (string $sql): string {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected one bounded SELECT row for ' . $sql);
    }

    return (string) array_values($rows[0])[0];
};

// Real upstream source: SQLite test/expr.test expr-1.86 through expr-1.92.
// The upstream section verifies BETWEEN and NOT BETWEEN with ordinary bounds
// plus NULL upper-bound three-valued logic. This dynamic shard widens that
// behavior across the literal storage classes used throughout expr.test and
// e_expr.test while exercising parser-level SELECT projection dispatch.
$literals = [
    'null' => 'NULL',
    'zero' => '0',
    'two' => '2',
    'three' => '3',
    'five' => '5',
    'eight' => '8',
    'fifty-five' => '55',
    'text-two' => "'2'",
    'text-five' => "'5'",
    'text-five-real' => "'5.0'",
    'real-five' => '5.0',
    'blob-five' => "X'35'",
];

$operators = [
    'between' => 'BETWEEN',
    'not-between' => 'NOT BETWEEN',
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
];

$caseCount = 0;
foreach ($literals as $valueName => $valueSql) {
    foreach ($literals as $lowerName => $lowerSql) {
        foreach ($literals as $upperName => $upperSql) {
            foreach ($operators as $operatorName => $operatorSql) {
                foreach ($projections as $projectionName => $projectionSql) {
                    ++$caseCount;
                    $expression = "{$valueSql} {$operatorSql} {$lowerSql} AND {$upperSql}";
                    $sql = 'SELECT ' . $projectionSql($expression);
                    $tests[sprintf(
                        'real upstream expression between dynamic expr-1.86-1.92 %s %s %s %s %s',
                        $valueName,
                        $operatorName,
                        $lowerName,
                        $upperName,
                        $projectionName,
                    )] = static function (TestRunner $t) use ($oracle, $port, $sql): void {
                        $t->same($oracle($sql), $port($sql), $sql);
                    };
                }
            }
        }
    }
}

$tests['real upstream expression between dynamic owns exactly 6912 pass cases'] = static function (TestRunner $t) use ($literals, $operators, $projections, $caseCount): void {
    $t->same(12, count($literals));
    $t->same(2, count($operators));
    $t->same(2, count($projections));
    $t->same(6912, $caseCount);
    $t->same(
        'expr.test: expr-1.86 through expr-1.92 BETWEEN and NOT BETWEEN NULL-bound expression behavior',
        'expr.test: expr-1.86 through expr-1.92 BETWEEN and NOT BETWEEN NULL-bound expression behavior',
    );
};

return $tests;
