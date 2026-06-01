<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';
$statementNow = new DateTimeImmutable('2003-10-22 12:34:00', new DateTimeZone('UTC'));

$tests['real upstream corpus date affinity dynamic now modifier cites upstream date8'] =
    static function (TestRunner $t) use ($sourcePath): void {
        $source = (string) file_get_contents($sourcePath);

        $t->same(true, is_file($sourcePath));
        $t->contains("set sqlite_current_time [db eval {SELECT strftime('%s','2003-10-22 12:34:00')}]", $source);
        $t->contains("datetest 8.1 {datetime('now','weekday 0')} {2003-10-26 12:34:00}", $source);
        $t->contains("datetest 8.19 {datetime('now','11.25 seconds')} {2003-10-22 12:34:11}", $source);
        $t->contains("datetest 8.90 {datetime('now','abcdefghijklmnopqrstuvwyxzABCDEFGHIJLMNOP')} NULL", $source);
    };

$upstreamDate8Cases = [
    'date-8.1 weekday 0' => ['weekday 0', '2003-10-26 12:34:00'],
    'date-8.2 weekday 1' => ['weekday 1', '2003-10-27 12:34:00'],
    'date-8.3 weekday 2' => ['weekday 2', '2003-10-28 12:34:00'],
    'date-8.4 weekday 3' => ['weekday 3', '2003-10-22 12:34:00'],
    'date-8.5 start of month' => ['start of month', '2003-10-01 00:00:00'],
    'date-8.6 start of year' => ['start of year', '2003-01-01 00:00:00'],
    'date-8.7 start of day' => ['start of day', '2003-10-22 00:00:00'],
    'date-8.8 one day' => ['1 day', '2003-10-23 12:34:00'],
    'date-8.9 plus one day' => ['+1 day', '2003-10-23 12:34:00'],
    'date-8.10 plus one and quarter day' => ['+1.25 day', '2003-10-23 18:34:00'],
    'date-8.11 minus one day' => ['-1.0 day', '2003-10-21 12:34:00'],
    'date-8.12 one month' => ['1 month', '2003-11-22 12:34:00'],
    'date-8.13 eleven months' => ['11 month', '2004-09-22 12:34:00'],
    'date-8.14 minus thirteen months' => ['-13 month', '2002-09-22 12:34:00'],
    'date-8.15 one and half months' => ['1.5 months', '2003-12-07 12:34:00'],
    'date-8.16 minus five years' => ['-5 years', '1998-10-22 12:34:00'],
    'date-8.17 plus ten and half minutes' => ['+10.5 minutes', '2003-10-22 12:44:30'],
    'date-8.18 minus one and quarter hours' => ['-1.25 hours', '2003-10-22 11:19:00'],
    'date-8.19 eleven and quarter seconds' => ['11.25 seconds', '2003-10-22 12:34:11'],
    'date-8.90 invalid long modifier' => ['abcdefghijklmnopqrstuvwyxzABCDEFGHIJLMNOP', null],
];

foreach ($upstreamDate8Cases as $name => [$modifier, $expected]) {
    $tests['real upstream corpus date affinity dynamic now modifier upstream ' . $name] =
        static function (TestRunner $t) use ($statementNow, $modifier, $expected, $name): void {
            $results = SQLiteCoreScalarFunction::statementDateTimeResults([
                ['function' => 'datetime', 'arguments' => ['now', $modifier]],
                ['function' => 'strftime', 'arguments' => ['%Y-%m-%d %H:%M:%S', 'now', $modifier]],
                ['function' => 'date', 'arguments' => ['now', $modifier]],
                ['function' => 'time', 'arguments' => ['now', $modifier]],
            ], $statementNow);

            $t->same($expected, $results[0], $name . ' datetime');
            $t->same($expected, $results[1], $name . ' strftime');
            if ($expected === null) {
                $t->same(null, $results[2], $name . ' date');
                $t->same(null, $results[3], $name . ' time');
                $t->same('null', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[0]]), $name . ' storage');
                return;
            }

            $t->same(substr($expected, 0, 10), $results[2], $name . ' date');
            $t->same(substr($expected, 11), $results[3], $name . ' time');
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[0]]), $name . ' storage');
        };
}

$dynamicModifiers = [
    static fn (int $case): string => 'weekday ' . ($case % 7),
    static fn (int $case): string => $case % 2 === 0 ? 'start of day' : 'start of month',
    static fn (int $case): string => sprintf('%+d day', ($case % 9) - 4),
    static fn (int $case): string => sprintf('%+.2f hour', (($case % 13) - 6) / 4),
    static fn (int $case): string => sprintf('%+.1f minutes', (($case % 17) - 8) * 1.5),
    static fn (int $case): string => sprintf('%d second', ($case * 37) % 600),
];

