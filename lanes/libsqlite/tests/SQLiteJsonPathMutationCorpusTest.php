<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$decodeText = static function (string $json): mixed {
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
};

$decodedMutation = static function (string $function, string|SQLiteBlobValue|null $json, string $path, mixed $value, mixed ...$extra): mixed {
    $result = SQLiteJsonMutation::mutateSqlFunction($function, $json, $path, $value, ...$extra);
    if ($result === null) {
        return null;
    }
    if ($result instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($result->bytes);
    }

    return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
};

$jsonbInput = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => false,
        'settings' => [
            'thresholds' => [10, 20],
            'features' => [
                ['name' => 'cache', 'enabled' => true],
                ['name' => 'seo', 'enabled' => false],
            ],
        ],
    ],
]));

$textCases = [
    'set object member replaces scalar' => ['json_set', '{"a":1}', '$.a', 2, ['a' => 2]],
    'set missing object member creates it' => ['json_set', '{"a":1}', '$.b', 2, ['a' => 1, 'b' => 2]],
    'set missing nested object creates path' => ['json_set', '{"a":1}', '$.b.c', 2, ['a' => 1, 'b' => ['c' => 2]]],
    'set missing nested array creates path' => ['json_set', '{"a":1}', '$.b[0].c', 2, ['a' => 1, 'b' => [['c' => 2]]]],
    'set root replaces document' => ['json_set', '{"a":1}', '$', new SQLiteJsonSubtypeValue('{"b":2}'), ['b' => 2]],
    'set array index replaces element' => ['json_set', '[1,2,3]', '$[1]', 20, [1, 20, 3]],
    'set array append adds element' => ['json_set', '[1,2]', '$[#]', 3, [1, 2, 3]],
    'set reverse index replaces last' => ['json_set', '[1,2,3]', '$[#-1]', 30, [1, 2, 30]],
    'set reverse zero appends' => ['json_set', '[1,2]', '$[#-0]', 3, [1, 2, 3]],
    'set out of range positive array path is unchanged' => ['json_set', '[1,2]', '$[4]', 9, [1, 2]],
    'set out of range reverse array path is unchanged' => ['json_set', '[1,2]', '$[#-3]', 9, [1, 2]],
    'set missing through scalar is unchanged' => ['json_set', '{"a":1}', '$.a.b', 2, ['a' => 1]],
    'set quoted object label' => ['json_set', '{"a.b":1}', '$."a.b"', 2, ['a.b' => 2]],
    'set quoted label containing bracket' => ['json_set', '{"a[0]":1}', '$."a[0]"', 2, ['a[0]' => 2]],
    'set quoted escaped quote label' => ['json_set', '{"a\\"b":1}', '$."a\\"b"', 2, ['a"b' => 2]],
    'set bare unicode escape label' => ['json_set', '{"café":1}', '$.caf\\u00e9', 2, ['café' => 2]],
    'set multiple pairs left to right' => ['json_set', '{"a":{"b":1},"x":[1]}', '$.a.b', 2, ['a' => ['b' => 2, 'c' => 3], 'x' => [1, 4]], '$.a.c', 3, '$.x[#]', 4],
    'set later path sees earlier created object' => ['json_set', '{"z":0}', '$.a', new SQLiteJsonSubtypeValue('{"seed":0}'), ['z' => 0, 'a' => ['seed' => 0, 'b' => 2]], '$.a.b', 2],
    'set later path sees earlier created array' => ['json_set', '{"z":0}', '$.a', new SQLiteJsonSubtypeValue('[]'), ['z' => 0, 'a' => [1, 2]], '$.a[#]', 1, '$.a[#]', 2],
    'set SQL scalar text stays text' => ['json_set', '{"z":0}', '$.a', '{"raw":true}', ['z' => 0, 'a' => '{"raw":true}']],
    'set JSON subtype object becomes JSON' => ['json_set', '{"z":0}', '$.a', new SQLiteJsonSubtypeValue('{"raw":true}'), ['z' => 0, 'a' => ['raw' => true]]],
    'set JSON subtype array becomes JSON' => ['json_set', '{"z":0}', '$.a', new SQLiteJsonSubtypeValue('[1,2]'), ['z' => 0, 'a' => [1, 2]]],
    'set JSONB blob replacement becomes JSON' => ['json_set', '{"z":0}', '$.a', new SQLiteBlobValue(SQLiteJsonB::encode(['raw' => true])), ['z' => 0, 'a' => ['raw' => true]]],
    'set JSON5 input accepted' => ['json_set', '{a:1,}', '$.b', 2, ['a' => 1, 'b' => 2]],
    'set JSON5 subtype value accepted' => ['json_set', '{"z":0}', '$.a', new SQLiteJsonSubtypeValue('{raw:true,}'), ['z' => 0, 'a' => ['raw' => true]]],
    'insert missing object member' => ['json_insert', '{"a":1}', '$.b', 2, ['a' => 1, 'b' => 2]],
    'insert existing object member is no op' => ['json_insert', '{"a":1}', '$.a', 2, ['a' => 1]],
    'insert root is no op' => ['json_insert', '{"a":1}', '$', new SQLiteJsonSubtypeValue('{"b":2}'), ['a' => 1]],
    'insert array append adds element' => ['json_insert', '[1,2]', '$[#]', 3, [1, 2, 3]],
    'insert existing array index is no op' => ['json_insert', '[1,2]', '$[0]', 9, [1, 2]],
    'insert reverse existing index is no op' => ['json_insert', '[1,2]', '$[#-1]', 9, [1, 2]],
    'insert missing nested object path creates it' => ['json_insert', '{"z":0}', '$.a.b', 2, ['z' => 0, 'a' => ['b' => 2]]],
    'insert missing nested array path creates it' => ['json_insert', '{"z":0}', '$.a[0]', 2, ['z' => 0, 'a' => [2]]],
    'insert multiple pairs preserves first write' => ['json_insert', '{"z":0}', '$.a', 1, ['z' => 0, 'a' => 1, 'b' => 3], '$.a', 2, '$.b', 3],
    'insert through scalar is unchanged' => ['json_insert', '{"a":1}', '$.a.b', 2, ['a' => 1]],
    'replace existing object member' => ['json_replace', '{"a":1}', '$.a', 2, ['a' => 2]],
    'replace missing object member is no op' => ['json_replace', '{"a":1}', '$.b', 2, ['a' => 1]],
    'replace root replaces document' => ['json_replace', '{"a":1}', '$', new SQLiteJsonSubtypeValue('{"b":2}'), ['b' => 2]],
    'replace existing array index' => ['json_replace', '[1,2,3]', '$[1]', 20, [1, 20, 3]],
    'replace append path is no op' => ['json_replace', '[1,2]', '$[#]', 3, [1, 2]],
    'replace reverse index' => ['json_replace', '[1,2,3]', '$[#-2]', 20, [1, 20, 3]],
    'replace missing nested path is no op' => ['json_replace', '{}', '$.a.b', 2, []],
    'replace multiple pairs only existing paths' => ['json_replace', '{"a":1,"b":2}', '$.a', 10, ['a' => 10, 'b' => 2], '$.c', 30],
    'replace later path sees earlier replacement' => ['json_replace', '{"a":{"b":1}}', '$.a', new SQLiteJsonSubtypeValue('{"b":2,"c":3}'), ['a' => ['b' => 4, 'c' => 3]], '$.a.b', 4],
];

