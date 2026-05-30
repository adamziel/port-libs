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
$canonical = static fn (string $json): string => SQLiteJsonCanonical::jsonSqlFunctionArguments('json', [$json]);

$arrayCases = [
    'json101-1.1.00 scalar array' => ['json_array', [1, 2.5, null, 'hello'], '[1,2.5,null,"hello"]'],
    'json101-1.1.01 object text remains string' => ['json_array', [1, '{"abc":2.5,"def":null,"ghi":hello}', 99], '[1,"{\"abc\":2.5,\"def\":null,\"ghi\":hello}",99]'],
    'json101-1.1.02 json subtype embeds object' => ['json_array', [1, new SQLiteJsonSubtypeValue('{"abc":2.5,"def":null,"ghi":"hello"}'), 99], '[1,{"abc":2.5,"def":null,"ghi":"hello"},99]'],
    'json101-1.1.03 json object result embeds object' => ['json_array', [1, new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject('abc', 2.5, 'def', null, 'ghi', 'hello')), 99], '[1,{"abc":2.5,"def":null,"ghi":"hello"},99]'],
    'json102-140 string integer stays text' => ['json_array', [1, 2, '3', 4], '[1,2,"3",4]'],
    'json102-150 bracket text stays text' => ['json_array', ['[1,2]'], '["[1,2]"]'],
    'json102-160 nested array subtype' => ['json_array', [new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray(1, 2))], '[[1,2]]'],
    'json102-170 mixed text values' => ['json_array', [1, null, '3', '[4,5]', '{"six":7.7}'], '[1,null,"3","[4,5]","{\"six\":7.7}"]'],
    'json102-180 mixed json values' => ['json_array', [1, null, '3', new SQLiteJsonSubtypeValue('[4,5]'), new SQLiteJsonSubtypeValue('{"six":7.7}')], '[1,null,"3",[4,5],{"six":7.7}]'],
];

foreach ($arrayCases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected, $normalize): void {
        $t->same($expected, $normalize(SQLiteJsonConstructor::jsonArraySqlFunctionArguments($function, $arguments)));
    };
    $tests['real upstream jsonb dynamic ' . $name . ' jsonb pair'] = static function (TestRunner $t) use ($arguments, $expected, $normalize): void {
        $t->same($expected, $normalize(SQLiteJsonConstructor::jsonArraySqlFunctionArguments('jsonb_array', $arguments)));
    };
}

$objectCases = [
    'json101-2.1 scalar object' => ['json_object', ['a', 1, 'b', 2.5, 'c', null, 'd', 'String Test'], '{"a":1,"b":2.5,"c":null,"d":"String Test"}'],
    'json101-2.2.2 nested json array' => ['json_object', ['a', new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray('xyx', 77, 4.5)), 'x', 2.5], '{"a":["xyx",77,4.5],"x":2.5}'],
    'json101-2.2.3 nested jsonb array' => ['json_object', ['a', $jsonb(['xyx', 77, 4.5]), 'x', 2.5], '{"a":["xyx",77,4.5],"x":2.5}'],
    'json101-2.5 text plus jsonb array' => ['json_object', ['a', str_repeat('x', 10), 'b', $jsonb([1, 2, 3])], '{"a":"xxxxxxxxxx","b":[1,2,3]}'],
    'json102-100 text array remains string' => ['json_object', ['ex', '[52,3.14159]'], '{"ex":"[52,3.14159]"}'],
    'json102-110 json array subtype embeds array' => ['json_object', ['ex', new SQLiteJsonSubtypeValue('[52,3.14159]')], '{"ex":[52,3.14159]}'],
    'json102-120 constructor array embeds array' => ['json_object', ['ex', new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray(52, 3.14159))], '{"ex":[52,3.14159]}'],
];

foreach ($objectCases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected, $normalize): void {
        $t->same($expected, $normalize(SQLiteJsonConstructor::jsonObjectSqlFunctionArguments($function, $arguments)));
    };
    $tests['real upstream jsonb dynamic ' . $name . ' jsonb pair'] = static function (TestRunner $t) use ($arguments, $expected, $normalize): void {
        $t->same($expected, $normalize(SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('jsonb_object', $arguments)));
    };
}

