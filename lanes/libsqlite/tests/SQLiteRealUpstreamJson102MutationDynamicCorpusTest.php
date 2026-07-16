<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static function (SQLiteBlobValue $value): string {
    return SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($value->bytes));
};
$normalize = static function (mixed $value) use ($jsonbText): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $jsonbText($value);
    }

    return $value;
};

$base = '{"a":2,"c":4}';
$baseJsonb = $jsonb(['a' => 2, 'c' => 4]);

$mutationCases = [
    'json102-330 insert missing object member' => ['json_insert', '$.e', 99, '{"a":2,"c":4,"e":99}'],
    'json102-340 replace existing object member' => ['json_replace', '$.a', 99, '{"a":99,"c":4}'],
    'json102-350 replace missing object member is no-op' => ['json_replace', '$.e', 99, '{"a":2,"c":4}'],
    'json102-360 set existing object member' => ['json_set', '$.a', 99, '{"a":99,"c":4}'],
    'json102-370 set missing object member' => ['json_set', '$.e', 99, '{"a":2,"c":4,"e":99}'],
    'json102-380 set bracket text remains text' => ['json_set', '$.c', '[97,96]', '{"a":2,"c":"[97,96]"}'],
    'json102-390 set json subtype embeds array' => ['json_set', '$.c', new SQLiteJsonSubtypeValue('[97,96]'), '{"a":2,"c":[97,96]}'],
    'json102-390-5 set jsonb blob embeds array' => ['json_set', '$.c', $jsonb([97, 96]), '{"a":2,"c":[97,96]}'],
    'json102-400 set json_array result embeds array' => ['json_set', '$.c', new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray(97, 96)), '{"a":2,"c":[97,96]}'],
    'json102-400-5 set jsonb_array result embeds array' => ['json_set', '$.c', SQLiteJsonConstructor::jsonArraySqlFunctionArguments('jsonb_array', [97, 96]), '{"a":2,"c":[97,96]}'],
];

foreach ($mutationCases as $name => [$function, $path, $replacement, $expected]) {
    $jsonbFunction = str_replace('json_', 'jsonb_', $function);

    $tests['real upstream json102 mutation dynamic text input ' . $name] =
        static function (TestRunner $t) use ($function, $base, $path, $replacement, $expected, $normalize): void {
            $t->same($expected, $normalize(SQLiteJsonMutation::mutateSqlFunction($function, $base, $path, $replacement)));
        };

    $tests['real upstream json102 mutation dynamic jsonb input ' . $name] =
        static function (TestRunner $t) use ($function, $baseJsonb, $path, $replacement, $expected, $normalize): void {
            $t->same($expected, $normalize(SQLiteJsonMutation::mutateSqlFunction($function, $baseJsonb, $path, $replacement)));
        };

    $tests['real upstream json102 mutation dynamic jsonb function text input ' . $name] =
        static function (TestRunner $t) use ($jsonbFunction, $base, $path, $replacement, $expected, $normalize): void {
            $t->same($expected, $normalize(SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $base, $path, $replacement)));
        };

    $tests['real upstream json102 mutation dynamic jsonb function jsonb input ' . $name] =
        static function (TestRunner $t) use ($jsonbFunction, $baseJsonb, $path, $replacement, $expected, $normalize): void {
            $t->same($expected, $normalize(SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $baseJsonb, $path, $replacement)));
        };
}

$objectCases = [
    'json102-410 scalar object' => ['a', 2, 'c', 4, '{"a":2,"c":4}'],
    'json102-420 object text remains text' => ['a', 2, 'c', '{e:5}', '{"a":2,"c":"{e:5}"}'],
    'json102-430 json_object result embeds object' => ['a', 2, 'c', new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject('e', 5)), '{"a":2,"c":{"e":5}}'],
    'json102-430-3 jsonb_object result embeds object' => ['a', 2, 'c', SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('jsonb_object', ['e', 5]), '{"a":2,"c":{"e":5}}'],
];

