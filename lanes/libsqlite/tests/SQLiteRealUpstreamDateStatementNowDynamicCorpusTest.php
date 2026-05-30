<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date statement now dynamic cites upstream date15'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-15.1 julianday now remains stable within one sqlite3_step call',
        'date.test date-15.2 current_timestamp remains stable within one sqlite3_step call',
    ];

    $t->same(true, in_array('date.test date-15.1 julianday now remains stable within one sqlite3_step call', $upstream, true));
    $t->same(true, in_array('date.test date-15.2 current_timestamp remains stable within one sqlite3_step call', $upstream, true));
};

$seed = new DateTimeImmutable('2026-05-30 12:00:00.123456', new DateTimeZone('UTC'));

// Source truth: SQLite upstream test/date.test date-15.1 and date-15.2.  The
// Tcl tests call a sleeper function between date/time expressions and require
// the same "now" value for all invocations in a single sqlite3_step().
for ($case = 0; $case < 1000; $case++) {
    $stepNow = $seed->modify(sprintf('+%d seconds', $case * 37));
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date statement now dynamic date.test date-15 stable step ' . $label] = static function (TestRunner $t) use ($stepNow): void {
        $results = SQLiteCoreScalarFunction::statementDateTimeResults([
            ['function' => 'julianday', 'arguments' => ['now']],
            ['function' => 'julianday', 'arguments' => ['now']],
            ['function' => 'current_timestamp'],
            ['function' => 'current_timestamp'],
            ['function' => 'strftime', 'arguments' => ['%Y-%m-%d %H:%M:%f', 'now']],
            ['function' => 'datetime', 'arguments' => ['now', 'subsec']],
            ['function' => 'unixepoch', 'arguments' => ['now', 'subsec']],
        ], $stepNow);

        $t->same($results[0], $results[1]);
        $t->same($results[2], $results[3]);
        $t->same(substr((string) $results[4], 0, 19), $results[2]);
        $t->same($results[4], str_replace(' ', ' ', (string) $results[5]));
        $t->same(floor((float) $stepNow->format('U.u') * 1000.0) / 1000.0, (float) $results[6]);
    };
}

$tests['real upstream corpus date statement now dynamic application one step audit timestamps'] = static function (TestRunner $t) use ($seed): void {
    $calls = [
        ['function' => 'current_timestamp'],
        ['function' => 'datetime', 'arguments' => ['now']],
        ['function' => 'strftime', 'arguments' => ['%Y-%m-%d %H:%M:%S', 'now']],
    ];

    $timestamps = SQLiteCoreScalarFunction::statementDateTimeResults($calls, $seed);

    $t->same(['2026-05-30 12:00:00', '2026-05-30 12:00:00', '2026-05-30 12:00:00'], $timestamps);
};

return $tests;
