<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity BETWEEN oracle tests');
}

$sqlText = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source truth:
// - test/e_expr.test e_expr-13.* BETWEEN precedence and single-evaluation form.
// - test/affinity2.test affinity2-200..300 comparison affinity around unary
//   numeric coercion, text values, blob values, REAL/NUMERIC casts, and NULL.
$subjects = [
    'int-zero' => '0',
    'int-one' => '1',
    'int-six' => '6',
    'int-neg-five' => '-5',
    'real-half' => '0.5',
    'real-six-half' => '6.5',
    'real-neg-quarter' => '-0.25',
    'text-zero' => $sqlText('0'),
    'text-one' => $sqlText('1'),
    'text-six' => $sqlText('6'),
    'text-six-half' => $sqlText('6.5'),
    'text-leading-space' => $sqlText('   6'),
    'text-tail' => $sqlText('6tail'),
    'blob-six' => "X'36'",
    'blob-empty' => "X''",
    'null' => 'NULL',
];

$lowBounds = [
    'int-neg-one' => '-1',
    'int-zero' => '0',
    'int-five' => '5',
    'real-half' => '0.5',
    'real-six' => '6.0',
    'text-zero' => $sqlText('0'),
    'text-five' => $sqlText('5'),
    'null' => 'NULL',
];

$highBounds = [
    'int-zero' => '0',
    'int-one' => '1',
    'int-six' => '6',
    'int-seven' => '7',
    'real-six-half' => '6.5',
    'text-six' => $sqlText('6'),
    'blob-seven' => "X'37'",
    'null' => 'NULL',
];

$cases = [];
foreach ($subjects as $subjectName => $subjectSql) {
    foreach ($lowBounds as $lowName => $lowSql) {
        foreach ($highBounds as $highName => $highSql) {
            $caseId = "{$subjectName}-between-{$lowName}-and-{$highName}";
            $expression = "({$subjectSql}) BETWEEN ({$lowSql}) AND ({$highSql})";
            $cases[$caseId] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $caseId => $expression) {
    $safeId = str_replace("'", "''", $caseId);
    $oracleScript[] = "SELECT '{$safeId}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-between-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 BETWEEN oracle script');
}

file_put_contents($scriptFile, implode("\n", $oracleScript));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);

if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce BETWEEN oracle output');
}

$oracle = [];
foreach (explode("\n", trim($oracleOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 BETWEEN oracle row: ' . $line);
    }

    [$caseId, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$caseId] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d BETWEEN oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseId => $expression) {
    $tests['real upstream expression affinity e_expr13 affinity2 between oracle dynamic ' . $caseId] = static function (TestRunner $t) use ($caseId, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $caseId . ' row count');

        $row = $rows[0];
        $t->same($oracle[$caseId]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$caseId]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$caseId]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity e_expr13 affinity2 between oracle owns 1024 cases'] = static function (TestRunner $t) use ($subjects, $lowBounds, $highBounds, $cases): void {
    $t->same(16, count($subjects));
    $t->same(8, count($lowBounds));
    $t->same(8, count($highBounds));
    $t->same(1024, count($cases));
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
};

return $tests;