foreach ($textCases as $name => $case) {
    $tests['json path mutation corpus text ' . $name] = static function (TestRunner $t) use ($case, $decodedMutation): void {
        [$function, $json, $path, $value, $expected] = $case;
        $extra = array_slice($case, 5);
        $t->same($expected, $decodedMutation($function, $json, $path, $value, ...$extra));
    };
}

$jsonbCases = [
    'jsonb set nested boolean' => ['jsonb_set', $jsonbInput, '$.plugin.enabled', true, ['plugin' => ['enabled' => true, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => false]]]]]],
    'jsonb set nested array append' => ['jsonb_set', $jsonbInput, '$.plugin.settings.thresholds[#]', 30, ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20, 30], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => false]]]]]],
    'jsonb set reverse nested object field' => ['jsonb_set', $jsonbInput, '$.plugin.settings.features[#-1].enabled', true, ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => true]]]]]],
    'jsonb insert existing nested field no op' => ['jsonb_insert', $jsonbInput, '$.plugin.settings.features[0].name', 'cdn', ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => false]]]]]],
    'jsonb insert nested missing field' => ['jsonb_insert', $jsonbInput, '$.plugin.settings.features[0].ttl', 300, ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true, 'ttl' => 300], ['name' => 'seo', 'enabled' => false]]]]]],
    'jsonb replace nested existing field' => ['jsonb_replace', $jsonbInput, '$.plugin.settings.features[1].name', 'search', ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'search', 'enabled' => false]]]]]],
    'jsonb replace missing nested field no op' => ['jsonb_replace', $jsonbInput, '$.plugin.settings.features[1].ttl', 300, ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => false]]]]]],
    'jsonb multiple pair mutation' => ['jsonb_set', $jsonbInput, '$.plugin.settings.features[0].enabled', false, ['plugin' => ['enabled' => false, 'settings' => ['thresholds' => [10, 20, 40], 'features' => [['name' => 'cache', 'enabled' => false], ['name' => 'seo', 'enabled' => false, 'ttl' => 120]]]]], '$.plugin.settings.features[1].ttl', 120, '$.plugin.settings.thresholds[#]', 40],
];

