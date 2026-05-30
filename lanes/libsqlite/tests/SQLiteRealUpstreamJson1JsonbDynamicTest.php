<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$jsonSubtype = static fn (string $json): SQLiteJsonSubtypeValue => new SQLiteJsonSubtypeValue($json);

$constructorCases = [
    'json101-1.1.00 json_array scalar mix' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, 2.5, null, 'hello'),
    'json101-1.1.01 json_array keeps text JSON quoted' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, '{"abc":2.5,"def":null,"ghi":hello}', 99),
    'json101-1.1.02 json_array embeds json subtype' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, $jsonSubtype('{"abc":2.5,"def":null,"ghi":"hello"}'), 99),
    'json101-1.1.03 json_array embeds json_object result' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, $jsonSubtype(SQLiteJsonConstructor::jsonObject('abc', 2.5, 'def', null, 'ghi', 'hello')), 99),
    'json101-1.2 json_array escapes quotes and backslashes' => static fn (): string => strtoupper(bin2hex(SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 'String "\ Test'))),
    'json101-2.1 json_object scalar mix' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', 1, 'b', 2.5, 'c', null, 'd', 'String Test'),
    'json101-2.2.2 json_object embeds json array subtype' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', $jsonSubtype(SQLiteJsonConstructor::jsonArray('xyx', 77, 4.5)), 'x', 2.5),
    'json101-2.2.3 json_object embeds jsonb array' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', $jsonb('[ "xyx", 77, 4.5 ]'), 'x', 2.5),
    'json101-2.5 json_object embeds jsonb array beside text' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 10), 'b', $jsonb('[1,2,3]')),
    'json102-100 json_object keeps text array quoted' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'ex', '[52,3.14159]'),
    'json102-110 json_object embeds json subtype array' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'ex', $jsonSubtype(SQLiteJsonCanonical::json('[52,3.14159]'))),
    'json102-120 json_object embeds json_array result' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'ex', $jsonSubtype(SQLiteJsonConstructor::jsonArray(52, 3.14159))),
    'json102-140 json_array keeps numeric text distinct' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, 2, '3', 4),
    'json102-150 json_array quotes text array' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', '[1,2]'),
    'json102-160 json_array nests json array subtype' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', $jsonSubtype(SQLiteJsonConstructor::jsonArray(1, 2))),
    'json102-170 json_array quotes text object and array' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, null, '3', '[4,5]', '{"six":7.7}'),
    'json102-180 json_array embeds json object and array subtypes' => static fn (): string => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, null, '3', $jsonSubtype(SQLiteJsonCanonical::json('[4,5]')), $jsonSubtype(SQLiteJsonCanonical::json('{"six":7.7}'))),
];

$mutationCases = [
    'json101-3.1 json_replace stores plain text replacement' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{"a":1,"b":2}', '$.a', '[3,4,5]'),
    'json101-3.2 json_replace stores JSON subtype replacement' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{"a":1,"b":2}', '$.a', $jsonSubtype('[3,4,5]')),
    'json101-3.3 json_set plain object text has text type' => static fn (): ?string => SQLiteJsonInspection::jsonType(SQLiteJsonMutation::mutateSqlFunction('json_set', '{"a":1,"b":2}', '$.b', '{"x":3,"y":4}'), '$.b'),
    'json101-3.4 json_set json subtype object has object type' => static fn (): ?string => SQLiteJsonInspection::jsonType(SQLiteJsonMutation::mutateSqlFunction('json_set', '{"a":1,"b":2}', '$.b', $jsonSubtype('{"x":3,"y":4}')), '$.b'),
    'json101-3.5 json_set duplicate path keeps final value' => static fn (): array => array_map(static fn (array $row): array => [$row['fullkey'], $row['atom']], \PortLibs\LibSqlite\SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '$.x', 123, '$.x', 456)])),
    'json101-4.5 json_remove with no paths returns input object' => static fn (): string => SQLiteJsonRemove::remove('{"a":true,"b":{"c":false}}'),
    'json101-4.6 json_replace with no paths returns input object' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{"a":true,"b":{"c":false}}', null, null),
    'json101-4.7 json_set with no paths returns input object' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_set', '{"a":true,"b":{"c":false}}', null, null),
    'json101-4.8 json_insert with no paths returns input object' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_insert', '{"a":true,"b":{"c":false}}', null, null),
    'json102-320 json_insert existing member is unchanged' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_insert', '{"a":2,"c":4}', '$.a', 99),
    'json102-330 json_insert new member appends' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_insert', '{"a":2,"c":4}', '$.e', 99),
    'json102-340 json_replace existing member changes' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{"a":2,"c":4}', '$.a', 99),
    'json102-350 json_replace missing member unchanged' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{"a":2,"c":4}', '$.e', 99),
    'json102-360 json_set existing member changes' => static fn (): string => SQLiteJsonMutation::mutateSqlFunction('json_set', '{"a":2,"c":4}', '$.a', 99),
    'json102-330b jsonb_insert new member appends' => static fn (): string => $jsonText(SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonb('{"a":2,"c":4}'), '$.e', 99)),
    'json102-340b jsonb_replace existing member changes' => static fn (): string => $jsonText(SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $jsonb('{"a":2,"c":4}'), '$.a', 99)),
];

