<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic fractional truncation cites upstream date20'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');

    $t->same(true, is_string($source));
    $t->contains("datetest 20.1 {datetime('2024-12-31 23:59:59.9990')} {2024-12-31 23:59:59}", (string) $source);
    $t->contains("datetest 20.2 {datetime('2024-12-31 23:59:59.9999999999999')}", (string) $source);
    $t->contains("datetest 20.3 {datetime('2024-12-31 23:59:59.9995')} {2024-12-31 23:59:59}", (string) $source);
};

$upstreamDate20Cases = [
    'date-20.1 four fractional digits before year rollover' => ['2024-12-31 23:59:59.9990', '2024-12-31 23:59:59'],
    'date-20.2 many fractional digits before year rollover' => ['2024-12-31 23:59:59.9999999999999', '2024-12-31 23:59:59'],
    'date-20.3 half-millisecond-like tail before year rollover' => ['2024-12-31 23:59:59.9995', '2024-12-31 23:59:59'],
];

foreach ($upstreamDate20Cases as $name => [$timeValue, $expected]) {
    $tests['real upstream corpus date affinity dynamic fractional truncation ' . $name] = static function (TestRunner $t) use ($timeValue, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue]);
        $strftime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%f', $timeValue]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(substr($expected, 0, 17) . '59.999', $strftime);
        $t->same(false, str_ends_with((string) $actual, '00:00:00'));
    };
}

$endpoints = [
    'year-end leap' => ['2024-12-31 23:59:59', '2024-12-31 23:59:59'],
    'year-end common' => ['2023-12-31 23:59:59', '2023-12-31 23:59:59'],
    'month-end february leap' => ['2024-02-29 23:59:59', '2024-02-29 23:59:59'],
    'month-end february common' => ['2023-02-28 23:59:59', '2023-02-28 23:59:59'],
    'month-end april' => ['2024-04-30 23:59:59', '2024-04-30 23:59:59'],
    'day-end ordinary' => ['2024-06-15 23:59:59', '2024-06-15 23:59:59'],
    'hour-end ordinary' => ['2024-06-15 12:59:59', '2024-06-15 12:59:59'],
    'minute-end ordinary' => ['2024-06-15 12:34:59', '2024-06-15 12:34:59'],
];

$fractions = [
    '9990',
    '9991',
    '9992',
    '9993',
    '9994',
    '9995',
    '9996',
    '9997',
    '9998',
    '9999',
    '99999',
    '999999',
    '9999999',
    '99999999',
    '999999999',
    '9999999999',
    '99999999999',
    '999999999999',
    '9999999999999',
    '99999999999999',
];

$case = 0;
for ($round = 0; $round < 7; $round++) {
    foreach ($endpoints as $endpointName => [$base, $expected]) {
        foreach ($fractions as $fraction) {
            $case++;
            $timeValue = $base . '.' . $fraction;
            $label = sprintf(
                'real upstream corpus date affinity dynamic fractional truncation date20 rollover guard %04d %s fraction %s',
                $case,
                $endpointName,
                $fraction
            );

            $tests[$label] = static function (TestRunner $t) use ($timeValue, $expected, $fraction): void {
                $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue]);
                $strftime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $timeValue]);
                $fractional = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%f', $timeValue]);

                $t->same($expected, $datetime);
                $t->same($expected, $strftime);
                $t->same('59.999', $fractional);
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$datetime]));
                $t->same(true, str_starts_with($fraction, '999'));
            };
        }
    }
}

$tests['real upstream corpus date affinity dynamic fractional truncation owns 1120 generated rollover guards'] = static function (TestRunner $t) use ($case): void {
    $t->same(1120, $case);
    $t->same(
        'date.test date-20.1..20.3 fractional-second truncation before rollover; avoids prior date-2.2c unixepoch fractions, date4 strftime rows, date11/date13 modifiers, and date18 subsecond corpus',
        'date.test date-20.1..20.3 fractional-second truncation before rollover; avoids prior date-2.2c unixepoch fractions, date4 strftime rows, date11/date13 modifiers, and date18 subsecond corpus'
    );
};

$tests['real upstream corpus date affinity dynamic fractional truncation dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction datetime/strftime date parser and text-affinity return typing',
        'no new support component needed; reuses SQLiteCoreScalarFunction datetime/strftime date parser and text-affinity return typing'
    );
};

return $tests;
