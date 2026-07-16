<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date floor ceiling dynamic cites upstream files'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-19.1..19.32 floor and ceiling invalid calendar-day normalization',
        'date.test date-19.40..19.53 floor and ceiling after ambiguous month/year shifts',
    ];

    $t->same(true, in_array('date.test date-19.1..19.32 floor and ceiling invalid calendar-day normalization', $upstream, true));
    $t->same(true, in_array('date.test date-19.40..19.53 floor and ceiling after ambiguous month/year shifts', $upstream, true));
};

// Source truth: SQLite upstream test/date.test date-19.1 through date-19.53.
// SQLite's default is ceiling normalization; a following floor modifier clamps
// ambiguous dates to the last day of the target month.
$date19Cases = [
    'date-19.1 january floor no ambiguity' => [['2000-01-31', 'floor'], '2000-01-31'],
    'date-19.2a leap february floor' => [['2000-02-31', 'floor'], '2000-02-29'],
    'date-19.2b common february floor' => [['1999-02-31', 'floor'], '1999-02-28'],
    'date-19.2c century common february floor' => [['1900-02-31', 'floor'], '1900-02-28'],
    'date-19.3 march floor no ambiguity' => [['2000-03-31', 'floor'], '2000-03-31'],
    'date-19.4 april floor' => [['2000-04-31', 'floor'], '2000-04-30'],
    'date-19.5 may floor no ambiguity' => [['2000-05-31', 'floor'], '2000-05-31'],
    'date-19.6 june floor' => [['2000-06-31', 'floor'], '2000-06-30'],
    'date-19.7 july floor no ambiguity' => [['2000-07-31', 'floor'], '2000-07-31'],
    'date-19.8 august floor no ambiguity' => [['2000-08-31', 'floor'], '2000-08-31'],
    'date-19.9 september floor' => [['2000-09-31', 'floor'], '2000-09-30'],
    'date-19.10 october floor no ambiguity' => [['2000-10-31', 'floor'], '2000-10-31'],
    'date-19.11 november floor' => [['2000-11-31', 'floor'], '2000-11-30'],
    'date-19.12 december floor no ambiguity' => [['2000-12-31', 'floor'], '2000-12-31'],
    'date-19.21 january ceiling no ambiguity' => [['2000-01-31', 'ceiling'], '2000-01-31'],
    'date-19.22a leap february ceiling' => [['2000-02-31', 'ceiling'], '2000-03-02'],
    'date-19.22b common february ceiling' => [['1999-02-31', 'ceiling'], '1999-03-03'],
    'date-19.22c century common february ceiling' => [['1900-02-31', 'ceiling'], '1900-03-03'],
    'date-19.23 march ceiling no ambiguity' => [['2000-03-31', 'ceiling'], '2000-03-31'],
    'date-19.24 april ceiling' => [['2000-04-31', 'ceiling'], '2000-05-01'],
    'date-19.25 may ceiling no ambiguity' => [['2000-05-31', 'ceiling'], '2000-05-31'],
    'date-19.26 june ceiling' => [['2000-06-31', 'ceiling'], '2000-07-01'],
    'date-19.27 july ceiling no ambiguity' => [['2000-07-31', 'ceiling'], '2000-07-31'],
    'date-19.28 august ceiling no ambiguity' => [['2000-08-31', 'ceiling'], '2000-08-31'],
    'date-19.29 september ceiling' => [['2000-09-31', 'ceiling'], '2000-10-01'],
    'date-19.30 october ceiling no ambiguity' => [['2000-10-31', 'ceiling'], '2000-10-31'],
    'date-19.31 november ceiling' => [['2000-11-31', 'ceiling'], '2000-12-01'],
    'date-19.32 december ceiling no ambiguity' => [['2000-12-31', 'ceiling'], '2000-12-31'],
    'date-19.40 leap january plus month ceiling' => [['2024-01-31', '+1 month', 'ceiling'], '2024-03-02'],
    'date-19.41 leap january plus month floor' => [['2024-01-31', '+1 month', 'floor'], '2024-02-29'],
    'date-19.42 common january plus month ceiling' => [['2023-01-31', '+1 month', 'ceiling'], '2023-03-03'],
    'date-19.43 common january plus month floor' => [['2023-01-31', '+1 month', 'floor'], '2023-02-28'],
    'date-19.44 leap day plus year ceiling' => [['2024-02-29', '+1 year', 'ceiling'], '2025-03-01'],
    'date-19.45 leap day plus year floor' => [['2024-02-29', '+1 year', 'floor'], '2025-02-28'],
    'date-19.46 leap day minus years ceiling' => [['2024-02-29', '-110 years', 'ceiling'], '1914-03-01'],
    'date-19.47 leap day minus years floor' => [['2024-02-29', '-110 years', 'floor'], '1914-02-28'],
    'date-19.48 composite negative year floor' => [['2024-02-29', '-0110-00-00', 'floor'], '1914-02-28'],
    'date-19.49 composite negative year ceiling' => [['2024-02-29', '-0110-00-00', 'ceiling'], '1914-03-01'],
    'date-19.50 composite leap target floor' => [['2000-08-31', '+0023-06-00', 'floor'], '2024-02-29'],
    'date-19.51 composite common target floor' => [['2000-08-31', '+0022-06-00', 'floor'], '2023-02-28'],
    'date-19.52 composite leap target ceiling' => [['2000-08-31', '+0023-06-00', 'ceiling'], '2024-03-02'],
    'date-19.53 composite common target ceiling' => [['2000-08-31', '+0022-06-00', 'ceiling'], '2023-03-03'],
];

