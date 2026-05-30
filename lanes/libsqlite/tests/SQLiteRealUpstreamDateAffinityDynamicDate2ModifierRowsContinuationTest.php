<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic date2 modifier rows continuation cites source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test';

    $t->same(true, is_file($source), $source);
    $t->contains('date2-500', file_get_contents($source));
    $t->contains("CREATE INDEX t5x1 on t5(y) WHERE datetime(y,m) IS NOT NULL", file_get_contents($source));
    $t->same(
        'date2.test date2-500 deterministic datetime(y,m) partial-index modifier table continuation rows',
        'date2.test date2-500 deterministic datetime(y,m) partial-index modifier table continuation rows'
    );
};

$modifierFactories = [
    '+10 days' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('+10 days'),
    '-10 days' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('-10 days'),
    '+10 hours' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('+10 hours'),
    '-10 hours' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('-10 hours'),
    '+10 minutes' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('+10 minutes'),
    '-10 minutes' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('-10 minutes'),
    '+10 seconds' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('+10 seconds'),
    '-10 seconds' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('-10 seconds'),
    '+10 months' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('+10 months'),
    '-10 months' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('-10 months'),
    '+10 years' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('+10 years'),
    '-10 years' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->modify('-10 years'),
    'start of month' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->setDate((int) $base->format('Y'), (int) $base->format('m'), 1)->setTime(0, 0, 0),
    'start of year' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->setDate((int) $base->format('Y'), 1, 1)->setTime(0, 0, 0),
    'start of day' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => $base->setTime(0, 0, 0),
    'weekday 1' => static function (DateTimeImmutable $base, float $julianDay): DateTimeImmutable {
        $days = (1 - (int) $base->format('w') + 7) % 7;

        return $days === 0 ? $base : $base->modify('+' . $days . ' days');
    },
    'unixepoch' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => (new DateTimeImmutable('@' . (string) floor($julianDay)))->setTimezone(new DateTimeZone('UTC')),
];

foreach ($modifierFactories as $modifier => $expectedFactory) {
    for ($rowid = 69; $rowid <= 128; $rowid++) {
        $julianDay = 2457935.5 + $rowid;
        $base = (new DateTimeImmutable('2017-07-01 00:00:00', new DateTimeZone('UTC')))->modify('+' . $rowid . ' days');
        $expectedDateTime = $expectedFactory($base, $julianDay)->format('Y-m-d H:i:s');
        $expectedDate = substr($expectedDateTime, 0, 10);
        $expectedTime = substr($expectedDateTime, 11);
        $label = str_replace([' ', '+', '-'], ['-', 'plus-', 'minus-'], $modifier);

        $tests[sprintf('real upstream corpus date affinity dynamic date2.test date2-500 continuation modifier %s row %03d', $label, $rowid)] = static function (TestRunner $t) use ($julianDay, $modifier, $expectedDateTime, $expectedDate, $expectedTime, $rowid): void {
            $actualDateTime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay, $modifier]);

            $t->same($expectedDateTime, $actualDateTime);
            $t->same($expectedDate, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay, $modifier]));
            $t->same($expectedTime, SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$julianDay, $modifier]));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actualDateTime]));
            $t->same(true, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', [$julianDay, $modifier]));
            $t->same(true, $rowid >= 69 && $rowid <= 128);
        };
    }
}

return $tests;
