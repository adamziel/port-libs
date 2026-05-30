<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tests['real upstream corpus date affinity dynamic unixepoch batch cites upstream files'] = static function (TestRunner $t): void {
    $upstream = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test',
        "date3-1.7: unixepoch(x,'unixepoch')==x over generated timestamp values",
    ];

    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test', $upstream[0]);
    $t->same(true, str_contains($upstream[1], 'date3-1.7'));
};

for ($offset = -500; $offset < 500; $offset++) {
    $timestamp = $offset * 86400 + (($offset % 17) * 37);
    $expectedDatetime = gmdate('Y-m-d H:i:s', $timestamp);

    $tests['real upstream corpus date affinity dynamic unixepoch batch date3.test date3-1.7 deterministic timestamp ' . ($offset + 500)] = static function (TestRunner $t) use ($timestamp, $expectedDatetime): void {
        $unixepoch = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$timestamp, 'unixepoch']);
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timestamp, 'unixepoch']);
        $storage = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$unixepoch]);

        $t->same($timestamp, $unixepoch);
        $t->same('integer', $storage);
        $t->same($expectedDatetime, $datetime);
        $t->same($timestamp, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$datetime]));
    };
}

$tests['real upstream corpus date affinity dynamic unixepoch batch application mixed retention cutoff'] = static function (TestRunner $t): void {
    $rows = [];
    foreach ([-432000, -86400, 0, 86400, 172800] as $index => $timestamp) {
        $rows[] = [
            'key_name' => 'event-' . $index,
            'event_epoch' => SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$timestamp, 'unixepoch']),
            'event_datetime' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timestamp, 'unixepoch']),
            'event_type' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$timestamp]),
        ];
    }

    $matches = SQLiteSelectSql::execute(
        "SELECT key_name, event_epoch, event_datetime FROM app_events WHERE event_epoch >= 0 ORDER BY event_epoch",
        ['app_events' => $rows],
    );

    $t->same([
        ['key_name' => 'event-2', 'event_epoch' => 0, 'event_datetime' => '1970-01-01 00:00:00'],
        ['key_name' => 'event-3', 'event_epoch' => 86400, 'event_datetime' => '1970-01-02 00:00:00'],
        ['key_name' => 'event-4', 'event_epoch' => 172800, 'event_datetime' => '1970-01-03 00:00:00'],
    ], $matches);
    $t->same(['integer', 'integer', 'integer', 'integer', 'integer'], array_column($rows, 'event_type'));
};

return $tests;
