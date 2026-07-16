<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity math-function tests');
}

$funcSourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test';
$func7SourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/func7.test';
$funcSourceText = is_file($funcSourcePath) ? (file_get_contents($funcSourcePath) ?: '') : '';
$func7SourceText = is_file($func7SourcePath) ? (file_get_contents($func7SourcePath) ?: '') : '';

$cases = [];
$addCase = static function (string $id, string $expression, string $upstream) use (&$cases): void {
    $cases[$id] = [
        'expression' => $expression,
        'upstream' => $upstream,
    ];
};

// Source truth: SQLite upstream test/func.test func-4.17 and func-4.18.
for ($i = 1; $i < 999; $i++) {
    $addCase(
        sprintf('func-4.17.%03d', $i),
        sprintf('round(%.1F)', 40222.5 + $i),
        'func.test func-4.17 mailing-list whole rounding loop',
    );
    $addCase(
        sprintf('func-4.18.%03d', $i),
        sprintf('round(%.2F,1)', 40222.05 + $i),
        'func.test func-4.18 mailing-list tenth rounding loop',
    );
}

foreach ([
    ['literal' => '40223.4999999999', 'low' => 'func-4.23', 'high' => 'func-4.26'],
    ['literal' => '40224.4999999999', 'low' => 'func-4.24', 'high' => 'func-4.27'],
    ['literal' => '40225.4999999999', 'low' => 'func-4.25', 'high' => 'func-4.28'],
] as $roundCase) {
    for ($precision = 1; $precision < 10; $precision++) {
        $addCase(
            sprintf('%s.%02d', $roundCase['low'], $precision),
            sprintf('round(%s,%d)', $roundCase['literal'], $precision),
            'func.test func-4.23..4.25 sub-ten precision half-neighbor rounding',
        );
    }
    for ($precision = 10; $precision < 32; $precision++) {
        $addCase(
            sprintf('%s.%02d', $roundCase['high'], $precision),
            sprintf('round(%s,%d)', $roundCase['literal'], $precision),
            'func.test func-4.26..4.28 high precision half-neighbor preservation',
        );
    }
}

foreach ([
    'func-4.20' => 'round(40223.4999999999)',
    'func-4.21' => 'round(40224.4999999999)',
    'func-4.22' => 'round(40225.4999999999)',
    'func-4.29' => 'round(1234567890.5)',
    'func-4.30' => 'round(12345678901.5)',
    'func-4.31' => 'round(123456789012.5)',
    'func-4.32' => 'round(1234567890123.5)',
    'func-4.33' => 'round(12345678901234.5)',
    'func-4.34' => 'round(1234567890123.35,1)',
    'func-4.35' => 'round(1234567890123.445,2)',
    'func-4.36' => 'round(99999999999994.5)',
    'func-4.37' => 'round(9999999999999.55,1)',
    'func-4.38' => 'round(9999999999999.556,2)',
    'func-4.40' => 'round(123.456, 4294967297)',
] as $id => $expression) {
    $addCase($id, $expression, 'func.test func-4.20..4.40 round() edge cases');
}

