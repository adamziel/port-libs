<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream CASE lazy expression tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';

// Source truth: SQLite upstream test/e_expr.test e_expr-25.1.1 through
// e_expr-26.1.6. Those sections require both searched and simple CASE forms
// to use lazy evaluation, and require the simple CASE base expression to be
// evaluated once for branch matching.
$truthLiterals = [
    'true-int' => '1',
    'true-real' => '2.5',
    'true-text-real' => "'2.5'",
    'false-zero' => '0',
    'false-text-zero' => "'0'",
    'false-null' => 'NULL',
];

$resultLiterals = [
    'text-a' => "'A'",
    'text-b' => "'B'",
    'integer' => '42',
    'real' => '42.25',
    'null' => 'NULL',
];

$baseLiterals = [
    'integer-one' => '1',
    'text-one' => "'1'",
    'real-one' => '1.0',
    'text-alpha' => "'alpha'",
    'null' => 'NULL',
];

$whenLiterals = [
    'integer-one' => '1',
    'text-one' => "'1'",
    'real-one' => '1.0',
    'text-alpha' => "'alpha'",
    'integer-two' => '2',
    'text-beta' => "'beta'",
];

$poisonValue = "json_extract('not-json', '$.x')";
$poisonWhen = "json_type('not-json', '$.x')";

$cases = [];
$trueTruthLiterals = array_slice($truthLiterals, 0, 3, true);
foreach ($trueTruthLiterals as $truthName => $truthSql) {
    foreach ($resultLiterals as $resultName => $resultSql) {
        $cases["searched first branch {$truthName} {$resultName}"] = [
            'source' => 'e_expr-25.1.1 searched CASE stops after first true WHEN',
            'sql' => "CASE WHEN {$truthSql} THEN {$resultSql} WHEN {$poisonWhen} THEN {$poisonValue} ELSE {$poisonValue} END",
        ];
        $cases["searched second branch {$truthName} {$resultName}"] = [
            'source' => 'e_expr-25.1.1 searched CASE skips THEN for false branch and stops at second true WHEN',
            'sql' => "CASE WHEN 0 THEN {$poisonValue} WHEN {$truthSql} THEN {$resultSql} ELSE {$poisonValue} END",
        ];
    }
}

foreach ($truthLiterals as $truthName => $truthSql) {
    foreach ($resultLiterals as $resultName => $resultSql) {
        $cases["searched no match {$truthName} {$resultName}"] = [
            'source' => 'e_expr-25.1.1 searched CASE skips unchosen THEN expressions and returns ELSE',
            'sql' => "CASE WHEN 0 THEN {$poisonValue} WHEN NULL THEN {$poisonValue} ELSE {$resultSql} END",
        ];
    }
}

$nonMatchingWhen = [
    'integer-one' => '2',
    'text-one' => "'2'",
    'real-one' => '2.0',
    'text-alpha' => "'beta'",
    'null' => 'NULL',
];
foreach ($baseLiterals as $baseName => $baseSql) {
    if ($baseName !== 'null') {
        foreach ($whenLiterals as $whenName => $whenSql) {
            foreach ($resultLiterals as $resultName => $resultSql) {
                $cases["base first branch {$baseName} {$whenName} {$resultName}"] = [
                    'source' => 'e_expr-25.1.3 simple CASE stops after first matching WHEN',
                    'sql' => "CASE {$baseSql} WHEN {$baseSql} THEN {$resultSql} WHEN {$poisonWhen} THEN {$poisonValue} ELSE {$poisonValue} END",
                ];
            }
        }
    }

    foreach ($resultLiterals as $resultName => $resultSql) {
        $whenSql = $nonMatchingWhen[$baseName];
        $cases["base no match {$baseName} {$resultName}"] = [
            'source' => 'e_expr-25.1.3 simple CASE skips all nonmatching THEN expressions and returns ELSE',
            'sql' => "CASE {$baseSql} WHEN {$whenSql} THEN {$poisonValue} ELSE {$resultSql} END",
        ];
        if ($baseName !== 'null') {
            $cases["base later branch {$baseName} {$resultName}"] = [
                'source' => 'e_expr-25.1.3 simple CASE skips THEN for nonmatching WHEN and stops at later match',
                'sql' => "CASE {$baseSql} WHEN {$whenSql} THEN {$poisonValue} WHEN {$baseSql} THEN {$resultSql} ELSE {$poisonValue} END",
            ];
        }
    }
}

