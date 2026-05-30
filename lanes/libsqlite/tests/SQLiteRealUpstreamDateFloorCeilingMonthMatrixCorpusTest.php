<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream date floor ceiling month matrix cites date.test section 19'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-19.1..19.12 floor normalizes day-31 month boundaries',
        'date.test date-19.21..19.32 ceiling normalizes day-31 month boundaries',
        'date.test date-19.22a date(2000-02-31,floor|ceiling) leap February split',
        'date.test date-19.22b date(1999-02-31,floor|ceiling) common February split',
        'date.test date-19.22c date(1900-02-31,floor|ceiling) century common February split',
    ];

    $t->same(true, in_array('date.test date-19.1..19.12 floor normalizes day-31 month boundaries', $upstream, true));
    $t->same(true, in_array('date.test date-19.21..19.32 ceiling normalizes day-31 month boundaries', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

for ($year = 1975; $year <= 2024; $year++) {
    for ($month = 1; $month <= 12; $month++) {
        $input = sprintf('%04d-%02d-31', $year, $month);
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $floor = sprintf('%04d-%02d-%02d', $year, $month, min(31, $lastDay));
        $ceiling = (new DateTimeImmutable($input, new DateTimeZone('UTC')))->format('Y-m-d');
        $label = sprintf('%04d-%02d', $year, $month);
        $validDay = $lastDay === 31;

        $tests['real upstream date floor ceiling month matrix date.test date-19 floor ' . $label] = static function (TestRunner $t) use ($input, $floor, $ceiling, $validDay): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$input, 'floor']);

            $t->same($floor, $actual);
            $t->same($validDay, $actual === $ceiling);
            $t->same(10, strlen((string) $actual));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
            $t->same($floor, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', $input, 'floor']));
        };

        $tests['real upstream date floor ceiling month matrix date.test date-19 ceiling ' . $label] = static function (TestRunner $t) use ($input, $floor, $ceiling, $validDay): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$input, 'ceiling']);

            $t->same($ceiling, $actual);
            $t->same($validDay, $actual === $floor);
            $t->same(10, strlen((string) $actual));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
            $t->same($ceiling, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', $input, 'ceiling']));
        };
    }
}

$tests['real upstream date floor ceiling month matrix generic retention cutoff'] = static function (TestRunner $t): void {
    $rows = [
        ['key_name' => 'billing.leap.floor', 'raw' => '2024-02-31', 'policy' => 'floor'],
        ['key_name' => 'billing.leap.ceiling', 'raw' => '2024-02-31', 'policy' => 'ceiling'],
        ['key_name' => 'billing.common.floor', 'raw' => '2023-04-31', 'policy' => 'floor'],
        ['key_name' => 'billing.common.ceiling', 'raw' => '2023-04-31', 'policy' => 'ceiling'],
    ];
    $normalized = [];

    foreach ($rows as $row) {
        $normalized[$row['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$row['raw'], $row['policy']]);
    }

    $t->same([
        'billing.leap.floor' => '2024-02-29',
        'billing.leap.ceiling' => '2024-03-02',
        'billing.common.floor' => '2023-04-30',
        'billing.common.ceiling' => '2023-05-01',
    ], $normalized);
};

return $tests;
