<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$normalize = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }

    return $value;
};

$tests = [];

$jsonbSettings = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'modes' => ['sync', 'cache'],
        'ttl' => 300,
    ],
]));

$canonicalCases = [
    'canonical json5 object labels and trailing commas' => ['json', ['{plugin:{enabled:true,modes:["sync","cache",],},}'], '{"plugin":{"enabled":true,"modes":["sync","cache"]}}'],
    'canonical single quoted strings' => ['json', ["{'plugin':'canary','quote':'it\\'s ok'}"], '{"plugin":"canary","quote":"it\'s ok"}'],
    'canonical comments and whitespace' => ['json', ["{/*a*/plugin://b\n{ttl:0x20, enabled:false}}"], '{"plugin":{"ttl":32,"enabled":false}}'],
    'canonical plus infinity spelling' => ['json', ['{value:+Infinity}'], '{"value":9e999}'],
    'canonical negative infinity spelling' => ['json', ['{value:-Infinity}'], '{"value":-9e999}'],
    'canonical nan spelling to null' => ['json', ['{value:NaN}'], '{"value":null}'],
    'canonical qnan spelling to null' => ['json', ['{value:QNaN}'], '{"value":null}'],
    'canonical snan spelling to null' => ['json', ['{value:SNaN}'], '{"value":null}'],
    'canonical escaped newline' => ['json', ['{"value":"line\\nfeed"}'], '{"value":"line\\nfeed"}'],
    'canonical blob text json' => ['json', [new SQLiteBlobValue('{plugin:{ttl:60,},}')], '{"plugin":{"ttl":60}}'],
    'canonical subtype value' => ['json', [new SQLiteJsonSubtypeValue('{plugin:{enabled:true}}')], '{"plugin":{"enabled":true}}'],
    'canonical null returns sql null' => ['json', [null], null],
    'jsonb canonical function returns blob' => ['jsonb', ['{plugin:{enabled:true,ttl:300}}'], new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'ttl' => 300]]))],
    'jsonb canonical function accepts text blob' => ['jsonb', [new SQLiteBlobValue('{plugin:{modes:["sync",],}}')], new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['modes' => ['sync']]]))],
    'jsonb canonical function accepts jsonb blob' => ['jsonb', [$jsonbSettings], $jsonbSettings],
];

foreach ($canonicalCases as $name => [$function, $arguments, $expected]) {
    $tests['json scalar regression canonical ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected, $normalize): void {
        $actual = SQLiteJsonCanonical::jsonSqlFunctionArguments($function, $arguments);
        $t->same($normalize($expected), $normalize($actual));
    };
}

$quoteCases = [
    'quote sql null' => [null, 'null'],
    'quote integer' => [17, '17'],
    'quote negative integer' => [-17, '-17'],
    'quote real with fraction' => [2.5, '2.5'],
    'quote true as integer one' => [true, '1'],
    'quote false as integer zero' => [false, '0'],
    'quote text string' => ['plugin', '"plugin"'],
    'quote apostrophe text' => ["canary's", '"canary\'s"'],
    'quote slash text without escaping slash' => ['site/url', '"site/url"'],
    'quote unicode text' => ["caf\u{00e9}", '"café"'],
    'quote subtype preserves json payload' => [new SQLiteJsonSubtypeValue('{"plugin":true}'), '{"plugin":true}'],
    'quote jsonb blob as json text' => [$jsonbSettings, '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300}}'],
    'quote infinity real' => [INF, '9.0e+999'],
    'quote negative infinity real' => [-INF, '-9.0e+999'],
    'quote nan real' => [NAN, 'null'],
];

foreach ($quoteCases as $name => [$value, $expected]) {
    $tests['json scalar regression quote ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', [$value]));
    };
}

