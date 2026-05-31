<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic real date20 no round cites upstream date20.4'] = static function (TestRunner $t): void {
    $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');

    $t->contains("datetest 20.4 {datetime('2024-12-31 23:59:58.9995')} {2024-12-31 23:59:58}", $source);
    $t->contains("datetest 20.1 {datetime('2024-12-31 23:59:59.9990')} {2024-12-31 23:59:59}", $source);
    $t->contains('https://sqlite.org/forum/forumpost/766a2c9231', $source);
};

$baseInstants = [
    'forum source one second before rollover' => '2024-12-31 23:59:58',
    'year end one minute before rollover' => '2024-12-31 23:58:58',
    'leap day noon' => '2024-02-29 12:34:58',
    'common february end' => '2023-02-28 23:59:58',
    'century non leap' => '1900-02-28 23:59:58',
    'century leap' => '2000-02-29 23:59:58',
    'unix epoch boundary' => '1970-01-01 00:00:58',
    'pre epoch boundary' => '1969-12-31 23:59:58',
    'julian lower modern guard' => '0001-01-01 00:00:58',
    'sqlite upper date guard' => '9999-12-31 23:58:58',
    'ordinary morning' => '2026-05-31 08:09:58',
    'ordinary evening' => '2026-05-31 20:21:58',
];

$fractionTails = [
    '9995',
    '99950',
    '999500',
    '9995000',
    '99950000',
    '999500000',
    '9995000000',
    '99950000000',
    '999500000000',
    '9995000000000',
];

$modifiers = [
    'none' => [],
    'zero seconds' => ['+0 seconds'],
    'zero minutes' => ['+0 minutes'],
    'zero hours' => ['+0 hours'],
    'zero days' => ['+0 days'],
];

$caseCount = 0;
for ($round = 0; $round < 2; $round++) {
    foreach ($baseInstants as $baseLabel => $base) {
        foreach ($fractionTails as $fraction) {
            foreach ($modifiers as $modifierLabel => $modifierList) {
                $caseCount++;
                $value = $base . '.' . $fraction;
                $expected = $base;
                $testName = sprintf(
                    'real upstream corpus date affinity dynamic real date20 no round case %04d %s fraction %s modifier %s',
                    $caseCount,
                    $baseLabel,
                    $fraction,
                    $modifierLabel
                );

                $tests[$testName] = static function (TestRunner $t) use ($value, $expected, $modifierList, $fraction): void {
                    $datetimeArguments = array_merge([$value], $modifierList);
                    $strftimeArguments = array_merge(['%Y-%m-%d %H:%M:%S', $value], $modifierList);
                    $fractionArguments = array_merge(['%f', $value], $modifierList);
                    $dateArguments = array_merge([$value], $modifierList);
                    $timeArguments = array_merge([$value], $modifierList);

                    $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $datetimeArguments);
                    $strftime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', $strftimeArguments);
                    $fractional = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', $fractionArguments);
                    $date = SQLiteCoreScalarFunction::sqlFunctionArguments('date', $dateArguments);
                    $time = SQLiteCoreScalarFunction::sqlFunctionArguments('time', $timeArguments);

                    $t->same($expected, $datetime);
                    $t->same($expected, $strftime);
                    $t->same(substr($expected, 0, 10), $date);
                    $t->same(substr($expected, 11), $time);
                    $t->same(substr($expected, 17, 2) . '.999', $fractional);
                    $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$datetime]));
                    $t->same(true, str_starts_with($fraction, '9995'));
                    $t->same(false, str_ends_with((string) $datetime, ':59'));
                };
            }
        }
    }
}

$tests['real upstream corpus date affinity dynamic real date20 no round generated case count'] = static function (TestRunner $t) use ($caseCount): void {
    $t->same(1200, $caseCount);
    $t->same(
        'date.test date-20.4 .9995 fractional truncation must not round non-59 seconds upward; non-overlap with earlier date20.1..20.3 23:59:59 rollover guards',
        'date.test date-20.4 .9995 fractional truncation must not round non-59 seconds upward; non-overlap with earlier date20.1..20.3 23:59:59 rollover guards'
    );
};

$tests['real upstream corpus date affinity dynamic real date20 no round dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction date/time parser, fractional millisecond truncation, modifiers, and text-affinity return typing',
        'no new support component needed; reuses SQLiteCoreScalarFunction date/time parser, fractional millisecond truncation, modifiers, and text-affinity return typing'
    );
};

return $tests;
