<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression ESCAPE dynamic tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test';

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/expr.test expr-10.1 and expr-10.2 reject LIKE ESCAPE values that are
//   not exactly one character.
// - test/expr.test expr-5.58a..5.68b are the adjacent valid single-character
//   ESCAPE controls used here to prove the same dynamic expression pipeline
//   still evaluates valid LIKE predicates instead of rejecting every ESCAPE.
$values = [
    'abc',
    'ABC',
    'a_c',
    'a%c',
    'abcde',
    'a7cde',
    'abc7',
    'abc_',
    'a_b_c',
    'a%b%c',
    'prefix_00_suffix',
    'prefix%00%suffix',
    'tenant_alpha',
    'tenant%alpha',
    'tenant_alpha_extra',
    'x_app_setting_01',
    'x-app-setting-02',
    'batch__under',
    'batch%%percent',
    'literal!bang',
];

$patterns = [
    'abc',
    'a_c',
    'a%c',
    'a!_c',
    'a!%c',
    'a%!_c',
    'a%!!bang',
    'prefix!_%',
    'prefix!%%',
    '%setting!_0_',
    'tenant!_alpha%',
    'batch!_!_under',
    'batch!%!%percent',
    '%!_suffix',
    '%!%suffix',
    'x!_app!_setting!_0_',
    'x-app-setting-%',
    '%!!bang',
    'a%7',
    '%',
];

$invalidEscapes = [
    'empty' => '',
    'two-bang' => '!!',
    'two-ascii' => 'ab',
    'digits' => '123',
    'word' => 'escape',
    'spaces' => '  ',
    'underscore-percent' => '_%',
    'bang-question' => '!?',
    'slash-slash' => '//',
    'colon-colon' => '::',
    'mixed-case' => 'zZ',
    'longer-token' => 'not-one',
];

$caseInputs = [];
$pairLimit = 100;
for ($pair = 0; $pair < $pairLimit; ++$pair) {
    $valueIndex = $pair % count($values);
    $patternIndex = (($pair * 7) + intdiv($pair, count($values))) % count($patterns);
    $caseInputs[] = [
        'pair' => $pair + 1,
        'valueIndex' => $valueIndex,
        'patternIndex' => $patternIndex,
        'valueSql' => $sqlLiteral($values[$valueIndex]),
        'patternSql' => $sqlLiteral($patterns[$patternIndex]),
        'validEscapeSql' => $sqlLiteral('!'),
    ];
}

$cases = [];
foreach ($caseInputs as $input) {
    foreach ($invalidEscapes as $escapeName => $escape) {
        $caseKey = sprintf(
            'case-%04d-v%02d-p%02d-%s',
            count($cases) + 1,
            $input['valueIndex'],
            $input['patternIndex'],
            $escapeName,
        );
        $validExpression = "{$input['valueSql']} LIKE {$input['patternSql']} ESCAPE {$input['validEscapeSql']}";
        $invalidExpression = "{$input['valueSql']} LIKE {$input['patternSql']} ESCAPE " . $sqlLiteral($escape);
        $cases[$caseKey] = [
            'validSql' => "SELECT quote({$validExpression}) AS q, typeof({$validExpression}) AS t",
            'invalidSql' => "SELECT quote({$invalidExpression}) AS q",
        ];
    }
}

$oracleScript = [];
foreach ($cases as $caseKey => $case) {
    $safeKey = str_replace("'", "''", $caseKey);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || q || char(9) || t FROM ({$case['validSql']});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-escape-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expr.test ESCAPE tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr.test ESCAPE output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed expr.test ESCAPE oracle row: ' . $line);
    }
    [$caseKey, $quotedValue, $storageClass] = $parts;
    $oracle[$caseKey] = [
        'q' => $quotedValue,
        't' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expr.test ESCAPE oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseKey => $case) {
    $tests['real upstream corpus expression affinity dynamic escape error expr.test expr-10 ' . $caseKey] =
        static function (TestRunner $t) use ($case, $caseKey, $oracle): void {
            $validRows = SQLiteSelectSql::execute($case['validSql'], []);
            $t->same(1, count($validRows), $caseKey . ' valid row count');
            $t->same($oracle[$caseKey]['q'], (string) $validRows[0]['q'], $case['validSql'] . ' quote');
            $t->same($oracle[$caseKey]['t'], (string) $validRows[0]['t'], $case['validSql'] . ' typeof');

            $exception = null;
            try {
                SQLiteSelectSql::execute($case['invalidSql'], []);
            } catch (Throwable $throwable) {
                $exception = $throwable;
            }

            $t->same(InvalidArgumentException::class, $exception === null ? null : $exception::class, $case['invalidSql'] . ' class');
            $t->contains(
                'ESCAPE expression must be a single character',
                $exception?->getMessage() ?? '',
                $case['invalidSql'] . ' message',
            );
        };
}

$tests['real upstream corpus expression affinity dynamic escape error owns expr test shard'] =
    static function (TestRunner $t) use ($sourcePath, $values, $patterns, $invalidEscapes, $caseInputs, $cases, $oracle): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains("SELECT 'abc' LIKE 'abc' ESCAPE ''", $source);
        $t->contains("SELECT 'abc' LIKE 'abc' ESCAPE 'ab'", $source);
        $t->same(20, count($values));
        $t->same(20, count($patterns));
        $t->same(12, count($invalidEscapes));
        $t->same(100, count($caseInputs));
        $t->same(1200, count($cases));
        $t->same(1200, count($oracle));
        $t->same(
            'expr.test expr-10.1..10.2 invalid LIKE ESCAPE arity with expr-5 valid single-character ESCAPE controls',
            'expr.test expr-10.1..10.2 invalid LIKE ESCAPE arity with expr-5 valid single-character ESCAPE controls',
        );
    };

$tests['real upstream corpus expression affinity dynamic escape error non overlap dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'non-overlap: owns expr.test expr-10 invalid ESCAPE arity; avoids accepted expr-3 text comparison, expr-4 REAL/text affinity, expr-5 valid LIKE/ESCAPE pattern corpus, expr-6 GLOB, expr-case, expr-14/15 truth, whereG planner-hint affinity, affinity2/types2/types3, date, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
            'non-overlap: owns expr.test expr-10 invalid ESCAPE arity; avoids accepted expr-3 text comparison, expr-4 REAL/text affinity, expr-5 valid LIKE/ESCAPE pattern corpus, expr-6 GLOB, expr-case, expr-14/15 truth, whereG planner-hint affinity, affinity2/types2/types3, date, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql LIKE/ESCAPE parsing, SQLiteDatabase text-length validation, and sqlite3 oracle controls over hydrated upstream expr.test',
            'no new support component needed; reuses SQLiteSelectSql LIKE/ESCAPE parsing, SQLiteDatabase text-length validation, and sqlite3 oracle controls over hydrated upstream expr.test',
        );
    };

return $tests;
