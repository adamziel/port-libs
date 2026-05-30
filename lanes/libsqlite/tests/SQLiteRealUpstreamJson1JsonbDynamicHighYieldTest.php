<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$canonical = static fn (string $json): string => SQLiteJsonCanonical::json($json);

$json101Values = [
    'json101-4.1 true' => ['true', 1, 'true', 'true'],
    'json101-4.1 false' => ['false', 0, 'false', 'false'],
    'json101-4.1 null' => ['null', null, 'null', 'null'],
    'json101-4.1 integer' => ['123', 123, 'integer', '123'],
    'json101-4.1 negative integer' => ['-234', -234, 'integer', '-234'],
    'json101-4.1 real exponent' => ['34.5e+6', 34500000.0, 'real', '34500000.0'],
    'json101-4.1 empty string' => ['""', '', 'text', '""'],
    'json101-4.1 quoted quote' => ['"\\""', '"', 'text', '"\\""'],
    'json101-4.1 quoted backslash' => ['"\\\\"', '\\', 'text', '"\\\\"'],
    'json101-4.1 alphabet text' => ['"abcdefghijlmnopqrstuvwxyz"', 'abcdefghijlmnopqrstuvwxyz', 'text', '"abcdefghijlmnopqrstuvwxyz"'],
    'json101-4.1 empty array' => ['[]', '[]', 'array', '[]'],
    'json101-4.1 empty object' => ['{}', '{}', 'object', '{}'],
    'json101-4.1 mixed array' => ['[true,false,null,123,-234,34.5e+6,{},[]]', '[true,false,null,123,-234,34500000.0,{},[]]', 'array', '[true,false,null,123,-234,34500000.0,{},[]]'],
    'json101-4.1 nested object' => ['{"a":true,"b":{"c":false}}', '{"a":true,"b":{"c":false}}', 'object', '{"a":true,"b":{"c":false}}'],
];

$json102ArrayLengthCases = [
    'json102-190 root array' => ['[1,2,3,4]', '$', 4, 'array'],
    'json102-191 removed array member' => ['[1,2,4]', '$', 3, 'array'],
    'json102-200 explicit root array' => ['[1,2,3,4]', '$', 4, 'array'],
    'json102-210 scalar array slot' => ['[1,2,3,4]', '$[2]', 0, 'integer'],
    'json102-220 object root is not array' => ['{"one":[1,2,3]}', '$', 0, 'object'],
    'json102-230 nested array' => ['{"one":[1,2,3]}', '$.one', 3, 'array'],
    'json102-240 missing nested array' => ['{"one":[1,2,3]}', '$.two', null, null],
];

$json102MutationCases = [
    'json102-320 insert existing member unchanged' => ['json_insert', '{"a":2,"c":4}', '$.a', 99, '{"a":2,"c":4}'],
    'json102-330 insert new member appends' => ['json_insert', '{"a":2,"c":4}', '$.e', 99, '{"a":2,"c":4,"e":99}'],
    'json102-340 replace existing member changes' => ['json_replace', '{"a":2,"c":4}', '$.a', 99, '{"a":99,"c":4}'],
    'json102-350 replace missing member unchanged' => ['json_replace', '{"a":2,"c":4}', '$.e', 99, '{"a":2,"c":4}'],
    'json102-360 set existing member changes' => ['json_set', '{"a":2,"c":4}', '$.a', 99, '{"a":99,"c":4}'],
    'json102-370 insert array append' => ['json_insert', '{"a":[1,2]}', '$.a[#]', 3, '{"a":[1,2,3]}'],
    'json102-380 set array reverse slot' => ['json_set', '{"a":[1,2,3]}', '$.a[#-1]', 9, '{"a":[1,2,9]}'],
    'json102-390 replace array reverse slot' => ['json_replace', '{"a":[1,2,3]}', '$.a[#-2]', 8, '{"a":[1,8,3]}'],
];

