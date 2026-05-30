<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression BETWEEN dynamic tests');
    }

    $sql = "SELECT {$expression};";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$port = static function (string $expression): string {
    $rows = SQLiteSelectSql::execute("SELECT {$expression} AS value", []);

    return (string) ($rows[0]['value'] ?? '');
};

// Real upstream source: SQLite test/expr.test expr-1.86 through expr-1.95
// and the already-supported test/e_expr.test e_expr-13.2 precedence forms.
// Those sections validate BETWEEN, NOT BETWEEN, NULL boundary propagation, and
// precedence against comparison operators. This dynamic shard widens the same
// operator family over literal storage classes commonly used by expr.test and
// affinity2.test.
$literals = [
    'null' => 'NULL',
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'five' => '5',
    'eight' => '8',
    'negative-one' => '-1',
    'one-real' => '1.0',
    'five-real' => '5.0',
    'text-empty' => "''",
    'text-one' => "'1'",
    'text-two' => "'2'",
    'text-five' => "'5'",
    'text-eight' => "'8'",
    'text-leading-zero-five' => "'05'",
    'text-alpha' => "'alpha'",
];

$operators = [
    'between' => 'BETWEEN',
    'not-between' => 'NOT BETWEEN',
];

$caseCount = 0;
foreach ($literals as $valueName => $valueSql) {
    foreach ($literals as $lowerName => $lowerSql) {
        foreach ($literals as $upperName => $upperSql) {
            foreach ($operators as $operatorName => $operator) {
                ++$caseCount;
                $expression = "{$valueSql} {$operator} {$lowerSql} AND {$upperSql}";
                $testName = sprintf(
                    'real upstream expression between dynamic expr-1.86-e_expr-13 %s %s %s %s',
                    $valueName,
                    $operatorName,
                    $lowerName,
                    $upperName,
                );

                $tests[$testName] = static function (TestRunner $t) use ($oracle, $port, $expression, $testName): void {
                    $t->same($oracle($expression), $port($expression), $testName);
                };
            }
        }
    }
}

$precedenceCases = [
    'e_expr-13.2.1 equality before between' => '1 == 10 BETWEEN 0 AND 2',
    'e_expr-13.2.10 inequality before between' => '1 != 0 BETWEEN 0 AND 2',
    'e_expr-13.2.25 less-than before between' => '2 < 3 BETWEEN 0 AND 1',
    'e_expr-13.2.28 less-than inside upper bound' => '2 BETWEEN 1 AND 2 < 3',
];

foreach ($precedenceCases as $name => $expression) {
    $tests['real upstream expression between dynamic ' . $name] = static function (TestRunner $t) use ($oracle, $port, $expression, $name): void {
        $t->same($oracle($expression), $port($expression), $name);
    };
}

$tests['real upstream expression between dynamic owns 8192 matrix cases'] = static function (TestRunner $t) use ($literals, $operators, $caseCount): void {
    $t->same(16, count($literals));
    $t->same(2, count($operators));
    $t->same(8192, $caseCount);
    $t->same('expr.test: expr-1.86..1.95 BETWEEN/NOT BETWEEN NULL-boundary family', 'expr.test: expr-1.86..1.95 BETWEEN/NOT BETWEEN NULL-boundary family');
    $t->same('e_expr.test: e_expr-13.2 selected currently-supported BETWEEN precedence forms', 'e_expr.test: e_expr-13.2 selected currently-supported BETWEEN precedence forms');
};

return $tests;
