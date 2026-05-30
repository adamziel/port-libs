<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$dateTimeCases = [
    'julian integer noon epoch date' => ['datetime', [2440588], '1970-01-01 12:00:00'],
    'julian numeric string epoch start' => ['datetime', ['2440587.5'], '1970-01-01 00:00:00'],
    'julian explicit modifier epoch start' => ['datetime', ['2440587.5', 'julianday'], '1970-01-01 00:00:00'],
    'julian text modifier preserves fractional seconds' => ['strftime', ['%f', '2440587.500001424', 'julianday'], '00.123'],
    'julian default numeric preserves fractional seconds' => ['strftime', ['%f', 2440587.5000057872], '00.500'],
    'julian date before unix epoch' => ['datetime', [2440586.5], '1969-12-31 00:00:00'],
    'julian start of day modifier' => ['datetime', [2460370.129257986, 'start of day'], '2024-02-29 00:00:00'],
    'julian weekday modifier' => ['date', ['2460370.129257986', 'weekday 0'], '2024-03-03'],
    'julian plus seconds modifier' => ['datetime', ['2440587.999988426', '+1 second'], '1970-01-01 12:00:00'],
    'julian unixepoch function returns negative seconds' => ['unixepoch', [2440586.5], -86400],
    'unixepoch fractional datetime truncates display seconds' => ['datetime', [1709219167.890, 'unixepoch'], '2024-02-29 15:06:07'],
    'unixepoch fractional strftime milliseconds' => ['strftime', ['%f', 1709219167.890, 'unixepoch'], '07.890'],
    'unixepoch string fractional strftime milliseconds' => ['strftime', ['%f', '1709219167.125', 'unixepoch'], '07.125'],
    'unixepoch negative second before epoch' => ['datetime', [-1, 'unixepoch'], '1969-12-31 23:59:59'],
    'unixepoch negative fractional milliseconds' => ['strftime', ['%s.%f', -0.25, 'unixepoch'], '-1.59.750'],
    'auto julian lower boundary' => ['datetime', [0, 'auto'], '-4713-11-24 12:00:00'],
    'auto julian upper boundary' => ['date', [5373484.499999, 'auto'], '9999-12-31'],
    'auto unix second above julian boundary' => ['datetime', [5373484.5, 'auto'], '1970-03-04 04:38:04'],
    'auto negative unix timestamp' => ['datetime', [-86400, 'auto'], '1969-12-31 00:00:00'],
    'auto text unix timestamp above boundary' => ['datetime', ['1709219167', 'auto'], '2024-02-29 15:06:07'],
    'auto text julian epoch' => ['datetime', ['2440587.5', 'auto'], '1970-01-01 00:00:00'],
    'auto preserves unix fractional milliseconds' => ['strftime', ['%f', '1709219167.875', 'auto'], '07.875'],
    'strftime julian day from unixepoch fractional' => ['strftime', ['%J', 1709219167.5, 'unixepoch'], '2460370.129253472'],
    'julianday from unixepoch fractional' => ['julianday', [1709219167.5, 'unixepoch'], 2460370.129253472],
    'unixepoch from julian modifier fractional rounds display second' => ['unixepoch', ['2460370.129253472', 'julianday'], 1709219167],
    'time from numeric string julian noon' => ['time', ['2440588'], '12:00:00'],
    'date from numeric string julian epoch' => ['date', ['2440587.5'], '1970-01-01'],
    'strftime unix seconds from numeric string julian epoch' => ['strftime', ['%s', '2440587.5'], '0'],
    'julianday auto unix above boundary' => ['julianday', [1709219167, 'auto'], 2460370.129247685],
    'unixepoch auto julian epoch' => ['unixepoch', [2440587.5, 'auto'], 0],
    'datetime auto fractional julian boundary minus epsilon' => ['datetime', [5373484.499999, 'auto'], '9999-12-31 23:59:59'],
    'datetime auto fractional julian boundary plus epsilon' => ['datetime', [5373484.500001, 'auto'], '1970-03-04 04:38:04'],
];

foreach ($dateTimeCases as $name => [$function, $arguments, $expected]) {
    $tests['upstream datetime julian unixepoch edge ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments);
        if (is_float($expected)) {
            $t->same($expected, round((float) $actual, 9));
            return;
        }

        $t->same($expected, $actual);
    };
}

$tests['upstream datetime julian unixepoch edge application cron mixed source summary'] = static function (TestRunner $t): void {
    $events = [
        ['hook' => 'wp_version_check', 'scheduled' => '2460370.129253472', 'mode' => 'julianday'],
        ['hook' => 'wp_update_plugins', 'scheduled' => '1709219167.875', 'mode' => 'unixepoch'],
        ['hook' => 'wp_delete_temp_updater_backups', 'scheduled' => '2440587.5', 'mode' => 'auto'],
    ];
    $summary = [];
    foreach ($events as $event) {
        $modifiers = $event['mode'] === 'julianday' ? ['julianday'] : ($event['mode'] === 'unixepoch' ? ['unixepoch'] : ['auto']);
        $summary[$event['hook']] = [
            'stamp' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', array_merge(['%FT%T.%fZ', $event['scheduled']], $modifiers)),
            'epoch' => SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', array_merge([$event['scheduled']], $modifiers)),
        ];
    }

    $t->same([
        'wp_version_check' => ['stamp' => '2024-02-29T15:06:07.07.499Z', 'epoch' => 1709219167],
        'wp_update_plugins' => ['stamp' => '2024-02-29T15:06:07.07.875Z', 'epoch' => 1709219167],
        'wp_delete_temp_updater_backups' => ['stamp' => '1970-01-01T00:00:00.00.000Z', 'epoch' => 0],
    ], $summary);
};

$tests['upstream datetime julian unixepoch edge invalid modifier returns null'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2440587.5', 'julianday', 'unixepoch']));
};

return $tests;
