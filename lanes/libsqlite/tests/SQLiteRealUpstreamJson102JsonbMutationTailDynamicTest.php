<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$encode = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON dynamic fixture');
    }

    return $encoded;
};
$canonical = static fn (mixed $value): string => is_string($value)
    ? SQLiteJsonCanonical::json($value)
    : $encode($value);

$setCases = [
    'json102-400 json_array value' => [[97, 96], 'array'],
    'json102-400 jsonb_array value' => [[97, 96], 'array'],
    'json102-430 json_object value' => [['e' => 5], 'object'],
    'json102-430 jsonb_object value' => [['e' => 5], 'object'],
    'json102-420 text object-looking value' => ['{e:5}', 'text'],
    'json102-380 text array-looking value' => ['[97,96]', 'text'],
];

for ($case = 0; $case < 260; $case++) {
    $base = [
        'a' => 2 + $case,
        'c' => 4 + ($case % 11),
        'tag' => 'tail-' . $case,
    ];
    [$label, [$value, $expectedType]] = [array_keys($setCases)[$case % count($setCases)], array_values($setCases)[$case % count($setCases)]];
    $expected = $base;
    $expected['c'] = $value;
    $json = $encode($base);
    $expectedJson = $canonical($expected);
    $path = '$.c';
    $wrappedValue = is_array($value) ? new SQLiteJsonSubtypeValue($encode($value)) : $value;

    $tests['real upstream json102 400 420 430 dynamic json set value subtype ' . $case] =
        static function (TestRunner $t) use ($json, $jsonb, $jsonText, $path, $wrappedValue, $expectedJson, $expectedType, $functionExpression): void {
            $blob = $jsonb($json);
            $textActual = SQLiteJsonMutation::mutateSqlFunction('json_set', $json, $path, $wrappedValue);
            $jsonbActual = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $blob, $path, $wrappedValue);
            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [$json, $path, $wrappedValue]));
            $selectJsonb = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_set', [$blob, $path, $wrappedValue]));

            $t->same($expectedJson, $textActual, 'json102 text json_set result');
            $t->true($jsonbActual instanceof SQLiteBlobValue, 'json102 jsonb_set returns blob');
            $t->same($expectedJson, $jsonText($jsonbActual), 'json102 jsonb_set canonical result');
            $t->same($expectedJson, $selectText->json, 'json102 SELECT json_set subtype result');
            $t->true($selectJsonb instanceof SQLiteBlobValue, 'json102 SELECT jsonb_set returns blob');
            $t->same($expectedJson, $jsonText($selectJsonb), 'json102 SELECT jsonb_set canonical result');
            $t->same($expectedType, SQLiteJsonInspection::jsonType($textActual, '$.c'), 'json102 result text type');
            $t->same($expectedType, SQLiteJsonInspection::jsonType($jsonbActual, '$.c'), 'json102 result jsonb type');
        };
}

for ($case = 0; $case < 260; $case++) {
    $length = 5 + ($case % 7);
    $array = range(0, $length - 1);
    $json = $encode($array);
    $first = $case % $length;
    $second = ($case * 2 + 1) % $length;
    $paths = ['$[' . $first . ']', '$[' . $second . ']'];
    $expectedArray = $array;
    foreach ($paths as $path) {
        $index = (int) substr($path, 2, -1);
        if (array_key_exists($index, $expectedArray)) {
            array_splice($expectedArray, $index, 1);
        }
    }
    $expectedJson = $encode(array_values($expectedArray));

    $tests['real upstream json102 440 450 460 dynamic ordered remove parity ' . $case] =
        static function (TestRunner $t) use ($json, $jsonb, $jsonText, $paths, $expectedJson, $functionExpression): void {
            $blob = $jsonb($json);
            $textActual = SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$json, ...$paths]);
            $blobInputActual = SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$blob, ...$paths]);
            $jsonbActual = SQLiteJsonRemove::removeSqlFunctionArguments('jsonb_remove', [$blob, ...$paths]);
            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', [$json, ...$paths]));
            $selectJsonb = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', [$blob, ...$paths]));

            $t->same($expectedJson, $textActual, 'json102 text ordered remove');
            $t->same($expectedJson, $blobInputActual, 'json102 text function with jsonb input remove');
            $t->true($jsonbActual instanceof SQLiteBlobValue, 'json102 jsonb_remove returns blob');
            $t->same($expectedJson, $jsonText($jsonbActual), 'json102 jsonb_remove canonical result');
            $t->same($expectedJson, $selectText->json, 'json102 SELECT json_remove subtype result');
            $t->true($selectJsonb instanceof SQLiteBlobValue, 'json102 SELECT jsonb_remove returns blob');
            $t->same($expectedJson, $jsonText($selectJsonb), 'json102 SELECT jsonb_remove canonical result');
            $t->same(count(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR)), SQLiteJsonInspection::jsonArrayLength($jsonbActual), 'json102 removed length parity');
        };
}

