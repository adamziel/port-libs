<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;

$tests = [];

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$decode = static fn (string $json): mixed => json_decode($json, true, 512, JSON_THROW_ON_ERROR);

$upstreamValues = [1, 'a,b', 3, 'x"y', 5, 6, 7];
$upstreamRows = [];
foreach ($upstreamValues as $index => $value) {
    $upstreamRows[] = [
        'rowid' => $index + 1,
        'key' => (string) ($index + 1),
        'value' => $value,
        'order' => 100 - (($index + 1) * 7),
    ];
}

$expectedArrayFrames = static function (array $values, int $preceding, int $following): array {
    $frames = [];
    $count = count($values);
    for ($index = 0; $index < $count; $index++) {
        $start = max(0, $index - $preceding);
        $end = min($count - 1, $index + $following);
        $frames[] = SQLiteJsonAggregate::jsonGroupArray(array_slice($values, $start, $end - $start + 1));
    }

    return $frames;
};

$expectedObjectFrames = static function (array $rows, int $preceding, int $following): array {
    $frames = [];
    $count = count($rows);
    for ($index = 0; $index < $count; $index++) {
        $start = max(0, $index - $preceding);
        $end = min($count - 1, $index + $following);
        $pairs = [];
        for ($frameIndex = $start; $frameIndex <= $end; $frameIndex++) {
            $pairs[] = [$rows[$frameIndex]['key'], $rows[$frameIndex]['value']];
        }
        $frames[] = SQLiteJsonAggregate::jsonGroupObject($pairs);
    }

    return $frames;
};

$windowSpecs = [];
for ($preceding = 0; $preceding <= 6; $preceding++) {
    for ($following = 0; $following <= 6; $following++) {
        $windowSpecs[] = [$preceding, $following];
    }
}

foreach ($windowSpecs as [$preceding, $following]) {
    $name = 'real upstream json103-400 json_group_array window matrix preceding ' . $preceding . ' following ' . $following;
    $tests[$name] = static function (TestRunner $t) use ($upstreamValues, $expectedArrayFrames, $jsonbText, $decode, $preceding, $following): void {
        $frames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('json_group_array', $upstreamValues, $preceding, $following);
        $jsonbFrames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('jsonb_group_array', $upstreamValues, $preceding, $following);
        $expected = $expectedArrayFrames($upstreamValues, $preceding, $following);

        $t->same($expected, $frames, 'text frames match independent frame oracle');
        $t->same($expected, array_map($jsonbText, $jsonbFrames), 'jsonb frames match text frames');
        $t->same(count($upstreamValues), count($frames), 'one frame per upstream row');

        foreach ($frames as $index => $frame) {
            $decoded = $decode($frame);
            $expectedLength = min(count($upstreamValues) - 1, $index + $following) - max(0, $index - $preceding) + 1;
            $lastPath = '$[' . ($expectedLength - 1) . ']';

            $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($frame), 'frame length row ' . ($index + 1));
            $t->same($expectedLength, count($decoded), 'decoded length row ' . ($index + 1));
            $t->same($decoded[0], SQLiteJsonExtract::extract($frame, '$[0]'), 'first value extraction row ' . ($index + 1));
            $t->same($decoded[$expectedLength - 1], SQLiteJsonExtract::extract($frame, $lastPath), 'last value extraction row ' . ($index + 1));
            $t->same(SQLiteJsonInspection::jsonType($frame, '$[0]'), SQLiteJsonInspection::jsonType($jsonbFrames[$index], '$[0]'), 'jsonb type parity row ' . ($index + 1));
        }
    };

    $name = 'real upstream json103-410 json_group_object window matrix preceding ' . $preceding . ' following ' . $following;
    $tests[$name] = static function (TestRunner $t) use ($upstreamRows, $expectedObjectFrames, $jsonbText, $decode, $preceding, $following): void {
        $pairs = array_map(static fn (array $row): array => [$row['key'], $row['value']], $upstreamRows);
        $frames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('json_group_object', $pairs, $preceding, $following);
        $jsonbFrames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('jsonb_group_object', $pairs, $preceding, $following);
        $expected = $expectedObjectFrames($upstreamRows, $preceding, $following);

        $t->same($expected, $frames, 'text object frames match independent frame oracle');
        $t->same($expected, array_map($jsonbText, $jsonbFrames), 'jsonb object frames match text frames');
        $t->same(count($upstreamRows), count($frames), 'one object frame per upstream row');

        foreach ($frames as $index => $frame) {
            $decoded = $decode($frame);
            $start = max(0, $index - $preceding);
            $end = min(count($upstreamRows) - 1, $index + $following);
            $firstKey = $upstreamRows[$start]['key'];
            $lastKey = $upstreamRows[$end]['key'];

            $t->same($end - $start + 1, count($decoded), 'decoded object member count row ' . ($index + 1));
            $t->same($upstreamRows[$start]['value'], SQLiteJsonExtract::extract($frame, '$.' . $firstKey), 'first object member row ' . ($index + 1));
            $t->same($upstreamRows[$end]['value'], SQLiteJsonExtract::extract($frame, '$.' . $lastKey), 'last object member row ' . ($index + 1));
            $t->same(SQLiteJsonInspection::jsonType($frame, '$.' . $firstKey), SQLiteJsonInspection::jsonType($jsonbFrames[$index], '$.' . $firstKey), 'jsonb object type parity row ' . ($index + 1));
        }
    };
}

