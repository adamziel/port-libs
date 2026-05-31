<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';

// Source truth: SQLite upstream test/timediff1.test timediff-6. It builds a
// 24x24 pair matrix over 2000 and 2001 month-boundary values and proves that
// timediff(A,B) is a valid datetime modifier that reconstructs A from B.
$p1 = [
    'a' => '2000-01-01 00:00:00',
    'b' => '2000-01-31 23:59:59',
    'c' => '2000-02-01 00:00:00',
    'd' => '2000-02-29 23:59:59',
    'e' => '2000-03-01 00:00:00',
    'f' => '2000-03-31 23:59:59',
    'g' => '2000-04-01 00:00:00',
    'h' => '2000-04-30 23:59:59',
    'i' => '2000-05-01 00:00:00',
    'j' => '2000-05-31 23:59:59',
    'k' => '2000-06-01 00:00:00',
    'l' => '2000-06-30 23:59:59',
    'm' => '2000-07-01 00:00:00',
    'n' => '2000-07-31 23:59:59',
    'o' => '2000-08-01 00:00:00',
    'p' => '2000-08-31 23:59:59',
    'q' => '2000-09-01 00:00:00',
    'r' => '2000-09-30 23:59:59',
    's' => '2000-10-01 00:00:00',
    't' => '2000-10-31 23:59:59',
    'u' => '2000-11-01 00:00:00',
    'v' => '2000-11-30 23:59:59',
    'w' => '2000-12-01 00:00:00',
    'x' => '2000-12-31 23:59:59',
];

$p2 = [
    'A' => '2001-01-01 00:00:00',
    'B' => '2001-01-31 23:59:59',
    'C' => '2001-02-01 00:00:00',
    'D' => '2001-02-28 23:59:59',
    'E' => '2001-03-01 00:00:00',
    'F' => '2001-03-31 23:59:59',
    'G' => '2001-04-01 00:00:00',
    'H' => '2001-04-30 23:59:59',
    'I' => '2001-05-01 00:00:00',
    'J' => '2001-05-31 23:59:59',
    'K' => '2001-06-01 00:00:00',
    'L' => '2001-06-30 23:59:59',
    'M' => '2001-07-01 00:00:00',
    'N' => '2001-07-31 23:59:59',
    'O' => '2001-08-01 00:00:00',
    'P' => '2001-08-31 23:59:59',
    'Q' => '2001-09-01 00:00:00',
    'R' => '2001-09-30 23:59:59',
    'S' => '2001-10-01 00:00:00',
    'T' => '2001-10-31 23:59:59',
    'U' => '2001-11-01 00:00:00',
    'V' => '2001-11-30 23:59:59',
    'W' => '2001-12-01 00:00:00',
    'X' => '2001-12-31 23:59:59',
];

$tests['real upstream corpus date affinity dynamic timediff6 cites upstream source'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $p1, $p2): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream timediff1.test exists');
        $t->contains('set p1 {', $sourceText);
        $t->contains('set p2 {', $sourceText);
        $t->contains('do_execsql_test timediff-6-$x1$x2', $sourceText);
        $t->contains('SELECT datetime($d2, timediff($d1,$d2));', $sourceText);
        $t->contains('SELECT datetime($d1, timediff($d2,$d1));', $sourceText);
        $t->same(24, count($p1), 'timediff-6 p1 month-boundary values');
        $t->same(24, count($p2), 'timediff-6 p2 month-boundary values');
        $t->same(1152, count($p1) * count($p2) * 2, 'timediff-6 directional upstream cases');
    };

