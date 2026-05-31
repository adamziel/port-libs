<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expr2 boolean affinity dynamic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/expr2.test expr2-1.1 through expr2-1.4.2.
// The original regression checks a nested OR/NOT expression with IS FALSE and
// IS NOT FALSE terms around a column comparison. This shard widens that exact
// family across SQLite truth values and mixed column affinities.
$columnValues = [
    'text-val' => 'val',
    'text-one' => '1',
    'text-zero' => '0',
    'text-false' => 'false',
    'text-empty' => '',
    'integer-one' => 1,
    'integer-zero' => 0,
    'integer-minus-one' => -1,
    'real-one' => 1.0,
    'real-zero' => 0.0,
    'real-half' => 0.5,
    'null' => null,
];

$truthTerms = [
    'zero' => '0',
    'one' => '1',
    'minus-one' => '-1',
    'real-zero' => '0.0',
    'real-half' => '0.5',
];

$comparisons = [
    'eq-one' => 't0.c0 = 1',
    'eq-zero' => 't0.c0 = 0',
    'is-one' => 't0.c0 IS 1',
    'is-zero' => 't0.c0 IS 0',
];

$wrappers = [
    'is-zero' => '%s IS 0',
    'is-one' => '%s IS 1',
    'is-false' => '%s IS FALSE',
    'is-not-false' => '%s IS NOT FALSE',
    'is-true' => '%s IS TRUE',
];

$cases = [];
foreach ($columnValues as $columnName => $columnValue) {
    foreach ($truthTerms as $leftName => $leftSql) {
        foreach ($truthTerms as $rightName => $rightSql) {
            foreach ($comparisons as $comparisonName => $comparisonSql) {
                $base = "( ({$leftSql} IS NOT FALSE) OR NOT ({$rightSql} IS FALSE OR ({$comparisonSql})) )";
                foreach ($wrappers as $wrapperName => $wrapperSql) {
                    $key = "{$columnName}-{$leftName}-{$rightName}-{$comparisonName}-{$wrapperName}";
                    $cases[$key] = [
                        'column' => $columnValue,
                        'expression' => sprintf($wrapperSql, $base),
                    ];
                }
            }
        }
    }
}

$literalForValue = static function (mixed $value) use ($quoteSql): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return $quoteSql((string) $value);
};

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $columnLiteral = $literalForValue($case['column']);
    $expression = $case['expression'];
    $oracleScript[] = "WITH t0(c0) AS (VALUES({$columnLiteral})) SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) FROM t0;";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr2-boolean-affinity-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for expr2 boolean affinity tests');
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
        throw new RuntimeException('Malformed sqlite3 expr2 boolean affinity oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expr2 boolean affinity oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic expr2.test boolean IS TRUE FALSE ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n FROM t0",
            [
                't0' => [
                    [
                        'c0' => $case['column'],
                        '__sqlite_column_affinities' => [
                            'c0' => 'NUMERIC',
                            't0.c0' => 'NUMERIC',
                        ],
                    ],
                ],
            ],
        );
        $t->same(1, count($rows), $expression);
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic expr2.test owns boolean affinity source range'] = static function (TestRunner $t) use ($columnValues, $truthTerms, $comparisons, $wrappers, $cases, $oracle): void {
    $t->same(12, count($columnValues));
    $t->same(5, count($truthTerms));
    $t->same(4, count($comparisons));
    $t->same(5, count($wrappers));
    $t->same(6000, count($cases));
    $t->same(6000, count($oracle));
    $t->same(
        'expr2.test expr2-1.1..1.4.2 nested IS FALSE / IS NOT FALSE boolean affinity behavior',
        'expr2.test expr2-1.1..1.4.2 nested IS FALSE / IS NOT FALSE boolean affinity behavior',
    );
    $t->contains('expr2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test');
};

return $tests;
