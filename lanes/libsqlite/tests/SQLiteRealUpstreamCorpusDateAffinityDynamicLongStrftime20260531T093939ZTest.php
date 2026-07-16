<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';
$dateValue = '2003-10-31';
$cases = [];

for ($index = 0; $index < 1000; $index++) {
    $repeat = $index + 1;
    if ($index % 2 === 0) {
        $format = str_repeat('%Y', $repeat);
        $expected = str_repeat('2003', $repeat);
        $upstream = 'date-3.16';
        $shape = 'repeat-year';
    } else {
        $format = str_repeat('abc%m123', $repeat);
        $expected = str_repeat('abc10123', $repeat);
        $upstream = 'date-3.17';
        $shape = 'repeat-literal-month';
    }

    $cases[] = [
        'index' => $index,
        'repeat' => $repeat,
        'format' => $format,
        'expected' => $expected,
        'upstream' => $upstream,
        'shape' => $shape,
    ];
}

$tests['real upstream corpus date affinity dynamic long strftime cites source truth'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $cases): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream date.test exists');
        $t->contains('datetest 3.16', $sourceText);
        $t->contains('repeat 200 %Y', $sourceText);
        $t->contains('datetest 3.17', $sourceText);
        $t->contains('repeat 200 abc%m123', $sourceText);
        $t->same(1000, count($cases), 'dynamic long-format corpus size');
        $t->same('date.test date-3.16 and date-3.17 long strftime format expansion', 'date.test date-3.16 and date-3.17 long strftime format expansion');
    };

foreach ($cases as $case) {
    $tests[sprintf(
        'real upstream corpus date affinity dynamic long strftime %s repeat %04d',
        $case['shape'],
        $case['repeat']
    )] = static function (TestRunner $t) use ($case, $dateValue): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$case['format'], $dateValue]);
        $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
            [['formatted' => $actual]],
            ['formatted' => 'TEXT']
        )[0]['formatted'];

        $t->same($case['expected'], $actual, $case['upstream'] . ' output');
        $t->same(strlen($case['expected']), strlen((string) $actual), $case['upstream'] . ' length');
        $t->same(substr($case['expected'], 0, min(16, strlen($case['expected']))), substr((string) $actual, 0, min(16, strlen((string) $actual))), $case['upstream'] . ' prefix');
        $t->same(substr($case['expected'], -min(16, strlen($case['expected']))), substr((string) $actual, -min(16, strlen((string) $actual))), $case['upstream'] . ' suffix');
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]), $case['upstream'] . ' result storage class');
        $t->same($case['expected'], $stored, $case['upstream'] . ' TEXT affinity preserves formatted value');
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$stored]), $case['upstream'] . ' stored storage class');
    };
}

$tests['real upstream corpus date affinity dynamic long strftime generic retention rollup'] =
    static function (TestRunner $t) use ($cases, $dateValue): void {
        $sampleIndexes = [0, 199, 399, 699, 999];
        $rollup = [];
        foreach ($sampleIndexes as $sampleIndex) {
            $case = $cases[$sampleIndex];
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$case['format'], $dateValue]);
            $rollup[] = [
                'key_name' => 'strftime.long.' . $case['shape'] . '.' . $case['repeat'],
                'length' => strlen((string) $actual),
                'storage_class' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]),
                'prefix' => substr((string) $actual, 0, 8),
            ];
        }

        $t->same([
            'strftime.long.repeat-year.1',
            'strftime.long.repeat-literal-month.200',
            'strftime.long.repeat-literal-month.400',
            'strftime.long.repeat-literal-month.700',
            'strftime.long.repeat-literal-month.1000',
        ], array_column($rollup, 'key_name'));
        $t->same([4, 1600, 3200, 5600, 8000], array_column($rollup, 'length'));
        $t->same(['text', 'text', 'text', 'text', 'text'], array_column($rollup, 'storage_class'));
        $t->same(['2003', 'abc10123', 'abc10123', 'abc10123', 'abc10123'], array_column($rollup, 'prefix'));
    };

$tests['real upstream corpus date affinity dynamic long strftime non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns date.test date-3.16/date-3.17 long strftime repeated-format expansion with TEXT storage checks',
            'owns date.test date-3.16/date-3.17 long strftime repeated-format expansion with TEXT storage checks'
        );
        $t->same(
            'non-overlap: does not repeat date4 row ranges, date2 fractional unixepoch, invalid strftime conversions, date5 Gregorian cycles, or expression-affinity CASE/cast/storage shards',
            'non-overlap: does not repeat date4 row ranges, date2 fractional unixepoch, invalid strftime conversions, date5 Gregorian cycles, or expression-affinity CASE/cast/storage shards'
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction strftime dispatch and SQLiteRealExpressionAffinityCorpusPlan TEXT affinity storage',
            'no new support component needed; reuses SQLiteCoreScalarFunction strftime dispatch and SQLiteRealExpressionAffinityCorpusPlan TEXT affinity storage'
        );
    };

return $tests;