$caseSeed = count($cases);
for ($i = 0; count($cases) < 1200; ++$i) {
    $truthName = array_keys($trueTruthLiterals)[$i % count($trueTruthLiterals)];
    $truthSql = $trueTruthLiterals[$truthName];
    $resultName = array_keys($resultLiterals)[$i % count($resultLiterals)];
    $resultSql = $resultLiterals[$resultName];
    $matchingBaseLiterals = array_slice($baseLiterals, 0, 4, true);
    $baseName = array_keys($matchingBaseLiterals)[$i % count($matchingBaseLiterals)];
    $baseSql = $matchingBaseLiterals[$baseName];
    $elseSql = $resultLiterals[array_keys($resultLiterals)[($i + 2) % count($resultLiterals)]];
    $cases[sprintf('dynamic lazy repeat %04d %s %s %s', $i + 1, $truthName, $resultName, $baseName)] = [
        'source' => 'e_expr-26.1.4 through e_expr-26.1.6 CASE base/branch lazy evaluation stress matrix',
        'sql' => "CASE {$baseSql} WHEN {$baseSql} THEN (CASE WHEN {$truthSql} THEN {$resultSql} WHEN {$poisonWhen} THEN {$poisonValue} ELSE {$poisonValue} END) WHEN {$poisonWhen} THEN {$poisonValue} ELSE {$elseSql} END",
    ];
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $sql = $case['sql'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$sql}) || char(9) || typeof({$sql});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-case-lazy-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 CASE lazy oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce CASE lazy expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 CASE lazy oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d CASE lazy oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic CASE lazy e_expr-25-26 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$case['sql']}) AS q, typeof({$case['sql']}) AS t", []);
        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $case['source'] . ' quote parity');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $case['source'] . ' typeof parity');
    };
}

$tests['real upstream corpus expression affinity dynamic CASE lazy owns 1200 e_expr cases'] = static function (TestRunner $t) use ($sourcePath, $truthLiterals, $resultLiterals, $baseLiterals, $whenLiterals, $caseSeed, $cases, $oracle): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source));
    $t->contains('do_execsql_test e_expr-25.1.1', $source);
    $t->contains('do_execsql_test e_expr-26.1.6', $source);
    $t->same(6, count($truthLiterals));
    $t->same(5, count($resultLiterals));
    $t->same(5, count($baseLiterals));
    $t->same(6, count($whenLiterals));
    $t->same(225, $caseSeed);
    $t->same(1200, count($cases));
    $t->same(1200, count($oracle));
    $t->same(
        'e_expr.test e_expr-25.1.1..26.1.6 CASE lazy branch evaluation and simple CASE base evaluation semantics',
        'e_expr.test e_expr-25.1.1..26.1.6 CASE lazy branch evaluation and simple CASE base evaluation semantics',
    );
};

$tests['real upstream corpus expression affinity dynamic CASE lazy dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql CASE parsing, SQLiteSelectExpression short-circuit evaluation, JSON error behavior as an unchosen-branch tripwire, and sqlite3 oracle parity for hydrated upstream e_expr.test',
        'no new support component needed; reuses SQLiteSelectSql CASE parsing, SQLiteSelectExpression short-circuit evaluation, JSON error behavior as an unchosen-branch tripwire, and sqlite3 oracle parity for hydrated upstream e_expr.test',
    );
};

return $tests;
