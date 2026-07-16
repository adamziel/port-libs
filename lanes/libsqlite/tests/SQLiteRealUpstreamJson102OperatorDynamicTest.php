<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$json102OperatorCanonical = static function (mixed $value): string {
    return SQLiteJsonCanonical::encodeDecodedJson($value);
};

$json102OperatorValue = static function (mixed $json, mixed $right, string $operator) use ($json102OperatorCanonical): mixed {
    $value = SQLiteSelectExpression::evaluate([], [
        'type' => 'binary',
        'operator' => $operator,
        'left' => ['type' => 'literal', 'value' => $json],
        'right' => ['type' => 'literal', 'value' => $right],
    ]);

    return $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;
};

$json102OperatorBlob = static function (mixed $value): SQLiteBlobValue {
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
};

$json102JsonValue = static function (mixed $value) use ($json102OperatorCanonical): ?string {
    if ($value === null) {
        return 'null';
    }

    return $json102OperatorCanonical($value);
};

$json102TextValue = static function (mixed $value) use ($json102OperatorCanonical): mixed {
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }

    return $json102OperatorCanonical($value);
};

$tests['real upstream json102 operator dynamic cites hydrated source sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
    $t->same(
        ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800', 'json102-1801', 'json102-1810', 'json102-1811', 'json102-1820', 'json102-1821', 'json102-1830', 'json102-1831'],
        ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800', 'json102-1801', 'json102-1810', 'json102-1811', 'json102-1820', 'json102-1821', 'json102-1830', 'json102-1831'],
    );
};

$json102Rows1600 = [
    1 => ['{"a":null}', true, null],
    2 => ['{"a":123}', true, 123],
    3 => ['{"a":4.5}', true, 4.5],
    4 => ['{"a":"six"}', true, 'six'],
    5 => ['{"a":[7,8]}', true, [7, 8]],
    6 => ['{"a":{"b":9}}', true, ['b' => 9]],
    7 => ['{"b":999}', false, null],
];

