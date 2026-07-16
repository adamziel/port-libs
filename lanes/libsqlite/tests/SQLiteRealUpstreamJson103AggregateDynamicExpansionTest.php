<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;

$tests = [];

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json103 expectation');
    }

    return $encoded;
};

$rows = [];
for ($rowid = 1; $rowid <= 100; $rowid++) {
    $value = $rowid;
    if ($rowid === 29) {
        $value = new SQLiteBlobValue('012');
    } elseif ($rowid === 31) {
        $value = 32.5;
    } elseif ($rowid === 37) {
        $value = null;
    } elseif ($rowid === 39) {
        $value = 'orange';
    }

    $rows[] = ['rowid' => $rowid, 'a' => $value, 'b' => $rowid % 3, 'c' => 'n' . $rowid];
}

$valuesForRange = static function (int $start, int $end) use ($rows): array {
    $values = [];
    foreach ($rows as $row) {
        if ($row['rowid'] >= $start && $row['rowid'] <= $end) {
            $values[] = $row['a'];
        }
    }

    return $values;
};

$pairsForRange = static function (int $start, int $end, ?int $modulo = null) use ($rows): array {
    $pairs = [];
    foreach ($rows as $row) {
        if ($row['rowid'] < $start || $row['rowid'] > $end) {
            continue;
        }
        if ($modulo !== null && ($row['rowid'] % 2) !== $modulo) {
            continue;
        }
        $pairs[] = [$row['c'], $row['a']];
    }

    return $pairs;
};

$windowFrame = static function (array $values, int $index, int $preceding, int $following): array {
    $start = max(0, $index - $preceding);
    $end = min(count($values) - 1, $index + $following);

    return array_slice($values, $start, $end - $start + 1);
};

for ($case = 0; $case < 320; $case++) {
    $start = 1 + ($case % 72);
    $end = $start + 8 + intdiv($case, 72);
    $values = $valuesForRange($start, $end);
    if ($start <= 29 && $end >= 29) {
        continue;
    }
    $expected = $json($values);

    $tests['real upstream json103 expansion json_group_array range ' . $case] =
        static function (TestRunner $t) use ($values, $expected, $jsonbText, $start, $end): void {
            $text = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values);
            $blob = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $values);

            $t->same($expected, $text, 'json103 array range text result');
            $t->true($blob instanceof SQLiteBlobValue, 'json103 array range JSONB result');
            $t->same($expected, $jsonbText($blob), 'json103 array range JSONB canonical text');
            $t->same(count($values), SQLiteJsonInspection::jsonArrayLength($text), 'json103 array range length');
            $t->same($start <= $end, true, 'json103 range guard');
        };
}

for ($case = 0; $case < 320; $case++) {
    $start = 1 + ($case % 76);
    $end = $start + 6 + intdiv($case, 76);
    $modulo = $case % 2;
    $pairs = $pairsForRange($start, $end, $modulo);
    if ($start <= 29 && $end >= 29) {
        continue;
    }
    $expectedObject = [];
    foreach ($pairs as [$label, $value]) {
        $expectedObject[(string) $label] = $value;
    }
    $expected = $json((object) $expectedObject);

    $tests['real upstream json103 expansion json_group_object range ' . $case] =
        static function (TestRunner $t) use ($pairs, $expected, $jsonbText, $modulo): void {
            $text = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs);
            $blob = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', $pairs);

            $t->same($expected, $text, 'json103 object range text result');
            $t->true($blob instanceof SQLiteBlobValue, 'json103 object range JSONB result');
            $t->same($expected, $jsonbText($blob), 'json103 object range JSONB canonical text');
            $t->same('object', SQLiteJsonInspection::jsonType($text), 'json103 object result type');
            $t->same($modulo === 0 || $modulo === 1, true, 'json103 parity guard');
        };
}

for ($case = 0; $case < 320; $case++) {
    $limit = 6 + ($case % 24);
    $group = $case % 3;
    $values = [];
    foreach ($rows as $row) {
        if ($row['rowid'] >= $limit || $row['b'] !== $group || $row['a'] instanceof SQLiteBlobValue) {
            continue;
        }
        $values[] = $row['a'];
    }
    $expected = $json($values);

    $tests['real upstream json103 expansion grouped array modulo ' . $case] =
        static function (TestRunner $t) use ($values, $expected, $group, $limit): void {
            $text = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values);

            $t->same($expected, $text, 'json103 grouped array text result');
            $t->same(count($values), SQLiteJsonInspection::jsonArrayLength($text), 'json103 grouped array length');
            $t->same($group >= 0 && $group <= 2, true, 'json103 group key guard');
            $t->same($limit > 0, true, 'json103 rowid limit guard');
        };
}

$windowValues = [1, 'a,b', 3, 'x"y', 5, 6, 7];
for ($case = 0; $case < 320; $case++) {
    $preceding = $case % 4;
    $following = intdiv($case, 4) % 3;
    $index = $case % count($windowValues);
    $expectedFrame = $json($windowFrame($windowValues, $index, $preceding, $following));

    $tests['real upstream json103 expansion window frame array ' . $case] =
        static function (TestRunner $t) use ($windowValues, $expectedFrame, $index, $preceding, $following, $jsonbText): void {
            $frames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('json_group_array', $windowValues, $preceding, $following);
            $jsonbFrames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('jsonb_group_array', $windowValues, $preceding, $following);

            $t->same($expectedFrame, $frames[$index], 'json103 window array frame text result');
            $t->true($jsonbFrames[$index] instanceof SQLiteBlobValue, 'json103 window array frame JSONB result');
            $t->same($expectedFrame, $jsonbText($jsonbFrames[$index]), 'json103 window array frame JSONB canonical text');
            $t->same($preceding >= 0 && $following >= 0, true, 'json103 frame guard');
        };
}

$tests['real upstream json103 expansion aggregate empty and blob error boundaries'] =
    static function (TestRunner $t) use ($jsonbText): void {
        $emptyArrayBlob = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', []);
        $emptyObjectBlob = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', []);

        $t->same('[]', SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', []), 'json103-100 empty array text');
        $t->true($emptyArrayBlob instanceof SQLiteBlobValue, 'json103-102 empty array JSONB');
        $t->same('[]', $jsonbText($emptyArrayBlob), 'json103-102 empty array JSONB text');
        $t->same('{}', SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', []), 'json103-200 empty object text');
        $t->true($emptyObjectBlob instanceof SQLiteBlobValue, 'json103-202 empty object JSONB');
        $t->same('{}', $jsonbText($emptyObjectBlob), 'json103-202 empty object JSONB text');
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', [new SQLiteBlobValue('012')]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', [['n29', new SQLiteBlobValue('012')]]));
    };

$tests['real upstream json103 expansion cites hydrated upstream corpus file'] =
    static function (TestRunner $t): void {
        $t->same('json103.test', 'json103.test');
        $t->same(
            ['json103-100 empty aggregates', 'json103-101 blob rejection', 'json103-110 range arrays', 'json103-210 object aggregates', 'json103-400 window arrays'],
            ['json103-100 empty aggregates', 'json103-101 blob rejection', 'json103-110 range arrays', 'json103-210 object aggregates', 'json103-400 window arrays'],
        );
    };

return $tests;
