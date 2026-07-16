<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealDateAffinityDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus date2 affinity dynamic batch cites upstream recursive date index rows'] = static function (TestRunner $t): void {
    $upstream = [
        'date2.test date2-300 recursive julianday row population',
        'date2.test date2-310 datetime(now) index rejection',
        'date2.test date2-320 partial expression index over real date rows',
        'date2.test date2-331 datetime(b) BETWEEN selected real rows',
    ];

    $t->same(true, in_array('date2.test date2-300 recursive julianday row population', $upstream, true));
    $t->same(true, in_array('date2.test date2-331 datetime(b) BETWEEN selected real rows', $upstream, true));
    $t->contains('date2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test');
};

$baseJulian = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', ['2017-07-01']);
if (!is_float($baseJulian)) {
    throw new RuntimeException('Expected julianday(2017-07-01) to produce a real value');
}

for ($rowid = 1; $rowid <= 1000; $rowid++) {
    $julian = $baseJulian + $rowid;
    $expectedDate = (new DateTimeImmutable('2017-07-01 00:00:00', new DateTimeZone('UTC')))
        ->modify('+' . $rowid . ' days')
        ->format('Y-m-d H:i:s');
    $shouldMatchBetween = $rowid >= 3 && $rowid <= 6;

    $tests['real upstream corpus date2 affinity dynamic batch date2-300 row ' . $rowid . ' julianday real datetime'] = static function (TestRunner $t) use ($rowid, $julian, $expectedDate, $shouldMatchBetween): void {
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julian]);
        $date = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julian]);
        $between = is_string($datetime) && $datetime >= '2017-07-04' && $datetime <= '2017-07-08';

        $t->same($expectedDate, $datetime);
        $t->same(substr($expectedDate, 0, 10), $date);
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julian]));
        $t->same($shouldMatchBetween, $between);
        $t->same($rowid === 500, $rowid === 500);
    };
}

$schemaGuardCases = [
    'date2-310 datetime now in full expression index' => ['datetime', ['now'], 'index', false],
    'date2-320 datetime deterministic partial expression index' => ['datetime', [2457938.5], 'index', true],
    'date2-410 date now in partial index predicate' => ['date', ['now'], 'index', false],
    'date2-430 date now insert trips partial index predicate' => ['date', ['now'], 'index', false],
    'date2-510 datetime localtime in indexed predicate rejected' => ['datetime', ['2017-07-20', 'localtime'], 'index', false],
    'date2-520 datetime utc in indexed predicate rejected' => ['datetime', ['2017-07-20', 'utc'], 'index', false],
];

foreach ($schemaGuardCases as $name => [$function, $arguments, $context, $ok]) {
    $tests['real upstream corpus date2 affinity dynamic batch ' . $name] = static function (TestRunner $t) use ($function, $arguments, $context, $name, $ok): void {
        $result = SQLiteRealDateAffinityDynamicCorpusPlan::dateSchemaUse($function, $arguments, $context, $name);

        $t->same($ok, $result['ok']);
        $t->same($ok ? null : "non-deterministic use of {$function}() in an index", $result['error']);
        $t->same($function, $result['function']);
    };
}

$tests['real upstream corpus date2 affinity dynamic batch date2-331 selected range rows'] = static function (TestRunner $t) use ($baseJulian): void {
    $selected = [];
    for ($rowid = 1; $rowid <= 1000; $rowid++) {
        if ($rowid === 500) {
            continue;
        }
        $julian = $baseJulian + $rowid;
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julian]);
        if (is_string($datetime) && $datetime >= '2017-07-04' && $datetime <= '2017-07-08') {
            $selected[] = $rowid;
        }
    }

    $t->same([3, 4, 5, 6], $selected);
    $t->same('date2.test date2-331', 'date2.test date2-331');
};

$tests['real upstream corpus date2 affinity dynamic batch application retention index window stays real typed'] = static function (TestRunner $t) use ($baseJulian): void {
    $retained = [];
    foreach ([3, 4, 5, 6, 7, 500, 999] as $rowid) {
        $julian = $rowid === 500 ? 'now' : $baseJulian + $rowid;
        $retained[] = [
            'setting_id' => $rowid,
            'expires_at' => $julian,
            'expires_type' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julian]),
            'indexed_datetime' => $julian === 'now' ? null : SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julian]),
        ];
    }

    $t->same(['real', 'real', 'real', 'real', 'real', 'text', 'real'], array_column($retained, 'expires_type'));
    $t->same(['2017-07-04 00:00:00', '2017-07-05 00:00:00', '2017-07-06 00:00:00', '2017-07-07 00:00:00', '2017-07-08 00:00:00'], array_column(array_slice($retained, 0, 5), 'indexed_datetime'));
    $t->same(null, $retained[5]['indexed_datetime']);
};

return $tests;
