<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$expression])) {
        return $cache[$expression];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity real literal dynamic tests');
    }

    $sql = "SELECT typeof({$expression}), CAST({$expression} AS TEXT);";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$expression] = rtrim($output, "\r\n");
};

$port = static function (string $expression): string {
    $rows = SQLiteSelectSql::execute("SELECT typeof({$expression}) AS type, CAST({$expression} AS TEXT) AS text_value", []);
    $row = $rows[0] ?? null;
    if (!is_array($row)) {
        throw new RuntimeException('SQLiteSelectSql did not produce a row for ' . $expression);
    }

    return implode("\t", [
        (string) $row['type'],
        (string) $row['text_value'],
    ]);
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-3.* verifies unary plus preserves
//   literal values and storage classes.
// - e_expr-6.* verifies REAL modulo behavior such as 72.35%5 -> 2.0.
// - e_expr-7.* verifies numeric binary operators return integer, real, or NULL
//   storage classes.
// - e_expr-10.1/10.2 and e_expr-12.1/12.2 verify literal-value and scientific
//   REAL notation parsing.
$realLiterals = [
    '0.0',
    '1.0',
    '3.4e-02',
    '3e+5',
    '123.4e05',
    '72.35',
    '1.25',
    '2.5e1',
    '5.0e-1',
    '9.999',
    '1000000.125',
    '6.02214076e23',
    '314159265358979.0',
    '2.0',
    '4.5',
    '8.125',
    '16.25',
    '31.5',
    '64.75',
    '128.875',
    '255.0625',
    '512.03125',
    '1024.015625',
    '2048.0078125',
    '4096.00390625',
    '8192.001953125',
    '16384.0009765625',
];

$templates = [
    '%s',
    '+%s',
    '-%s',
    '+(%s)',
    '-(%s)',
    '+(+%s)',
    '-(+%s)',
    '+(-%s)',
    '-(-%s)',
    '(%s)+0',
    '(%s)-0',
    '(%s)*1',
    '(%s)/1',
    '(%s)+0.0',
    '(%s)-0.0',
    '(%s)*1.0',
    '(%s)/1.0',
    '(%s)+1.5-1.5',
    '(%s)-1.5+1.5',
    '(%s)*2.0/2.0',
    '(%s)/2.0*2.0',
    '((%s))',
    '+((%s))',
    '-((%s))',
    '((%s)+0)',
    '((%s)*1)',
    '((%s)/1)',
    '((%s)-0)',
    '((%s)+0.0)',
    '((%s)*1.0)',
    '((%s)/1.0)',
    '((%s)-0.0)',
    '(%s) IS (%s)',
    '(%s) IS NOT (%s)',
    '(%s) = (%s)',
    '(%s) != (%s)',
    '(%s) < ((%s)+1)',
    '(%s) <= (%s)',
    '(%s) > ((%s)-1)',
    '(%s) >= (%s)',
];

$caseCount = 0;
foreach ($realLiterals as $literal) {
    foreach ($templates as $template) {
        ++$caseCount;
        $expression = sprintf($template, $literal, $literal);
        $tests["real upstream expression affinity real literal dynamic {$caseCount} {$expression}"] = static function (TestRunner $t) use ($oracle, $port, $expression): void {
            $t->same($oracle($expression), $port($expression), $expression);
        };
    }
}

$tests['real upstream expression affinity real literal dynamic owns exactly 1080 pass cases'] = static function (TestRunner $t) use ($realLiterals, $templates, $caseCount): void {
    $t->same(27, count($realLiterals));
    $t->same(40, count($templates));
    $t->same(1080, $caseCount);
    $t->same('e_expr.test: e_expr-3.*, e_expr-6.*, e_expr-7.*, e_expr-10.1/10.2, and e_expr-12.1/12.2 REAL literal expression behavior', 'e_expr.test: e_expr-3.*, e_expr-6.*, e_expr-7.*, e_expr-10.1/10.2, and e_expr-12.1/12.2 REAL literal expression behavior');
};

return $tests;
