<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonMutation;

$tests = [];

$decode = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};

$decodeJsonInput = static function (string $json): mixed {
    try {
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return SQLiteJson5Parser::decode($json);
    }
};

$arrayInsertCases = [
    'json109 1.1 repeated zero index inserts before current element' => ['json_array_insert', '[1,2,3]', '$[0]', 999, [888, 999, 1, 2, 3], '$[0]', 888],
    'json109 1.2 zero index then current append slot' => ['json_array_insert', '[1,2,3]', '$[0]', 999, [999, 1, 2, 3, 888], '$[#]', 888],
    'json109 1.3 inserts before positive current index one' => ['json_array_insert', '[1,2,3]', '$[1]', 888, [1, 888, 2, 3]],
    'json109 1.4 inserts before positive current index two' => ['json_array_insert', '[1,2,3]', '$[2]', 888, [1, 2, 888, 3]],
    'json109 1.5 inserts at current length index' => ['json_array_insert', '[1,2,3]', '$[3]', 888, [1, 2, 3, 888]],
    'json109 1.6 reverse current last index inserts before last' => ['json_array_insert', '[1,2,3]', '$[#-1]', 888, [1, 2, 888, 3]],
    'json109 1.7 reverse current middle index inserts before middle' => ['json_array_insert', '[1,2,3]', '$[#-2]', 888, [1, 888, 2, 3]],
    'json109 1.8 reverse current first index inserts before first' => ['json_array_insert', '[1,2,3]', '$[#-3]', 888, [888, 1, 2, 3]],
    'json109 1.9 reverse current index before first is unchanged' => ['json_array_insert', '[1,2,3]', '$[#-4]', 888, [1, 2, 3]],
    'json109 2.3 missing object path creates array for current element' => ['json_array_insert', '{a:[1,2,3]}', '$.b[0]', 888, ['a' => [1, 2, 3], 'b' => [888]]],
    'json109 2.4 missing nested object path creates final array' => ['json_array_insert', '{a:[1,2,3]}', '$.b.c.d[0]', 888, ['a' => [1, 2, 3], 'b' => ['c' => ['d' => [888]]]]],
    'json109 2.7 object root current array path is unchanged' => ['json_array_insert', '{a:[1,2,3]}', '$[0]', 888, ['a' => [1, 2, 3]]],
    'jsonb repeated zero index inserts before current element' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[0]', 999, [888, 999, 1, 2, 3], '$[0]', 888],
    'jsonb zero index then current append slot' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[0]', 999, [999, 1, 2, 3, 888], '$[#]', 888],
    'jsonb inserts before positive current index one' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[1]', 888, [1, 888, 2, 3]],
    'jsonb inserts before positive current index two' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[2]', 888, [1, 2, 888, 3]],
    'jsonb inserts at current length index' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[3]', 888, [1, 2, 3, 888]],
    'jsonb reverse current last index inserts before last' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[#-1]', 888, [1, 2, 888, 3]],
    'jsonb reverse current middle index inserts before middle' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[#-2]', 888, [1, 888, 2, 3]],
    'jsonb reverse current first index inserts before first' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[#-3]', 888, [888, 1, 2, 3]],
    'jsonb reverse current index before first is unchanged' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3])), '$[#-4]', 888, [1, 2, 3]],
    'jsonb missing object path creates array for current element' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.b[0]', 888, ['a' => [1, 2, 3], 'b' => [888]]],
    'jsonb missing nested object path creates final array' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.b.c.d[0]', 888, ['a' => [1, 2, 3], 'b' => ['c' => ['d' => [888]]]]],
    'jsonb object root current array path is unchanged' => ['jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$[0]', 888, ['a' => [1, 2, 3]]],
];

foreach ($arrayInsertCases as $name => [$function, $json, $path, $value, $expected]) {
    $extra = array_slice($arrayInsertCases[$name], 5);
    $tests['jsonb mutation path current next16 array insert ' . $name] = static function (TestRunner $t) use ($function, $json, $path, $value, $expected, $extra, $decode): void {
        $t->same($expected, $decode(SQLiteJsonArrayInsert::arrayInsertSqlFunction($function, $json, $path, $value, ...$extra)));
    };
}

