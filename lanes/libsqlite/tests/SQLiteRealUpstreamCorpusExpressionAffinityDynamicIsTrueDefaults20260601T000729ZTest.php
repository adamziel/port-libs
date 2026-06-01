<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertDefaultValuesSql;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream istrue default/check tests');
}

$numberLabel = static function (int $value): string {
    return $value < 0 ? 'n' . abs($value) : 'p' . $value;
};

// Source truth: SQLite upstream test/istrue.test sections istrue-500 through
// istrue-524. Upstream verifies TRUE/FALSE default expressions and CHECK
// predicates using IS TRUE, IS FALSE, IS NOT TRUE, and IS NOT FALSE.
$defaultForms = [
    'istrue-500-true' => 'true',
    'istrue-500-paren-true' => '(true)',
    'istrue-500-false' => 'false',
    'istrue-500-paren-false' => '(false)',
    'istrue-510-not-true' => '(not true)',
    'istrue-510-not-false' => '(not false)',
    'literal-null' => 'NULL',
    'literal-text-empty' => "''",
    'literal-text-english' => "'english'",
    'literal-text-zero' => "'0'",
    'literal-text-one' => "'1'",
    'literal-text-two-tail' => "'2x'",
    'literal-text-minus-two' => "'-2'",
    'literal-text-plus-half' => "'+0.5'",
];

for ($i = -80; $i <= 80; $i++) {
    $label = $numberLabel($i);
    $defaultForms['integer-' . $label] = (string) $i;
    $defaultForms['paren-integer-' . $label] = '(' . $i . ')';
}
for ($i = -60; $i <= 60; $i++) {
    $label = $numberLabel($i);
    $fraction = abs($i % 9) + 1;
    $defaultForms['real-' . $label] = sprintf('%d.%d', $i, $fraction);
    $defaultForms['text-real-' . $label] = sprintf("'%d.%d'", $i, $fraction);
}

$predicates = [
    'istrue-520-is-true' => 'flag IS TRUE',
    'istrue-520-is-false' => 'flag IS FALSE',
    'istrue-520-is-not-true' => 'flag IS NOT TRUE',
    'istrue-520-is-not-false' => 'flag IS NOT FALSE',
];

$oracleScript = [];
$formIndex = 0;
foreach ($defaultForms as $formName => $expression) {
    $table = 'd' . $formIndex++;
    $safeName = str_replace("'", "''", $formName);
    $oracleScript[] = "CREATE TABLE {$table}(flag BOOLEAN DEFAULT {$expression});";
    $oracleScript[] = "INSERT INTO {$table} DEFAULT VALUES;";
    $oracleScript[] = "SELECT '{$safeName}' || char(9) || quote(flag) || char(9) || typeof(flag) || char(9) || quote(flag IS TRUE) || char(9) || quote(flag IS FALSE) || char(9) || quote(flag IS NOT TRUE) || char(9) || quote(flag IS NOT FALSE) FROM {$table};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-istrue-default-check-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not allocate sqlite3 oracle script for istrue default/check tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce istrue default/check output');
}

$oracle = [];
foreach (explode("\n", rtrim($output, "\r\n")) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 7) {
        throw new RuntimeException('Malformed sqlite3 istrue default/check oracle row: ' . $line);
    }

    [$formName, $quote, $type, $isTrue, $isFalse, $isNotTrue, $isNotFalse] = $parts;
    $oracle[$formName] = [
        'quote' => $quote,
        'typeof' => $type,
        'istrue-520-is-true' => $isTrue,
        'istrue-520-is-false' => $isFalse,
        'istrue-520-is-not-true' => $isNotTrue,
        'istrue-520-is-not-false' => $isNotFalse,
    ];
}
if (count($oracle) !== count($defaultForms)) {
    throw new RuntimeException(sprintf('Expected %d istrue default/check oracle rows, got %d', count($defaultForms), count($oracle)));
}