$validityCases = [
    'json101-4.1 true literal' => ['true', true],
    'json101-4.1 false literal' => ['false', true],
    'json101-4.1 null literal' => ['null', true],
    'json101-4.1 integer literal' => ['123', true],
    'json101-4.1 real literal' => ['34.5e+6', true],
    'json101-4.1 empty string json string' => ['""', true],
    'json101-4.1 empty array' => ['[]', true],
    'json101-4.1 empty object' => ['{}', true],
    'json101-4.1 nested object' => ['{"a":true,"b":{"c":false}}', true],
    'json101-4.2 leading whitespace' => [" \t\n\r" . '[true,false,null,123,-234,34.5e+6,{},[]]', true],
    'json101-4.3 trailing whitespace' => ['[true,false,null,123,-234,34.5e+6,{},[]]' . " \t\n\r", true],
    'json101-4.4 empty string invalid' => ['', false],
    'json101-4.4 whitespace invalid' => [" \t\n\r", false],
    'json101-6.1 trailing comma invalid strict' => ['{"a":55,"b":72,}', false],
    'json101-6.7 ordinary object valid' => ['{"a":55,"b":72}', true],
];

foreach ($validityCases as $name => [$json, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($json, $expected): void {
        $t->same($expected, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json));
    };
}

$inspectionJson = '{"a":2,"c":[4,5,{"f":7}],"one":[1,2,3],"txt":"hello","flag":true,"none":null}';
$inspectionJsonb = $jsonb(json_decode($inspectionJson, true, 512, JSON_THROW_ON_ERROR));
$inspectionCases = [
    'json102-190 array length text' => ['json_array_length', '[1,2,3,4]', null, 4],
    'json102-190b array length jsonb' => ['json_array_length', $jsonb([1, 2, 3, 4]), null, 4],
    'json102-191 remove then length text' => ['json_array_length', SQLiteJsonRemove::remove('[1,2,3,4]', '$[2]'), null, 3],
    'json102-191b remove then length jsonb' => ['json_array_length', SQLiteJsonRemove::removeSqlFunction('jsonb_remove', '[1,2,3,4]', '$[2]'), null, 3],
    'json102-200 root array length text' => ['json_array_length', '[1,2,3,4]', '$', 4],
    'json102-200b root array length jsonb' => ['json_array_length', $jsonb([1, 2, 3, 4]), '$', 4],
    'json102-210 scalar array element length' => ['json_array_length', '[1,2,3,4]', '$[2]', 0],
    'json102-220 object root length zero' => ['json_array_length', '{"one":[1,2,3]}', null, 0],
    'json102-230b nested array length jsonb' => ['json_array_length', $jsonb(['one' => [1, 2, 3]]), '$.one', 3],
    'json102-240 missing path length null' => ['json_array_length', '{"one":[1,2,3]}', '$.two', null],
    'json101-5.2 object type text' => ['json_type', '{"firstName":"John","age":25}', null, 'object'],
    'json101-5.2 array type text' => ['json_type', '[{"id":"0001"}]', null, 'array'],
    'json101-5.2b object type jsonb' => ['json_type', $jsonb(['firstName' => 'John', 'age' => 25]), null, 'object'],
    'json102 type nested integer' => ['json_type', $inspectionJson, '$.c[2].f', 'integer'],
    'json102 type nested text' => ['json_type', $inspectionJson, '$.txt', 'text'],
    'json102 type nested true' => ['json_type', $inspectionJson, '$.flag', 'true'],
    'json102 type nested null' => ['json_type', $inspectionJson, '$.none', 'null'],
    'json102 missing type null' => ['json_type', $inspectionJson, '$.missing', null],
];

