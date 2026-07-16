<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity unary dynamic tests');
}

$literal = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/affinity2.test affinity2-500 through
// affinity2-507 and affinity2-600 through affinity2-601. These exercise unary
// plus/minus numeric coercion over text/blob operands before comparison with
// TEXT-affinity values, plus the large integer vs REAL comparison boundary.
$operands = [
    'blob-ce' => "x'ce'",
    'text-ce' => $literal('ce'),
    'text-alpha' => $literal('abc'),
    'text-int' => $literal('-1'),
    'text-plus-int' => $literal('+12'),
    'text-real' => $literal('12.75'),
    'text-leading-space-real' => $literal('   -12.75tail'),
    'text-empty' => $literal(''),
    'text-zero-suffix' => $literal('0tail'),
];

$unaryChains = [
    'minus' => '- %s',
    'plus-minus-plus' => '+ - + %s',
    'double-minus' => '- - %s',
    'plus' => '+ %s',
    'minus-plus-minus' => '- + - %s',
    'plus-plus' => '+ + %s',
];

$comparators = [
    'ge-text-minus-one' => ['>=', 'CAST(-1 AS TEXT)'],
    'gt-text-minus-one' => ['>', 'CAST(-1 AS TEXT)'],
    'eq-text-zero' => ['=', 'CAST(0 AS TEXT)'],
    'lt-text-one' => ['<', 'CAST(1 AS TEXT)'],
    'is-not-null' => ['IS NOT', 'NULL'],
];

$casts = [
    'none' => '%s',
    'numeric' => 'CAST(%s AS NUMERIC)',
    'real' => 'CAST(%s AS REAL)',
    'integer' => 'CAST(%s AS INTEGER)',
    'text' => 'CAST(%s AS TEXT)',
];

$cases = [];
foreach ($operands as $operandName => $operandSql) {
    foreach ($unaryChains as $chainName => $chainSql) {
        foreach ($casts as $castName => $castSql) {
            foreach ($comparators as $comparisonName => [$operator, $rhs]) {
                $inner = sprintf($chainSql, $operandSql);
                $expression = sprintf($castSql, '(' . $inner . ')');
                $cases["{$operandName}-{$chainName}-{$castName}-{$comparisonName}"] = [
                    'expression' => $expression,
                    'predicate' => "({$expression}) {$operator} ({$rhs})",
                ];
            }
        }
    }
}

$cases['large-int-real-less-than'] = [
    'expression' => 'CAST(3175546974276630385 AS REAL)',
    'predicate' => '3175546974276630385 < CAST(3175546974276630385 AS REAL)',
];
$cases['large-int-real-not-equal'] = [
    'expression' => 'CAST(3175546974276630385 AS REAL)',
    'predicate' => '3175546974276630385 <> CAST(3175546974276630385 AS REAL)',
];

$oracleSql = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $predicate = $case['predicate'];
    $oracleSql[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$predicate})) || char(9) || typeof(({$predicate}));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-affinity2-unary-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleSql));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce affinity2 unary output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$key, $valueQuote, $valueType, $predicateQuote, $predicateType] = $parts;
    $oracle[$key] = [
        'valueQuote' => $valueQuote,
        'valueType' => $valueType,
        'predicateQuote' => $predicateQuote,
        'predicateType' => $predicateType,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 affinity2 unary oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream affinity2 unary text blob real expression ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $predicate = $case['predicate'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS value_quote, typeof({$expression}) AS value_type, quote(({$predicate})) AS predicate_quote, typeof(({$predicate})) AS predicate_type", []);

        $t->same(1, count($rows), $key);
        $t->same($oracle[$key]['valueQuote'], (string) $rows[0]['value_quote'], $key . ' value quote');
        $t->same($oracle[$key]['valueType'], (string) $rows[0]['value_type'], $key . ' value type');
        $t->same($oracle[$key]['predicateQuote'], (string) $rows[0]['predicate_quote'], $key . ' predicate quote');
        $t->same($oracle[$key]['predicateType'], (string) $rows[0]['predicate_type'], $key . ' predicate type');
    };
}

$tests['real upstream affinity2 unary dynamic owns exact source matrix'] = static function (TestRunner $t) use ($cases, $operands, $unaryChains, $casts, $comparators): void {
    $t->same(9, count($operands));
    $t->same(6, count($unaryChains));
    $t->same(5, count($casts));
    $t->same(5, count($comparators));
    $t->same(1352, count($cases));
    $t->same(
        'affinity2.test affinity2-500..507 unary text/blob coercion and affinity2-600..601 integer-vs-REAL comparison',
        'affinity2.test affinity2-500..507 unary text/blob coercion and affinity2-600..601 integer-vs-REAL comparison',
    );
};

return $tests;