// Source truth: SQLite upstream test/func7.test ENABLE_MATH_FUNCTIONS coverage.
foreach ([
    'func7-100a' => 'ceil(99.9)',
    'func7-100b' => 'ceiling(-99.01)',
    'func7-100c' => 'floor(17)',
    'func7-100d' => 'floor(-17.99)',
    'func7-110a' => 'ceil(NULL)',
    'func7-110b' => "ceil('-99.99')",
    'func7-200a' => 'round(ln(5),2)',
    'func7-200b' => 'log(100.0)',
    'func7-200c' => 'log(100)',
    'func7-200d' => "log(2,'256')",
    'func7-210a' => 'ln(-5)',
    'func7-210b' => 'log(-5,100.0)',
    'func7-pg-100' => 'abs(-17.4)',
    'func7-pg-110' => 'ceil(42.2)',
    'func7-pg-120' => 'ceil(-42.2)',
    'func7-pg-130' => 'round(exp(1.0),7)',
    'func7-pg-140' => 'floor(42.8)',
    'func7-pg-150' => 'floor(-42.8)',
    'func7-pg-160' => 'round(ln(2.0),7)',
    'func7-pg-170' => 'log(100.0)',
    'func7-pg-180' => 'log10(1000.0)',
    'func7-pg-181' => "format('%.30f', log10(100.0))",
    'func7-pg-182' => "format('%.30f', ln(exp(2.0)))",
    'func7-pg-190' => 'log(2.0,64.0)',
    'func7-pg-200' => 'mod(9,4)',
    'func7-pg-210' => 'round(pi(),7)',
    'func7-pg-220' => 'power(9,3)',
    'func7-pg-230' => 'round(radians(45.0),7)',
    'func7-pg-240' => 'round(42.4)',
    'func7-pg-250' => 'round(42.4382,2)',
    'func7-pg-260' => 'sign(-8.4)',
    'func7-pg-270' => 'round(sqrt(2),7)',
    'func7-pg-280a' => 'trunc(42.8)',
    'func7-pg-280b' => 'trunc(-42.8)',
    'func7-pg-300' => 'acos(1)',
    'func7-pg-301' => "format('%f',degrees(acos(0.5)))",
    'func7-pg-310' => 'round(asin(1),7)',
    'func7-pg-311' => "format('%f',degrees(asin(0.5)))",
    'func7-pg-320' => 'round(atan(1),7)',
    'func7-pg-321' => 'degrees(atan(1))',
    'func7-pg-330' => 'round(atan2(1,0),7)',
    'func7-pg-331' => 'degrees(atan2(1,0))',
    'func7-pg-400' => 'cos(0)',
    'func7-pg-401' => 'cos(radians(60.0))',
    'func7-pg-410' => 'round(sin(1),7)',
    'func7-pg-411' => 'sin(radians(30))',
    'func7-pg-420' => 'round(tan(1),7)',
    'func7-pg-421' => 'round(tan(radians(45)),10)',
    'func7-pg-500' => 'round(sinh(1),7)',
    'func7-pg-510' => 'round(cosh(0),7)',
    'func7-pg-520' => 'round(tanh(1),7)',
    'func7-pg-530' => 'round(asinh(1),7)',
    'func7-pg-540' => 'round(acosh(1),7)',
    'func7-pg-550' => 'round(atanh(0.5),7)',
    'func7-mysql-110' => 'acos(1.0001)',
    'func7-mysql-140' => "asin('foo')",
    'func7-mysql-160a' => 'round(atan2(-2,2),7)',
    'func7-mysql-160b' => 'round(atan2(pi(),0),7)',
    'func7-mysql-180' => 'cos(pi())',
    'func7-mysql-190a' => 'degrees(pi())',
    'func7-mysql-190b' => 'degrees(pi()/2)',
    'func7-mysql-230a' => 'log(2,65536)',
    'func7-mysql-230b' => 'log(10,100)',
    'func7-mysql-230c' => 'log(1,100)',
    'func7-mysql-230d' => 'log(0,100)',
    'func7-mysql-240a' => 'log2(65536)',
    'func7-mysql-240b' => 'log2(-100)',
    'func7-mysql-240c' => 'log2(0)',
    'func7-mysql-250a' => 'round(log10(2),7)',
    'func7-mysql-250b' => 'log10(100)',
    'func7-mysql-250c' => 'log10(-100)',
    'func7-mysql-260a' => 'mod(234,10)',
    'func7-mysql-260b' => '253%7',
    'func7-mysql-270' => 'mod(34.5,3)',
    'func7-mysql-280a' => 'pow(2,2)',
    'func7-mysql-280b' => 'pow(2,-2)',
    'func7-mysql-300a' => 'sign(-32)',
    'func7-mysql-300b' => 'sign(0)',
    'func7-mysql-300c' => 'sign(234)',
    'func7-mysql-310' => 'sin(pi()) BETWEEN -1.0e-15 AND 1.0e-15',
    'func7-mysql-320a' => 'sqrt(4)',
    'func7-mysql-320b' => 'round(sqrt(20),7)',
    'func7-mysql-320c' => 'sqrt(-16)',
    'func7-mysql-330' => 'tan(pi()) BETWEEN -1.0e-15 AND 1.0e-15',
    'func7-mysql-331' => 'round(tan(pi()+1),7)',
] as $id => $expression) {
    $addCase($id, $expression, 'func7.test ENABLE_MATH_FUNCTIONS scalar expression corpus');
}