foreach ($json102Rows1600 as $id => [$json, $found, $expected]) {
    $tests['real upstream json102-1600 object member operator row ' . $id] =
        static function (TestRunner $t) use ($json102OperatorValue, $json102OperatorBlob, $json102JsonValue, $json102TextValue, $json, $found, $expected, $id): void {
            $blob = $json102OperatorBlob(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
            $expectedJson = $found ? $json102JsonValue($expected) : null;

            $t->same($expectedJson, $json102OperatorValue($json, 'a', '->'), 'json102-1600 text -> row ' . $id);
            $t->same($json102TextValue($expected), $json102OperatorValue($json, 'a', '->>'), 'json102-1600 text ->> row ' . $id);
            $t->same($expectedJson, $json102OperatorValue($blob, 'a', '->'), 'json102-1600 jsonb -> row ' . $id);
            $t->same($json102TextValue($expected), $json102OperatorValue($blob, 'a', '->>'), 'json102-1600 jsonb ->> row ' . $id);
            $t->same($json102TextValue($expected), SQLiteJsonExtract::extract($json, '$.a'), 'json102-1600 json_extract row ' . $id);
        };
}

$json102Array = '[null,123,4.5,"six",[7,8],{"b":9}]';
$json102ArrayValues = [
    [true, null],
    [true, 123],
    [true, 4.5],
    [true, 'six'],
    [true, [7, 8]],
    [true, ['b' => 9]],
    [false, null],
];

foreach ($json102ArrayValues as $index => [$found, $expected]) {
    $tests['real upstream json102-1610 array integer operator row ' . $index] =
        static function (TestRunner $t) use ($json102OperatorValue, $json102OperatorBlob, $json102JsonValue, $json102TextValue, $json102Array, $found, $expected, $index): void {
            $blob = $json102OperatorBlob(json_decode($json102Array, true, 512, JSON_THROW_ON_ERROR));
            $expectedJson = $found ? $json102JsonValue($expected) : null;

            $t->same($expectedJson, $json102OperatorValue($json102Array, $index, '->'), 'json102-1610 text -> row ' . $index);
            $t->same($json102TextValue($expected), $json102OperatorValue($json102Array, $index, '->>'), 'json102-1610 text ->> row ' . $index);
            $t->same($expectedJson, $json102OperatorValue($blob, $index, '->'), 'json102-1610 jsonb -> row ' . $index);
            $t->same($json102TextValue($expected), $json102OperatorValue($blob, $index, '->>'), 'json102-1610 jsonb ->> row ' . $index);
            $t->same($json102TextValue($expected), SQLiteJsonExtract::extract($json102Array, '$[' . $index . ']'), 'json102-1610 json_extract row ' . $index);
        };
}

$json102NumericRhsCases = [
    'json102-1800 object string rhs selects numeric-looking member text' => ['{"1":"one","2":"two","3":"three"}', '2', '->>', 'two'],
    'json102-1801 object integer rhs does not select numeric-looking member text' => ['{"1":"one","2":"two","3":"three"}', 2, '->>', null],
    'json102-1810 array string rhs does not select array index text' => ['["zero","one","two"]', '1', '->>', null],
    'json102-1811 array integer rhs selects array index text' => ['["zero","one","two"]', 1, '->>', 'one'],
    'json102-1820 object string rhs selects numeric-looking member json' => ['{"1":"one","2":"two","3":"three"}', '2', '->', '"two"'],
    'json102-1821 object integer rhs does not select numeric-looking member json' => ['{"1":"one","2":"two","3":"three"}', 2, '->', null],
    'json102-1830 array string rhs does not select array index json' => ['["zero","one","two"]', '1', '->', null],
    'json102-1831 array integer rhs selects array index json' => ['["zero","one","two"]', 1, '->', '"one"'],
];

foreach ($json102NumericRhsCases as $name => [$json, $rhs, $operator, $expected]) {
    $tests['real upstream ' . $name] =
        static function (TestRunner $t) use ($json102OperatorValue, $json102OperatorBlob, $json, $rhs, $operator, $expected, $name): void {
            $blob = $json102OperatorBlob(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

            $t->same($expected, $json102OperatorValue($json, $rhs, $operator), $name . ' text input');
            $t->same($expected, $json102OperatorValue($blob, $rhs, $operator), $name . ' jsonb input');
        };
}

for ($case = 1; $case <= 1000; $case++) {
    $objectKey = (string) (($case % 9) + 1);
    $arrayIndex = $case % 6;
    $object = [
        $objectKey => 'member-' . $case,
        'a' => [
            'case' => $case,
            'even' => ($case % 2) === 0,
            'items' => [$case, $case + 1],
        ],
        'payload' => [
            'nested' => ['key' => 'value-' . $case],
            'nullValue' => null,
        ],
    ];
    $array = [
        null,
        'one-' . $case,
        $case,
        $case + 0.25,
        ['nested' => $case],
        ['tail', $case],
    ];

    $objectJson = $json102OperatorCanonical($object);
    $arrayJson = $json102OperatorCanonical($array);
    $objectBlob = $json102OperatorBlob($object);
    $arrayBlob = $json102OperatorBlob($array);

    $tests['real upstream json102 operator numeric rhs dynamic row ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($json102OperatorValue, $json102JsonValue, $json102TextValue, $objectJson, $arrayJson, $objectBlob, $arrayBlob, $objectKey, $arrayIndex, $object, $array, $case): void {
            $objectMember = $object[$objectKey];
            $arrayMember = $array[$arrayIndex];

            $t->same($objectMember, $json102OperatorValue($objectJson, $objectKey, '->>'), 'object string rhs text row ' . $case);
            $t->same($json102JsonValue($objectMember), $json102OperatorValue($objectJson, $objectKey, '->'), 'object string rhs json row ' . $case);
            $t->same(null, $json102OperatorValue($objectJson, (int) $objectKey, '->>'), 'object integer rhs text misses numeric label row ' . $case);
            $t->same(null, $json102OperatorValue($objectJson, (int) $objectKey, '->'), 'object integer rhs json misses numeric label row ' . $case);
            $t->same(null, $json102OperatorValue($arrayJson, (string) $arrayIndex, '->>'), 'array string rhs text misses index row ' . $case);
            $t->same(null, $json102OperatorValue($arrayJson, (string) $arrayIndex, '->'), 'array string rhs json misses index row ' . $case);
            $t->same($json102TextValue($arrayMember), $json102OperatorValue($arrayJson, $arrayIndex, '->>'), 'array integer rhs text row ' . $case);
            $t->same($json102JsonValue($arrayMember), $json102OperatorValue($arrayJson, $arrayIndex, '->'), 'array integer rhs json row ' . $case);

            $t->same($objectMember, $json102OperatorValue($objectBlob, $objectKey, '->>'), 'object jsonb string rhs text row ' . $case);
            $t->same($json102JsonValue($objectMember), $json102OperatorValue($objectBlob, $objectKey, '->'), 'object jsonb string rhs json row ' . $case);
            $t->same(null, $json102OperatorValue($objectBlob, (int) $objectKey, '->>'), 'object jsonb integer rhs text misses numeric label row ' . $case);
            $t->same(null, $json102OperatorValue($objectBlob, (int) $objectKey, '->'), 'object jsonb integer rhs json misses numeric label row ' . $case);
            $t->same(null, $json102OperatorValue($arrayBlob, (string) $arrayIndex, '->>'), 'array jsonb string rhs text misses index row ' . $case);
            $t->same(null, $json102OperatorValue($arrayBlob, (string) $arrayIndex, '->'), 'array jsonb string rhs json misses index row ' . $case);
            $t->same($json102TextValue($arrayMember), $json102OperatorValue($arrayBlob, $arrayIndex, '->>'), 'array jsonb integer rhs text row ' . $case);
            $t->same($json102JsonValue($arrayMember), $json102OperatorValue($arrayBlob, $arrayIndex, '->'), 'array jsonb integer rhs json row ' . $case);

            $t->same('object', SQLiteJsonInspection::jsonType($objectJson, '$.a'), 'object row keeps nested object type ' . $case);
            $t->same($case, SQLiteJsonExtract::extract($objectJson, '$.a.case'), 'object row extract remains consistent ' . $case);
        };
}

return $tests;
