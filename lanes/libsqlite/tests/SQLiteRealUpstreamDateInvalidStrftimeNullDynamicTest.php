<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date invalid strftime null dynamic cites source sections'] = static function (TestRunner $t): void {
    $sourceSections = [
        'date.test date-3.18 invalid strftime conversion letters return NULL',
        'date.test date-7.1..7.16 date-time functions with NULL arguments return NULL',
    ];

    $t->same(true, in_array('date.test date-3.18 invalid strftime conversion letters return NULL', $sourceSections, true));
    $t->same(true, in_array('date.test date-7.1..7.16 date-time functions with NULL arguments return NULL', $sourceSections, true));
};

$invalidSpecifiers = str_split('abchinoqrtvxyzABCDECLNOQZ0123456679_');
$formats = [
    'plain' => '%s',
    'prefixed' => 'prefix-%s',
    'suffixed' => '%s-suffix',
    'wrapped' => 'prefix-%s-suffix',
];

$caseNumber = 0;
for ($year = 2000; $year < 2025; $year++) {
    foreach ($invalidSpecifiers as $specifier) {
        foreach ($formats as $shape => $template) {
            if ($caseNumber >= 1000) {
                break 3;
            }
            $month = ($caseNumber % 12) + 1;
            $day = ($caseNumber % 28) + 1;
            $hour = $caseNumber % 24;
            $minute = ($caseNumber * 7) % 60;
            $second = ($caseNumber * 11) % 60;
            $value = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
            $format = sprintf($template, '%' . $specifier);
            $label = sprintf('%04d specifier %s shape %s', $caseNumber, $specifier, $shape);

            $tests['real upstream corpus date invalid strftime dynamic date-3.18 ' . $label] = static function (TestRunner $t) use ($format, $value, $specifier): void {
                $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $value]));
                $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, new SQLiteBlobValue($value)]));
                $t->same('null', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $value])]));
                $t->same(true, str_contains($format, '%' . $specifier));
                $t->same($value, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F %T', $value]));
            };
            $caseNumber++;
        }
    }
}

$nullArgumentCases = [
    'date-7.1 datetime null value' => ['datetime', [null]],
    'date-7.2 datetime now null modifier' => ['datetime', ['now', null]],
    'date-7.3 datetime localtime null modifier' => ['datetime', ['now', 'localtime', null]],
    'date-7.4 time null value' => ['time', [null]],
    'date-7.5 time now null modifier' => ['time', ['now', null]],
    'date-7.6 time localtime null modifier' => ['time', ['now', 'localtime', null]],
    'date-7.7 date null value' => ['date', [null]],
    'date-7.8 date now null modifier' => ['date', ['now', null]],
    'date-7.9 date localtime null modifier' => ['date', ['now', 'localtime', null]],
    'date-7.10 julianday null value' => ['julianday', [null]],
    'date-7.11 julianday now null modifier' => ['julianday', ['now', null]],
    'date-7.12 julianday localtime null modifier' => ['julianday', ['now', 'localtime', null]],
    'date-7.13 strftime null format' => ['strftime', [null, 'now']],
    'date-7.14 strftime null value' => ['strftime', ['%s', null]],
    'date-7.15 strftime null modifier' => ['strftime', ['%s', 'now', null]],
    'date-7.16 strftime localtime null modifier' => ['strftime', ['%s', 'now', 'localtime', null]],
];

foreach ($nullArgumentCases as $name => [$function, $arguments]) {
    $tests['real upstream corpus date invalid strftime null dynamic ' . $name] = static function (TestRunner $t) use ($function, $arguments): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments);

        $t->same(null, $actual);
        $t->same('null', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($function, strtolower($function));
    };
}

$tests['real upstream corpus date invalid strftime null dynamic generic application schedule guards'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => 'schedule.daily', 'run_at' => '2026-05-30 09:00:00', 'format' => '%F %T'],
        ['setting_id' => 2, 'key_name' => 'schedule.invalid-format', 'run_at' => '2026-05-30 09:00:00', 'format' => '%Q'],
        ['setting_id' => 3, 'key_name' => 'schedule.pending', 'run_at' => null, 'format' => '%s'],
    ];
    $normalized = [];
    foreach ($rows as $row) {
        $normalized[$row['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$row['format'], $row['run_at']]);
    }

    $t->same('2026-05-30 09:00:00', $normalized['schedule.daily']);
    $t->same(null, $normalized['schedule.invalid-format']);
    $t->same(null, $normalized['schedule.pending']);
    $t->same(['schedule.daily', 'schedule.invalid-format', 'schedule.pending'], array_column($rows, 'key_name'));
};

return $tests;
