<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$strftimeCases = [
    'year four digits' => ['%Y', '2024-02-29 15:06:07.890', '2024'],
    'month two digits' => ['%m', '2024-02-29 15:06:07.890', '02'],
    'day two digits' => ['%d', '2024-02-29 15:06:07.890', '29'],
    'day space padded' => ['%e', '2024-01-09 15:06:07.890', ' 9'],
    'hour two digits' => ['%H', '2024-02-29 05:06:07.890', '05'],
    'hour space padded' => ['%k', '2024-02-29 05:06:07.890', ' 5'],
    'hour twelve two digits' => ['%I', '2024-02-29 15:06:07.890', '03'],
    'hour twelve space padded' => ['%l', '2024-02-29 15:06:07.890', ' 3'],
    'minute two digits' => ['%M', '2024-02-29 15:06:07.890', '06'],
    'second two digits' => ['%S', '2024-02-29 15:06:07.890', '07'],
    'fractional second milliseconds' => ['%f', '2024-02-29 15:06:07.890', '07.890'],
    'fractional second absent becomes zero' => ['%f', '2024-02-29 15:06:07', '07.000'],
    'day of year leap day' => ['%j', '2024-02-29 15:06:07.890', '060'],
    'weekday sunday zero' => ['%w', '2024-03-03 15:06:07.890', '0'],
    'weekday monday one based' => ['%u', '2024-03-03 15:06:07.890', '7'],
    'week monday first' => ['%W', '2024-02-29 15:06:07.890', '09'],
    'week sunday first' => ['%U', '2024-02-29 15:06:07.890', '08'],
    'iso week' => ['%V', '2024-02-29 15:06:07.890', '09'],
    'iso week year' => ['%G', '2024-12-31 23:59:59', '2025'],
    'iso week year short' => ['%g', '2024-12-31 23:59:59', '25'],
    'am marker' => ['%p', '2024-02-29 05:06:07.890', 'AM'],
    'pm marker lowercase' => ['%P', '2024-02-29 15:06:07.890', 'pm'],
    'short time composite' => ['%R', '2024-02-29 15:06:07.890', '15:06'],
    'full time composite' => ['%T', '2024-02-29 15:06:07.890', '15:06:07'],
    'date composite' => ['%F', '2024-02-29 15:06:07.890', '2024-02-29'],
    'unix seconds keeps integer part' => ['%s', '2024-02-29 15:06:07.890', '1709219167'],
    'literal percent' => ['%%', '2024-02-29 15:06:07.890', '%'],
    'full application timestamp label' => ['%F %T.%f %p week=%W iso=%G-W%V-%u', '2024-02-29 15:06:07.890', '2024-02-29 15:06:07.07.890 PM week=09 iso=2024-W09-4'],
    'new year monday week starts at one' => ['%Y-%W-%U-%V', '2024-01-01 00:00:00', '2024-01-00-01'],
    'new year before sunday week zero' => ['%Y-%W-%U-%V', '2022-01-01 00:00:00', '2022-00-00-52'],
    'leap year end week numbers' => ['%Y-%j-%W-%U-%V', '2024-12-31 23:59:59', '2024-366-53-52-01'],
    'midday twelve hour' => ['%H:%I:%l %p', '2024-02-29 12:00:00', '12:12:12 PM'],
    'midnight twelve hour' => ['%H:%I:%l %P', '2024-02-29 00:00:00', '00:12:12 am'],
    'julian day unix epoch' => ['%J', '1970-01-01 00:00:00', '2440587.5'],
    'julian day fractional timestamp' => ['%J', '2024-02-29 15:06:07.890', '2460370.129257986'],
    'unsupported format returns null' => ['%Q', '2024-02-29 15:06:07.890', null],
];

foreach ($strftimeCases as $name => [$format, $value, $expected]) {
    $tests['upstream strftime modifier corpus ' . $name] = static function (TestRunner $t) use ($format, $value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $value]));
    };
}

