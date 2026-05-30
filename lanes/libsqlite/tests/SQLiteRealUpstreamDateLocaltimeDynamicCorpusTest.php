<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$localtimeRules = [
    ['utcStart' => '0000-01-01 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '2000-10-30 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '2022-02-10 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '2022-02-11 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '3000-10-29 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '3000-10-30 00:00:00', 'offsetMinutes' => -30],
];

$call = static fn (string $function, array $arguments): mixed => SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules($function, $arguments, $localtimeRules);

$tests['real upstream corpus date localtime dynamic cites upstream date6 and date18'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-6.1..6.12 deterministic localtime/utc transition shim',
        'date.test date-6.21..6.24 out-of-band localtime/utc conversion',
        'date.test date-6.28..6.32 localtime/utc modifier chains',
        'date.test date-18.1 localtime modifier preserves fractional seconds',
    ];

    $t->same(true, in_array('date.test date-6.1..6.12 deterministic localtime/utc transition shim', $upstream, true));
    $t->same(true, in_array('date.test date-18.1 localtime modifier preserves fractional seconds', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$date6Cases = [
    'date-6.1 utc to local before transition' => ['datetime', ['2000-10-29 12:00:00', 'localtime'], '2000-10-29 12:30:00'],
    'date-6.2 local to utc before transition' => ['datetime', ['2000-10-29 12:30:00', 'utc'], '2000-10-29 12:00:00'],
    'date-6.3 utc to local after transition' => ['datetime', ['2000-10-30 12:00:00', 'localtime'], '2000-10-30 11:30:00'],
    'date-6.4 local to utc after transition' => ['datetime', ['2000-10-30 11:30:00', 'utc'], '2000-10-30 12:00:00'],
    'date-6.5 utc to local last second before repeated hour' => ['datetime', ['2000-10-28 23:59:59', 'localtime'], '2000-10-29 00:29:59'],
    'date-6.6 utc to local first repeated-hour source' => ['datetime', ['2000-10-29 00:00:00', 'localtime'], '2000-10-29 00:30:00'],
    'date-6.7 nonexistent local to utc uses deterministic candidate' => ['datetime', ['2000-10-29 00:10:00', 'utc'], '2000-10-28 23:40:00'],
    'date-6.8 utc to local 2022 pre-transition last second' => ['datetime', ['2022-02-10 23:59:59', 'localtime'], '2022-02-11 00:29:59'],
    'date-6.9 utc to local 2022 transition instant' => ['datetime', ['2022-02-11 00:00:00', 'localtime'], '2022-02-10 23:30:00'],
    'date-6.10 utc to local 2022 ambiguous first value' => ['datetime', ['2022-02-10 23:45:00', 'localtime'], '2022-02-11 00:15:00'],
    'date-6.11 utc to local 2022 ambiguous second value' => ['datetime', ['2022-02-11 00:45:00', 'localtime'], '2022-02-11 00:15:00'],
    'date-6.12 ambiguous local to utc chooses later matching instant' => ['datetime', ['2022-02-11 00:15:00', 'utc'], '2022-02-11 00:45:00'],
    'date-6.21 far past utc to local' => ['datetime', ['1800-10-29 12:00:00', 'localtime'], '1800-10-29 12:30:00'],
    'date-6.22 far past local to utc' => ['datetime', ['1800-10-29 12:30:00', 'utc'], '1800-10-29 12:00:00'],
    'date-6.23 far future utc to local' => ['datetime', ['3000-10-30 12:00:00', 'localtime'], '3000-10-30 11:30:00'],
    'date-6.24 far future local to utc' => ['datetime', ['3000-10-30 11:30:00', 'utc'], '3000-10-30 12:00:00'],
    'date-6.28 z localtime converts from explicit utc' => ['datetime', ['2000-10-29 12:00:00Z', 'localtime'], '2000-10-29 12:30:00'],
    'date-6.29 utc localtime chain' => ['datetime', ['2000-10-29 12:00:00Z', 'utc', 'localtime'], '2000-10-29 12:30:00'],
    'date-6.30 utc localtime utc chain' => ['datetime', ['2000-10-29 12:00:00Z', 'utc', 'localtime', 'utc'], '2000-10-29 12:00:00'],
    'date-6.31 utc localtime utc localtime chain' => ['datetime', ['2000-10-29 12:00:00Z', 'utc', 'localtime', 'utc', 'localtime'], '2000-10-29 12:30:00'],
    'date-6.32 double localtime chain' => ['datetime', ['2000-10-29 12:00:00Z', 'localtime', 'localtime'], '2000-10-29 12:30:00'],
    'date-18.1 localtime preserves fractional seconds' => ['strftime', ['%f', 1.234, 'unixepoch', 'localtime'], '01.234'],
];

foreach ($date6Cases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date localtime dynamic ' . $name] = static function (TestRunner $t) use ($call, $function, $arguments, $expected): void {
        $actual = $call($function, $arguments);

        $t->same($expected, $actual);
        $t->same(SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]), 'text');
    };
}

$seedUtc = new DateTimeImmutable('1998-01-01 00:00:00', new DateTimeZone('UTC'));
for ($case = 0; $case < 1000; $case++) {
    $utc = $seedUtc->modify(sprintf('+%d hours', $case * 5));
    $offsetMinutes = 30;
    $local = $utc->modify(sprintf('%+d minutes', $offsetMinutes));
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date localtime dynamic date.test date-6 generated utc local roundtrip ' . $label] = static function (TestRunner $t) use ($call, $utc, $local): void {
        $utcText = $utc->format('Y-m-d H:i:s');
        $localText = $local->format('Y-m-d H:i:s');

        $t->same($localText, $call('datetime', [$utcText, 'localtime']));
        $t->same($utcText, $call('datetime', [$localText, 'utc']));
        $t->same(substr($localText, 0, 10), $call('date', [$utcText, 'localtime']));
        $t->same(substr($localText, 11), $call('time', [$utcText, 'localtime']));
        $t->same($local->format('s') . '.000', $call('strftime', ['%f', $utcText, 'localtime']));
    };
}

$tests['real upstream corpus date localtime dynamic application schedule audit'] = static function (TestRunner $t) use ($call): void {
    $events = [
        'before-transition' => ['stored_utc' => '2000-10-29 12:00:00', 'local' => '2000-10-29 12:30:00'],
        'after-transition' => ['stored_utc' => '2000-10-30 12:00:00', 'local' => '2000-10-30 11:30:00'],
        'future-transition' => ['stored_utc' => '3000-10-30 12:00:00', 'local' => '3000-10-30 11:30:00'],
    ];
    $actual = [];
    foreach ($events as $key => $event) {
        $actual[$key] = [
            'stored_utc' => $call('datetime', [$event['local'], 'utc']),
            'local' => $call('datetime', [$event['stored_utc'], 'localtime']),
        ];
    }

    $t->same($events, $actual);
};

return $tests;