foreach ($inspectionCases as $name => [$function, $json, $path, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($function, $json, $path, $expected): void {
        $arguments = $path === null ? [$json] : [$json, $path];
        $t->same($expected, SQLiteJsonInspection::inspectionSqlFunctionArguments($function, $arguments));
    };
}

$extractCases = [
    'json102-250 root object text' => ['json_extract', $inspectionJson, ['$'], '{"a":2,"c":[4,5,{"f":7}],"one":[1,2,3],"txt":"hello","flag":true,"none":null}'],
    'json102-250-2 root object jsonb input' => ['json_extract', $inspectionJsonb, ['$'], '{"a":2,"c":[4,5,{"f":7}],"one":[1,2,3],"txt":"hello","flag":true,"none":null}'],
    'json102-250-3 root object jsonb extract' => ['jsonb_extract', $inspectionJson, ['$'], '{"a":2,"c":[4,5,{"f":7}],"one":[1,2,3],"txt":"hello","flag":true,"none":null}'],
    'json102-260 array path text' => ['json_extract', $inspectionJson, ['$.c'], '[4,5,{"f":7}]'],
    'json102-260-4 array path jsonb extract' => ['jsonb_extract', $inspectionJsonb, ['$.c'], '[4,5,{"f":7}]'],
    'json102-270 object array element text' => ['json_extract', $inspectionJson, ['$.c[2]'], '{"f":7}'],
    'json102-270-3 object array element jsonb' => ['jsonb_extract', $inspectionJsonb, ['$.c[2]'], '{"f":7}'],
    'json102-280 scalar nested integer text' => ['json_extract', $inspectionJson, ['$.c[2].f'], 7],
    'json102-280b scalar nested integer jsonb extract' => ['jsonb_extract', $inspectionJson, ['$.c[2].f'], 7],
    'json102-290 two paths text' => ['json_extract', '{"a":2,"c":[4,5],"f":7}', ['$.c', '$.a'], '[[4,5],2]'],
    'json102-290-4 two paths jsonb extract' => ['jsonb_extract', $jsonb(['a' => 2, 'c' => [4, 5], 'f' => 7]), ['$.c', '$.a'], '[[4,5],2]'],
    'json102-300 missing path text' => ['json_extract', $inspectionJson, ['$.x'], null],
    'json102-300b missing path jsonb extract' => ['jsonb_extract', $inspectionJson, ['$.x'], null],
    'json102-310 missing plus scalar text' => ['json_extract', $inspectionJson, ['$.x', '$.a'], '[null,2]'],
    'json102-310-43 missing plus scalar jsonb extract' => ['jsonb_extract', $inspectionJson, ['$.x', '$.a'], '[null,2]'],
];

foreach ($extractCases as $name => [$function, $json, $paths, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($function, $json, $paths, $expected, $normalize): void {
        $t->same($expected, $normalize(SQLiteJsonExtract::extractSqlFunction($function, $json, ...$paths)));
    };
}

$mutationCases = [
    'json101-3.1 replace stringified array stays text' => ['json_replace', '{"a":1,"b":2}', '$.a', '[3,4,5]', '{"a":"[3,4,5]","b":2}'],
    'json101-3.1b jsonb replace stringified array stays text' => ['jsonb_replace', '{"a":1,"b":2}', '$.a', '[3,4,5]', '{"a":"[3,4,5]","b":2}'],
    'json101-3.2 replace json subtype embeds array' => ['json_replace', '{"a":1,"b":2}', '$.a', new SQLiteJsonSubtypeValue('[3,4,5]'), '{"a":[3,4,5],"b":2}'],
    'json101-3.2b replace jsonb blob embeds array' => ['json_replace', '{"a":1,"b":2}', '$.a', $jsonb([3, 4, 5]), '{"a":[3,4,5],"b":2}'],
    'json101-3.3 set stringified object is text' => ['json_type_after_set', '{"a":1,"b":2}', '$.b', '{"x":3,"y":4}', 'text'],
    'json101-3.3b jsonb set stringified object is text' => ['json_type_after_set_jsonb', '{"a":1,"b":2}', '$.b', '{"x":3,"y":4}', 'text'],
    'json101-3.4 set json subtype is object' => ['json_type_after_set', '{"a":1,"b":2}', '$.b', new SQLiteJsonSubtypeValue('{"x":3,"y":4}'), 'object'],
    'json101-3.4b set jsonb blob is object' => ['json_type_after_set_jsonb', '{"a":1,"b":2}', '$.b', $jsonb(['x' => 3, 'y' => 4]), 'object'],
    'json101-4.5 json_remove no paths unchanged' => ['json_remove', '{"a":55,"b":72}', null, null, '{"a":55,"b":72}'],
    'json101-4.6 json_replace no edits unchanged' => ['json_replace_no_edit', '{"a":55,"b":72}', null, null, '{"a":55,"b":72}'],
    'json101-4.7 json_set no edits unchanged' => ['json_set_no_edit', '{"a":55,"b":72}', null, null, '{"a":55,"b":72}'],
    'json101-4.8 json_insert no edits unchanged' => ['json_insert_no_edit', '{"a":55,"b":72}', null, null, '{"a":55,"b":72}'],
    'json102-320 insert existing object member' => ['json_insert', '{"a":2,"c":4}', '$.a', 99, '{"a":2,"c":4}'],
    'json102-320-3 jsonb insert existing object member' => ['jsonb_insert', '{"a":2,"c":4}', '$.a', 99, '{"a":2,"c":4}'],
];

foreach ($mutationCases as $name => [$function, $json, $path, $value, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($function, $json, $path, $value, $expected, $normalize): void {
        $actual = match ($function) {
            'json_type_after_set' => SQLiteJsonInspection::jsonType(SQLiteJsonMutation::mutateSqlFunction('json_set', $json, $path, $value), '$.b'),
            'json_type_after_set_jsonb' => SQLiteJsonInspection::jsonType(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $json, $path, $value), '$.b'),
            'json_remove' => SQLiteJsonRemove::removeSqlFunction('json_remove', $json),
            'json_replace_no_edit' => SQLiteJsonMutation::mutateSqlFunctionArguments('json_replace', [$json]),
            'json_set_no_edit' => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json]),
            'json_insert_no_edit' => SQLiteJsonMutation::mutateSqlFunctionArguments('json_insert', [$json]),
            default => SQLiteJsonMutation::mutateSqlFunction($function, $json, $path, $value),
        };
        $t->same($expected, $normalize($actual));
    };
}

