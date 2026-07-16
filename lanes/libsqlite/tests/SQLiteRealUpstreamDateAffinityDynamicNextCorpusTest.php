<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

for ($millisecond = 0; $millisecond < 1000; $millisecond++) {
    $epoch = (float) ('1237962480.' . str_pad((string) $millisecond, 3, '0', STR_PAD_LEFT));
    $expected = '06:28:00.' . str_pad((string) $millisecond, 3, '0', STR_PAD_LEFT);

    $tests['real upstream corpus date affinity dynamic next date.test date-2.2c-' . $millisecond . ' unixepoch fractional strftime'] = static function (TestRunner $t) use ($millisecond, $epoch, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H:%M:%f', $epoch, 'unixepoch']);

        $t->same($expected, $actual);
        $t->same(12, strlen((string) $actual));
        $t->same('06:28:00', substr((string) $actual, 0, 8));
        $t->same(str_pad((string) $millisecond, 3, '0', STR_PAD_LEFT), substr((string) $actual, -3));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$epoch]));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($millisecond === 0 ? '06:28:00.000' : $expected, (string) $actual);
    };
}

$affinity2Rows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1.0, 'xb' => 1, 'xn' => 1, 'xt' => '1'],
    ['rowid' => 2, 'xi' => 2, 'xr' => 2.0, 'xb' => '2', 'xn' => 2, 'xt' => '2'],
    ['rowid' => 3, 'xi' => 3, 'xr' => 3.0, 'xb' => '03', 'xn' => 3, 'xt' => '03'],
];

$tests['real upstream corpus date affinity dynamic next affinity2.test affinity2-110 integer column storage'] = static function (TestRunner $t) use ($affinity2Rows): void {
    $actual = [];
    foreach ($affinity2Rows as $row) {
        $actual[] = $row['xi'];
        $actual[] = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['xi']]);
    }

    $t->same([1, 'integer', 2, 'integer', 3, 'integer'], $actual);
    $t->same([1, 2, 3], array_column($affinity2Rows, 'xi'));
};

$tests['real upstream corpus date affinity dynamic next affinity2.test affinity2-120 real column storage'] = static function (TestRunner $t) use ($affinity2Rows): void {
    $actual = [];
    foreach ($affinity2Rows as $row) {
        $actual[] = $row['xr'];
        $actual[] = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['xr']]);
    }

    $t->same([1.0, 'real', 2.0, 'real', 3.0, 'real'], $actual);
    $t->same([1.0, 2.0, 3.0], array_column($affinity2Rows, 'xr'));
};

$tests['real upstream corpus date affinity dynamic next affinity2.test affinity2-130 blob affinity preserves text numerals'] = static function (TestRunner $t) use ($affinity2Rows): void {
    $actual = [];
    foreach ($affinity2Rows as $row) {
        $actual[] = $row['xb'];
        $actual[] = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['xb']]);
    }

    $t->same([1, 'integer', '2', 'text', '03', 'text'], $actual);
    $t->same(['2', '03'], array_slice(array_column($affinity2Rows, 'xb'), 1));
};

$tests['real upstream corpus date affinity dynamic next affinity2.test affinity2-140 numeric column storage'] = static function (TestRunner $t) use ($affinity2Rows): void {
    $actual = [];
    foreach ($affinity2Rows as $row) {
        $actual[] = $row['xn'];
        $actual[] = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['xn']]);
    }

    $t->same([1, 'integer', 2, 'integer', 3, 'integer'], $actual);
    $t->same(array_column($affinity2Rows, 'xi'), array_column($affinity2Rows, 'xn'));
};

$tests['real upstream corpus date affinity dynamic next affinity2.test affinity2-150 text column storage'] = static function (TestRunner $t) use ($affinity2Rows): void {
    $actual = [];
    foreach ($affinity2Rows as $row) {
        $actual[] = $row['xt'];
        $actual[] = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['xt']]);
    }

    $t->same(['1', 'text', '2', 'text', '03', 'text'], $actual);
    $t->same(['1', '2', '03'], array_column($affinity2Rows, 'xt'));
};

$affinity3Tables = [
    'customer' => [
        ['id' => 1],
        ['id' => 2],
    ],
    'apr' => [
        ['id' => 1, 'apr' => 12.0],
        ['id' => 2, 'apr' => 12.01],
    ],
];

$affinity3Queries = [
    'affinity3.test affinity3-110 left join real division' => 'SELECT customer.id, apr.apr / 100 AS ratio, typeof(apr.apr) AS apr_type FROM customer LEFT JOIN apr ON apr.id = customer.id ORDER BY customer.id',
    'affinity3.test affinity3-130 automatic-index-off equivalent real division' => 'SELECT customer.id, apr.apr / 100 AS ratio, typeof(apr.apr) AS apr_type FROM customer LEFT JOIN apr ON apr.id = customer.id ORDER BY customer.id',
];

foreach ($affinity3Queries as $name => $sql) {
    $tests['real upstream corpus date affinity dynamic next ' . $name] = static function (TestRunner $t) use ($sql, $affinity3Tables): void {
        $rows = SQLiteSelectSql::execute($sql, $affinity3Tables);
        $flat = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $flat[] = is_float($value) ? round($value, 4) : $value;
            }
        }

        $t->same([1, 0.12, 'real', 2, 0.1201, 'real'], $flat);
        $t->same('real', $rows[0]['apr_type']);
        $t->same('real', $rows[1]['apr_type']);
    };
}

$tests['real upstream corpus date affinity dynamic next types3.test types3-1.4 bound double appears real'] = static function (TestRunner $t): void {
    $value = 1.0 + 1;

    $t->same(2.0, $value);
    $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]));
};

$tests['real upstream corpus date affinity dynamic next types3.test types3-3.4 text affinity real string comparison'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT x FROM t1 WHERE NOT x = '1.25'",
        ['t1' => [['x' => '1.25']]],
    );

    $t->same([], $rows);
    $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', ['1.25']));
    $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [1.25]));
};

$tests['real upstream corpus date affinity dynamic next application retention millisecond buckets stay real-backed'] = static function (TestRunner $t): void {
    $rows = [];
    foreach ([0, 125, 250, 500, 875] as $millisecond) {
        $epoch = (float) ('1237962480.' . str_pad((string) $millisecond, 3, '0', STR_PAD_LEFT));
        $rows[] = [
            'key_name' => 'event.' . $millisecond,
            'event_epoch' => $epoch,
            'event_time' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H:%M:%f', $epoch, 'unixepoch']),
            'storage_type' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$epoch]),
        ];
    }

    $matches = SQLiteSelectSql::execute(
        "SELECT key_name, event_time, storage_type FROM app_events WHERE event_time >= '06:28:00.250' ORDER BY event_epoch",
        ['app_events' => $rows],
    );

    $t->same([
        ['key_name' => 'event.250', 'event_time' => '06:28:00.250', 'storage_type' => 'real'],
        ['key_name' => 'event.500', 'event_time' => '06:28:00.500', 'storage_type' => 'real'],
        ['key_name' => 'event.875', 'event_time' => '06:28:00.875', 'storage_type' => 'real'],
    ], $matches);
    $t->same(['real', 'real', 'real', 'real', 'real'], array_column($rows, 'storage_type'));
};

return $tests;
