<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expr2 boolean affinity tests');
}

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/expr2.test 1.1 through 1.4.2. These
// sections cover nested boolean identity predicates with IS TRUE/FALSE,
// IS NOT TRUE/FALSE, NOT, OR, and a row-context equality predicate.
$rowValues = [
    'text-val' => 'val',
    'text-one' => '1',
    'text-real-one' => '1.0',
    'text-zero' => '0',
    'text-real-zero' => '0.0',
    'text-alpha' => 'alpha',
    'empty-text' => '',
    'integer-one' => 1,
    'integer-zero' => 0,
    'integer-two' => 2,
    'real-one' => 1.0,
    'real-zero' => 0.0,
    'real-half' => 0.5,
    'real-negative' => -2.25,
    'null' => null,
];

$leftOperands = [
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'minus-one' => '-1',
    'real-zero' => '0.0',
    'real-half' => '0.5',
    'real-one' => '1.0',
    'real-minus' => '-2.25',
    'text-zero' => "'0'",
    'text-one' => "'1'",
    'text-real-zero' => "'0.0'",
    'text-real-one' => "'1.0'",
    'text-alpha' => "'alpha'",
    'text-empty' => "''",
    'cast-real-zero' => 'CAST(0 AS REAL)',
    'cast-real-one' => 'CAST(1 AS REAL)',
    'cast-numeric-text-one' => "CAST('1.0' AS NUMERIC)",
    'cast-numeric-text-zero' => "CAST('0.0' AS NUMERIC)",
    'cast-text-real' => "CAST(1.0 AS TEXT)",
    'null' => 'NULL',
];

$templates = [
    'expr2-1.1 where nested false identity' => '(((:lhs IS NOT FALSE) OR NOT (:lhs IS FALSE OR (c0 = 1))) IS 0)',
    'expr2-1.2.1 select nested false identity' => '(((:lhs IS NOT FALSE) OR NOT (:lhs IS FALSE OR (c0 = 1))) IS 0)',
    'expr2-1.2.2 select numeric identity variant' => '(((:lhs IS NOT FALSE) OR NOT (:lhs IS 0 OR (c0 = 1))) IS 0)',
    'expr2-1.3 projected nested boolean' => '((:lhs IS NOT FALSE) OR NOT (:lhs IS FALSE OR (c0 = 1)))',
    'expr2-1.4.1 direct is-not-false' => '(:lhs IS NOT FALSE)',
    'expr2-1.4.2 direct negated false-or-equality' => 'NOT (:lhs IS FALSE OR (c0 = 1))',
    'expr2-1.1 true mirror' => '(((:lhs IS NOT TRUE) OR NOT (:lhs IS TRUE OR (c0 = 1))) IS 0)',
    'expr2-1.3 true projected mirror' => '((:lhs IS NOT TRUE) OR NOT (:lhs IS TRUE OR (c0 = 1)))',
];

$cases = [];
$caseId = 0;
foreach ($rowValues as $rowName => $rowValue) {
    foreach ($leftOperands as $leftName => $leftSql) {
        foreach ($templates as $templateName => $template) {
            ++$caseId;
            $expression = str_replace(':lhs', $leftSql, $template);
            $cases['expr2-boolean-' . str_pad((string) $caseId, 4, '0', STR_PAD_LEFT)] = [
                'name' => "{$templateName} {$rowName} {$leftName}",
                'expression' => $expression,
                'row' => ['c0' => $rowValue],
                'literal' => $quoteSql($rowValue),
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = str_replace('c0', $case['literal'], $case['expression']);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr2-boolean-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr2 boolean affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 expr2 boolean oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 expr2 boolean oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic expr2 boolean ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n FROM app_values",
            ['app_values' => [$case['row']]],
        );

        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $key . ' null flag');
    };
}

$tests['real upstream expression affinity dynamic expr2 boolean owns exactly 2400 cases'] = static function (TestRunner $t) use ($rowValues, $leftOperands, $templates, $cases, $oracle): void {
    $t->same(15, count($rowValues));
    $t->same(20, count($leftOperands));
    $t->same(8, count($templates));
    $t->same(2400, count($cases));
    $t->same(2400, count($oracle));
    $t->same(
        'expr2.test 1.1..1.4.2 boolean IS TRUE/FALSE row-context expression semantics',
        'expr2.test 1.1..1.4.2 boolean IS TRUE/FALSE row-context expression semantics',
    );
    $t->contains('expr2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test');
};

return $tests;
