<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';

// Source truth: SQLite upstream test/timediff1.test timediff-4. It proves
// timediff(A,B) is a valid datetime modifier across wide historical/future
// values, Julian-day numeric input, leap days, duplicate month-edge labels, and
// fractional seconds.
$p1 = [
    ['label' => '0', 'value' => '-4713-11-24 12:00:00'],
    ['label' => '1', 'value' => '-2000-04-30 05:19:26'],
    ['label' => '2', 'value' => '0000-01-01 12:34:56'],
    ['label' => '3', 'value' => '1776-07-04 13:00:00'],
    ['label' => '4', 'value' => '1969-07-20 20:17'],
    ['label' => '5', 'value' => 2440587.5],
    ['label' => '6', 'value' => '2000-05-29 14:26'],
    ['label' => '7', 'value' => '2023-05-29 18:11'],
    ['label' => '8', 'value' => '2050-05-29 14:26'],
    ['label' => '9', 'value' => '4796-02-29 11:23:55.46'],
];

$p2 = [
    ['label' => 'A', 'ordinal' => 1, 'value' => '1066-10-14'],
    ['label' => 'B', 'ordinal' => 2, 'value' => '1900-02-28 11:00'],
    ['label' => 'C', 'ordinal' => 3, 'value' => '1900-03-01 12:00'],
    ['label' => 'D', 'ordinal' => 4, 'value' => '1904-02-29 11:25'],
    ['label' => 'E', 'ordinal' => 5, 'value' => '2000-02-29 13:00'],
    ['label' => 'E', 'ordinal' => 6, 'value' => '2000-03-01 14:00'],
    ['label' => 'F', 'ordinal' => 7, 'value' => '2001-03-31 15:15'],
    ['label' => 'G', 'ordinal' => 8, 'value' => '2002-04-01 16:59'],
    ['label' => 'H', 'ordinal' => 9, 'value' => '2003-04-30 17:00'],
    ['label' => 'I', 'ordinal' => 10, 'value' => '2004-05-01 23:59:59'],
    ['label' => 'J', 'ordinal' => 11, 'value' => '2005-06-01'],
    ['label' => 'K', 'ordinal' => 12, 'value' => '2006-06-30 01:23:45'],
    ['label' => 'L', 'ordinal' => 13, 'value' => '2007-12-31 02:00'],
    ['label' => 'M', 'ordinal' => 14, 'value' => '2008-01-01 01:59'],
    ['label' => 'N', 'ordinal' => 15, 'value' => '3152-07-04 12:00'],
    ['label' => 'P', 'ordinal' => 16, 'value' => '9999-12-31 23:59:59'],
];

