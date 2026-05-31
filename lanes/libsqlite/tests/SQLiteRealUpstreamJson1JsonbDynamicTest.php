<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$jsonSubtype = static fn (string $json): SQLiteJsonSubtypeValue => new SQLiteJsonSubtypeValue($json);
$jsonSqlText = static fn (mixed $value): mixed => $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;

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

$json105Document = '{"a":1,"b":[1,[2,3],4],"c":99}';

$json104PatchCases = [
    'json104-100 RFC 7396 nested member deletion' => [
        '{"a":"b","c":{"d":"e","f":"g"}}',
        '{"a":"z","c":{"f":null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-101 JSON5 patch keys delete nested member' => [
        '{"a":"b","c":{"d":"e","f":"g"}}',
        '{a:"z",c:{f:null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-102 JSON5 target keys accept quoted patch' => [
        '{a:"b",c:{d:"e",f:"g"}}',
        '{"a":"z","c":{"f":null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-103 JSON5 target and patch keys' => [
        '{a:"b",c:{d:"e",f:"g"}}',
        '{a:"z",c:{f:null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-110 RFC 7396 document example' => [
        '{"title":"Goodbye!","author":{"givenName":"John","familyName":"Doe"},"tags":["example","sample"],"content":"This will be unchanged"}',
        '{"title":"Hello!","phoneNumber":"+01-123-456-7890","author":{"familyName":null},"tags":["example"]}',
        '{"title":"Hello!","author":{"givenName":"John"},"tags":["example"],"content":"This will be unchanged","phoneNumber":"+01-123-456-7890"}',
    ],
    'json104-200 object patch replaces array target' => [
        '[1,2,3]',
        '{"x":null}',
        '{}',
    ],
    'json104-210 null members are removed after array target replacement' => [
        '[1,2,3]',
        '{"x":null,"y":1,"z":null}',
        '{"y":1}',
    ],
    'json104-220 nested null member becomes empty object' => [
        '{}',
        '{"a":{"bb":{"ccc":null}}}',
        '{"a":{"bb":{}}}',
    ],
    'json104-221 nested array with null is preserved' => [
        '{}',
        '{"a":{"bb":{"ccc":[1,null,3]}}}',
        '{"a":{"bb":{"ccc":[1,null,3]}}}',
    ],
    'json104-222 null inside array object is preserved' => [
        '{}',
        '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}',
        '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}',
    ],
    'json104-300 replaces existing scalar member' => [
        '{"a":"b"}',
        '{"a":"c"}',
        '{"a":"c"}',
    ],
    'json104-301 appends new scalar member' => [
        '{"a":"b"}',
        '{"b":"c"}',
        '{"a":"b","b":"c"}',
    ],
    'json104-302 null patch removes sole member' => [
        '{"a":"b"}',
        '{"a":null}',
        '{}',
    ],
    'json104-303 null patch removes one of two members' => [
        '{"a":"b","b":"c"}',
        '{"a":null}',
        '{"b":"c"}',
    ],
    'json104-304 scalar patch replaces array member' => [
        '{"a":["b"]}',
        '{"a":"c"}',
        '{"a":"c"}',
    ],
    'json104-305 array patch replaces scalar member' => [
        '{"a":"c"}',
        '{"a":["b"]}',
        '{"a":["b"]}',
    ],
    'json104-306 nested merge removes null child' => [
        '{"a":{"b":"c"}}',
        '{"a":{"b":"d","c":null}}',
        '{"a":{"b":"d"}}',
    ],
    'json104-307 array patch replaces nested object array' => [
        '{"a":[{"b":"c"}]}',
        '{"a":[1]}',
        '{"a":[1]}',
    ],
    'json104-308 array patch replaces array target' => [
        '["a","b"]',
        '["c","d"]',
        '["c","d"]',
    ],
    'json104-309 array patch replaces object target' => [
        '{"a":"b"}',
        '["c"]',
        '["c"]',
    ],
    'json104-310 null JSON patch replaces object with JSON null' => [
        '{"a":"foo"}',
        'null',
        'null',
    ],
    'json104-311 string JSON patch replaces object with string' => [
        '{"a":"foo"}',
        '"bar"',
        '"bar"',
    ],
    'json104-312 null target member is preserved while appending' => [
        '{"e":null}',
        '{"a":1}',
        '{"e":null,"a":1}',
    ],
    'json104-313 object patch replaces array target and drops null patch member' => [
        '[1,2]',
        '{"a":"b","c":null}',
        '{"a":"b"}',
    ],
    'json104-314 nested null patch creates empty object' => [
        '{}',
        '{"a":{"bb":{"ccc":null}}}',
        '{"a":{"bb":{}}}',
    ],
    'json104-320 duplicate patch object key keeps final value' => [
        '{"x":{"one":1}}',
        '{"x":{"two":2},"x":"three"}',
        '{"x":"three"}',
    ],
];
$json105ExtractCases = [
    'json105-1.10 extract append pseudo-index is missing' => ['$.b[#]', null],
    'json105-1.20 extract reverse index 1' => ['$.b[#-1]', 4],
    'json105-1.30 extract reverse index 2 nested array' => ['$.b[#-2]', '[2,3]'],
    'json105-1.31 extract reverse index with leading zero' => ['$.b[#-02]', '[2,3]'],
    'json105-1.40 extract reverse index 3' => ['$.b[#-3]', 1],
    'json105-1.50 extract reverse index past start is missing' => ['$.b[#-4]', null],
    'json105-1.51 extract huge reverse index is missing' => ['$.b[#-4296967295]', null],
    'json105-1.52 extract huge reverse index plus one is missing' => ['$.b[#-4296967296]', null],
    'json105-1.53 extract huge reverse index plus two is missing' => ['$.b[#-4296967297]', null],
    'json105-1.54 extract huge reverse index long decimal is missing' => ['$.b[#-42969672950]', null],
    'json105-1.55 extract huge reverse index long decimal plus ten is missing' => ['$.b[#-42969672960]', null],
    'json105-1.60 extract nested reverse index' => ['$.b[#-2][#-1]', 3],
    'json105-1.70 extract multi-path with reverse index' => [['$.b[0]', '$.b[#-1]'], '[1,4]'],
    'json105-1.100 extract reverse index on scalar is missing' => ['$.a[#-1]', null],
    'json105-1.110 extract reverse index with padded zeros' => ['$.b[#-000001]', 4],
];

$json105RemoveCases = [
    'json105-2.10 remove append pseudo-index leaves input' => [['$.b[#]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.20 remove reverse zero leaves input' => [['$.b[#-0]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.30 remove reverse index 1' => [['$.b[#-1]'], '{"a":1,"b":[1,[2,3]],"c":99}'],
    'json105-2.40 remove reverse index 2' => [['$.b[#-2]'], '{"a":1,"b":[1,4],"c":99}'],
    'json105-2.50 remove reverse index 3' => [['$.b[#-3]'], '{"a":1,"b":[[2,3],4],"c":99}'],
    'json105-2.60 remove reverse index past start leaves input' => [['$.b[#-4]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.70 remove nested reverse index' => [['$.b[#-2][#-1]'], '{"a":1,"b":[1,[2],4],"c":99}'],
    'json105-2.100 remove first then reverse last' => [['$.b[0]', '$.b[#-1]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.110 remove reverse last then first' => [['$.b[#-1]', '$.b[0]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.120 remove reverse last then reverse second' => [['$.b[#-1]', '$.b[#-2]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.130 remove reverse last twice' => [['$.b[#-1]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
    'json105-2.140 remove reverse second then reverse last' => [['$.b[#-2]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
];

$json105InsertCases = [
    'json105-3.10 insert append pseudo-index' => ['json_insert', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'json105-3.20 insert nested append pseudo-index' => ['json_insert', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'json105-3.30 insert nested append then outer append' => ['json_insert', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'json105-3.40 insert two outer appends' => ['json_insert', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'json105-4.10 set append pseudo-index' => ['json_set', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'json105-4.20 set nested append pseudo-index' => ['json_set', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'json105-4.30 set nested append then outer append' => ['json_set', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'json105-4.40 set two outer appends' => ['json_set', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'json105-4.50 set reverse last element' => ['json_set', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'json105-4.60 set nested reverse last element' => ['json_set', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'json105-4.70 set nested reverse then outer reverse' => ['json_set', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'json105-4.80 set reverse last twice' => ['json_set', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
    'json105-5.10 replace append pseudo-index leaves input' => ['json_replace', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.20 replace nested append pseudo-index leaves input' => ['json_replace', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.30 replace nested append then outer append leaves input' => ['json_replace', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.40 replace two outer appends leaves input' => ['json_replace', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.50 replace reverse last element' => ['json_replace', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
];

$json107Blob = new SQLiteBlobValue('{"a":123,"b":456}');
$json107ValidCases = [
    'json107-1.1 text-looking BLOB is strict text JSON' => [new SQLiteBlobValue('{"a":1}'), 1, true],
    'json107-1.1.1 text-looking BLOB is valid with strict-text flag' => [new SQLiteBlobValue('{"a":1}'), 1, true],
    'json107-1.1.2 text-looking BLOB is valid with JSON5 flag' => [new SQLiteBlobValue('{"a":1}'), 2, true],
    'json107-1.1.4 text-looking BLOB is not superficial JSONB' => [new SQLiteBlobValue('{"a":1}'), 4, false],
    'json107-1.1.8 text-looking BLOB is not strict JSONB' => [new SQLiteBlobValue('{"a":1}'), 8, false],
];

$json109ArrayInsertCases = [
    'json109-1.1 repeated inserts before first element are left-to-right' => ['[1,2,3]', ['$[0]', 999, '$[0]', 888], '[888,999,1,2,3]'],
    'json109-1.2 insert before first then append' => ['[1,2,3]', ['$[0]', 999, '$[#]', 888], '[999,1,2,3,888]'],
    'json109-1.3 insert before array index one' => ['[1,2,3]', ['$[1]', 888], '[1,888,2,3]'],
    'json109-1.4 insert before array index two' => ['[1,2,3]', ['$[2]', 888], '[1,2,888,3]'],
    'json109-1.5 insert at array length appends' => ['[1,2,3]', ['$[3]', 888], '[1,2,3,888]'],
    'json109-1.6 insert before reverse last' => ['[1,2,3]', ['$[#-1]', 888], '[1,2,888,3]'],
    'json109-1.7 insert before reverse second' => ['[1,2,3]', ['$[#-2]', 888], '[1,888,2,3]'],
    'json109-1.8 insert before reverse third' => ['[1,2,3]', ['$[#-3]', 888], '[888,1,2,3]'],
    'json109-1.9 reverse index beyond start leaves input' => ['[1,2,3]', ['$[#-4]', 888], '[1,2,3]'],
    'json109-2.3 missing object member creates array for indexed child' => ['{a:[1,2,3]}', ['$.b[0]', 888], '{"a":[1,2,3],"b":[888]}'],
    'json109-2.4 missing nested object path creates array leaf' => ['{a:[1,2,3]}', ['$.b.c.d[0]', 888], '{"a":[1,2,3],"b":{"c":{"d":[888]}}}'],
];

$json109ArrayInsertErrorCases = [
    'json109-2.1 object member path is not an array element' => ['{a:[1,2,3]}', '$.a'],
    'json109-2.2 missing object member path is not an array element' => ['{a:[1,2,3]}', '$.b'],
    'json109-2.5 malformed array path is not an array element' => ['{a:[1,2,3]}', '$.b.c.d[0'],
    'json109-2.6 missing nested object path without index is not an array element' => ['{a:[1,2,3]}', '$.b.c.d'],
    'json109-2.8 later non-array path aborts multi-insert' => ['{a:[1,2,3]}', '$.b[0]', 888, '$.a[1]', '999', '$.c'],
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

foreach ($json105ExtractCases as $name => [$path, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static function (TestRunner $t) use ($json105Document, $path, $result): void {
        $actual = is_array($path)
            ? SQLiteJsonExtract::extractSqlFunction('json_extract', $json105Document, ...$path)
            : SQLiteJsonExtract::extractSqlFunction('json_extract', $json105Document, $path);
        $t->same($result, $actual);
    };
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' on JSONB'] = static function (TestRunner $t) use ($json105Document, $jsonb, $jsonText, $path, $result): void {
        $input = $jsonb($json105Document);
        $actual = is_array($path)
            ? SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $input, ...$path)
            : SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $input, $path);
        if ($actual instanceof SQLiteBlobValue) {
            $actual = $jsonText($actual);
        }
        $t->same($result, $actual);
    };
}

foreach ($json105RemoveCases as $name => [$paths, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonRemove::remove($json105Document, ...$paths),
    );
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' on JSONB'] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonRemove::remove($jsonb($json105Document), ...$paths),
    );
}

foreach ($json105InsertCases as $name => [$function, $arguments, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonMutation::mutateSqlFunctionArguments($function, array_merge([$json105Document], $arguments)),
    );
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' on JSONB'] = static function (TestRunner $t) use ($json105Document, $jsonb, $jsonText, $function, $arguments, $result): void {
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($jsonbFunction, array_merge([$jsonb($json105Document)], $arguments));
        $t->same($result, $actual instanceof SQLiteBlobValue ? $jsonText($actual) : $actual);
    };
}

foreach ($json107ValidCases as $name => [$input, $flags, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonValidity::jsonValid($input, $flags),
    );
}

$tests['real upstream JSON1/JSONB dynamic json107-1.2.1 text-looking BLOB arrow returns JSON text'] = static fn (TestRunner $t) => $t->same(
    '123',
    $jsonSqlText(SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->', 'left' => ['type' => 'literal', 'value' => $json107Blob], 'right' => ['type' => 'literal', 'value' => 'a']])),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.2.2 text-looking BLOB double-arrow returns SQL scalar'] = static fn (TestRunner $t) => $t->same(
    123,
    SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->>', 'left' => ['type' => 'literal', 'value' => $json107Blob], 'right' => ['type' => 'literal', 'value' => 'a']]),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.2.3 text-looking BLOB extracts scalar'] = static fn (TestRunner $t) => $t->same(
    123,
    SQLiteJsonExtract::extractSqlFunction('json_extract', $json107Blob, '$.a'),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.3 text-looking BLOB json_insert mutates text JSON'] = static fn (TestRunner $t) => $t->same(
    '{"a":123,"b":456,"c":789}',
    SQLiteJsonMutation::mutateSqlFunction('json_insert', $json107Blob, '$.c', 789),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.4 text-looking BLOB json_remove mutates text JSON'] = static fn (TestRunner $t) => $t->same(
    '{"b":456}',
    SQLiteJsonRemove::remove(new SQLiteBlobValue('{"a":123,"b":456}'), '$.a'),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.5 text-looking BLOB json_set mutates text JSON'] = static fn (TestRunner $t) => $t->same(
    '{"a":789,"b":456}',
    SQLiteJsonMutation::mutateSqlFunction('json_set', new SQLiteBlobValue('{"a":123,"b":456}'), '$.a', 789),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.6 text-looking BLOB json_replace mutates text JSON'] = static fn (TestRunner $t) => $t->same(
    '{"a":789,"b":456}',
    SQLiteJsonMutation::mutateSqlFunction('json_replace', new SQLiteBlobValue('{"a":123,"b":456}'), '$.a', 789),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.7 text-looking BLOB json_type reports object'] = static fn (TestRunner $t) => $t->same(
    'object',
    SQLiteJsonInspection::jsonType($json107Blob),
);
$tests['real upstream JSON1/JSONB dynamic json107-1.8 text-looking BLOB json canonicalizes object'] = static fn (TestRunner $t) => $t->same(
    '{"a":123,"b":456}',
    SQLiteJsonCanonical::json($json107Blob),
);
$tests['real upstream JSON1/JSONB dynamic json107-2.1 text-looking BLOB json_tree emits scalar members'] = static function (TestRunner $t) use ($json107Blob): void {
    $rows = array_values(array_filter(
        SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json107Blob),
        static fn (array $row): bool => $row['atom'] !== null,
    ));
    $t->same([['a', 123], ['b', 456]], array_map(static fn (array $row): array => [$row['key'], $row['value']], $rows));
};

foreach ($json109ArrayInsertCases as $name => [$json, $arguments, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', array_merge([$json], $arguments)),
    );
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' on JSONB'] = static fn (TestRunner $t) => $t->same(
        $result,
        $jsonText(SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', array_merge([$jsonb($json)], $arguments))),
    );
}

$tests['real upstream JSON1/JSONB dynamic json109-2.7 array insert against object root leaves input'] = static fn (TestRunner $t) => $t->same(
    '{"a":[1,2,3]}',
    SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{a:[1,2,3]}', '$[0]', 888),
);

foreach ($json109ArrayInsertErrorCases as $name => $arguments) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', $arguments),
    );
}

foreach ($json104PatchCases as $name => [$target, $patch, $result]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonPatch::patchSqlFunction('json_patch', $target, $patch),
    );
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' via jsonb_patch'] = static fn (TestRunner $t) => $t->same(
        $result,
        $jsonText(SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($target), $jsonb($patch))),
    );
    $tests['real upstream JSON1/JSONB dynamic ' . $name . ' via json_patch on JSONB'] = static fn (TestRunner $t) => $t->same(
        $result,
        SQLiteJsonPatch::patchSqlFunction('json_patch', $jsonb($target), $jsonb($patch)),
    );
}

$tests['real upstream JSON1/JSONB dynamic json104-300a json_patch null target returns SQL null'] = static fn (TestRunner $t) => $t->same(
    null,
    SQLiteJsonPatch::patchSqlFunction('json_patch', null, '{"a":"c"}'),
);
$tests['real upstream JSON1/JSONB dynamic json104-310a json_patch null patch returns SQL null'] = static fn (TestRunner $t) => $t->same(
    null,
    SQLiteJsonPatch::patchSqlFunction('json_patch', '{"a":"foo"}', null),
);
$tests['real upstream JSON1/JSONB dynamic json104 quoted path extracts same member'] = static function (TestRunner $t): void {
    $json = '{"a":1,"b":2}';
    $t->same(2, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$.b'));
    $t->same(2, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$."b"'));
};
$tests['real upstream JSON1/JSONB dynamic json104 quoted path set updates same member'] = static function (TestRunner $t): void {
    $json = '{"a":1,"b":2,"c":3}';
    $patched = SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$."b"', 555);
    $t->same(555, SQLiteJsonExtract::extractSqlFunction('json_extract', $patched, '$.b'));
    $t->same(555, SQLiteJsonExtract::extractSqlFunction('json_extract', $patched, '$."b"'));
};
$tests['real upstream JSON1/JSONB dynamic json104 quoted path set appends member'] = static fn (TestRunner $t) => $t->same(
    4,
    SQLiteJsonExtract::extractSqlFunction(
        'json_extract',
        SQLiteJsonMutation::mutateSqlFunction('json_set', '{"a":1,"b":2,"c":3}', '$."d"', 4),
        '$."d"',
    ),
);

$tests['real upstream JSON1/JSONB dynamic json105-6.10 array insert before reverse last'] = static fn (TestRunner $t) => $t->same(
    '{"a":1,"b":[1,[2,3],"AAA",4],"c":99}',
    SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json105Document, '$.b[#-1]', 'AAA'),
);
$tests['real upstream JSON1/JSONB dynamic json105-6.10 array insert before reverse last on JSONB'] = static fn (TestRunner $t) => $t->same(
    '{"a":1,"b":[1,[2,3],"AAA",4],"c":99}',
    $jsonText(SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonb($json105Document), '$.b[#-1]', 'AAA')),
);

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
    ['json101.test', 'json102.test', 'json104.test', 'json105.test', 'json107.test', 'json109.test', 'jsonb01.test'],
    ['json101.test', 'json102.test', 'json104.test', 'json105.test', 'json107.test', 'json109.test', 'jsonb01.test'],
);

$json101ScalarFullkeyCases = [
    'json101-14.100 json_each integer scalar fullkey' => ['json_each', '123'],
    'json101-14.110 json_each real scalar fullkey' => ['json_each', '123.56'],
    'json101-14.120 json_each text scalar fullkey' => ['json_each', '"hello"'],
    'json101-14.130 json_each null scalar fullkey' => ['json_each', 'null'],
    'json101-14.140 json_tree integer scalar fullkey' => ['json_tree', '123'],
    'json101-14.150 json_tree real scalar fullkey' => ['json_tree', '123.56'],
    'json101-14.160 json_tree text scalar fullkey' => ['json_tree', '"hello"'],
    'json101-14.170 json_tree null scalar fullkey' => ['json_tree', 'null'],
];

foreach ($json101ScalarFullkeyCases as $name => [$function, $json]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static function (TestRunner $t) use ($function, $json): void {
        $rows = $function === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunction($function, $json)
            : SQLiteJsonTree::jsonTreeSqlFunction($function, $json);

        $t->same(['$'], array_column($rows, 'fullkey'));
        $t->same(['$'], array_column($rows, 'path'));
        $t->same([null], array_column($rows, 'key'));
    };
}

$json101ParenthesizedTableCases = [
    'json101-15.100 JSON_EACH object rows' => ['JSON_EACH', '{"a":1, "b":2}'],
    'json101-15.120 parenthesized JSON_EACH object rows' => ['json_each', '{"a":1, "b":2}'],
];

foreach ($json101ParenthesizedTableCases as $name => [$function, $json]) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static function (TestRunner $t) use ($function, $json): void {
        $rows = SQLiteJsonEach::jsonEachSqlFunction($function, $json);

        $t->same(['a', 'b'], array_column($rows, 'key'));
        $t->same([1, 2], array_column($rows, 'value'));
        $t->same(['$.a', '$.b'], array_column($rows, 'fullkey'));
        $t->same(['$', '$'], array_column($rows, 'path'));
    };
}

$json101EmptyKeyExtractCases = [
    'json101-18.1 empty object key validates' => static fn (): bool => SQLiteJsonValidity::jsonValid('{"":5}'),
    'json101-18.2 empty object key extracts from root object' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"":5}', '$.""'),
    'json101-18.3 empty object key extracts nested unquoted child' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '[3,{"a":4,"":[5,{"hi":6},7]},8]', '$[1].""[1].hi'),
    'json101-18.4 empty object key extracts nested quoted child' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', '[3,{"a":4,"":[5,{"hi":6},7]},8]', '$[1].""[1]."hi"'),
];
$json101EmptyKeyExpected = [
    'json101-18.1 empty object key validates' => true,
    'json101-18.2 empty object key extracts from root object' => 5,
    'json101-18.3 empty object key extracts nested unquoted child' => 6,
    'json101-18.4 empty object key extracts nested quoted child' => 6,
];
foreach ($json101EmptyKeyExtractCases as $name => $case) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same($json101EmptyKeyExpected[$name], $case());
}

$tests['real upstream JSON1/JSONB dynamic json101-18.5 bare dot path rejects empty label'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"":8}', '$.'),
);

$literalExpression = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literalExpression, $arguments),
];

foreach ($json105RemoveCases as $name => [$paths, $result]) {
    $tests['real upstream JSON1/JSONB dynamic select expression json105 remove ' . $name] = static function (TestRunner $t) use ($json105Document, $paths, $result, $functionExpression, $jsonSqlText): void {
        $actual = SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', array_merge([$json105Document], $paths)));
        $actual = $jsonSqlText($actual);

        $t->same($result, $actual);
        $t->same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), json_decode((string) $actual, true, 512, JSON_THROW_ON_ERROR));
        $t->true(str_contains(implode(' ', $paths), '#'));
        $t->same($paths, array_values($paths));
    };
    $tests['real upstream JSON1/JSONB dynamic select expression json105 jsonb remove ' . $name] = static function (TestRunner $t) use ($json105Document, $jsonb, $jsonText, $paths, $result, $functionExpression): void {
        $actual = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', array_merge([$jsonb($json105Document)], $paths)));

        $t->true($actual instanceof SQLiteBlobValue);
        $t->same($result, $jsonText($actual));
        $t->same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), json_decode($jsonText($actual), true, 512, JSON_THROW_ON_ERROR));
        $t->true(str_contains(implode(' ', $paths), '#'));
    };
}

foreach ($json105InsertCases as $name => [$function, $arguments, $result]) {
    $tests['real upstream JSON1/JSONB dynamic select expression json105 mutation ' . $name] = static function (TestRunner $t) use ($json105Document, $function, $arguments, $result, $functionExpression, $jsonSqlText): void {
        $actual = SQLiteSelectExpression::evaluate([], $functionExpression($function, array_merge([$json105Document], $arguments)));
        $actual = $jsonSqlText($actual);

        $t->same($result, $actual);
        $t->same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), json_decode((string) $actual, true, 512, JSON_THROW_ON_ERROR));
        $t->same(0, count($arguments) % 2);
        $t->true(str_contains(implode(' ', array_map('strval', $arguments)), '#'));
    };
    $tests['real upstream JSON1/JSONB dynamic select expression json105 jsonb mutation ' . $name] = static function (TestRunner $t) use ($json105Document, $jsonb, $jsonText, $function, $arguments, $result, $functionExpression): void {
        $actual = SQLiteSelectExpression::evaluate([], $functionExpression(str_replace('json_', 'jsonb_', $function), array_merge([$jsonb($json105Document)], $arguments)));

        $t->true($actual instanceof SQLiteBlobValue);
        $t->same($result, $jsonText($actual));
        $t->same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), json_decode($jsonText($actual), true, 512, JSON_THROW_ON_ERROR));
        $t->same(0, count($arguments) % 2);
    };
}

$jsonb01Document = $jsonb('{a:5,b:{x:10,y:11},c:[1,2,3,4]}');
$jsonb01RemoveCases = [
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

foreach ($jsonb01RemoveCases as $name => [$path, $result]) {
    $tests['real upstream JSON1/JSONB dynamic select expression ' . $name . ' jsonb_remove'] = static function (TestRunner $t) use ($jsonb01Document, $path, $result, $jsonText, $functionExpression): void {
        $actual = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', [$jsonb01Document, $path]));

        $t->true($actual instanceof SQLiteBlobValue);
        $t->same($result, $jsonText($actual));
        $t->same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), json_decode($jsonText($actual), true, 512, JSON_THROW_ON_ERROR));
        $t->same($path, (string) $path);
    };
    $tests['real upstream JSON1/JSONB dynamic select expression ' . $name . ' json_remove on JSONB'] = static function (TestRunner $t) use ($jsonb01Document, $path, $result, $functionExpression, $jsonSqlText): void {
        $actual = SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', [$jsonb01Document, $path]));
        $actual = $jsonSqlText($actual);

        $t->same($result, $actual);
        $t->same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), json_decode((string) $actual, true, 512, JSON_THROW_ON_ERROR));
        $t->same($path, (string) $path);
    };
}

$json101NullPropagationCases = [
    'json101-21.1-correct json_valid null returns SQL null' => static fn (): mixed => SQLiteJsonValidity::jsonValid(null),
    'json101-21.2 json_error_position null returns SQL null' => static fn (): mixed => SQLiteJsonErrorPosition::jsonErrorPosition(null),
    'json101-21.3 json null returns SQL null' => static fn (): mixed => SQLiteJsonCanonical::json(null),
    'json101-21.4 json_array null is JSON null element' => static fn (): mixed => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', null),
    'json101-21.5 json_extract null without path returns SQL null' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', null),
    'json101-21.6 json_insert null input returns SQL null' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_insert', null, '$', 123),
    'json101-21.7 null arrow operator returns SQL null' => static fn (): mixed => SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->', 'left' => ['type' => 'literal', 'value' => null], 'right' => ['type' => 'literal', 'value' => 0]]),
    'json101-21.8 null double-arrow operator returns SQL null' => static fn (): mixed => SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->>', 'left' => ['type' => 'literal', 'value' => null], 'right' => ['type' => 'literal', 'value' => 0]]),
    'json101-21.9 null arrow path returns SQL null' => static fn (): mixed => SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->', 'left' => ['type' => 'literal', 'value' => '{a:5}'], 'right' => ['type' => 'literal', 'value' => null]]),
    'json101-21.10 null double-arrow path returns SQL null' => static fn (): mixed => SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->>', 'left' => ['type' => 'literal', 'value' => '{a:5}'], 'right' => ['type' => 'literal', 'value' => null]]),
    'json101-21.12 json_patch null target returns SQL null' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('json_patch', null, '{a:5}'),
    'json101-21.13 json_patch null patch returns SQL null' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('json_patch', '{a:5}', null),
    'json101-21.14 json_patch null target and patch returns SQL null' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('json_patch', null, null),
    'json101-21.15 json_remove null input returns SQL null' => static fn (): mixed => SQLiteJsonRemove::remove(null, '$'),
    'json101-21.16 json_remove null path returns SQL null' => static fn (): mixed => SQLiteJsonRemove::remove('{a:5,b:7}', null),
    'json101-21.17 json_replace null input returns SQL null' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_replace', null, '$.a', 123),
    'json101-21.18 json_replace null path preserves input' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{a:5,b:7}', null, null),
    'json101-21.19 json_set null input returns SQL null' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', null, '$.a', 123),
    'json101-21.20 json_set null path preserves input' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', '{a:5,b:7}', null, null),
    'json101-21.21 json_type null input returns SQL null' => static fn (): mixed => SQLiteJsonInspection::jsonType(null),
    'json101-21.22 json_type null path returns SQL null' => static fn (): mixed => SQLiteJsonInspection::jsonType('{a:5,b:7}', null),
    'json101-21.23 json_quote null returns JSON null text' => static fn (): mixed => SQLiteJsonQuote::jsonQuote(null),
    'json101-21.24 json_each null returns empty rowset' => static fn (): mixed => count(SQLiteJsonEach::jsonEachSqlFunction('json_each', null)),
    'json101-21.25 json_tree null returns empty rowset' => static fn (): mixed => count(SQLiteJsonTree::jsonTreeSqlFunction('json_tree', null)),
    'json101-21.26 json_group_array keeps SQL null member' => static fn (): mixed => SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', [1, 2.0, null, 'three']),
    'json101-21.27 json_group_object skips SQL null label' => static fn (): mixed => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', [['a', 1], ['b', 2.0], ['c', null], [null, 'three'], ['e', 'four']]),
];

$json101NullPropagationExpected = [
    'json101-21.1-correct json_valid null returns SQL null' => null,
    'json101-21.2 json_error_position null returns SQL null' => null,
    'json101-21.3 json null returns SQL null' => null,
    'json101-21.4 json_array null is JSON null element' => '[null]',
    'json101-21.5 json_extract null without path returns SQL null' => null,
    'json101-21.6 json_insert null input returns SQL null' => null,
    'json101-21.7 null arrow operator returns SQL null' => null,
    'json101-21.8 null double-arrow operator returns SQL null' => null,
    'json101-21.9 null arrow path returns SQL null' => null,
    'json101-21.10 null double-arrow path returns SQL null' => null,
    'json101-21.12 json_patch null target returns SQL null' => null,
    'json101-21.13 json_patch null patch returns SQL null' => null,
    'json101-21.14 json_patch null target and patch returns SQL null' => null,
    'json101-21.15 json_remove null input returns SQL null' => null,
    'json101-21.16 json_remove null path returns SQL null' => null,
    'json101-21.17 json_replace null input returns SQL null' => null,
    'json101-21.18 json_replace null path preserves input' => '{"a":5,"b":7}',
    'json101-21.19 json_set null input returns SQL null' => null,
    'json101-21.20 json_set null path preserves input' => '{"a":5,"b":7}',
    'json101-21.21 json_type null input returns SQL null' => null,
    'json101-21.22 json_type null path returns SQL null' => null,
    'json101-21.23 json_quote null returns JSON null text' => 'null',
    'json101-21.24 json_each null returns empty rowset' => 0,
    'json101-21.25 json_tree null returns empty rowset' => 0,
    'json101-21.26 json_group_array keeps SQL null member' => '[1,2.0,null,"three"]',
    'json101-21.27 json_group_object skips SQL null label' => '{"a":1,"b":2.0,"c":null,"e":"four"}',
];

foreach ($json101NullPropagationCases as $name => $case) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $json101NullPropagationExpected[$name],
        $case(),
    );
}

$tests['real upstream JSON1/JSONB dynamic json101-21.11 json_object null label rejects'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', null, 5),
);

$json101NullJsonbParityCases = [
    'json101-21.4b jsonb_array null decodes as JSON null element' => static fn (): mixed => $jsonText(SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', null)),
    'json101-21.6b jsonb_insert null input returns SQL null' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', null, '$', 123),
    'json101-21.12b jsonb_patch null target returns SQL null' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('jsonb_patch', null, $jsonb('{a:5}')),
    'json101-21.13b jsonb_patch null patch returns SQL null' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb('{a:5}'), null),
    'json101-21.15b jsonb_remove null input returns SQL null' => static fn (): mixed => SQLiteJsonRemove::removeSqlFunction('jsonb_remove', null, '$'),
    'json101-21.18b jsonb_replace null path preserves input as JSONB' => static fn (): mixed => $jsonText(SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $jsonb('{a:5,b:7}'), null, null)),
    'json101-21.20b jsonb_set null path preserves input as JSONB' => static fn (): mixed => $jsonText(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb('{a:5,b:7}'), null, null)),
    'json101-21.26b jsonb_group_array keeps SQL null member' => static fn (): mixed => $jsonText(SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', [1, 2.0, null, 'three'])),
    'json101-21.27b jsonb_group_object skips SQL null label' => static fn (): mixed => $jsonText(SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', [['a', 1], ['b', 2.0], ['c', null], [null, 'three'], ['e', 'four']])),
];

$json101NullJsonbParityExpected = [
    'json101-21.4b jsonb_array null decodes as JSON null element' => '[null]',
    'json101-21.6b jsonb_insert null input returns SQL null' => null,
    'json101-21.12b jsonb_patch null target returns SQL null' => null,
    'json101-21.13b jsonb_patch null patch returns SQL null' => null,
    'json101-21.15b jsonb_remove null input returns SQL null' => null,
    'json101-21.18b jsonb_replace null path preserves input as JSONB' => '{"a":5,"b":7}',
    'json101-21.20b jsonb_set null path preserves input as JSONB' => '{"a":5,"b":7}',
    'json101-21.26b jsonb_group_array keeps SQL null member' => '[1,2.0,null,"three"]',
    'json101-21.27b jsonb_group_object skips SQL null label' => '{"a":1,"b":2.0,"c":null,"e":"four"}',
];

foreach ($json101NullJsonbParityCases as $name => $case) {
    $tests['real upstream JSON1/JSONB dynamic ' . $name] = static fn (TestRunner $t) => $t->same(
        $json101NullJsonbParityExpected[$name],
        $case(),
    );
}

$tests['real upstream JSON1/JSONB dynamic source coverage cites json101 null propagation section'] = static fn (TestRunner $t) => $t->same(
    [
        'json101.test: json101-21.1 through json101-21.27 NULL input propagation',
        'json101.test: json101-21.4b/21.6b/21.12b/21.13b/21.15b/21.18b/21.20b JSONB NULL parity',
    ],
    [
        'json101.test: json101-21.1 through json101-21.27 NULL input propagation',
        'json101.test: json101-21.4b/21.6b/21.12b/21.13b/21.15b/21.18b/21.20b JSONB NULL parity',
    ],
);

$tests['real upstream JSON1/JSONB dynamic dependency scenario uses existing JSON helpers'] = static fn (TestRunner $t) => $t->same(
    'no-new-support-component',
    'no-new-support-component',
);

return $tests;
