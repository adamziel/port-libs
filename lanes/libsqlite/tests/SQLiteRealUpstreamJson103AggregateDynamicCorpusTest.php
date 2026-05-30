<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$decodeJson = static fn (string $json): mixed => json_decode($json, true, 512, JSON_THROW_ON_ERROR);

$rows = [];
for ($rowid = 1; $rowid <= 100; $rowid++) {
    $value = $rowid;
    if ($rowid === 39) {
        $value = 'orange';
    } elseif ($rowid === 31) {
        $value = 32.5;
    } elseif ($rowid === 29) {
        $value = new SQLiteBlobValue('012');
    } elseif ($rowid === 37) {
        $value = null;
    }

    $rows[] = [
        'rowid' => $rowid,
        'a' => $value,
        'b' => $rowid % 3,
        'c' => 'n' . $rowid,
    ];
}

$valuesBetween = static fn (int $start, int $end): array => array_values(array_map(
    static fn (array $row): mixed => $row['a'],
    array_filter($rows, static fn (array $row): bool => $row['rowid'] >= $start && $row['rowid'] <= $end),
));

$tests['real upstream json103-100 empty json_group_array returns empty array'] = static function (TestRunner $t): void {
    $empty = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', []);
    $jsonb = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', []);

    $t->same('[]', $empty);
    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same([], SQLiteJsonB::decode($jsonb->bytes));
    $t->same('array', SQLiteJsonInspection::jsonType($empty));
    $t->same(0, SQLiteJsonInspection::jsonArrayLength($empty));
    $t->same('[]', SQLiteJsonCanonical::json($jsonb));
};

$tests['real upstream json103-101 json_group_array rejects raw blob values'] = static function (TestRunner $t) use ($rows): void {
    $values = array_column($rows, 'a');

    $t->throws(InvalidArgumentException::class, static fn (): string => SQLiteJsonAggregate::jsonGroupArray($values));
    $t->throws(InvalidArgumentException::class, static fn (): string|SQLiteBlobValue => SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values));
    $t->throws(InvalidArgumentException::class, static fn (): string|SQLiteBlobValue => SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $values));
    $t->same('JSON cannot hold BLOB values', (static function () use ($values): string {
        try {
            SQLiteJsonAggregate::jsonGroupArray($values);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'no error';
    })());
};

$tests['real upstream json103-110 json_group_array preserves mixed scalar order'] = static function (TestRunner $t) use ($valuesBetween): void {
    $values = $valuesBetween(31, 39);
    $json = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values);
    $jsonb = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $values);

    $t->same('[32.5,32,33,34,35,36,null,38,"orange"]', $json);
    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same('[32.5,32,33,34,35,36,null,38,"orange"]', SQLiteJsonCanonical::json($jsonb));
    $t->same(9, SQLiteJsonInspection::jsonArrayLength($json));
    $t->same(9, SQLiteJsonInspection::jsonArrayLength($jsonb));
    $t->same('real', SQLiteJsonInspection::jsonType($json, '$[0]'));
    $t->same('null', SQLiteJsonInspection::jsonType($json, '$[6]'));
    $t->same('text', SQLiteJsonInspection::jsonType($json, '$[8]'));
};

$tests['real upstream json103-120 grouped json_group_array partitions by b'] = static function (TestRunner $t) use ($rows): void {
    $groups = [];
    foreach ($rows as $row) {
        if ($row['rowid'] >= 10) {
            continue;
        }
        $groups[$row['b']][] = $row['a'];
    }
    ksort($groups);

    $t->same([0, 1, 2], array_keys($groups));
    $t->same('[3,6,9]', SQLiteJsonAggregate::jsonGroupArray($groups[0]));
    $t->same('[1,4,7]', SQLiteJsonAggregate::jsonGroupArray($groups[1]));
    $t->same('[2,5,8]', SQLiteJsonAggregate::jsonGroupArray($groups[2]));
    $t->same(['[3,6,9]', '[1,4,7]', '[2,5,8]'], array_map(SQLiteJsonAggregate::jsonGroupArray(...), $groups));
    $t->same(3, SQLiteJsonInspection::jsonArrayLength(SQLiteJsonAggregate::jsonGroupArray($groups[0])));
    $t->same('integer', SQLiteJsonInspection::jsonType(SQLiteJsonAggregate::jsonGroupArray($groups[2]), '$[2]'));
};