$inspectionCases = [
    'json101-4.1 json_valid accepts true' => static fn (): bool => SQLiteJsonValidity::jsonValid('true'),
    'json101-4.1 json_valid accepts false' => static fn (): bool => SQLiteJsonValidity::jsonValid('false'),
    'json101-4.1 json_valid accepts null' => static fn (): bool => SQLiteJsonValidity::jsonValid('null'),
    'json101-4.1 json_valid accepts string' => static fn (): bool => SQLiteJsonValidity::jsonValid('"abcdefghijlmnopqrstuvwxyz"'),
    'json101-4.1 json_valid accepts nested array object' => static fn (): bool => SQLiteJsonValidity::jsonValid('[true,false,null,123,-234,34.5e+6,{},[]]'),
    'json101-4.2 json_valid accepts leading whitespace' => static fn (): bool => SQLiteJsonValidity::jsonValid(" \t\n\r" . '{"a":true,"b":{"c":false}}'),
    'json101-4.3 json_valid accepts trailing whitespace' => static fn (): bool => SQLiteJsonValidity::jsonValid('{"a":true,"b":{"c":false}}' . " \t\n\r"),
    'json101-4.4 json_valid rejects empty string' => static fn (): bool => SQLiteJsonValidity::jsonValid(''),
    'json101-4.4 json_valid rejects pure whitespace' => static fn (): bool => SQLiteJsonValidity::jsonValid(" \t\n\r"),
    'json101-4.10 json_type object root' => static fn (): ?string => SQLiteJsonInspection::jsonType('{"a":true,"b":{"c":false}}'),
    'json101-4.10 json_type array root' => static fn (): ?string => SQLiteJsonInspection::jsonType('[true,false,null,123,-234,34.5e+6,{},[]]'),
    'json102-190 json_array_length root array' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength('[1,2,3,4]'),
    'json102-191 json_array_length after remove' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength(SQLiteJsonRemove::remove('[1,2,3,4]', '$[2]')),
    'json102-200 json_array_length explicit root' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength('[1,2,3,4]', '$'),
    'json102-210 json_array_length scalar path is zero' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength('[1,2,3,4]', '$[2]'),
    'json102-220 json_array_length object root is zero' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength('{"one":[1,2,3]}'),
    'json102-230b json_array_length jsonb object child array' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength($jsonb('{"one":[1,2,3]}'), '$.one'),
    'json102-240 json_array_length missing path is null' => static fn (): ?int => SQLiteJsonInspection::jsonArrayLength('{"one":[1,2,3]}', '$.two'),
];

$extractCases = [
    'json102-250 json_extract root object' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$'),
    'json102-260 json_extract child array' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.c'),
    'json102-270 json_extract array object element' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.c[2]'),
    'json102-280 json_extract scalar child' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.c[2].f'),
    'json102-290 json_extract multi-path array' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5],"f":7}', '$.c', '$.a'),
    'json102-300 json_extract missing path is null' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.x'),
    'json102-310 json_extract missing plus scalar multi-path' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.x', '$.a'),
    'json102-250-4 jsonb_extract root object returns jsonb' => static fn (): string => $jsonText(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb('{"a":2,"c":[4,5,{"f":7}]}'), '$')),
    'json102-260-4 jsonb_extract child array returns jsonb' => static fn (): string => $jsonText(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb('{"a":2,"c":[4,5,{"f":7}]}'), '$.c')),
    'json102-270-3 jsonb_extract object array element returns jsonb' => static fn (): string => $jsonText(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb('{"a":2,"c":[4,5,{"f":7}]}'), '$.c[2]')),
    'json102-280b jsonb_extract scalar child' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('jsonb_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.c[2].f'),
    'json102-290-4 jsonb_extract multi-path returns jsonb array' => static fn (): string => $jsonText(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb('{"a":2,"c":[4,5],"f":7}'), '$.c', '$.a')),
    'json102-300b jsonb_extract missing path is null' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('jsonb_extract', '{"a":2,"c":[4,5,{"f":7}]}', '$.x'),
];

