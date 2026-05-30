<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date localtime failure dynamic cites upstream date6'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-6.20 SQLITE_TESTCTRL_LOCALTIME_FAULT localtime_r failure raises local time unavailable',
        'date.test date-6.1..6.12 deterministic localtime/utc transition shim',
        'date.test date-6.21..6.24 out-of-band localtime/utc conversion',
    ];

    $t->same(true, in_array('date.test date-6.20 SQLITE_TESTCTRL_LOCALTIME_FAULT localtime_r failure raises local time unavailable', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$localtimeRules = [
    ['utcStart' => '0000-01-01 00:00:00', 'offsetMinutes' => 30, 'failAtUtc' => '2000-05-29 14:16:00'],
    ['utcStart' => '2000-10-30 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '2022-02-10 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '2022-02-11 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '3000-10-29 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '3000-10-30 00:00:00', 'offsetMinutes' => -30],
];

$call = static fn (string $function, array $arguments, ?array $rules = null): mixed => SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules($function, $arguments, $rules ?? $localtimeRules);

$tests['real upstream corpus date localtime failure dynamic date.test date-6.20 exact unavailable instant'] = static function (TestRunner $t) use ($call): void {
    $t->throws(RuntimeException::class, static fn () => $call('datetime', ['2000-05-29 14:16:00', 'localtime']));

    try {
        $call('datetime', ['2000-05-29 14:16:00', 'localtime']);
    } catch (RuntimeException $exception) {
        $t->same('local time unavailable', $exception->getMessage());
    }
};

$tests['real upstream corpus date localtime failure dynamic date.test date-6.20 neighboring instants still convert'] = static function (TestRunner $t) use ($call): void {
    $t->same('2000-05-29 14:45:59', $call('datetime', ['2000-05-29 14:15:59', 'localtime']));
    $t->same('2000-05-29 14:46:01', $call('datetime', ['2000-05-29 14:16:01', 'localtime']));
    $t->same('2000-05-29', $call('date', ['2000-05-29 14:15:59', 'localtime']));
    $t->same('14:46:01', $call('time', ['2000-05-29 14:16:01', 'localtime']));
};

// Source truth: SQLite upstream test/date.test date-6.20.  The Tcl harness
// configures localtime_r() to fail for 2000-05-29 14:16:00.  This dynamic
// matrix exercises the same deterministic failure hook over many configured
// unavailable instants, while checking adjacent UTC instants still use the
// active offset rule.
for ($case = 0; $case < 1000; $case++) {
    $failure = (new DateTimeImmutable('2000-05-29 14:16:00', new DateTimeZone('UTC')))
        ->modify(sprintf('+%d minutes', $case * 17));
    $before = $failure->modify('-1 second');
    $after = $failure->modify('+1 second');
    $rules = [
        [
            'utcStart' => '0000-01-01 00:00:00',
            'offsetMinutes' => 30,
            'failAtUtc' => $failure->format('Y-m-d H:i:s'),
        ],
    ];
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date localtime failure dynamic date.test date-6.20 generated unavailable instant ' . $label] = static function (TestRunner $t) use ($call, $rules, $failure, $before, $after): void {
        $failureText = $failure->format('Y-m-d H:i:s');
        $beforeText = $before->format('Y-m-d H:i:s');
        $afterText = $after->format('Y-m-d H:i:s');
        $beforeLocal = $before->modify('+30 minutes')->format('Y-m-d H:i:s');
        $afterLocal = $after->modify('+30 minutes')->format('Y-m-d H:i:s');

        $t->throws(RuntimeException::class, static fn () => $call('datetime', [$failureText, 'localtime'], $rules));

        try {
            $call('datetime', [$failureText, 'localtime'], $rules);
        } catch (RuntimeException $exception) {
            $t->same('local time unavailable', $exception->getMessage());
        }

        $t->same($beforeLocal, $call('datetime', [$beforeText, 'localtime'], $rules));
        $t->same($afterLocal, $call('datetime', [$afterText, 'localtime'], $rules));
        $t->same(substr($afterLocal, 0, 10), $call('date', [$afterText, 'localtime'], $rules));
        $t->same(substr($beforeLocal, 11), $call('time', [$beforeText, 'localtime'], $rules));
    };
}

$tests['real upstream corpus date localtime failure dynamic application scheduled conversion reports unavailable local calendar'] = static function (TestRunner $t) use ($call, $localtimeRules): void {
    $events = [
        'normal' => '2000-05-29 14:15:59',
        'unavailable' => '2000-05-29 14:16:00',
        'resumed' => '2000-05-29 14:16:01',
    ];
    $actual = [];

    foreach ($events as $key => $utc) {
        try {
            $actual[$key] = $call('datetime', [$utc, 'localtime'], $localtimeRules);
        } catch (RuntimeException $exception) {
            $actual[$key] = $exception->getMessage();
        }
    }

    $t->same([
        'normal' => '2000-05-29 14:45:59',
        'unavailable' => 'local time unavailable',
        'resumed' => '2000-05-29 14:46:01',
    ], $actual);
};

return $tests;
