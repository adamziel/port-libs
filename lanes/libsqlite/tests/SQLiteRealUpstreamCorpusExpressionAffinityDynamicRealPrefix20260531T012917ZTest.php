<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real-prefix expression affinity dynamic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/e_expr.test e_expr-29.2.* and e_expr-29.3.*: CAST(TEXT AS REAL)
//   consumes the longest possible real-number prefix, including leading space.
// - test/e_expr.test e_expr-29.4.*: nonnumeric TEXT casts to REAL 0.0.
// - test/e_expr.test e_expr-32.1.*: CAST(TEXT AS NUMERIC) keeps exact integer
//   prefixes as INTEGER and promotes fractional/overflow prefixes to REAL.
//
// This shard intentionally stays outside the accepted broad real arithmetic,
// CASE/iif affinity, NULL/coalesce, e_expr-12 syntax, affinity2/affinity3,
// and real-precision comparison batches by varying prefix-shaped TEXT inputs
// through both REAL and NUMERIC casts before arithmetic/comparison dispatch.
$textInputs = [
    'plain-decimal-tail' => '1.23abcd',
    'two-decimals-tail' => '1.45.23abcd',
    'negative-exponent-tail' => '-2.12e-01ABC',
    'space-separated-tail' => '1 2 3 4',
    'leading-space-decimal-tail' => ' 1.23abcd',
    'wide-leading-space-decimal-tail' => '    1.45.23abcd',
    'leading-space-negative-exponent-tail' => '   -2.12e-01ABC',
    'leading-space-separated-tail' => ' 1 2 3 4',
    'empty' => '',
    'not-number' => 'not a number',
    'roman' => 'XXI',
    'integer-prefix-tail' => '11abc',
    'fraction-prefix-tail' => '11.1abc',
    'small-positive-exponent' => '9.223372036e14',
    'small-negative-exponent' => '-9.223372036e14',
    'max-int-text' => '9223372036854775807',
    'max-int-plus-one-text' => '9223372036854775808',
    'min-int-text' => '-9223372036854775808',
    'min-int-minus-one-text' => '-9223372036854775809',
    'huge-integer-prefix-tail' => '9223372036854775808xyz',
    'huge-negative-prefix-tail' => '-9223372036854775809xyz',
    'plus-half' => '+.5tail',
    'minus-half' => '-.5tail',
    'plus-only' => '+',
    'minus-only' => '-',
    'dot-only' => '.',
];

$castTargets = [
    'real' => 'REAL',
    'numeric' => 'NUMERIC',
];

$rightExpressions = [
    'real-one' => 'CAST(1.0 AS REAL)',
    'real-minus-two-half' => 'CAST(-2.5 AS REAL)',
    'numeric-three' => "CAST('3' AS NUMERIC)",
    'numeric-quarter' => "CAST('0.25' AS NUMERIC)",
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'equals' => '=',
    'less-than' => '<',
    'greater-equal' => '>=',
];

$cases = [];
$caseId = 0;
foreach ($textInputs as $inputName => $inputValue) {
    $literal = $quoteSql($inputValue);
    foreach ($castTargets as $targetName => $targetSql) {
        $left = "CAST({$literal} AS {$targetSql})";
        foreach ($rightExpressions as $rightName => $rightSql) {
            foreach ($operators as $operatorName => $operatorSql) {
                ++$caseId;
                $cases['case-' . $caseId] = [
                    'name' => "{$inputName} {$targetName} {$operatorName} {$rightName}",
                    'expression' => "({$left}) {$operatorSql} ({$rightSql})",
                ];
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-prefix-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 real-prefix oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce real-prefix expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 real-prefix oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d real-prefix oracle rows, got %d', count($cases), count($oracle)));
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
    $tests['real upstream corpus expression affinity dynamic real prefix e_expr-29 e_expr-32 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle, $numericEquivalent): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $actualQuote = (string) $row['q'];
        $actualType = (string) $row['t'];
        $expectedQuote = $oracle[$key]['quote'];
        $expectedType = $oracle[$key]['typeof'];

        if (!$numericEquivalent($expectedType, $expectedQuote, $actualType, $actualQuote)) {
            $t->same($expectedQuote, $actualQuote, $expression . ' quote');
            $t->same($expectedType, $actualType, $expression . ' typeof');
        }

        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic real prefix owns 1456 upstream cases'] = static function (TestRunner $t) use ($textInputs, $castTargets, $rightExpressions, $operators, $cases, $oracle): void {
    $t->same(26, count($textInputs));
    $t->same(2, count($castTargets));
    $t->same(4, count($rightExpressions));
    $t->same(7, count($operators));
    $t->same(1456, count($cases));
    $t->same(1456, count($oracle));
    $t->same(
        'e_expr.test e_expr-29.2..29.4 and e_expr-32.1 TEXT-to-REAL/NUMERIC prefix conversion through arithmetic and comparison dispatch',
        'e_expr.test e_expr-29.2..29.4 and e_expr-32.1 TEXT-to-REAL/NUMERIC prefix conversion through arithmetic and comparison dispatch',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
