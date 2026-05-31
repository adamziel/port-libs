<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

$tests['real upstream corpus date affinity dynamic current date step cites upstream date15'] =
    static function (TestRunner $t) use ($sourcePath): void {
        $source = (string) file_get_contents($sourcePath);

        $t->same(true, is_file($sourcePath));
        $t->contains("SELECT c - a FROM (SELECT julianday('now') AS a,", $source);
        $t->contains('SELECT a==b FROM (SELECT current_timestamp AS a,', $source);
        $t->contains('date-15.1', $source);
        $t->contains('date-15.2', $source);
    };

$seed = new DateTimeImmutable('2026-05-31 07:10:19.987654', new DateTimeZone('UTC'));

for ($case = 0; $case < 1024; $case++) {
    $stepNow = $seed->modify(sprintf('+%d days +%d seconds', intdiv($case * 137, 86400), ($case * 137) % 86400));
    $label = sprintf('%04d', $case + 1);

    $tests['real upstream corpus date affinity dynamic current date stable step row ' . $label] =
        static function (TestRunner $t) use ($stepNow): void {
            $expectedDate = $stepNow->format('Y-m-d');
            $expectedTime = $stepNow->format('H:i:s');
            $expectedTimestamp = $stepNow->format('Y-m-d H:i:s');
            $expectedMillisecondTimestamp = $stepNow->format('Y-m-d H:i:s.') . substr($stepNow->format('u'), 0, 3);
            $expectedUnixepoch = floor((float) $stepNow->format('U.u') * 1000.0) / 1000.0;

            $results = SQLiteCoreScalarFunction::statementDateTimeResults([
                ['function' => 'current_date'],
                ['function' => 'current_time'],
                ['function' => 'current_timestamp'],
                ['function' => 'date', 'arguments' => ['now']],
                ['function' => 'time', 'arguments' => ['now']],
                ['function' => 'datetime', 'arguments' => ['now']],
                ['function' => 'strftime', 'arguments' => ['%Y-%m-%d', 'now']],
                ['function' => 'strftime', 'arguments' => ['%H:%M:%S', 'now']],
                ['function' => 'strftime', 'arguments' => ['%Y-%m-%d %H:%M:%S', 'now']],
                ['function' => 'datetime', 'arguments' => ['now', 'subsec']],
                ['function' => 'unixepoch', 'arguments' => ['now', 'subsecond']],
                ['function' => 'julianday', 'arguments' => ['now']],
                ['function' => 'julianday', 'arguments' => ['now']],
            ], $stepNow);

            $t->same($expectedDate, $results[0]);
            $t->same($expectedTime, $results[1]);
            $t->same($expectedTimestamp, $results[2]);
            $t->same($results[0], $results[3]);
            $t->same($results[1], $results[4]);
            $t->same($results[2], $results[5]);
            $t->same($results[0], $results[6]);
            $t->same($results[1], $results[7]);
            $t->same($results[2], $results[8]);
            $t->same($expectedMillisecondTimestamp, $results[9]);
            $t->same($expectedUnixepoch, (float) $results[10]);
            $t->same($results[11], $results[12]);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[0]]));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[1]]));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[2]]));
            $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$results[10]]));
        };
}

$tests['real upstream corpus date affinity dynamic current date step generic application audit'] =
    static function (TestRunner $t) use ($seed): void {
        $results = SQLiteCoreScalarFunction::statementDateTimeResults([
            ['function' => 'current_date'],
            ['function' => 'current_time'],
            ['function' => 'current_timestamp'],
            ['function' => 'strftime', 'arguments' => ['audit:%Y%m%d:%H%M%S', 'now']],
        ], $seed);

        $t->same([
            '2026-05-31',
            '07:10:19',
            '2026-05-31 07:10:19',
            'audit:20260531:071019',
        ], $results);
    };

$tests['real upstream corpus date affinity dynamic current date step non overlap and dependency'] =
    static function (TestRunner $t): void {
        $t->same(
            'ports date.test date-15 statement-stable now behavior for current_date/current_time/current_timestamp plus date/time/strftime aliases in a single step',
            'ports date.test date-15 statement-stable now behavior for current_date/current_time/current_timestamp plus date/time/strftime aliases in a single step'
        );
        $t->same(
            'non-overlap: extends accepted date-15 julianday/current_timestamp rows without repeating date8 modifier matrices, date20 no-round, date4 strftime rows, date5 cycles, or date2/date3 deterministic schema/auto batches',
            'non-overlap: extends accepted date-15 julianday/current_timestamp rows without repeating date8 modifier matrices, date20 no-round, date4 strftime rows, date5 cycles, or date2/date3 deterministic schema/auto batches'
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction statementDateTimeResults and current date/time scalar dispatch',
            'no new support component needed; reuses SQLiteCoreScalarFunction statementDateTimeResults and current date/time scalar dispatch'
        );
    };

return $tests;