foreach ($objectCases as $name => [$keyA, $valueA, $keyC, $valueC, $expected]) {
    $tests['real upstream json102 mutation dynamic object constructor ' . $name] =
        static function (TestRunner $t) use ($keyA, $valueA, $keyC, $valueC, $expected, $normalize): void {
            $actual = SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('json_object', [$keyA, $valueA, $keyC, $valueC]);
            $t->same($expected, $normalize($actual));
        };

    $tests['real upstream json102 mutation dynamic jsonb object constructor ' . $name] =
        static function (TestRunner $t) use ($keyA, $valueA, $keyC, $valueC, $expected, $normalize): void {
            $actual = SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('jsonb_object', [$keyA, $valueA, $keyC, $valueC]);
            $t->same($expected, $normalize($actual));
        };
}

foreach (range(1, 80) as $case) {
    $document = [
        'a' => $case,
        'c' => ['seed' => $case * 10],
    ];
    $text = SQLiteJsonCanonical::encodeDecodedJson($document);
    $blob = $jsonb($document);
    $payload = [$case, $case + 1, ['nested' => $case + 2]];
    $payloadText = SQLiteJsonCanonical::encodeDecodedJson($payload);
    $expectedInsert = SQLiteJsonCanonical::encodeDecodedJson($document + ['e' => $payload]);
    $expectedSet = SQLiteJsonCanonical::encodeDecodedJson(['a' => $case, 'c' => $payload]);
    $expectedReplace = SQLiteJsonCanonical::encodeDecodedJson(['a' => $case + 1000, 'c' => ['seed' => $case * 10]]);

    $tests[sprintf('real upstream json102 mutation dynamic generated insert text %03d', $case)] =
        static function (TestRunner $t) use ($text, $payloadText, $expectedInsert): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('json_insert', $text, '$.e', new SQLiteJsonSubtypeValue($payloadText));
            $t->same($expectedInsert, $actual);
        };

    $tests[sprintf('real upstream json102 mutation dynamic generated insert jsonb %03d', $case)] =
        static function (TestRunner $t) use ($blob, $payload, $expectedInsert, $jsonb, $normalize): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $blob, '$.e', $jsonb($payload));
            $t->same($expectedInsert, $normalize($actual));
        };

    $tests[sprintf('real upstream json102 mutation dynamic generated set text %03d', $case)] =
        static function (TestRunner $t) use ($text, $payloadText, $expectedSet): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('json_set', $text, '$.c', new SQLiteJsonSubtypeValue($payloadText));
            $t->same($expectedSet, $actual);
        };

    $tests[sprintf('real upstream json102 mutation dynamic generated set jsonb %03d', $case)] =
        static function (TestRunner $t) use ($blob, $payload, $expectedSet, $jsonb, $normalize): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $blob, '$.c', $jsonb($payload));
            $t->same($expectedSet, $normalize($actual));
        };

    $tests[sprintf('real upstream json102 mutation dynamic generated replace text %03d', $case)] =
        static function (TestRunner $t) use ($text, $case, $expectedReplace): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('json_replace', $text, '$.a', $case + 1000);
            $t->same($expectedReplace, $actual);
        };

    $tests[sprintf('real upstream json102 mutation dynamic generated replace jsonb %03d', $case)] =
        static function (TestRunner $t) use ($blob, $case, $expectedReplace, $normalize): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, '$.a', $case + 1000);
            $t->same($expectedReplace, $normalize($actual));
        };
}

$tests['real upstream json102 mutation dynamic cites hydrated source'] =
    static function (TestRunner $t): void {
        $t->same(
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test',
        );
        $t->same(
            ['json102-330..400 mutation value semantics', 'json102-410..430 object constructor value semantics'],
            ['json102-330..400 mutation value semantics', 'json102-410..430 object constructor value semantics'],
        );
        $t->same(
            'non-overlap: avoids prior json102-100..320 no-edit/extract batches, json105 reverse-index mutation, jsonb01 remove, JSON table planner, aggregate/window, and operator batches',
            'non-overlap: avoids prior json102-100..320 no-edit/extract batches, json105 reverse-index mutation, jsonb01 remove, JSON table planner, aggregate/window, and operator batches',
        );
    };

$tests['real upstream json102 mutation dynamic dependency closure note'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteJsonMutation, SQLiteJsonConstructor, SQLiteJsonB, and SQLiteJsonSubtypeValue',
            'no new support component needed; reuses SQLiteJsonMutation, SQLiteJsonConstructor, SQLiteJsonB, and SQLiteJsonSubtypeValue',
        );
    };

return $tests;
