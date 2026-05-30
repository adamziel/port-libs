<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

for ($rowid = 1; $rowid <= 620; $rowid++) {
    $julianDay = 2457935.5 + $rowid; // julianday('2017-07-01') + rowid
    $expectedDateTime = (new DateTimeImmutable('2017-07-01 00:00:00', new DateTimeZone('UTC')))
        ->modify('+' . $rowid . ' days')
        ->format('Y-m-d H:i:s');
    $expectedInRange = $rowid >= 3 && $rowid <= 6;

    $tests['real upstream corpus date2 deterministic dynamic date2.test date2-331 row ' . $rowid . ' datetime range predicate'] = static function (TestRunner $t) use ($julianDay, $expectedDateTime, $expectedInRange): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);

        $t->same($expectedDateTime, $actual);
        $t->same($expectedInRange, $actual >= '2017-07-04' && $actual <= '2017-07-08');
    };
}

$modifiers = [
    '+10 days' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('+10 days'),
    '-10 days' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('-10 days'),
    '+10 hours' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('+10 hours'),
    '-10 hours' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('-10 hours'),
    '+10 minutes' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('+10 minutes'),
    '-10 minutes' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('-10 minutes'),
    '+10 seconds' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('+10 seconds'),
    '-10 seconds' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('-10 seconds'),
    '+10 months' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('+10 months'),
    '-10 months' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('-10 months'),
    '+10 years' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('+10 years'),
    '-10 years' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->modify('-10 years'),
    'start of month' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->setDate((int) $base->format('Y'), (int) $base->format('m'), 1)->setTime(0, 0, 0),
    'start of year' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->setDate((int) $base->format('Y'), 1, 1)->setTime(0, 0, 0),
    'start of day' => static fn (DateTimeImmutable $base): DateTimeImmutable => $base->setTime(0, 0, 0),
    'weekday 1' => static function (DateTimeImmutable $base): DateTimeImmutable {
        $days = (1 - (int) $base->format('w') + 7) % 7;

        return $days === 0 ? $base : $base->modify('+' . $days . ' days');
    },
    'unixepoch' => static fn (DateTimeImmutable $base, float $julianDay): DateTimeImmutable => (new DateTimeImmutable('@' . (string) floor($julianDay)))->setTimezone(new DateTimeZone('UTC')),
];

foreach ($modifiers as $modifier => $expectedFactory) {
    for ($rowid = 1; $rowid < 5; $rowid++) {
        $julianDay = 2457935.5 + $rowid;
        $base = (new DateTimeImmutable('2017-07-01 00:00:00', new DateTimeZone('UTC')))->modify('+' . $rowid . ' days');
        $expected = $expectedFactory($base, $julianDay)->format('Y-m-d H:i:s');
        $label = str_replace([' ', '+', '-'], ['-', 'plus-', 'minus-'], $modifier);

        $tests['real upstream corpus date2 deterministic dynamic date2.test date2-500 modifier ' . $label . ' row ' . $rowid] = static function (TestRunner $t) use ($julianDay, $modifier, $expected): void {
            $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay, $modifier]));
        };
    }
}

$tests['real upstream corpus date2 deterministic dynamic application retention window uses date affinity'] = static function (TestRunner $t): void {
    $settings = [
        ['key_name' => 'cache.alpha', 'stored_at' => 2457938.5],
        ['key_name' => 'cache.beta', 'stored_at' => 2457942.5],
        ['key_name' => 'cache.gamma', 'stored_at' => '2457943.5'],
    ];
    $selected = [];

    foreach ($settings as $setting) {
        $storedAt = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$setting['stored_at']]);
        if ($storedAt >= '2017-07-04 00:00:00' && $storedAt <= '2017-07-08 00:00:00') {
            $selected[] = $setting['key_name'];
        }
    }

    $t->same(['cache.alpha', 'cache.beta'], $selected);
};

return $tests;