$tests['real upstream json103 full nonblob array aggregate round trips every row'] = static function (TestRunner $t) use ($rows, $decodeJson, $jsonbText): void {
    $nonBlobRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['a'] instanceof SQLiteBlobValue));
    $values = array_column($nonBlobRows, 'a');
    $json = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values);
    $jsonb = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $values);
    $decoded = $decodeJson($json);

    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same($json, $jsonbText($jsonb));
    $t->same(99, SQLiteJsonInspection::jsonArrayLength($json));
    $t->same(99, count($decoded));

    foreach ($nonBlobRows as $index => $row) {
        $path = '$[' . $index . ']';
        $expected = $row['a'];

        $t->same($expected, $decoded[$index], 'json103 array decoded row ' . $row['rowid']);
        $t->same($expected, SQLiteJsonB::decode($jsonb->bytes)[$index], 'json103 jsonb array decoded row ' . $row['rowid']);
        $t->same(SQLiteJsonInspection::jsonType($json, $path), SQLiteJsonInspection::jsonType($jsonb, $path), 'json103 type parity row ' . $row['rowid']);
        $t->same($row['rowid'] === 37 ? 'null' : ($row['rowid'] === 39 ? 'text' : (is_float($expected) ? 'real' : 'integer')), SQLiteJsonInspection::jsonType($json, $path), 'json103 expected type row ' . $row['rowid']);
    }
};

$tests['real upstream json103-200 empty json_group_object returns empty object'] = static function (TestRunner $t): void {
    $empty = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', []);
    $jsonb = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', []);

    $t->same('{}', $empty);
    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same('{}', SQLiteJsonCanonical::json($jsonb));
    $t->same('object', SQLiteJsonInspection::jsonType($empty));
    $t->same('{}', SQLiteJsonCanonical::json($jsonb));
};

$tests['real upstream json103-201 json_group_object rejects raw blob values'] = static function (TestRunner $t) use ($rows): void {
    $pairs = array_map(static fn (array $row): array => [$row['c'], $row['a']], $rows);

    $t->throws(InvalidArgumentException::class, static fn (): string => SQLiteJsonAggregate::jsonGroupObject($pairs));
    $t->throws(InvalidArgumentException::class, static fn (): string|SQLiteBlobValue => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs));
    $t->throws(InvalidArgumentException::class, static fn (): string|SQLiteBlobValue => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', $pairs));
    $t->same('JSON cannot hold BLOB values', (static function () use ($pairs): string {
        try {
            SQLiteJsonAggregate::jsonGroupObject($pairs);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'no error';
    })());
};

$tests['real upstream json103-210 json_group_object preserves odd row members'] = static function (TestRunner $t) use ($rows, $jsonbText): void {
    $pairs = [];
    foreach ($rows as $row) {
        if ($row['rowid'] >= 31 && $row['rowid'] <= 39 && $row['rowid'] % 2 === 1) {
            $pairs[] = [$row['c'], $row['a']];
        }
    }

    $json = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs);
    $jsonb = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', $pairs);

    $t->same('{"n31":32.5,"n33":33,"n35":35,"n37":null,"n39":"orange"}', $json);
    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same('{"n31":32.5,"n33":33,"n35":35,"n37":null,"n39":"orange"}', $jsonbText($jsonb));
    $t->same('real', SQLiteJsonInspection::jsonType($json, '$.n31'));
    $t->same('null', SQLiteJsonInspection::jsonType($json, '$.n37'));
    $t->same('text', SQLiteJsonInspection::jsonType($json, '$.n39'));
};

$tests['real upstream json103-220 grouped json_group_object partitions by b'] = static function (TestRunner $t) use ($rows): void {
    $groups = [];
    foreach ($rows as $row) {
        if ($row['rowid'] >= 7) {
            continue;
        }
        $groups[$row['b']][] = [$row['c'], $row['a']];
    }
    ksort($groups);

    $t->same('{"n3":3,"n6":6}', SQLiteJsonAggregate::jsonGroupObject($groups[0]));
    $t->same('{"n1":1,"n4":4}', SQLiteJsonAggregate::jsonGroupObject($groups[1]));
    $t->same('{"n2":2,"n5":5}', SQLiteJsonAggregate::jsonGroupObject($groups[2]));
    $t->same(['{"n3":3,"n6":6}', '{"n1":1,"n4":4}', '{"n2":2,"n5":5}'], array_map(SQLiteJsonAggregate::jsonGroupObject(...), $groups));
    $t->same('integer', SQLiteJsonInspection::jsonType(SQLiteJsonAggregate::jsonGroupObject($groups[0]), '$.n6'));
};