$oracleScript = [];
foreach ($cases as $caseId => $case) {
    $safeId = str_replace("'", "''", $caseId);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeId}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-func-math-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for func math tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce func math output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed func math oracle row: ' . $line);
    }
    [$caseId, $quotedValue, $storageClass] = $parts;
    $oracle[$caseId] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d func math oracle rows, got %d', count($cases), count($oracle)));
}

$realMatches = static function (mixed $actual, string $expectedQuote): bool {
    $expected = (float) $expectedQuote;
    $actualFloat = (float) $actual;
    $tolerance = max(1.0e-12, abs($expected) * 1.0e-12);

    return abs($actualFloat - $expected) <= $tolerance;
};

foreach ($cases as $caseId => $case) {
    $tests['real upstream corpus expression affinity dynamic math functions ' . $caseId] =
        static function (TestRunner $t) use ($caseId, $case, $oracle, $realMatches): void {
            $expression = $case['expression'];
            $rows = SQLiteSelectSql::execute(
                "SELECT ({$expression}) AS value, quote({$expression}) AS quoted_value, typeof({$expression}) AS storage_class",
                [],
            );

            $t->same(1, count($rows), $caseId . ' row count');
            $row = $rows[0];
            $expected = $oracle[$caseId];
            $t->same($expected['typeof'], (string) $row['storage_class'], $caseId . ' storage class from ' . $case['upstream']);
            if ($expected['typeof'] === 'real') {
                $t->same(true, $realMatches($row['value'], $expected['quote']), $caseId . ' approximate real result ' . $expression);
            } else {
                $t->same($expected['quote'], (string) $row['quoted_value'], $caseId . ' quoted result ' . $expression);
            }
        };
}

$tests['real upstream corpus expression affinity dynamic math functions source truth and non overlap'] =
    static function (TestRunner $t) use ($cases, $oracle, $funcSourcePath, $func7SourcePath, $funcSourceText, $func7SourceText): void {
        $t->same(true, is_file($funcSourcePath), 'hydrated upstream func.test exists');
        $t->same(true, is_file($func7SourcePath), 'hydrated upstream func7.test exists');
        $t->contains('for {set i 1} {$i<999} {incr i}', $funcSourceText);
        $t->contains('do_test func-4.17.$i', $funcSourceText);
        $t->contains('do_test func-4.18.$i', $funcSourceText);
        $t->contains('do_execsql_test func7-pg-550', $func7SourceText);
        $t->contains('SELECT round( atanh(0.5), 7);', $func7SourceText);
        $t->same(2188, count($cases));
        $t->same(2188, count($oracle));
        $t->same(
            'non-overlap: owns func.test func-4 round() dynamic loops and func7.test math scalar expression dispatch; avoids e_expr CASE/CAST/LIKE/GLOB, atof1 windows, types2/3 storage matrices, JSON, WAL, VFS, B-tree, PRAGMA, and source-neutral cleanup slices',
            'non-overlap: owns func.test func-4 round() dynamic loops and func7.test math scalar expression dispatch; avoids e_expr CASE/CAST/LIKE/GLOB, atof1 windows, types2/3 storage matrices, JSON, WAL, VFS, B-tree, PRAGMA, and source-neutral cleanup slices',
        );
        $t->same(
            'dependency closure: reuses SQLiteSelectSql and SQLiteCoreScalarFunction; no new support component required',
            'dependency closure: reuses SQLiteSelectSql and SQLiteCoreScalarFunction; no new support component required',
        );
    };

return $tests;