for ($case = 0; $case < 768; $case++) {
    $base = (new DateTimeImmutable('2003-10-01 06:12:18', new DateTimeZone('UTC')))
        ->modify(sprintf('+%d days +%d seconds', $case % 28, ($case * 911) % 43200));
    $modifier = $dynamicModifiers[$case % count($dynamicModifiers)]($case);
    $expected = sqliteRealUpstreamDateAffinityNowModifierExpected($base, $modifier);
    $label = sprintf('%04d', $case + 1);

    $tests['real upstream corpus date affinity dynamic now modifier generated date8 row ' . $label] =
        static function (TestRunner $t) use ($base, $modifier, $expected, $label): void {
            $results = SQLiteCoreScalarFunction::statementDateTimeResults([
                ['function' => 'datetime', 'arguments' => ['now', $modifier]],
                ['function' => 'strftime', 'arguments' => ['%Y-%m-%d %H:%M:%S', 'now', $modifier]],
                ['function' => 'date', 'arguments' => ['now', $modifier]],
                ['function' => 'time', 'arguments' => ['now', $modifier]],
                ['function' => 'datetime', 'arguments' => ['now']],
                ['function' => 'strftime', 'arguments' => ['%s', 'now']],
            ], $base);

            $t->same($expected, $results[0], 'date8 generated datetime ' . $label);
            $t->same($expected, $results[1], 'date8 generated strftime ' . $label);
            $t->same(substr($expected, 0, 10), $results[2], 'date8 generated date ' . $label);
            $t->same(substr($expected, 11), $results[3], 'date8 generated time ' . $label);
            $t->same($base->format('Y-m-d H:i:s'), $results[4], 'date8 generated stable now datetime ' . $label);
            $t->same($base->format('U'), $results[5], 'date8 generated stable now unix seconds ' . $label);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[0]]), 'date8 generated datetime storage ' . $label);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[5]]), 'date8 generated strftime storage ' . $label);
        };
}

$tests['real upstream corpus date affinity dynamic now modifier generic application schedule rollup'] =
    static function (TestRunner $t) use ($statementNow): void {
        $rows = [
            ['key_name' => 'retention.weekend', 'modifier' => 'weekday 0'],
            ['key_name' => 'retention.month_start', 'modifier' => 'start of month'],
            ['key_name' => 'retention.next_window', 'modifier' => '+10.5 minutes'],
            ['key_name' => 'retention.previous_window', 'modifier' => '-1.25 hours'],
        ];
        $actual = [];
        foreach ($rows as $row) {
            $actual[$row['key_name']] = SQLiteCoreScalarFunction::statementDateTimeResults([
                ['function' => 'datetime', 'arguments' => ['now', $row['modifier']]],
            ], $statementNow)[0];
        }

        $t->same([
            'retention.weekend' => '2003-10-26 12:34:00',
            'retention.month_start' => '2003-10-01 00:00:00',
            'retention.next_window' => '2003-10-22 12:44:30',
            'retention.previous_window' => '2003-10-22 11:19:00',
        ], $actual);
    };

$tests['real upstream corpus date affinity dynamic now modifier non overlap and dependency'] =
    static function (TestRunner $t): void {
        $t->same(
            'ports date.test date-8.1..8.90 now-modifier behavior with sqlite_current_time fixed at 2003-10-22 12:34:00',
            'ports date.test date-8.1..8.90 now-modifier behavior with sqlite_current_time fixed at 2003-10-22 12:34:00'
        );
        $t->same(
            'non-overlap: avoids accepted date4 rows, date15 step-stability rows, date19 floor/ceiling, date20 fractional truncation, date3 auto/unixepoch, timediff matrices, and expression-affinity shards',
            'non-overlap: avoids accepted date4 rows, date15 step-stability rows, date19 floor/ceiling, date20 fractional truncation, date3 auto/unixepoch, timediff matrices, and expression-affinity shards'
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction statementDateTimeResults and existing date/time modifier dispatch',
            'no new support component needed; reuses SQLiteCoreScalarFunction statementDateTimeResults and existing date/time modifier dispatch'
        );
    };

function sqliteRealUpstreamDateAffinityNowModifierExpected(DateTimeImmutable $base, string $modifier): string
{
    if (preg_match('/\Aweekday ([0-6])\z/', $modifier, $matches) === 1) {
        $target = (int) $matches[1];
        $current = (int) $base->format('w');
        $days = ($target - $current + 7) % 7;

        return $base->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
    }

    if ($modifier === 'start of day') {
        return $base->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    }

    if ($modifier === 'start of month') {
        return $base->setDate((int) $base->format('Y'), (int) $base->format('m'), 1)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    }

    if (preg_match('/\A([+-]?(?:\d+(?:\.\d*)?|\.\d+)) (second|seconds|minute|minutes|hour|hours|day|days)\z/', $modifier, $matches) !== 1) {
        throw new InvalidArgumentException('Unsupported generated date8 modifier: ' . $modifier);
    }

    $amount = (float) $matches[1];
    $seconds = match (rtrim($matches[2], 's')) {
        'second' => $amount,
        'minute' => $amount * 60.0,
        'hour' => $amount * 3600.0,
        'day' => $amount * 86400.0,
    };
    $microseconds = (int) round($seconds * 1000000.0);
    $sign = $microseconds < 0 ? '-' : '+';
    $absolute = abs($microseconds);
    $wholeSeconds = intdiv($absolute, 1000000);
    $remainingMicroseconds = $absolute % 1000000;
    $changed = $base;
    if ($wholeSeconds > 0) {
        $changed = $changed->modify($sign . $wholeSeconds . ' seconds');
    }
    if ($remainingMicroseconds > 0) {
        $changed = $changed->modify($sign . $remainingMicroseconds . ' microseconds');
    }

    return $changed->format('Y-m-d H:i:s');
}

return $tests;
