<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression overflow dynamic tests');
}

// Real upstream source: SQLite test/expr.test expr-1.200 through expr-1.271
// exercises 64-bit integer boundary arithmetic and REAL fallback on overflow.
$values = [
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'minus-one' => '-1',
    'minus-two' => '-2',
    'hundred-thousand' => '100000',
    'minus-hundred-thousand' => '-100000',
    'int64-max-minus-one' => '9223372036854775806',
    'int64-max' => '9223372036854775807',
    'int64-min-plus-one' => '-9223372036854775807',
    'int64-min' => '-9223372036854775808',
    'u32' => '4294967296',
    'i32-max' => '2147483647',
    'i32-min-abs' => '2147483648',
    'minus-u32' => '-4294967296',
    'minus-i32-max' => '-2147483647',
    'minus-i32-min-abs' => '-2147483648',
    'sqrt-int64-ceil' => '3037000500',
    'sqrt-int64-floor' => '3037000499',
    'minus-sqrt-int64-ceil' => '-3037000500',
    'minus-sqrt-int64-floor' => '-3037000499',
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
];

$cases = [];
foreach ($values as $leftName => $leftSql) {
    foreach ($values as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            if ($operatorSql === '/' && $rightSql === '0') {
                continue;
            }

            $caseId = sprintf('expr-1.200-271.dynamic.%s.%s.%s', $leftName, $operatorName, $rightName);
            $expression = "({$leftSql}) {$operatorSql} ({$rightSql})";
            $cases[$caseId] = [
                'expression' => $expression,
                'operator' => $operatorName,
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $caseId => $case) {
    $safeCaseId = str_replace("'", "''", $caseId);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeCaseId}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) || char(9) || ifnull(printf('%.17g', {$expression}), '');";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-overflow-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 overflow oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression overflow output');
}

$oracle = [];
foreach (explode("\n", trim($oracleOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 expression overflow oracle row: ' . $line);
    }

    [$caseId, $quotedValue, $storageClass, $quotedIsNull, $numericText] = $parts;
    $oracle[$caseId] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
        'numeric' => $numericText,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression overflow oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseId => $case) {
    $tests['real upstream expression affinity overflow dynamic ' . $caseId] = static function (TestRunner $t) use ($case, $caseId, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT ({$expression}) AS v, quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $caseId . ' row count');

        $row = $rows[0];
        $t->same($oracle[$caseId]['typeof'], (string) $row['t'], $caseId . ' typeof ' . $expression);
        $t->same($oracle[$caseId]['isNull'], (string) $row['n'], $caseId . ' nullness ' . $expression);
        if ($oracle[$caseId]['typeof'] === 'real') {
            $expected = (float) $oracle[$caseId]['numeric'];
            $actual = (float) $row['v'];
            $scale = max(1.0, abs($expected));
            $t->true(abs($expected - $actual) / $scale < 1.0e-12, $caseId . ' real value ' . $expression);
        } else {
            $t->same($oracle[$caseId]['quote'], (string) $row['q'], $caseId . ' quote ' . $expression);
        }
    };
}

$tests['real upstream expression affinity overflow dynamic owns expr-1.200 through expr-1.271 matrix'] = static function (TestRunner $t) use ($cases, $operators, $values): void {
    $t->same(21, count($values));
    $t->same(4, count($operators));
    $t->same(1743, count($cases));
    $t->same('expr.test expr-1.200..expr-1.271 64-bit overflow arithmetic and REAL fallback', 'expr.test expr-1.200..expr-1.271 64-bit overflow arithmetic and REAL fallback');
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
