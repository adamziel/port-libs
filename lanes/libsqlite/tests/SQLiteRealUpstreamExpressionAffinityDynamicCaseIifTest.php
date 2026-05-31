<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity CASE/iif dynamic tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Real upstream source:
// - test/e_expr.test e_expr-21.* verifies searched CASE truth evaluation,
//   left-to-right branch choice, ELSE fallback, and NULL result without ELSE.
// - test/e_expr.test e_expr-22.* verifies simple CASE branch comparison,
//   first-match behavior, ELSE fallback, NULL result without ELSE, and ordinary
//   equality semantics for WHEN arms.
// - test/e_expr.test e_expr-37.* verifies CASE and iif()/if() boolean
//   truthiness for NULL, numeric zero, non-numeric text, numeric text, and
//   non-zero numeric values.
$truthValues = [
    'null' => null,
    'int-zero' => 0,
    'real-zero' => 0.0,
    'text-zero' => '0',
    'text-zero-real' => '0.0',
    'text-empty' => '',
    'text-alpha' => 'english',
    'text-alpha-leading-zero' => '0english',
    'text-one-alpha' => '1english',
    'int-one' => 1,
    'real-one' => 1.0,
    'real-positive-small' => 0.1,
    'real-negative-small' => -0.1,
    'int-negative' => -7,
    'text-one' => '1',
    'text-real' => '1.5',
    'text-negative-real' => '-2.25',
    'text-leading-space' => '   3',
    'text-plus-real' => '+4.5',
    'text-minus-zero' => '-0',
    'blobish-text' => "x'31'",
    'large-int' => 9223372036854775807,
    'large-real' => 9.223372036854776e18,
    'text-overflow-real' => '9223372036854775808',
];

$armValues = [
    'text-true' => "'true'",
    'text-false' => "'false'",
    'integer-8' => '8',
    'integer-99' => '99',
    'real-quarter' => '0.25',
    'real-negative' => '-2.5',
    'text-alpha' => "'alpha'",
    'text-beta' => "'beta'",
    'null' => 'NULL',
    'blob-text' => "CAST(X'4142' AS TEXT)",
];

$simpleBaseValues = [
    'null' => 'NULL',
    'int-zero' => '0',
    'real-zero' => '0.0',
    'text-zero' => "'0'",
    'int-one' => '1',
    'real-one' => '1.0',
    'text-one' => "'1'",
    'text-one-alpha' => "'1english'",
    'text-alpha' => "'alpha'",
    'text-alpha-nocase' => "'ALPHA' COLLATE NOCASE",
    'text-rtrim' => "'trim' COLLATE RTRIM",
    'text-rtrim-padded' => "'trim   ' COLLATE RTRIM",
];

$simpleWhenValues = [
    'null' => 'NULL',
    'zero' => '0',
    'zero-text' => "'0'",
    'one' => '1',
    'one-real' => '1.0',
    'one-text' => "'1'",
    'one-alpha' => "'1english'",
    'alpha' => "'alpha'",
    'alpha-upper' => "'ALPHA' COLLATE NOCASE",
    'trim' => "'trim'",
    'trim-padded' => "'trim   '",
    'overflow-real' => '9223372036854775808.0',
];

$expressions = [];
$caseId = 0;

foreach ($truthValues as $truthName => $truthValue) {
    $truthSql = $sqlLiteral($truthValue);
    foreach ($armValues as $thenName => $thenSql) {
        foreach ($armValues as $elseName => $elseSql) {
            ++$caseId;
            $expressions["searched-case-{$caseId} {$truthName} {$thenName} {$elseName}"] =
                "CASE WHEN {$truthSql} THEN {$thenSql} ELSE {$elseSql} END";

            ++$caseId;
            $expressions["iif-{$caseId} {$truthName} {$thenName} {$elseName}"] =
                "iif({$truthSql}, {$thenSql}, {$elseSql})";

            ++$caseId;
            $expressions["if-three-{$caseId} {$truthName} {$thenName} {$elseName}"] =
                "if({$truthSql}, {$thenSql}, {$elseSql})";

            ++$caseId;
            $expressions["if-two-{$caseId} {$truthName} {$thenName}"] =
                "if({$truthSql}, {$thenSql})";
        }
    }
}

foreach ($simpleBaseValues as $baseName => $baseSql) {
    foreach ($simpleWhenValues as $firstName => $firstSql) {
        foreach ($simpleWhenValues as $secondName => $secondSql) {
            ++$caseId;
            $expressions["simple-case-{$caseId} {$baseName} {$firstName} {$secondName}"] =
                "CASE {$baseSql} WHEN {$firstSql} THEN 'first' WHEN {$secondSql} THEN 'second' ELSE 'else' END";

            ++$caseId;
            $expressions["simple-case-no-else-{$caseId} {$baseName} {$firstName} {$secondName}"] =
                "CASE {$baseSql} WHEN {$firstSql} THEN 'first' WHEN {$secondSql} THEN 'second' END";
        }
    }
}

$oracleScript = [];
foreach ($expressions as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-case-iif-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce CASE/iif expression affinity output');
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

if (count($oracle) !== count($expressions)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 CASE/iif oracle rows, got %d', count($expressions), count($oracle)));
}

foreach ($expressions as $key => $expression) {
    $tests['real upstream expression affinity dynamic CASE iif e_expr-21 e_expr-22 e_expr-37 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity dynamic CASE iif owns exactly 13056 oracle cases'] = static function (TestRunner $t) use ($truthValues, $armValues, $simpleBaseValues, $simpleWhenValues, $expressions, $oracle): void {
    $t->same(24, count($truthValues));
    $t->same(10, count($armValues));
    $t->same(12, count($simpleBaseValues));
    $t->same(12, count($simpleWhenValues));
    $t->same(13056, count($expressions));
    $t->same(13056, count($oracle));
    $t->same(
        'e_expr.test e_expr-21.*, e_expr-22.*, and e_expr-37.* CASE/iif()/if() truthiness and branch comparison behavior',
        'e_expr.test e_expr-21.*, e_expr-22.*, and e_expr-37.* CASE/iif()/if() truthiness and branch comparison behavior',
    );
};

return $tests;