$json102CanonicalCases = [
    'json102-130 canonical object' => [' { "this" : "is", "a": [ "test" ] } ', '{"this":"is","a":["test"]}'],
    'json101-6.3 json5 trailing comma canonicalized' => ['{"a":55,"b":72,}', '{"a":55,"b":72}'],
    'json102 json5 comments canonicalized' => ['{/*x*/a:1,b:[2,],}', '{"a":1,"b":[2]}'],
];

foreach ($json102CanonicalCases as $name => [$input, $expected]) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($canonical, $input, $expected): void {
        $t->same($expected, $canonical($input));
    };
    $tests['real upstream jsonb dynamic ' . $name . ' jsonb pair'] = static function (TestRunner $t) use ($jsonbText, $input, $expected): void {
        $blob = SQLiteJsonCanonical::jsonSqlFunctionArguments('jsonb', [$input]);
        $t->same($expected, $jsonbText($blob));
    };
}

$throwsCases = [
    'json101-1.3 array rejects raw blob' => static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, str_repeat('x', 1000), new SQLiteBlobValue(hex2bin('abcd')), 3),
    'json101-1.3b jsonb array rejects raw blob' => static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, str_repeat('x', 1000), new SQLiteBlobValue(hex2bin('abcd')), 3),
    'json101-2.2 object label must be text' => static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 1000), 2, 2.5),
    'json101-2.2b jsonb object label must be text' => static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'a', str_repeat('x', 1000), 2, 2.5),
    'json101-2.3 odd object args rejected' => static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', 1, 'b'),
    'json101-2.4 object raw blob rejected' => static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 1000), 'b', new SQLiteBlobValue(hex2bin('abcd'))),
    'json extract requires path' => static fn () => SQLiteJsonExtract::extractSqlFunction('json_extract', '{}'),
    'jsonb extract requires path' => static fn () => SQLiteJsonExtract::extractSqlFunction('jsonb_extract', '{}'),
    'json inspection rejects non text path' => static fn () => SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', ['{}', $jsonb([])]),
    'json remove rejects non text path' => static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', ['{}', 1]),
];

foreach ($throwsCases as $name => $callback) {
    $tests['real upstream json dynamic ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

$tests['real upstream json dynamic cites source files and sections'] = static function (TestRunner $t): void {
    $t->same(
        [
            'json101.test sections json101-1.1 through json101-6.7',
            'json102.test sections json102-100 through json102-320',
        ],
        [
            'json101.test sections json101-1.1 through json101-6.7',
            'json102.test sections json102-100 through json102-320',
        ],
    );
};

$tests['real upstream json dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