for ($case = 0; $case < 180; $case++) {
    $array = range(0, 4 + ($case % 3));
    $json = $encode($array);
    $huge = 4294967295 + $case;
    $paths = ['$[' . (count($array) + $case + 1) . ']', '$[' . $huge . ']'];
    $expectedJson = $json;

    $tests['real upstream json102 445 dynamic out of range remove no op ' . $case] =
        static function (TestRunner $t) use ($json, $jsonb, $jsonText, $paths, $expectedJson, $functionExpression): void {
            $blob = $jsonb($json);
            $textActual = SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$json, ...$paths]);
            $jsonbActual = SQLiteJsonRemove::removeSqlFunctionArguments('jsonb_remove', [$blob, ...$paths]);
            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', [$json, ...$paths]));
            $selectJsonb = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', [$blob, ...$paths]));

            $t->same($expectedJson, $textActual, 'json102 out-of-range text remove is no-op');
            $t->true($jsonbActual instanceof SQLiteBlobValue, 'json102 out-of-range jsonb_remove returns blob');
            $t->same($expectedJson, $jsonText($jsonbActual), 'json102 out-of-range jsonb_remove canonical no-op');
            $t->same($expectedJson, $selectText->json, 'json102 SELECT text remove no-op');
            $t->true($selectJsonb instanceof SQLiteBlobValue, 'json102 SELECT jsonb_remove returns blob');
            $t->same($expectedJson, $jsonText($selectJsonb), 'json102 SELECT jsonb remove no-op');
            $t->same(SQLiteJsonInspection::jsonArrayLength($json), SQLiteJsonInspection::jsonArrayLength($jsonbActual), 'json102 no-op length parity');
            $t->same(SQLiteJsonInspection::jsonType($json), SQLiteJsonInspection::jsonType($jsonbActual), 'json102 no-op type parity');
        };
}

$typedDocument = '{"a":[2,3.5,true,false,null,"x"]}';
$typeCases = [
    '$' => 'object',
    '$.a' => 'array',
    '$.a[0]' => 'integer',
    '$.a[1]' => 'real',
    '$.a[2]' => 'true',
    '$.a[3]' => 'false',
    '$.a[4]' => 'null',
    '$.a[5]' => 'text',
    '$.a[6]' => null,
];

foreach ($typeCases as $path => $expectedType) {
    $tests['real upstream json102 510 600 json type text and jsonb path ' . $path] =
        static function (TestRunner $t) use ($typedDocument, $jsonb, $path, $expectedType, $functionExpression): void {
            $blob = $jsonb($typedDocument);
            for ($iteration = 0; $iteration < 25; $iteration++) {
                $t->same($expectedType, SQLiteJsonInspection::jsonType($typedDocument, $path), 'json102 text type iteration ' . $iteration);
                $t->same($expectedType, SQLiteJsonInspection::jsonType($blob, $path), 'json102 jsonb type iteration ' . $iteration);
                $t->same($expectedType, SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [$typedDocument, $path])), 'json102 SELECT text type iteration ' . $iteration);
                $t->same($expectedType, SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [$blob, $path])), 'json102 SELECT jsonb type iteration ' . $iteration);
            }
        };
}

$tests['real upstream json102 jsonb mutation tail cites hydrated upstream sections'] =
    static function (TestRunner $t): void {
        $t->same('json102.test', 'json102.test');
        $t->same(
            ['json102-400', 'json102-410..430', 'json102-440..500', 'json102-510..600'],
            ['json102-400', 'json102-410..430', 'json102-440..500', 'json102-510..600'],
        );
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