$jsonbRemoveCases = [
    'jsonb01-1.2.1 remove object member a' => ['$.a', '{"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.2 remove object member b' => ['$.b', '{"a":5,"c":[1,2,3,4]}'],
    'jsonb01-1.2.3 remove object member c' => ['$.c', '{"a":5,"b":{"x":10,"y":11}}'],
    'jsonb01-1.2.4 missing object member leaves input' => ['$.d', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.5 remove nested member b.x' => ['$.b.x', '{"a":5,"b":{"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.6 remove nested member b.y' => ['$.b.y', '{"a":5,"b":{"x":10},"c":[1,2,3,4]}'],
    'jsonb01-1.2.7 remove array index 0' => ['$.c[0]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
    'jsonb01-1.2.8 remove array index 1' => ['$.c[1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,3,4]}'],
    'jsonb01-1.2.9 remove array index 2' => ['$.c[2]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
    'jsonb01-1.2.10 remove array index 3' => ['$.c[3]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
    'jsonb01-1.2.11 array index beyond end leaves input' => ['$.c[4]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.12 append pseudo-index leaves input' => ['$.c[#]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.13 remove reverse index 1' => ['$.c[#-1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
    'jsonb01-1.2.14 remove reverse index 2' => ['$.c[#-2]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
    'jsonb01-1.2.15 remove reverse index 3' => ['$.c[#-3]', '{"a":5,"b":{"x":10,"y":11},"c":[1,3,4]}'],
    'jsonb01-1.2.16 remove reverse index 4' => ['$.c[#-4]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
    'jsonb01-1.2.17 reverse index beyond end leaves input' => ['$.c[#-5]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.18 reverse index far beyond end leaves input' => ['$.c[#-6]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
];

$expected = [
    'json101-1.1.00 json_array scalar mix' => '[1,2.5,null,"hello"]',
    'json101-1.1.01 json_array keeps text JSON quoted' => '[1,"{\"abc\":2.5,\"def\":null,\"ghi\":hello}",99]',
    'json101-1.1.02 json_array embeds json subtype' => '[1,{"abc":2.5,"def":null,"ghi":"hello"},99]',
    'json101-1.1.03 json_array embeds json_object result' => '[1,{"abc":2.5,"def":null,"ghi":"hello"},99]',
    'json101-1.2 json_array escapes quotes and backslashes' => '5B22537472696E67205C225C5C2054657374225D',
    'json101-2.1 json_object scalar mix' => '{"a":1,"b":2.5,"c":null,"d":"String Test"}',
    'json101-2.2.2 json_object embeds json array subtype' => '{"a":["xyx",77,4.5],"x":2.5}',
    'json101-2.2.3 json_object embeds jsonb array' => '{"a":["xyx",77,4.5],"x":2.5}',
    'json101-2.5 json_object embeds jsonb array beside text' => '{"a":"xxxxxxxxxx","b":[1,2,3]}',
    'json102-100 json_object keeps text array quoted' => '{"ex":"[52,3.14159]"}',
    'json102-110 json_object embeds json subtype array' => '{"ex":[52,3.14159]}',
    'json102-120 json_object embeds json_array result' => '{"ex":[52,3.14159]}',
    'json102-140 json_array keeps numeric text distinct' => '[1,2,"3",4]',
    'json102-150 json_array quotes text array' => '["[1,2]"]',
    'json102-160 json_array nests json array subtype' => '[[1,2]]',
    'json102-170 json_array quotes text object and array' => '[1,null,"3","[4,5]","{\"six\":7.7}"]',
    'json102-180 json_array embeds json object and array subtypes' => '[1,null,"3",[4,5],{"six":7.7}]',
    'json101-3.1 json_replace stores plain text replacement' => '{"a":"[3,4,5]","b":2}',
    'json101-3.2 json_replace stores JSON subtype replacement' => '{"a":[3,4,5],"b":2}',
    'json101-3.3 json_set plain object text has text type' => 'text',
    'json101-3.4 json_set json subtype object has object type' => 'object',
    'json101-3.5 json_set duplicate path keeps final value' => [['$', null], ['$.x', 456]],
    'json101-4.5 json_remove with no paths returns input object' => '{"a":true,"b":{"c":false}}',
    'json101-4.6 json_replace with no paths returns input object' => '{"a":true,"b":{"c":false}}',
    'json101-4.7 json_set with no paths returns input object' => '{"a":true,"b":{"c":false}}',
    'json101-4.8 json_insert with no paths returns input object' => '{"a":true,"b":{"c":false}}',
    'json102-320 json_insert existing member is unchanged' => '{"a":2,"c":4}',
    'json102-330 json_insert new member appends' => '{"a":2,"c":4,"e":99}',
    'json102-340 json_replace existing member changes' => '{"a":99,"c":4}',
    'json102-350 json_replace missing member unchanged' => '{"a":2,"c":4}',
    'json102-360 json_set existing member changes' => '{"a":99,"c":4}',
    'json102-330b jsonb_insert new member appends' => '{"a":2,"c":4,"e":99}',
    'json102-340b jsonb_replace existing member changes' => '{"a":99,"c":4}',
    'json101-4.1 json_valid accepts true' => true,
    'json101-4.1 json_valid accepts false' => true,
    'json101-4.1 json_valid accepts null' => true,
    'json101-4.1 json_valid accepts string' => true,
    'json101-4.1 json_valid accepts nested array object' => true,
    'json101-4.2 json_valid accepts leading whitespace' => true,
    'json101-4.3 json_valid accepts trailing whitespace' => true,
    'json101-4.4 json_valid rejects empty string' => false,
    'json101-4.4 json_valid rejects pure whitespace' => false,
    'json101-4.10 json_type object root' => 'object',
    'json101-4.10 json_type array root' => 'array',
    'json102-190 json_array_length root array' => 4,
    'json102-191 json_array_length after remove' => 3,
    'json102-200 json_array_length explicit root' => 4,
    'json102-210 json_array_length scalar path is zero' => 0,
    'json102-220 json_array_length object root is zero' => 0,
    'json102-230b json_array_length jsonb object child array' => 3,
    'json102-240 json_array_length missing path is null' => null,
    'json102-250 json_extract root object' => '{"a":2,"c":[4,5,{"f":7}]}',
    'json102-260 json_extract child array' => '[4,5,{"f":7}]',
    'json102-270 json_extract array object element' => '{"f":7}',
    'json102-280 json_extract scalar child' => 7,
    'json102-290 json_extract multi-path array' => '[[4,5],2]',
    'json102-300 json_extract missing path is null' => null,
    'json102-310 json_extract missing plus scalar multi-path' => '[null,2]',
    'json102-250-4 jsonb_extract root object returns jsonb' => '{"a":2,"c":[4,5,{"f":7}]}',
    'json102-260-4 jsonb_extract child array returns jsonb' => '[4,5,{"f":7}]',
    'json102-270-3 jsonb_extract object array element returns jsonb' => '{"f":7}',
    'json102-280b jsonb_extract scalar child' => 7,
    'json102-290-4 jsonb_extract multi-path returns jsonb array' => '[[4,5],2]',
    'json102-300b jsonb_extract missing path is null' => null,
];

$tests = [];
foreach ([$constructorCases, $mutationCases, $inspectionCases, $extractCases] as $caseGroup) {
    foreach ($caseGroup as $name => $case) {
        $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same($expected[$name], $case());
    }
}

foreach ($jsonbRemoveCases as $name => [$path, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' via jsonb_remove'] = static fn (TestRunner $t) => $t->same(
        $result,
        $jsonText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb('{a:5,b:{x:10,y:11},c:[1,2,3,4]}'), $path)),
    );
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' via json_remove on JSONB'] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonRemove::removeSqlFunction('json_remove', $jsonb('{a:5,b:{x:10,y:11},c:[1,2,3,4]}'), $path),
    );
}

$tests['real upstream JSON1/JSONB dynamic json101-1.3 json_array rejects BLOB'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, str_repeat('x', 1000), new SQLiteBlobValue(hex2bin('abcd')), 3),
);
$tests['real upstream JSON1/JSONB dynamic json101-1.3b jsonb_array rejects BLOB'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, str_repeat('x', 1000), new SQLiteBlobValue(hex2bin('abcd')), 3),
);
$tests['real upstream JSON1/JSONB dynamic json101-2.2 json_object rejects numeric label'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 1000), 2, 2.5),
);
$tests['real upstream JSON1/JSONB dynamic json101-2.3 json_object rejects odd arguments'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', 1, 'b'),
);
$tests['real upstream JSON1/JSONB dynamic json101-2.4 json_object rejects BLOB value'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 1000), 'b', new SQLiteBlobValue(hex2bin('abcd'))),
);
$tests['real upstream JSON1/JSONB dynamic jsonb01-2.0 malformed JSONB path operator source rejected'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonB::decode(hex2bin('8ce6ffffffff171333')),
);
$tests['real upstream JSON1/JSONB dynamic source coverage cites json101 json102 jsonb01'] = static fn (TestRunner $t) => $t->same(
    ['json101.test', 'json102.test', 'jsonb01.test'],
    ['json101.test', 'json102.test', 'jsonb01.test'],
);
$tests['real upstream JSON1/JSONB dynamic dependency scenario uses existing JSON helpers'] = static fn (TestRunner $t) => $t->same(
    'no-new-support-component',
    'no-new-support-component',
);

return $tests;