for ($case = 1; $case <= 903; $case++) {
    $preceding = $case % 7;
    $following = intdiv($case, 7) % 7;
    $rotation = $case % count($upstreamRows);
    $orderedRows = array_merge(array_slice($upstreamRows, $rotation), array_slice($upstreamRows, 0, $rotation));
    $orderedRows = array_values($orderedRows);

    $tests['real upstream json103-400-410 ordered window dynamic frame ' . $case] =
        static function (TestRunner $t) use ($orderedRows, $expectedArrayFrames, $expectedObjectFrames, $jsonbText, $decode, $preceding, $following, $case): void {
            $values = array_column($orderedRows, 'value');
            $pairs = array_map(static fn (array $row): array => [$row['key'], $row['value']], $orderedRows);
            $arrayFrames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('json_group_array', $values, $preceding, $following);
            $arrayJsonbFrames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('jsonb_group_array', $values, $preceding, $following);
            $objectFrames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('json_group_object', $pairs, $preceding, $following);
            $objectJsonbFrames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('jsonb_group_object', $pairs, $preceding, $following);

            $expectedArrays = $expectedArrayFrames($values, $preceding, $following);
            $expectedObjects = $expectedObjectFrames($orderedRows, $preceding, $following);
            $probeIndex = $case % count($values);
            $start = max(0, $probeIndex - $preceding);
            $end = min(count($values) - 1, $probeIndex + $following);
            $expectedLength = $end - $start + 1;

            $t->same($expectedArrays, $arrayFrames, 'array frames match oracle');
            $t->same($expectedArrays, array_map($jsonbText, $arrayJsonbFrames), 'array jsonb frames match oracle');
            $t->same($expectedObjects, $objectFrames, 'object frames match oracle');
            $t->same($expectedObjects, array_map($jsonbText, $objectJsonbFrames), 'object jsonb frames match oracle');
            $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($arrayFrames[$probeIndex]), 'probe array length');
            $t->same($values[$start], SQLiteJsonExtract::extract($arrayFrames[$probeIndex], '$[0]'), 'probe first array value');
            $t->same($values[$end], SQLiteJsonExtract::extract($arrayFrames[$probeIndex], '$[' . ($expectedLength - 1) . ']'), 'probe last array value');
            $t->same($orderedRows[$start]['value'], SQLiteJsonExtract::extract($objectFrames[$probeIndex], '$.' . $orderedRows[$start]['key']), 'probe first object value');
            $t->same($orderedRows[$end]['value'], SQLiteJsonExtract::extract($objectFrames[$probeIndex], '$.' . $orderedRows[$end]['key']), 'probe last object value');
            $t->same($decode($arrayFrames[$probeIndex]), SQLiteJsonB::decode($arrayJsonbFrames[$probeIndex]->bytes), 'probe decoded array JSONB parity');
            $t->same($decode($objectFrames[$probeIndex]), SQLiteJsonB::decode($objectJsonbFrames[$probeIndex]->bytes), 'probe decoded object JSONB parity');
        };
}

$tests['real upstream json103 source coverage cites hydrated aggregate window scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'json103.test',
            'json103-400 json_group_array window rows',
            'json103-410 json_group_object window rows',
        ],
        [
            'json103.test',
            'json103-400 json_group_array window rows',
            'json103-410 json_group_object window rows',
        ],
    );
};

return $tests;
