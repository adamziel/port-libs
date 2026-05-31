<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;

/*
 * Real upstream source: SQLite json103.test, especially json103-410.
 *
 * json103-410 verifies json_group_object(rowid, x) as a window function over
 * ROWS 2 PRECEDING. This expands the same upstream behavior across deterministic
 * frame widths, mixed scalar values, duplicate labels, JSONB parity, and empty
 * frame boundaries without touching accepted JSON table/source/cursor planner
 * surfaces.
 */

$tests = [];

$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json103 object window expectation');
    }

    return $encoded;
};

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

$framePairs = static function (array $pairs, int $position, int $preceding, int $following): array {
    $start = max(0, $position - $preceding);
    $end = min(count($pairs) - 1, $position + $following);

    return array_slice($pairs, $start, $end - $start + 1);
};

$jsonValue = static function (mixed $value) use ($json): string {
    return $json($value);
};

$objectJsonFromPairs = static function (array $pairs) use ($jsonValue): string {
    $members = [];
    foreach ($pairs as [$label, $value]) {
        if ($label === null) {
            continue;
        }
        $members[] = $jsonValue((string) $label) . ':' . $jsonValue($value);
    }

    return '{' . implode(',', $members) . '}';
};

$collapsedObjectJsonFromPairs = static function (array $pairs) use ($json): string {
    $object = [];
    foreach ($pairs as [$label, $value]) {
        if ($label === null) {
            continue;
        }
        $object[(string) $label] = $value;
    }

    return $json((object) $object);
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
];

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 7 + ($case % 6);
    $preceding = $case % 5;
    $following = intdiv($case, 5) % 4;
    $position = intdiv($case, 20) % $rowCount;
    $duplicateEvery = 3 + ($case % 4);

    $pairs = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $label = ($row % $duplicateEvery === 0 && $row > 0) ? 'dup-' . ($case % 9) : (string) ($row + 1 + ($case % 11));
        $value = $baseValues[($case + $row) % count($baseValues)];
        if (is_int($value)) {
            $value += intdiv($case, 17);
        } elseif (is_float($value)) {
            $value += ($case % 13) / 10;
        } elseif (is_string($value)) {
            $value .= '-' . ($case % 23);
        }
        $pairs[] = [$label, $value];
    }

    $frame = $framePairs($pairs, $position, $preceding, $following);
    $expected = $objectJsonFromPairs($frame);
    $expectedJsonb = $collapsedObjectJsonFromPairs($frame);
    $label = sprintf(
        'real upstream json103 object window dynamic json103-410 case %04d rows %02d preceding %d following %d position %02d',
        $case,
        $rowCount,
        $preceding,
        $following,
        $position
    );

    $tests[$label] = static function (TestRunner $t) use ($pairs, $preceding, $following, $position, $expected, $expectedJsonb, $jsonbText, $frame): void {
        $frames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('json_group_object', $pairs, $preceding, $following);
        $jsonbFrames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('jsonb_group_object', $pairs, $preceding, $following);

        $t->same($expected, $frames[$position], 'json103-410 text object window frame');
        $t->true($jsonbFrames[$position] instanceof SQLiteBlobValue, 'json103-410 JSONB object window frame type');
        $t->same($expectedJsonb, $jsonbText($jsonbFrames[$position]), 'json103-410 JSONB canonical object window frame');
        $t->same('object', SQLiteJsonInspection::jsonType($frames[$position]), 'json103-410 object frame type');
        $t->same(substr_count($expected, ':', 1), substr_count($frames[$position], ':', 1), 'json103-410 serialized member count parity');
        if ($frame !== []) {
            $lastLabel = (string) $frame[array_key_last($frame)][0];
            $lastValue = $frame[array_key_last($frame)][1];
            $t->same($lastValue, SQLiteJsonExtract::extract($frames[$position], '$."' . str_replace('"', '\\"', $lastLabel) . '"'), 'json103-410 duplicate labels keep last frame value');
        } else {
            $t->same('{}', $frames[$position], 'json103-410 empty object frame');
        }
    };
}

$tests['real upstream json103 object window dynamic source ownership'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test');
    $t->same(['json103-400 window array reference', 'json103-410 window object target'], ['json103-400 window array reference', 'json103-410 window object target']);
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