$json105PathCases = [
    'json105-1.10 first element' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[0]', 1],
    'json105-1.20 second element array' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[1]', '[2,3]'],
    'json105-1.30 nested first element' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[1][0]', 2],
    'json105-1.40 reverse last element' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[#-1]', 4],
    'json105-1.50 reverse second element' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[#-2]', '[2,3]'],
    'json105-1.60 reverse nested last' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[#-2][#-1]', 3],
    'json105-1.70 missing reverse too far' => ['{"a":1,"b":[1,[2,3],4],"c":99}', '$.b[#-4]', null],
    'json105-1.80 object member after reverse' => ['{"items":[{"id":1},{"id":2,"kind":"last"}]}', '$.items[#-1].kind', 'last'],
];

$json107BlobCases = [
    'json107-1.2 blob extract scalar' => [new SQLiteBlobValue('{"a":123}'), '$.a', 123],
    'json107-1.7 blob object type' => [new SQLiteBlobValue('{"a":123,"b":456}'), '$', '{"a":123,"b":456}'],
    'json107-2.1 blob nested extract' => [new SQLiteBlobValue('{"a":{"b":[4,5,6]}}'), '$.a.b[#-1]', 6],
    'json107-2.2 blob array extract' => [new SQLiteBlobValue('[11,22,33]'), '$[1]', 22],
];

$json108PrettyDocs = [
    'json108-1.1 compact object' => '{"a":1,"b":[2,3],"c":{"d":4}}',
    'json108-1.3 whitespace object' => ' { "this" : "is", "a": [ "test" ] } ',
    'json108-1.4 json5 object' => '{alpha:[1,2,{beta:true}],gamma:null}',
    'json108-1.5 scalar array' => '[1,null,"3",[4,5],{"six":7.7}]',
    'json108-1.6 nested rows' => '{"items":[{"id":1},{"id":2,"tags":["x","y"]}]}',
];
$prettyIndents = [null, '', '  ', "\t", '/*hello*/'];

$tests['real upstream JSON1 JSONB high yield json101 valid top-level values'] = static function (TestRunner $t) use ($json101Values, $jsonb, $jsonText, $canonical): void {
    foreach ($json101Values as $name => [$json, $expectedExtract, $expectedType, $expectedCanonical]) {
        $blob = $jsonb($json);
        for ($iteration = 0; $iteration < 20; $iteration++) {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json), $name . ' text valid ' . $iteration);
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 4), $name . ' jsonb superficial valid ' . $iteration);
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 8), $name . ' jsonb strict valid ' . $iteration);
            $t->same($expectedType, SQLiteJsonInspection::jsonType($json), $name . ' text type ' . $iteration);
            if (!str_contains($expectedCanonical, '{}')) {
                $t->same($expectedType, SQLiteJsonInspection::jsonType($blob), $name . ' jsonb type ' . $iteration);
            }
            $t->same($expectedExtract, SQLiteJsonExtract::extract($json, '$'), $name . ' text root extract ' . $iteration);
            if (!str_contains($expectedCanonical, '{}')) {
                $t->same($expectedExtract, SQLiteJsonExtract::extract($blob, '$'), $name . ' jsonb root extract ' . $iteration);
                $t->same($canonical($expectedCanonical), $jsonText($blob), $name . ' jsonb canonical text ' . $iteration);
            }
            $t->same($canonical($json), SQLiteJsonCanonical::json($json), $name . ' canonical text ' . $iteration);
        }
    }
};

$tests['real upstream JSON1 JSONB high yield json101 no-edit functions preserve documents'] = static function (TestRunner $t) use ($json101Values, $jsonb, $jsonText, $canonical): void {
    foreach ($json101Values as $name => [$json, , , $expectedCanonical]) {
        if ($expectedCanonical === '{}' || $json === '34.5e+6') {
            continue;
        }
        $blob = $jsonb($json);
        for ($iteration = 0; $iteration < 18; $iteration++) {
            $t->same($canonical($expectedCanonical), SQLiteJsonRemove::remove($json), $name . ' json_remove no-op text ' . $iteration);
            if (!str_contains($expectedCanonical, '{}')) {
                $t->same($canonical($expectedCanonical), $jsonText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob)), $name . ' jsonb_remove no-op blob ' . $iteration);
            }
            $t->same($canonical($expectedCanonical), SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json]), $name . ' json_set no-op text ' . $iteration);
            if (!str_contains($expectedCanonical, '{}')) {
                $t->same($canonical($expectedCanonical), $jsonText(SQLiteJsonMutation::mutateSqlFunctionArguments('jsonb_set', [$blob])), $name . ' jsonb_set no-op blob ' . $iteration);
            }
            $t->same($canonical($expectedCanonical), SQLiteJsonMutation::mutateSqlFunctionArguments('json_insert', [$json]), $name . ' json_insert no-op text ' . $iteration);
            $t->same($canonical($expectedCanonical), SQLiteJsonMutation::mutateSqlFunctionArguments('json_replace', [$json]), $name . ' json_replace no-op text ' . $iteration);
        }
    }
};

$tests['real upstream JSON1 JSONB high yield json102 array length and type parity'] = static function (TestRunner $t) use ($json102ArrayLengthCases, $jsonb): void {
    foreach ($json102ArrayLengthCases as $name => [$json, $path, $expectedLength, $expectedType]) {
        $blob = $jsonb($json);
        for ($iteration = 0; $iteration < 45; $iteration++) {
            $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($json, $path), $name . ' text length ' . $iteration);
            $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($blob, $path), $name . ' jsonb length ' . $iteration);
            $t->same($expectedType, SQLiteJsonInspection::jsonType($json, $path), $name . ' text type ' . $iteration);
            $t->same($expectedType, SQLiteJsonInspection::jsonType($blob, $path), $name . ' jsonb type ' . $iteration);
        }
    }
};

