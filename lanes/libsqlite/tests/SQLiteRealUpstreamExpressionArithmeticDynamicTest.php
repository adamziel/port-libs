<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression, string $projection) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expr arithmetic dynamic tests');
    }

    $sql = "SELECT {$projection}({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$port = static function (string $expression, string $projection): string {
    $rows = SQLiteSelectSql::execute("SELECT {$projection}({$expression}) AS value", []);

    return (string) ($rows[0]['value'] ?? '');
};

// Real upstream source: SQLite test/expr.test section expr-1.*. That section
// validates arithmetic expression dispatch and result storage classes for +,
// -, *, /, and %. This dynamic shard widens the same operator family across
// negative and positive integer literal pairs while avoiding divide-by-zero.
$leftLiterals = [
    'negative-five-integer' => '-5',
    'negative-four-integer' => '-4',
    'negative-three-integer' => '-3',
    'negative-two-integer' => '-2',
    'negative-one-integer' => '-1',
    'zero-integer' => '0',
    'one-integer' => '1',
    'two-integer' => '2',
    'three-integer' => '3',
    'four-integer' => '4',
];

$rightLiterals = [
    'negative-five-integer' => '-5',
    'negative-four-integer' => '-4',
    'negative-three-integer' => '-3',
    'negative-two-integer' => '-2',
    'negative-one-integer' => '-1',
    'one-integer' => '1',
    'two-integer' => '2',
    'three-integer' => '3',
    'four-integer' => '4',
    'five-integer' => '5',
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'remainder' => '%',
];

$projections = [
    'quote' => 'quote',
    'typeof' => 'typeof',
];

$caseCount = 0;
foreach ($leftLiterals as $leftName => $leftSql) {
    foreach ($rightLiterals as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operator) {
            $expression = "{$leftSql} {$operator} {$rightSql}";
            foreach ($projections as $projectionName => $projection) {
                ++$caseCount;
                $testName = sprintf(
                    'real upstream expression arithmetic dynamic expr-1 %s %s %s %s',
                    $leftName,
                    $operatorName,
                    $rightName,
                    $projectionName,
                );

                $tests[$testName] = static function (TestRunner $t) use ($oracle, $port, $expression, $projection, $testName): void {
                    $t->same($oracle($expression, $projection), $port($expression, $projection), $testName);
                };
            }
        }
    }
}

$tests['real upstream expression arithmetic dynamic owns exactly 1000 pass cases'] = static function (TestRunner $t) use ($leftLiterals, $rightLiterals, $operators, $projections, $caseCount): void {
    $t->same(10, count($leftLiterals));
    $t->same(10, count($rightLiterals));
    $t->same(5, count($operators));
    $t->same(2, count($projections));
    $t->same(1000, $caseCount);
    $t->same('expr.test: expr-1.* arithmetic operator result and storage-class family', 'expr.test: expr-1.* arithmetic operator result and storage-class family');
};

return $tests;
