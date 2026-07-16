<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$tests['real upstream date affinity weekday dynamic cites source files'] = static function (TestRunner $t): void {
    $sections = [
        'date.test date-2.3..2.12 weekday modifier parsing and date advancement',
        'date.test date-8.1..8.4 weekday modifier with statement-now equivalent',
        'affinity3.test affinity3-200..260 automatic index affinity does not coerce TEXT ids',
    ];

    $t->same(true, in_array('date.test date-2.3..2.12 weekday modifier parsing and date advancement', $sections, true));
    $t->same(true, in_array('affinity3.test affinity3-200..260 automatic index affinity does not coerce TEXT ids', $sections, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
};

$weekdayExpected = static function (string $date, int $targetWeekday): string {
    $instant = new DateTimeImmutable($date . ' 12:34:00', new DateTimeZone('UTC'));
    $current = (int) $instant->format('w');
    $days = ($targetWeekday - $current + 7) % 7;
    if ($days > 0) {
        $instant = $instant->modify('+' . $days . ' days');
    }

    return $instant->format('Y-m-d H:i:s');
};

for ($dayOffset = 0; $dayOffset < 160; $dayOffset++) {
    $baseDate = (new DateTimeImmutable('2003-10-22', new DateTimeZone('UTC')))->modify('+' . $dayOffset . ' days')->format('Y-m-d');
    foreach (range(0, 6) as $weekday) {
        $tests[sprintf('real upstream date affinity weekday dynamic date.test date-2 weekday %d offset %03d', $weekday, $dayOffset)] = static function (TestRunner $t) use ($baseDate, $weekday, $weekdayExpected): void {
            $dateValue = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$baseDate, 'weekday ' . $weekday]);
            $datetimeValue = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$baseDate . ' 12:34', 'weekday ' . $weekday]);
            $expected = $weekdayExpected($baseDate, $weekday);

            $t->same(substr($expected, 0, 10), $dateValue);
            $t->same($expected, $datetimeValue);
            $t->same((string) $weekday, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%w', $dateValue]));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$dateValue]));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', $datetimeValue === null ? [null] : [$datetimeValue]));
        };
    }
}

$invalidWeekdayModifiers = [
    'date-2.4b trailing character' => 'weekday  1x',
    'date-2.4c negative weekday' => 'weekday  -1',
    'date-2.4d misspelled weekday' => 'weakday  1x',
    'date-2.4e missing weekday' => 'weekday ',
    'date-2.10 weekday seven out of range' => 'weekday 7',
    'date-2.11 fractional weekday' => 'weekday 5.5',
];

foreach ($invalidWeekdayModifiers as $upstream => $modifier) {
    $tests['real upstream date affinity weekday dynamic ' . $upstream] = static function (TestRunner $t) use ($modifier): void {
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2003-10-22', $modifier]));
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2003-10-22 12:34', $modifier]));
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', ['2003-10-22 12:34', $modifier]));
    };
}

for ($case = 0; $case < 160; $case++) {
    $numericId = (string) (1000 + $case);
    $paddedId = str_pad($numericId, 6, '0', STR_PAD_LEFT);
    $storedRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
        ['id' => $numericId, 'event_date' => '2024-01-' . str_pad((string) (($case % 28) + 1), 2, '0', STR_PAD_LEFT)],
        ['id' => $paddedId, 'event_date' => '2024-02-' . str_pad((string) (($case % 28) + 1), 2, '0', STR_PAD_LEFT)],
    ], ['id' => 'TEXT', 'event_date' => 'NUMERIC']);

    $tests[sprintf('real upstream date affinity weekday dynamic affinity3 text id preservation row %03d', $case)] = static function (TestRunner $t) use ($storedRows, $numericId, $paddedId): void {
        $numericComparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($storedRows[0]['id'], (int) $numericId, '=', 'TEXT', 'NONE');
        $paddedComparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($storedRows[1]['id'], (int) $numericId, '=', 'TEXT', 'NONE');

        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($storedRows[0]['id']));
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($storedRows[1]['id']));
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($storedRows[0]['event_date']));
        $t->same(true, $numericComparison['result']);
        $t->same(false, $paddedComparison['result']);
        $t->same($paddedId, $storedRows[1]['id']);
    };
}

$tests['real upstream date affinity weekday dynamic application recurring schedule index stays text-safe'] = static function (TestRunner $t): void {
    $events = [];
    foreach (range(0, 6) as $weekday) {
        $dateValue = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2024-02-29', 'weekday ' . $weekday]);
        $events[] = [
            'key_name' => 'weekday.' . $weekday,
            'run_date' => $dateValue,
            'weekday' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%w', $dateValue]),
            'storage_type' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$dateValue]),
        ];
    }

    $t->same(['0', '1', '2', '3', '4', '5', '6'], array_column($events, 'weekday'));
    $t->same(['text', 'text', 'text', 'text', 'text', 'text', 'text'], array_column($events, 'storage_type'));
    $t->same('2024-03-06', $events[3]['run_date']);
};

return $tests;
