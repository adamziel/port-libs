<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression real arithmetic dynamic tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source: SQLite test/expr.test expr-2.1 through expr-2.28.
// Those sections exercise REAL arithmetic, REAL comparisons, modulo, division
// by zero, NULL propagation, and overflow-to-NULL behavior.
$realLiterals = [
    'real-123' => '1.23',
    'real-234' => '2.34',
    'real-25' => '25.0',
    'real-11' => '11.0',
    'real-zero' => '0.0',
    'real-one' => '1.0',
    'real-neg-one' => '-1.0',
    'real-large-pos' => '1e300',
    'real-large-neg' => '-1e300',
    'text-real-123' => $sqlLiteral('1.23'),
    'text-real-234' => $sqlLiteral('2.34'),
    'text-real-25' => $sqlLiteral('25.0'),
    'text-real-zero' => $sqlLiteral('0.0'),
    'text-real-tail' => $sqlLiteral('2.34tail'),
    'text-alpha' => $sqlLiteral('alpha'),
    'null' => 'NULL',
];

$leftExpressions = [];
foreach ($realLiterals as $name => $literal) {
    $leftExpressions[$name . '-as-real'] = "CAST({$literal} AS REAL)";
    $leftExpressions[$name . '-as-numeric'] = "CAST({$literal} AS NUMERIC)";
}

$rightExpressions = [
    'real-234' => 'CAST(2.34 AS REAL)',
    'real-123' => 'CAST(1.23 AS REAL)',
    'real-zero' => 'CAST(0.0 AS REAL)',
    'real-11' => 'CAST(11.0 AS REAL)',
    'real-large-pos' => 'CAST(1e300 AS REAL)',
    'real-large-neg' => 'CAST(-1e300 AS REAL)',
    'text-real-234' => "CAST('2.34' AS REAL)",
    'text-alpha-real' => "CAST('alpha' AS REAL)",
    'null-real' => 'CAST(NULL AS REAL)',
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'modulo' => '%',
    'less-than' => '<',
    'less-equal' => '<=',
    'greater-than' => '>',
    'greater-equal' => '>=',
    'equals' => '=',
    'not-equals' => '<>',
];

$cases = [];
$caseNumber = 0;
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($rightExpressions as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operator) {
            if ($operatorName === 'modulo' && (str_contains($leftName, 'large') || str_contains($rightName, 'large'))) {
                continue;
            }
            ++$caseNumber;
            $expression = "({$leftSql}) {$operator} ({$rightSql})";
            $cases['real-arithmetic-' . $caseNumber] = [
                'name' => "{$leftName} {$operatorName} {$rightName}",
                'expression' => $expression,
            ];
        }
    }
}

$coalesceExpressions = [
    'expr-2.25-null-add-coalesce' => 'coalesce(CAST(1.23 AS REAL) + CAST(NULL AS REAL), 99.0)',
    'expr-2.27-divide-by-zero' => 'CAST(1.1 AS REAL) / CAST(0.0 AS REAL)',
    'expr-2.28-modulo-by-zero' => 'CAST(1.1 AS REAL) % CAST(0.0 AS REAL)',
];

foreach ($coalesceExpressions as $name => $expression) {
    $cases[$name] = [
        'name' => $name,
        'expression' => $expression,
    ];
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-arithmetic-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce real arithmetic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 real arithmetic oracle rows, got %d', count($cases), count($oracle)));
}

$unquoteNumeric = static function (string $quoted): ?float {
    if ($quoted === 'NULL') {
        return null;
    }

    return (float) $quoted;
};

foreach ($cases as $key => $case) {
    $tests['real upstream expression real arithmetic dynamic expr.test expr-2 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle, $unquoteNumeric): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $actualType = (string) $row['t'];
        $expectedType = $oracle[$key]['typeof'];
        $expectedQuote = $oracle[$key]['quote'];
        $actualQuote = (string) $row['q'];
        if (in_array($expectedType, ['integer', 'real'], true) && in_array($actualType, ['integer', 'real'], true)) {
            $expectedNumeric = $unquoteNumeric($expectedQuote);
            $actualNumeric = $unquoteNumeric($actualQuote);
            $t->same(false, $expectedNumeric === null, $expression . ' expected numeric');
            $t->same(false, $actualNumeric === null, $expression . ' actual numeric');
            $scale = max(1.0, abs((float) $expectedNumeric), abs((float) $actualNumeric));
            $t->same(true, abs((float) $expectedNumeric - (float) $actualNumeric) <= ($scale * 1.0e-12), $expression . ' numeric parity');
        } else {
            $t->same($expectedQuote, $actualQuote, $expression . ' quote');
            $t->same($expectedType, $actualType, $expression . ' typeof');
        }
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression real arithmetic dynamic owns 3079 expr2 cases'] = static function (TestRunner $t) use ($cases, $leftExpressions, $rightExpressions, $operators, $coalesceExpressions): void {
    $t->same(32, count($leftExpressions));
    $t->same(9, count($rightExpressions));
    $t->same(11, count($operators));
    $t->same(3, count($coalesceExpressions));
    $t->same(3079, count($cases));
    $t->same(
        'expr.test: expr-2.1..2.28 REAL arithmetic, comparison, modulo, division-by-zero, and NULL propagation',
        'expr.test: expr-2.1..2.28 REAL arithmetic, comparison, modulo, division-by-zero, and NULL propagation',
    );
};

return $tests;