$patchCases = [
    'patch object member replacement' => ['json_patch', '{"plugin":{"enabled":false,"ttl":60}}', '{"plugin":{"enabled":true}}', '{"plugin":{"enabled":true,"ttl":60}}'],
    'patch object member deletion with null' => ['json_patch', '{"plugin":{"enabled":true,"ttl":60}}', '{"plugin":{"ttl":null}}', '{"plugin":{"enabled":true}}'],
    'patch nested object merge' => ['json_patch', '{"plugin":{"modes":["sync"],"flags":{"network":false}}}', '{"plugin":{"flags":{"network":true,"beta":false}}}', '{"plugin":{"modes":["sync"],"flags":{"network":true,"beta":false}}}'],
    'patch array replaces whole array' => ['json_patch', '{"plugin":{"modes":["sync","cache"]}}', '{"plugin":{"modes":["forms"]}}', '{"plugin":{"modes":["forms"]}}'],
    'patch scalar target with object patch' => ['json_patch', '5', '{"plugin":true}', '{"plugin":true}'],
    'patch object target with scalar patch' => ['json_patch', '{"plugin":true}', '7', '7'],
    'patch json5 target and patch' => ['json_patch', '{plugin:{ttl:60,flags:{a:1}}}', '{plugin:{ttl:120,flags:{b:2}}}', '{"plugin":{"ttl":120,"flags":{"a":1,"b":2}}}'],
    'patch text blob target' => ['json_patch', new SQLiteBlobValue('{"plugin":{"enabled":false}}'), '{"plugin":{"enabled":true}}', '{"plugin":{"enabled":true}}'],
    'patch text blob patch' => ['json_patch', '{"plugin":{"enabled":false}}', new SQLiteBlobValue('{"plugin":{"ttl":30}}'), '{"plugin":{"enabled":false,"ttl":30}}'],
    'patch jsonb target' => ['json_patch', $jsonbSettings, '{"plugin":{"ttl":600}}', '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":600}}'],
    'patch jsonb patch' => ['json_patch', '{"plugin":{"enabled":false}}', new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'ttl' => 90]])), '{"plugin":{"enabled":true,"ttl":90}}'],
    'patch null target returns sql null' => ['json_patch', null, '{"plugin":true}', null],
    'patch null patch returns sql null' => ['json_patch', '{"plugin":true}', null, null],
    'jsonb patch returns blob' => ['jsonb_patch', '{"plugin":{"enabled":false}}', '{"plugin":{"enabled":true}}', new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true]]))],
];

foreach ($patchCases as $name => [$function, $target, $patch, $expected]) {
    $tests['json scalar regression patch ' . $name] = static function (TestRunner $t) use ($function, $target, $patch, $expected, $normalize): void {
        $actual = SQLiteJsonPatch::patchSqlFunctionArguments($function, [$target, $patch]);
        $t->same($normalize($expected), $normalize($actual));
    };
}

$prettyCases = [
    'pretty object default indent' => ['{"plugin":{"enabled":true,"ttl":300}}', null, "{\n    \"plugin\": {\n        \"enabled\": true,\n        \"ttl\": 300\n    }\n}"],
    'pretty array default indent' => ['["sync","cache"]', null, "[\n    \"sync\",\n    \"cache\"\n]"],
    'pretty object custom indent' => ['{"plugin":{"enabled":true}}', '  ', "{\n  \"plugin\": {\n    \"enabled\": true\n  }\n}"],
    'pretty json5 input canonicalized' => ['{plugin:{modes:["sync",],enabled:false,},}', ' ', "{\n \"plugin\": {\n  \"modes\": [\n   \"sync\"\n  ],\n  \"enabled\": false\n }\n}"],
    'pretty jsonb blob' => [$jsonbSettings, ' ', "{\n \"plugin\": {\n  \"enabled\": true,\n  \"modes\": [\n   \"sync\",\n   \"cache\"\n  ],\n  \"ttl\": 300\n }\n}"],
    'pretty null returns sql null' => [null, null, null],
];

foreach ($prettyCases as $name => [$value, $indent, $expected]) {
    $tests['json scalar regression pretty ' . $name] = static function (TestRunner $t) use ($value, $indent, $expected): void {
        $arguments = $indent === null ? [$value] : [$value, $indent];
        $t->same($expected, SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', $arguments));
    };
}

$tests['json scalar regression invalid canonical argument count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunctionArguments('json', []));
};

$tests['json scalar regression invalid quote blob'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', [new SQLiteBlobValue('not-jsonb')]));
};

$tests['json scalar regression invalid patch function'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('json_mergepatch', ['{}', '{}']));
};

$tests['json scalar regression invalid patch argument count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('json_patch', ['{}']));
};

$tests['json scalar regression invalid pretty argument count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', []));
};

return $tests;