$modifierCases = [
    'unixepoch with fractional format' => ['%F %T.%f', 1709219167, ['unixepoch'], '2024-02-29 15:06:07.07.000'],
    'start of day before fractional format' => ['%F %T.%f', '2024-02-29 15:06:07.890', ['start of day'], '2024-02-29 00:00:00.00.000'],
    'start of month before week format' => ['%F week=%W', '2024-02-29 15:06:07.890', ['start of month'], '2024-02-01 week=05'],
    'start of year before day of year' => ['%F day=%j', '2024-02-29 15:06:07.890', ['start of year'], '2024-01-01 day=001'],
    'weekday modifier preserves time fields' => ['%F %T %w', '2024-02-29 15:06:07.890', ['weekday 0'], '2024-03-03 15:06:07 0'],
    'weekday same day stays put' => ['%F %w', '2024-03-03 15:06:07.890', ['weekday 0'], '2024-03-03 0'],
    'signed day modifier' => ['%F %j', '2024-02-29 15:06:07.890', ['+1 day'], '2024-03-01 061'],
    'signed hour modifier' => ['%F %H:%M:%S', '2024-02-29 23:06:07.890', ['+2 hours'], '2024-03-01 01:06:07'],
    'signed minute modifier' => ['%F %H:%M:%S', '2024-02-29 15:06:07.890', ['-10 minutes'], '2024-02-29 14:56:07'],
    'signed second modifier' => ['%F %H:%M:%S', '2024-02-29 15:06:07.890', ['+53 seconds'], '2024-02-29 15:07:00'],
    'signed month modifier' => ['%F', '2024-01-31 15:06:07.890', ['+1 month'], '2024-03-02'],
    'signed year modifier' => ['%F', '2024-02-29 15:06:07.890', ['+1 year'], '2025-03-01'],
];

foreach ($modifierCases as $name => [$format, $value, $modifiers, $expected]) {
    $tests['upstream strftime modifier corpus ' . $name] = static function (TestRunner $t) use ($format, $value, $modifiers, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', array_merge([$format, $value], $modifiers)));
    };
}

$tests['upstream strftime modifier corpus null time value propagates'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', null]));
};

$tests['upstream strftime modifier corpus null modifier propagates'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', '2024-02-29', null]));
};

$tests['upstream strftime modifier corpus null format propagates'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [null, '2024-02-29']));
};

$tests['upstream strftime modifier corpus julianday accepts fractional value'] = static function (TestRunner $t): void {
    $t->same(2460370.129258, round(SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', ['2024-02-29 15:06:07.890']), 6));
};

$tests['upstream strftime modifier corpus datetime preserves fractional parse second'] = static function (TestRunner $t): void {
    $t->same('2024-02-29 15:06:07', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2024-02-29 15:06:07.890']));
};

$tests['upstream strftime modifier corpus time preserves fractional parse second'] = static function (TestRunner $t): void {
    $t->same('15:06:07', SQLiteCoreScalarFunction::sqlFunctionArguments('time', ['2024-02-29 15:06:07.890']));
};

$tests['upstream strftime modifier corpus unsupported modifier remains guarded'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', '2024-02-29', 'localtime']));
};

$tests['upstream strftime modifier corpus bad fractional value remains guarded'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', '2024-02-29 15:06:07.bad']));
};

$tests['upstream strftime modifier corpus too few arguments remains guarded'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F']));
};

$tests['upstream strftime modifier corpus application cron bucket summary'] = static function (TestRunner $t): void {
    $summary = [
        'bucket' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%G-W%V-%u', '2024-12-31 23:59:59']),
        'stamp' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%FT%TZ', 1709219167, 'unixepoch']),
        'fraction' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%f', '2024-02-29 15:06:07.890']),
    ];
    $t->same(['bucket' => '2025-W01-2', 'stamp' => '2024-02-29T15:06:07Z', 'fraction' => '07.890'], $summary);
};

$tests['upstream strftime modifier corpus application monthly archive key'] = static function (TestRunner $t): void {
    $t->same('2024/02/day-060', SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y/%m/day-%j', '2024-02-29 15:06:07.890']));
};

$tests['upstream strftime modifier corpus application twelve hour audit label'] = static function (TestRunner $t): void {
    $t->same('03:06:07 PM', SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%I:%M:%S %p', '2024-02-29 15:06:07.890']));
};

return $tests;