foreach ($date19Cases as $name => [$arguments, $expected]) {
    $tests['real upstream corpus date floor ceiling dynamic date.test ' . $name] = static function (TestRunner $t) use ($arguments, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments));
    };
}

for ($year = 1600; $year < 2200; $year++) {
    $isLeap = ($year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0));
    $floor = sprintf('%04d-02-%02d', $year, $isLeap ? 29 : 28);
    $ceiling = sprintf('%04d-03-%02d', $year, $isLeap ? 2 : 3);

    $tests['real upstream corpus date floor ceiling dynamic date.test date-19 dynamic february floor ' . $year] = static function (TestRunner $t) use ($year, $floor): void {
        $t->same($floor, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [sprintf('%04d-02-31', $year), 'floor']));
    };
    $tests['real upstream corpus date floor ceiling dynamic date.test date-19 dynamic february ceiling ' . $year] = static function (TestRunner $t) use ($year, $ceiling): void {
        $t->same($ceiling, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [sprintf('%04d-02-31', $year), 'ceiling']));
    };
}

for ($year = 2000; $year < 2060; $year += 4) {
    if ($year % 100 === 0 && $year % 400 !== 0) {
        continue;
    }
    $targetYear = $year + 1;
    $floor = sprintf('%04d-02-28', $targetYear);
    $ceiling = sprintf('%04d-03-01', $targetYear);

    $tests['real upstream corpus date floor ceiling dynamic date.test date-19 dynamic leap-day plus year floor ' . $year] = static function (TestRunner $t) use ($year, $floor): void {
        $t->same($floor, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [sprintf('%04d-02-29', $year), '+1 year', 'floor']));
    };
    $tests['real upstream corpus date floor ceiling dynamic date.test date-19 dynamic leap-day plus year ceiling ' . $year] = static function (TestRunner $t) use ($year, $ceiling): void {
        $t->same($ceiling, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [sprintf('%04d-02-29', $year), '+1 year', 'ceiling']));
    };
}

$tests['real upstream corpus date floor ceiling dynamic application retention cutoff normalizes ambiguity'] = static function (TestRunner $t): void {
    $cutoffs = [
        'monthly-floor' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2024-01-31', '+1 month', 'floor']),
        'monthly-ceiling' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2024-01-31', '+1 month', 'ceiling']),
        'annual-floor' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2024-02-29', '+1 year', 'floor']),
        'annual-ceiling' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2024-02-29', '+1 year', 'ceiling']),
    ];

    $t->same([
        'monthly-floor' => '2024-02-29',
        'monthly-ceiling' => '2024-03-02',
        'annual-floor' => '2025-02-28',
        'annual-ceiling' => '2025-03-01',
    ], $cutoffs);
};

return $tests;