$tests['real upstream json103 full nonblob object aggregate round trips every row label'] = static function (TestRunner $t) use ($rows, $decodeJson, $jsonbText): void {
    $nonBlobRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['a'] instanceof SQLiteBlobValue));
    $pairs = array_map(static fn (array $row): array => [$row['c'], $row['a']], $nonBlobRows);
    $json = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs);
    $jsonb = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', $pairs);
    $decoded = $decodeJson($json);
    $decodedJsonb = SQLiteJsonB::decode($jsonb->bytes);

    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same($json, $jsonbText($jsonb));
    $t->same(99, count($decoded));
    $t->same(99, count($decodedJsonb));

    foreach ($nonBlobRows as $row) {
        $path = '$.' . $row['c'];
        $expected = $row['a'];

        $t->same($expected, $decoded[$row['c']], 'json103 object decoded row ' . $row['rowid']);
        $t->same($expected, $decodedJsonb[$row['c']], 'json103 jsonb object decoded row ' . $row['rowid']);
        $t->same(SQLiteJsonInspection::jsonType($json, $path), SQLiteJsonInspection::jsonType($jsonb, $path), 'json103 object type parity row ' . $row['rowid']);
        $t->same($row['rowid'] === 37 ? 'null' : ($row['rowid'] === 39 ? 'text' : (is_float($expected) ? 'real' : 'integer')), SQLiteJsonInspection::jsonType($json, $path), 'json103 object expected type row ' . $row['rowid']);
    }
};

$tests['real upstream json103-300 aggregate subtype state resets between scalars and objects'] = static function (TestRunner $t): void {
    $values = [1, 'abc'];
    $objectValues = [
        new SQLiteJsonSubtypeValue('{"x":1}'),
        new SQLiteJsonSubtypeValue('{"x":"abc"}'),
    ];

    $array = SQLiteJsonAggregate::jsonGroupArray($values);
    $objectArray = SQLiteJsonAggregate::jsonGroupArray($objectValues);
    $jsonbArray = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $objectValues);

    $t->same('[1,"abc"]', $array);
    $t->same('[{"x":1},{"x":"abc"}]', $objectArray);
    $t->true($jsonbArray instanceof SQLiteBlobValue);
    $t->same('[{"x":1},{"x":"abc"}]', SQLiteJsonCanonical::json($jsonbArray));
    $t->same('integer', SQLiteJsonInspection::jsonType($array, '$[0]'));
    $t->same('object', SQLiteJsonInspection::jsonType($objectArray, '$[0]'));
    $t->same('text', SQLiteJsonInspection::jsonType($objectArray, '$[1].x'));
};

$windowValues = [1, 'a,b', 3, 'x"y', 5, 6, 7];

$tests['real upstream json103-400 json_group_array window rows two preceding'] = static function (TestRunner $t) use ($windowValues): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('json_group_array', $windowValues, 2);
    $jsonbFrames = SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction('jsonb_group_array', $windowValues, 2);

    $t->same(['[1]', '[1,"a,b"]', '[1,"a,b",3]', '["a,b",3,"x\"y"]', '[3,"x\"y",5]', '["x\"y",5,6]', '[5,6,7]'], $frames);
    $t->same($frames, array_map(static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value), $jsonbFrames));
    $t->same(7, count($frames));
    $t->same(1, SQLiteJsonInspection::jsonArrayLength($frames[0]));
    $t->same(3, SQLiteJsonInspection::jsonArrayLength($frames[2]));
    $t->same('text', SQLiteJsonInspection::jsonType($frames[3], '$[2]'));
};

$tests['real upstream json103-410 json_group_object window rows two preceding'] = static function (TestRunner $t) use ($windowValues): void {
    $pairs = [];
    foreach ($windowValues as $index => $value) {
        $pairs[] = [(string) ($index + 1), $value];
    }

    $frames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('json_group_object', $pairs, 2);
    $jsonbFrames = SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction('jsonb_group_object', $pairs, 2);

    $t->same(['{"1":1}', '{"1":1,"2":"a,b"}', '{"1":1,"2":"a,b","3":3}', '{"2":"a,b","3":3,"4":"x\"y"}', '{"3":3,"4":"x\"y","5":5}', '{"4":"x\"y","5":5,"6":6}', '{"5":5,"6":6,"7":7}'], $frames);
    $t->same($frames, array_map(static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value), $jsonbFrames));
    $t->same(7, count($frames));
    $t->same('integer', SQLiteJsonInspection::jsonType($frames[0], '$.1'));
    $t->same('text', SQLiteJsonInspection::jsonType($frames[3], '$.4'));
    $t->same('integer', SQLiteJsonInspection::jsonType($frames[6], '$.7'));
};

return $tests;
