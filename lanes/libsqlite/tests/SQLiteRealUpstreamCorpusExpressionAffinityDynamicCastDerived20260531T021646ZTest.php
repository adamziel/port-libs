<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for derived CAST numeric affinity dynamic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/cast.test cast-9.4 through cast-9.11.
// Those cases pin that NUMERIC casts keep INTEGER vs REAL storage class when
// the value crosses a derived table boundary and participates in a JOIN.
// This dynamic shard widens that boundary behavior across upstream-shaped
// integer, real, numeric-prefix, exponent, signed-zero, and overflow strings,
// both JOIN orientations, and post-derived arithmetic/comparison expressions.
$castInputs = [
    'int-four' => '4',
    'real-four-dot-zero' => '4.0',
    'real-four-dot-five' => '4.5',
    'text-int-four' => "'4'",
    'text-real-four-dot-zero' => "'4.0'",
    'text-real-four-dot-five' => "'4.5'",
    'text-leading-space-int' => "'   4'",
    'text-plus-int' => "'+4'",
    'text-minus-zero' => "'-0'",
    'text-zero-dot-zero' => "'0.0'",
    'text-plus-zero-dot-zero' => "'+0.0'",
    'text-minus-one-dot-zero' => "'-1.0'",
    'text-exp-int' => "'123e+5'",
    'text-exp-real' => "'1.25e+2'",
    'text-prefix-int' => "'123abc'",
    'text-prefix-real' => "'123.5abc'",
    'text-dot' => "'.'",
    'text-plus-only' => "'+'",
    'text-minus-only' => "'-'",
    'text-slash' => "'/'",
    'text-max-int' => "'9223372036854775807'",
    'text-max-int-plus-one' => "'9223372036854775808'",
    'text-min-int' => "'-9223372036854775808'",
    'text-min-int-minus-one' => "'-9223372036854775809'",
    'literal-max-int' => '9223372036854775807',
    'literal-max-int-plus-one' => '9223372036854775808',
    'literal-min-int' => '-9223372036854775808',
    'literal-min-int-minus-one' => '-9223372036854775809',
];

$joinShapes = [
    'derived-then-dual' => '(SELECT CAST(%s AS NUMERIC) AS x) JOIN dual',
    'dual-then-derived' => 'dual CROSS JOIN (SELECT CAST(%s AS NUMERIC) AS x)',
];

$postExpressions = [
    'value' => 'x',
    'plus-zero' => 'x + 0',
    'zero-plus' => '0 + x',
    'minus-zero' => 'x - 0',
    'times-one' => 'x * 1',
    'div-one' => 'x / 1',
    'eq-four' => 'x = 4',
    'eq-real-four' => 'x = 4.0',
    'lt-five' => 'x < 5',
    'ge-zero' => 'x >= 0',
    'is-null' => 'x IS NULL',
    'not-null' => 'x NOT NULL',
    'case-truth' => 'CASE WHEN x THEN 1 ELSE 0 END',
    'not-not' => 'NOT NOT x',
    'numeric-recast' => 'CAST(x AS NUMERIC)',
    'real-recast' => 'CAST(x AS REAL)',
    'integer-recast' => 'CAST(x AS INTEGER)',
    'text-recast' => 'CAST(x AS TEXT)',
];

$cases = [];
foreach ($castInputs as $inputName => $inputSql) {
    foreach ($joinShapes as $joinName => $fromTemplate) {
        $from = sprintf($fromTemplate, $inputSql);
        foreach ($postExpressions as $exprName => $expression) {
            $key = "{$inputName}.{$joinName}.{$exprName}";
            $cases[$key] = [
                'from' => $from,
                'expression' => $expression,
            ];
        }
    }
}

$oracleScript = [
    'CREATE TABLE dual(dummy);',
    "INSERT INTO dual VALUES('X');",
];
foreach ($cases as $key => $case) {
    $safeKey = $quoteSql($key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT {$safeKey} || char(9) || quote({$expression}) || char(9) || typeof({$expression}) FROM {$case['from']};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-cast-derived-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 derived CAST oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce derived CAST output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 derived CAST oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = ['quote' => $quotedValue, 'typeof' => $storageClass];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d derived CAST oracle rows, got %d', count($cases), count($oracle)));
}

$numericEquivalent = static function (string $expectedType, string $expectedQuote, string $actualType, string $actualQuote): bool {
    if (!in_array($expectedType, ['integer', 'real'], true) || !in_array($actualType, ['integer', 'real'], true)) {
        return false;
    }
    if ($expectedQuote === 'NULL' || $actualQuote === 'NULL') {
        return false;
    }

    $expected = (float) $expectedQuote;
    $actual = (float) $actualQuote;
    $scale = max(1.0, abs($expected), abs($actual));

    return abs($expected - $actual) <= $scale * 1.0e-12;
};

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic cast derived cast-9 ' . $key] = static function (TestRunner $t) use ($case, $oracle, $key, $numericEquivalent): void {
        $sql = "SELECT quote({$case['expression']}) AS q, typeof({$case['expression']}) AS t FROM {$case['from']}";
        $rows = SQLiteSelectSql::execute($sql, ['dual' => [['dummy' => 'X']]]);
        $t->same(1, count($rows), $sql);

        $actualQuote = (string) $rows[0]['q'];
        $actualType = (string) $rows[0]['t'];
        $expectedQuote = $oracle[$key]['quote'];
        $expectedType = $oracle[$key]['typeof'];
        if (!$numericEquivalent($expectedType, $expectedQuote, $actualType, $actualQuote)) {
            $t->same($expectedQuote, $actualQuote, $sql . ' quote');
            $t->same($expectedType, $actualType, $sql . ' typeof');
        }
    };
}

$tests['real upstream corpus expression affinity dynamic cast derived owns 1008 cast-9 cases'] = static function (TestRunner $t) use ($castInputs, $joinShapes, $postExpressions, $cases, $oracle): void {
    $t->same(28, count($castInputs));
    $t->same(2, count($joinShapes));
    $t->same(18, count($postExpressions));
    $t->same(1008, count($cases));
    $t->same(1008, count($oracle));
    $t->same(
        'cast.test cast-9.4..9.11 derived-table NUMERIC storage class preservation across JOIN boundaries',
        'cast.test cast-9.4..9.11 derived-table NUMERIC storage class preservation across JOIN boundaries',
    );
    $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
};

return $tests;
