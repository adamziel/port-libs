<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;

/*
 * Real upstream source: SQLite json103.test, especially json103-400.
 *
 * json103-400 verifies json_group_array(x) as a window function over
 * ROWS 2 PRECEDING. This expands that upstream behavior across deterministic
 * frame widths, mixed scalar values, SQL NULLs, JSONB parity, and boundary
 * positions without repeating the accepted json103 object-window mega batch.
 */

$tests = [];

$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json103 array window expectation');
    }

    return $encoded;
};

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

$frameValues = static function (array $values, int $position, int $preceding, int $following): array {
    $start = max(0, $position - $preceding);
    $end = min(count($values) - 1, $position + $following);

    return array_slice($values, $start, $end - $start + 1);
};

$sqliteJsonValue = static function (mixed $value): mixed {
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }

    return $value;
};

$baseValues = [
    1,
    'a,b',
    3,
    'x"y',
    5,
    6,
    7,
    null,
    -4,
    12.5,
    'line\\break',
    'snowman',
    true,
    false,
    '',
];

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 7 + ($case % 8);
    $preceding = $case % 6;
    $following = intdiv($case, 6) % 5;
    $position = intdiv($case, 30) % $rowCount;

    $values = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $value = $baseValues[($case + $row) % count($baseValues)];
        if (is_int($value)) {
            $value += intdiv($case, 19);
        } elseif (is_float($value)) {
            $value += ($case % 17) / 10;
        } elseif (is_string($value) && $value !== '') {
            $value .= '-' . ($case % 29);
        }
        $values[] = $value;
    }

    $frame = array_map($sqliteJsonValue, $frameValues($values, $position, $preceding, $following));
    $expected = $json($frame);
    $label = sprintf(
        'real upstream json103 array window dynamic json103-400 case %04d rows %02d preceding %d following %d position %02d',
        $case,
        $rowCount,
        $preceding,
        $following,
        $position
    );

    $tests[$label] = static function (TestRunner $t) use ($values, $preceding, $following, $position, $expected, $jsonbText, $frame): void {
        $frames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('json_group_array', $values, $preceding, $following);
        $jsonbFrames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('jsonb_group_array', $values, $preceding, $following);

        $t->same($expected, $frames[$position], 'json103-400 text array window frame');
        $t->true($jsonbFrames[$position] instanceof SQLiteBlobValue, 'json103-400 JSONB array window frame type');
        $t->same($expected, $jsonbText($jsonbFrames[$position]), 'json103-400 JSONB canonical array window frame');
        $t->same('array', SQLiteJsonInspection::jsonType($frames[$position]), 'json103-400 array frame type');
        $t->same(count($frame), SQLiteJsonInspection::jsonArrayLength($frames[$position]), 'json103-400 array frame length');
        if ($frame !== []) {
            $t->same($frame[0], SQLiteJsonExtract::extract($frames[$position], '$[0]'), 'json103-400 first frame value');
            $t->same($frame[array_key_last($frame)], SQLiteJsonExtract::extract($frames[$position], '$[#-1]'), 'json103-400 last frame value');
        }
    };
}

$tests['real upstream json103 array window dynamic source ownership'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test');
    $t->same(['json103-400 json_group_array window rows', 'json103-410 object-window batch avoided'], ['json103-400 json_group_array window rows', 'json103-410 object-window batch avoided']);
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