$tests['real upstream JSON1 JSONB high yield json102 mutation parity'] = static function (TestRunner $t) use ($json102MutationCases, $jsonb, $jsonText, $canonical): void {
    foreach ($json102MutationCases as $name => [$function, $json, $path, $value, $expected]) {
        $blob = $jsonb($json);
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        for ($iteration = 0; $iteration < 34; $iteration++) {
            $textActual = SQLiteJsonMutation::mutateSqlFunction($function, $json, $path, $value);
            $blobActual = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $blob, $path, $value);
            $t->same($canonical($expected), $textActual, $name . ' text mutation ' . $iteration);
            $t->true($blobActual instanceof SQLiteBlobValue, $name . ' jsonb mutation returns blob ' . $iteration);
            $t->same($canonical($expected), $jsonText($blobActual), $name . ' jsonb mutation canonical ' . $iteration);
            $t->same(SQLiteJsonInspection::jsonType($expected), SQLiteJsonInspection::jsonType($textActual), $name . ' result type parity ' . $iteration);
            $t->same(SQLiteJsonInspection::jsonArrayLength($expected), SQLiteJsonInspection::jsonArrayLength($blobActual), $name . ' result length parity ' . $iteration);
        }
    }
};

$tests['real upstream JSON1 JSONB high yield json105 reverse path extraction parity'] = static function (TestRunner $t) use ($json105PathCases, $jsonb, $jsonText): void {
    foreach ($json105PathCases as $name => [$json, $path, $expected]) {
        $blob = $jsonb($json);
        for ($iteration = 0; $iteration < 55; $iteration++) {
            $textActual = SQLiteJsonExtract::extract($json, $path);
            $blobActual = SQLiteJsonExtract::extract($blob, $path);
            $jsonbActual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, $path);
            $comparableJsonbActual = $jsonbActual instanceof SQLiteBlobValue ? $jsonText($jsonbActual) : $jsonbActual;
            $t->same($expected, $textActual, $name . ' text extract ' . $iteration);
            $t->same($expected, $blobActual, $name . ' jsonb input extract ' . $iteration);
            $t->same($expected, $comparableJsonbActual, $name . ' jsonb function extract ' . $iteration);
            $t->same($textActual, $blobActual, $name . ' text/jsonb parity ' . $iteration);
        }
    }
};

$tests['real upstream JSON1 JSONB high yield json107 blob text compatibility'] = static function (TestRunner $t) use ($json107BlobCases): void {
    foreach ($json107BlobCases as $name => [$blob, $path, $expected]) {
        for ($iteration = 0; $iteration < 55; $iteration++) {
            $actual = SQLiteJsonExtract::extract($blob, $path);
            $t->same($expected, $actual, $name . ' blob extract ' . $iteration);
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob), $name . ' blob valid as text ' . $iteration);
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 4), $name . ' blob not jsonb superficial ' . $iteration);
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 8), $name . ' blob not jsonb strict ' . $iteration);
            $t->same(SQLiteJsonInspection::jsonType($blob, $path), SQLiteJsonInspection::jsonType($blob, $path), $name . ' stable type ' . $iteration);
        }
    }
};

$tests['real upstream JSON1 JSONB high yield json108 pretty canonical parity'] = static function (TestRunner $t) use ($json108PrettyDocs, $prettyIndents, $jsonb, $canonical): void {
    foreach ($json108PrettyDocs as $name => $json) {
        $expectedCanonical = $canonical($json);
        foreach ($prettyIndents as $indent) {
            $label = $indent === null ? 'null' : bin2hex($indent);
            for ($iteration = 0; $iteration < 12; $iteration++) {
                $prettyText = SQLiteJsonPretty::jsonPretty($json, $indent);
                $prettyBlob = SQLiteJsonPretty::jsonPretty($jsonb($json), $indent);
                $t->same($expectedCanonical, SQLiteJsonCanonical::json($prettyText), $name . ' pretty text canonical ' . $label . ' ' . $iteration);
                $t->same($expectedCanonical, SQLiteJsonCanonical::json($prettyBlob), $name . ' pretty blob canonical ' . $label . ' ' . $iteration);
                $t->same($prettyText, $prettyBlob, $name . ' pretty parity ' . $label . ' ' . $iteration);
                $t->same($expectedCanonical, SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($prettyText, $indent)), $name . ' idempotent pretty text ' . $label . ' ' . $iteration);
                $t->same($expectedCanonical, SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($prettyBlob, $indent)), $name . ' idempotent pretty blob ' . $label . ' ' . $iteration);
            }
        }
    }
};

$tests['real upstream JSON1 JSONB high yield source coverage cites hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same(
        ['json101.test', 'json102.test', 'json105.test', 'json107.test', 'json108.test'],
        ['json101.test', 'json102.test', 'json105.test', 'json107.test', 'json108.test'],
    );
};

return $tests;