$nestedMutationCases = [
    'json101 24.1 insert creates current object chain from empty object' => ['json_insert', '{}', '$.a.b.c', 9, ['a' => ['b' => ['c' => 9]]]],
    'json101 24.1 set creates current object chain from empty object' => ['json_set', '{}', '$.a.b.c', 9, ['a' => ['b' => ['c' => 9]]]],
    'json101 24.1 replace cannot create current object chain' => ['json_replace', '{}', '$.a.b.c', 9, []],
    'json101 24.2 insert stops at scalar current member' => ['json_insert', '{a:4}', '$.a.b.c', 9, ['a' => 4]],
    'json101 24.2 set stops at scalar current member' => ['json_set', '{a:4}', '$.a.b.c', 9, ['a' => 4]],
    'json101 24.2 replace stops at scalar current member' => ['json_replace', '{a:4}', '$.a.b.c', 9, ['a' => 4]],
    'json101 24.3 insert creates below existing empty object' => ['json_insert', '{"a":{}}', '$.a.b.c', 9, ['a' => ['b' => ['c' => 9]]]],
    'json101 24.3 set creates below existing empty object' => ['json_set', '{"a":{}}', '$.a.b.c', 9, ['a' => ['b' => ['c' => 9]]]],
    'json101 24.3 replace leaves existing empty object unchanged' => ['json_replace', '{"a":{}}', '$.a.b.c', 9, ['a' => []]],
    'json101 24.4 insert appends object array chain at current length' => ['json_insert', '[0,1,2]', '$[3].a[0].b', 9, [0, 1, 2, ['a' => [['b' => 9]]]]],
    'json101 24.4 set appends object array chain at current length' => ['json_set', '[0,1,2]', '$[3].a[0].b', 9, [0, 1, 2, ['a' => [['b' => 9]]]]],
    'json101 24.4 replace cannot append object array chain' => ['json_replace', '[0,1,2]', '$[3].a[0].b', 9, [0, 1, 2]],
    'json101 24.5 insert skips scalar array element current path' => ['json_insert', '[0,1,2]', '$[1].a[0].b', 9, [0, 1, 2]],
    'json101 24.5 set skips scalar array element current path' => ['json_set', '[0,1,2]', '$[1].a[0].b', 9, [0, 1, 2]],
    'json101 24.5 replace skips scalar array element current path' => ['json_replace', '[0,1,2]', '$[1].a[0].b', 9, [0, 1, 2]],
    'json101 24.6 insert creates below empty object array element' => ['json_insert', '[0,{},2]', '$[1].a[0].b', 9, [0, ['a' => [['b' => 9]]], 2]],
    'json101 24.6 set creates below empty object array element' => ['json_set', '[0,{},2]', '$[1].a[0].b', 9, [0, ['a' => [['b' => 9]]], 2]],
    'json101 24.6 replace leaves empty object array element unchanged' => ['json_replace', '[0,{},2]', '$[1].a[0].b', 9, [0, [], 2]],
    'json101 24.7 insert appends array chain at current length' => ['json_insert', '[0,1,2]', '$[3][0].b', 9, [0, 1, 2, [['b' => 9]]]],
    'json101 24.7 set appends array chain at current length' => ['json_set', '[0,1,2]', '$[3][0].b', 9, [0, 1, 2, [['b' => 9]]]],
    'json101 24.7 replace cannot append array chain' => ['json_replace', '[0,1,2]', '$[3][0].b', 9, [0, 1, 2]],
    'json101 24.8 insert skips scalar current array element' => ['json_insert', '[0,1,2]', '$[1][0].b', 9, [0, 1, 2]],
    'json101 24.8 set skips scalar current array element' => ['json_set', '[0,1,2]', '$[1][0].b', 9, [0, 1, 2]],
    'json101 24.8 replace skips scalar current array element' => ['json_replace', '[0,1,2]', '$[1][0].b', 9, [0, 1, 2]],
];

foreach ($nestedMutationCases as $name => [$function, $json, $path, $value, $expected]) {
    $tests['jsonb mutation path current next16 nested mutation ' . $name] = static function (TestRunner $t) use ($function, $json, $path, $value, $expected, $decode, $decodeJsonInput): void {
        $t->same($expected, $decode(SQLiteJsonMutation::mutateSqlFunction($function, $json, $path, $value)));
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        $t->same($expected, $decode(SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, new SQLiteBlobValue(SQLiteJsonB::encode($decodeJsonInput($json))), $path, $value)));
    };
}

$throwsCases = [
    'json109 2.1 object member is not current array element' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{a:[1,2,3]}', '$.a', 888),
    'json109 2.2 missing object member is not current array element' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{a:[1,2,3]}', '$.b', 888),
    'json109 2.5 malformed current array path rejected' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{a:[1,2,3]}', '$.b.c.d[0', 888),
    'json109 2.6 nested object member is not current array element' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{a:[1,2,3]}', '$.b.c.d', 888),
    'json109 2.8 later invalid current path aborts multi-pair call' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{a:[1,2,3]}', '$.b[0]', 888, '$.a[1]', '999', '$.c', 0),
    'jsonb object member is not current array element' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.a', 888),
    'jsonb missing object member is not current array element' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.b', 888),
    'jsonb malformed current array path rejected' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.b.c.d[0', 888),
    'jsonb nested object member is not current array element' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.b.c.d', 888),
    'jsonb later invalid current path aborts multi-pair call' => static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => [1, 2, 3]])), '$.b[0]', 888, '$.a[1]', '999', '$.c', 0),
];

foreach ($throwsCases as $name => $callback) {
    $tests['jsonb mutation path current next16 throws ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