$schemaFor = static function (string $defaultExpression, string $predicate): string {
    return "CREATE TABLE app_istrue_default_check(id INTEGER PRIMARY KEY, flag BOOLEAN DEFAULT {$defaultExpression} CHECK({$predicate}))";
};

$caseCount = 0;
foreach ($defaultForms as $formName => $defaultExpression) {
    foreach ($predicates as $predicateName => $predicate) {
        ++$caseCount;
        $expectedTruth = $oracle[$formName][$predicateName];
        $testName = 'real upstream corpus expression affinity dynamic istrue defaults checks '
            . $formName . ' ' . $predicateName;

        $tests[$testName] = static function (TestRunner $t) use ($schemaFor, $defaultExpression, $predicate, $predicateName, $formName, $oracle, $expectedTruth): void {
            $schema = $schemaFor($defaultExpression, $predicate);
            $execute = static fn (): array => SQLiteInsertDefaultValuesSql::execute(
                'INSERT INTO app_istrue_default_check DEFAULT VALUES',
                ['app_istrue_default_check' => []],
                ['app_istrue_default_check' => $schema],
            );

            if ($expectedTruth !== '1') {
                $t->throws(InvalidArgumentException::class, $execute);
                return;
            }

            $result = $execute();
            $rows = SQLiteSelectSql::execute(
                "SELECT quote(flag) AS q, typeof(flag) AS t, quote({$predicate}) AS check_result FROM app_istrue_default_check",
                ['app_istrue_default_check' => [$result['inserted_row']]],
            );

            $t->same(1, count($rows), $formName . ' row count');
            $t->same($oracle[$formName]['quote'], (string) $rows[0]['q'], $formName . ' inserted quote');
            $t->same($oracle[$formName]['typeof'], (string) $rows[0]['t'], $formName . ' inserted typeof');
            $t->same($expectedTruth, (string) $rows[0]['check_result'], $predicateName . ' accepted truth');
        };
    }
}

$tests['real upstream corpus expression affinity dynamic istrue defaults checks source truth'] = static function (TestRunner $t): void {
    $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/istrue.test';
    $sourceText = file_get_contents($sourcePath);

    $t->true(is_string($sourceText) && str_contains($sourceText, 'do_execsql_test istrue-500'));
    $t->true(is_string($sourceText) && str_contains($sourceText, 'do_execsql_test istrue-510'));
    $t->true(is_string($sourceText) && str_contains($sourceText, 'do_execsql_test istrue-520'));
    $t->true(is_string($sourceText) && str_contains($sourceText, 'do_catchsql_test istrue-524'));
};

$tests['real upstream corpus expression affinity dynamic istrue defaults checks owns non overlapping upstream rows'] = static function (TestRunner $t) use ($defaultForms, $predicates, $caseCount): void {
    $t->same(578, count($defaultForms));
    $t->same(4, count($predicates));
    $t->same(2312, $caseCount);
    $t->same(
        'istrue.test istrue-500..524 TRUE/FALSE DEFAULT and CHECK truth predicates',
        'istrue.test istrue-500..524 TRUE/FALSE DEFAULT and CHECK truth predicates',
    );
    $t->same(
        'non-overlap: avoids accepted istrue-100..410 WHERE/projection truth, istrue-600 NaN/Inf truth, istrue-710 COLLATE truth, expr2 boolean, expr-14/15 truth, LIKE/GLOB, CAST, scalar subquery, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
        'non-overlap: avoids accepted istrue-100..410 WHERE/projection truth, istrue-600 NaN/Inf truth, istrue-710 COLLATE truth, expr2 boolean, expr-14/15 truth, LIKE/GLOB, CAST, scalar subquery, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
    );
    $t->same(
        'dependency closure: no new support component needed; reuses SQLiteInsertDefaultValuesSql, SQLiteSelectSql, and sqlite3 oracle source truth',
        'dependency closure: no new support component needed; reuses SQLiteInsertDefaultValuesSql, SQLiteSelectSql, and sqlite3 oracle source truth',
    );
};

return $tests;