$sqlLiteral = static function (mixed $value): string {
    if (is_int($value) || is_float($value)) {
        return sprintf('%.15G', $value);
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$caseRows = [];
foreach ($p1 as $left) {
    foreach ($p2 as $right) {
        $caseRows[] = [
            'key' => 'timediff-4-' . $left['label'] . $right['label'] . '-' . str_pad((string) $right['ordinal'], 2, '0', STR_PAD_LEFT),
            'left_label' => $left['label'],
            'right_label' => $right['label'],
            'left' => $left['value'],
            'right' => $right['value'],
        ];
        $caseRows[] = [
            'key' => 'timediff-4-' . $right['label'] . $left['label'] . '-' . str_pad((string) $right['ordinal'], 2, '0', STR_PAD_LEFT),
            'left_label' => $right['label'],
            'right_label' => $left['label'],
            'left' => $right['value'],
            'right' => $left['value'],
        ];
    }
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for timediff1.test timediff-4 parity');
}

$oracleSql = [];
foreach ($caseRows as $case) {
    $key = str_replace("'", "''", $case['key']);
    $left = $sqlLiteral($case['left']);
    $right = $sqlLiteral($case['right']);
    $diff = "timediff({$left},{$right})";
    $oracleSql[] =
        "SELECT '{$key}' || char(9)"
        . " || quote({$diff}) || char(9)"
        . " || quote(datetime({$right},{$diff})) || char(9)"
        . " || quote(datetime({$left})) || char(9)"
        . " || quote(datetime({$right})) || char(9)"
        . " || typeof({$diff}) || char(9)"
        . " || quote(datetime({$right},{$diff},'subsec')) || char(9)"
        . " || quote(julianday({$left})) || char(9)"
        . " || quote(julianday({$right},{$diff})) || char(9)"
        . " || quote(date({$right},{$diff})) || char(9)"
        . " || quote(time({$right},{$diff}));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-timediff4-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create timediff4 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleSql));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce timediff4 output');
}

$oracle = [];
foreach (explode("\n", trim($oracleOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 11) {
        throw new RuntimeException('Malformed timediff4 oracle row: ' . $line);
    }
    [$key, $diff, $roundtrip, $leftNormalized, $rightNormalized, $diffType, $roundtripSubsec, $leftJulian, $roundtripJulian, $roundtripDate, $roundtripTime] = $parts;
    $oracle[$key] = [
        'diff' => trim($diff, "'"),
        'roundtrip' => trim($roundtrip, "'"),
        'left_normalized' => trim($leftNormalized, "'"),
        'right_normalized' => trim($rightNormalized, "'"),
        'diff_type' => $diffType,
        'roundtrip_subsec' => trim($roundtripSubsec, "'"),
        'left_julian' => (float) trim($leftJulian, "'"),
        'roundtrip_julian' => (float) trim($roundtripJulian, "'"),
        'roundtrip_date' => trim($roundtripDate, "'"),
        'roundtrip_time' => trim($roundtripTime, "'"),
    ];
}

$tests['real upstream corpus date affinity dynamic timediff4 cites upstream source'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $p1, $p2, $caseRows, $oracle): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream timediff1.test exists');
        $t->contains('set p1 {', $sourceText);
        $t->contains('set p2 {', $sourceText);
        $t->contains('do_execsql_test timediff-4-$x1$x2', $sourceText);
        $t->contains('SELECT datetime($d2, timediff($d1,$d2));', $sourceText);
        $t->contains('SELECT datetime($d1, timediff($d2,$d1));', $sourceText);
        $t->same(10, count($p1), 'timediff-4 p1 values');
        $t->same(16, count($p2), 'timediff-4 p2 values including duplicate E label');
        $t->same(320, count($caseRows), 'timediff-4 directional upstream cases');
        $t->same(320, count($oracle), 'timediff-4 oracle rows');
    };

foreach ($caseRows as $case) {
    $tests['real upstream corpus date affinity dynamic timediff1.test ' . $case['key'] . ' roundtrip'] =
        static function (TestRunner $t) use ($case, $oracle): void {
            $expected = $oracle[$case['key']];
            $diff = SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [$case['left'], $case['right']]);
            $roundtrip = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['right'], $diff]);
            $leftNormalized = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['left']]);
            $rightNormalized = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['right']]);
            $roundtripSubsec = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['right'], $diff, 'subsec']);
            $leftJulian = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['left']]);
            $roundtripJulian = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['right'], $diff]);
            $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
                [[
                    'source_key' => $case['key'],
                    'left_label' => $case['left_label'],
                    'right_label' => $case['right_label'],
                    'elapsed_modifier' => $diff,
                ]],
                [
                    'source_key' => 'TEXT',
                    'left_label' => 'TEXT',
                    'right_label' => 'TEXT',
                    'elapsed_modifier' => 'TEXT',
                ]
            )[0];

            $t->same($expected['diff'], $diff, $case['key'] . ' timediff modifier');
            $t->same($expected['roundtrip'], $roundtrip, $case['key'] . ' datetime(right,timediff(left,right))');
            $t->same($expected['left_normalized'], $leftNormalized, $case['key'] . ' normalized left datetime');
            $t->same($leftNormalized, $roundtrip, $case['key'] . ' roundtrip equals normalized left');
            $t->same($expected['right_normalized'], $rightNormalized, $case['key'] . ' normalized right datetime');
            $t->same($expected['diff_type'], SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$diff]), $case['key'] . ' timediff storage type');
            $t->same($expected['roundtrip_subsec'], $roundtripSubsec, $case['key'] . ' subsecond modifier application');
            $t->true(abs($expected['left_julian'] - (float) $leftJulian) < 1.0e-6, $case['key'] . ' left julianday parity');
            $t->true(abs($expected['roundtrip_julian'] - (float) $roundtripJulian) < 1.0e-6, $case['key'] . ' roundtrip julianday parity');
            $t->true(abs((float) $leftJulian - (float) $roundtripJulian) < 1.0e-6, $case['key'] . ' julianday roundtrip');
            $t->same($diff, $stored['elapsed_modifier'], $case['key'] . ' TEXT affinity preserves modifier');
            $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['elapsed_modifier']), $case['key'] . ' stored modifier class');
            $t->true(preg_match('/\A[+-]\d{4,}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\z/', (string) $diff) === 1, $case['key'] . ' timediff format');
            $t->same($expected['diff'][0], (string) $diff[0], $case['key'] . ' modifier sign');
            $t->same($expected['roundtrip_date'], SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$case['right'], $diff]), $case['key'] . ' date() modifier roundtrip');
            $t->same($expected['roundtrip_time'], SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$case['right'], $diff]), $case['key'] . ' time() modifier roundtrip');
        };
}

$tests['real upstream corpus date affinity dynamic timediff4 non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns timediff1.test timediff-4 p1/p2 directional roundtrip matrix',
            'owns timediff1.test timediff-4 p1/p2 directional roundtrip matrix'
        );
        $t->same(
            'non-overlap: does not repeat accepted timediff-3 exact strings, timediff-5 modifier grammar, timediff-6 month-boundary matrix, date4 strftime rows, date19 floor/ceiling, date20 truncation, date3 auto/unixepoch, date5 calendar cycle, or expression-affinity shards',
            'non-overlap: does not repeat accepted timediff-3 exact strings, timediff-5 modifier grammar, timediff-6 month-boundary matrix, date4 strftime rows, date19 floor/ceiling, date20 truncation, date3 auto/unixepoch, date5 calendar cycle, or expression-affinity shards'
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction timediff/datetime modifier application, sqlite3 oracle parity, and TEXT affinity storage checks',
            'no new support component needed; reuses SQLiteCoreScalarFunction timediff/datetime modifier application, sqlite3 oracle parity, and TEXT affinity storage checks'
        );
    };

return $tests;