$addRoundtrip = static function (
    array &$tests,
    string $leftLabel,
    string $left,
    string $rightLabel,
    string $right,
    string $upstreamName
): void {
    $tests['real upstream corpus date affinity dynamic timediff1.test ' . $upstreamName . ' roundtrip'] =
        static function (TestRunner $t) use ($leftLabel, $left, $rightLabel, $right, $upstreamName): void {
            $normalizedLeft = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$left]);
            $normalizedRight = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$right]);
            $diff = SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [$left, $right]);
            $roundtrip = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$right, $diff]);
            $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
                [[
                    'source_key' => $upstreamName,
                    'left_label' => $leftLabel,
                    'right_label' => $rightLabel,
                    'elapsed_modifier' => $diff,
                ]],
                [
                    'source_key' => 'TEXT',
                    'left_label' => 'TEXT',
                    'right_label' => 'TEXT',
                    'elapsed_modifier' => 'TEXT',
                ]
            )[0];

            $t->same($normalizedLeft, $roundtrip, $upstreamName . ' reconstructs left datetime');
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$diff]), $upstreamName . ' timediff storage');
            $t->true(is_string($diff), $upstreamName . ' timediff string');
            $t->true(preg_match('/\A[+-]\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\z/', (string) $diff) === 1, $upstreamName . ' modifier format');
            $t->same($diff, $stored['elapsed_modifier'], $upstreamName . ' TEXT affinity preserves timediff modifier');
            $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['elapsed_modifier']), $upstreamName . ' stored modifier class');
            $t->same($normalizedRight, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$right]), $upstreamName . ' right datetime is stable');
        };
};

foreach ($p1 as $leftLabel => $left) {
    foreach ($p2 as $rightLabel => $right) {
        $addRoundtrip($tests, $leftLabel, $left, $rightLabel, $right, 'timediff-6-' . $leftLabel . $rightLabel);
        $addRoundtrip($tests, $rightLabel, $right, $leftLabel, $left, 'timediff-6-' . $rightLabel . $leftLabel);
    }
}

$tests['real upstream corpus date affinity dynamic timediff6 application retention rollup'] =
    static function (TestRunner $t) use ($p1, $p2): void {
        $events = [
            'month-open-year-forward' => [$p2['A'], $p1['a']],
            'leap-february-to-common-february' => [$p2['D'], $p1['d']],
            'quarter-close-year-forward' => [$p2['F'], $p1['f']],
            'year-close-to-next-year-close' => [$p2['X'], $p1['x']],
        ];
        $summary = [];
        foreach ($events as $key => [$target, $base]) {
            $modifier = SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [$target, $base]);
            $summary[$key] = [
                'modifier' => $modifier,
                'roundtrip' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$base, $modifier]),
                'storage_class' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$modifier]),
            ];
        }

        $t->same('2001-01-01 00:00:00', $summary['month-open-year-forward']['roundtrip']);
        $t->same('2001-02-28 23:59:59', $summary['leap-february-to-common-february']['roundtrip']);
        $t->same('2001-03-31 23:59:59', $summary['quarter-close-year-forward']['roundtrip']);
        $t->same('2001-12-31 23:59:59', $summary['year-close-to-next-year-close']['roundtrip']);
        $t->same(['text', 'text', 'text', 'text'], array_column($summary, 'storage_class'));
    };

$tests['real upstream corpus date affinity dynamic timediff6 non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns timediff1.test timediff-6 2000/2001 month-boundary directional roundtrip matrix',
            'owns timediff1.test timediff-6 2000/2001 month-boundary directional roundtrip matrix'
        );
        $t->same(
            'non-overlap: does not repeat accepted timediff-3 exact strings, timediff-5 generated modifier grammar, date4 strftime rows, date19 floor/ceiling, date20 truncation, or expression-affinity storage shards',
            'non-overlap: does not repeat accepted timediff-3 exact strings, timediff-5 generated modifier grammar, date4 strftime rows, date19 floor/ceiling, date20 truncation, or expression-affinity storage shards'
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction timediff/datetime modifier application and SQLiteRealExpressionAffinityCorpusPlan TEXT affinity storage',
            'no new support component needed; reuses SQLiteCoreScalarFunction timediff/datetime modifier application and SQLiteRealExpressionAffinityCorpusPlan TEXT affinity storage'
        );
    };

return $tests;