foreach ($jsonbCases as $name => $case) {
    $tests['json path mutation corpus jsonb ' . $name] = static function (TestRunner $t) use ($case, $decodedMutation): void {
        [$function, $json, $path, $value, $expected] = $case;
        $extra = array_slice($case, 5);
        $t->same($expected, $decodedMutation($function, $json, $path, $value, ...$extra));
    };
}

$boundaryCases = [
    'null input returns null' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', null, '$.a', 1),
    'mixed case text function dispatch' => static fn (): mixed => $decodedMutation('Json_Set', '{"a":1}', '$.a', 2),
    'mixed case jsonb function dispatch' => static fn (): mixed => $decodedMutation('JsonB_Set', $jsonbInput, '$.plugin.enabled', true),
    'argument vector dispatch text' => static fn (): mixed => $decodeText((string) SQLiteJsonMutation::mutateSqlFunctionArguments('JSON_SET', ['{"a":1}', '$.a', 2])),
    'argument vector dispatch jsonb' => static fn (): mixed => SQLiteJsonB::decode(SQLiteJsonMutation::mutateSqlFunctionArguments('JSONB_SET', [$jsonbInput, '$.plugin.enabled', true])->bytes),
    'text result is canonical json' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', "{a:1, b:[2,],}", '$.b[#]', 3),
    'jsonb result is blob value' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('jsonb_set', '{"a":1}', '$.a', 2) instanceof SQLiteBlobValue,
];

$boundaryExpected = [
    'null input returns null' => null,
    'mixed case text function dispatch' => ['a' => 2],
    'mixed case jsonb function dispatch' => ['plugin' => ['enabled' => true, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => false]]]]],
    'argument vector dispatch text' => ['a' => 2],
    'argument vector dispatch jsonb' => ['plugin' => ['enabled' => true, 'settings' => ['thresholds' => [10, 20], 'features' => [['name' => 'cache', 'enabled' => true], ['name' => 'seo', 'enabled' => false]]]]],
    'text result is canonical json' => '{"a":1,"b":[2,3]}',
    'jsonb result is blob value' => true,
];

foreach ($boundaryCases as $name => $callback) {
    $tests['json path mutation corpus boundary ' . $name] = static function (TestRunner $t) use ($callback, $boundaryExpected, $name): void {
        $t->same($boundaryExpected[$name], $callback());
    };
}

$throwsCases = [
    'unknown function rejected' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_merge', '{}', '$.a', 1),
    'path must start with dollar' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '.a', 1),
    'unterminated quoted path rejected' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '$."a', 1),
    'malformed reverse index rejected' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', '[]', '$[#-]', 1),
    'raw blob replacement rejected' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '$.a', new SQLiteBlobValue('raw')),
    'argument vector rejects non string path' => static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', ['{}', 1, 2]),
    'argument vector rejects even argument count' => static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', ['{}', '$.a']),
    'argument vector rejects object input' => static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [[], '$.a', 1]),
    'extra path must be string' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '$.a', 1, 2, 3),
];

foreach ($throwsCases as $name => $callback) {
    $tests['json path mutation corpus throws ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
