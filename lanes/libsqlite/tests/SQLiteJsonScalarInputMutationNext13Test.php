<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonRemove;

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

$patchCases = [
    'integer target patched by object' => [static fn () => SQLiteJsonPatch::patch(1, '{"a":2}'), ['a' => 2]],
    'float target patched by object' => [static fn () => SQLiteJsonPatch::patch(1.5, '{"a":2}'), ['a' => 2]],
    'true target patched by object' => [static fn () => SQLiteJsonPatch::patch(true, '{"a":2}'), ['a' => 2]],
    'false target patched by object' => [static fn () => SQLiteJsonPatch::patch(false, '{"a":2}'), ['a' => 2]],
    'object target patched by integer' => [static fn () => SQLiteJsonPatch::patch('{"a":1}', 2), 2],
    'object target patched by float' => [static fn () => SQLiteJsonPatch::patch('{"a":1}', 2.25), 2.25],
    'object target patched by true' => [static fn () => SQLiteJsonPatch::patch('{"a":1}', true), 1],
    'object target patched by false' => [static fn () => SQLiteJsonPatch::patch('{"a":1}', false), 0],
    'integer target patched by integer' => [static fn () => SQLiteJsonPatch::patch(1, 2), 2],
    'integer target patched by empty object' => [static fn () => SQLiteJsonPatch::patch(1, '{}'), []],
    'argument vector integer target' => [static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('JSON_PATCH', [1, '{"a":2}']), ['a' => 2]],
    'argument vector integer patch' => [static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('JSON_PATCH', ['{"a":1}', 2]), 2],
    'jsonb patch integer target returns blob' => [static fn () => SQLiteJsonPatch::patchSqlFunction('JSONB_PATCH', 1, '{"a":2}'), ['a' => 2]],
    'jsonb patch integer patch returns blob' => [static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('JSONB_PATCH', ['{"a":1}', 2]), 2],
    'null still propagates with integer target side' => [static fn () => SQLiteJsonPatch::patchSqlFunction('json_patch', 1, null), null],
    'null still propagates with integer patch side' => [static fn () => SQLiteJsonPatch::patchSqlFunction('json_patch', null, 1), null],
];

foreach ($patchCases as $name => [$callback, $expected]) {
    $tests['json scalar input patch next13 ' . $name] = static function (TestRunner $t) use ($callback, $decode, $expected): void {
        $t->same($expected, $decode($callback()));
    };
}

$mutationCases = [
    'integer root set replaces document' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', 1, '$', 9), 9],
    'integer object path set leaves scalar unchanged' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', 1, '$.a', 9), 1],
    'integer array path set leaves scalar unchanged' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', 1, '$[0]', 9), 1],
    'integer root insert is no op' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_insert', 1, '$', 9), 1],
    'integer object path insert leaves scalar unchanged' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_insert', 1, '$.a', 9), 1],
    'integer root replace replaces document' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_replace', 1, '$', 9), 9],
    'float root set replaces document' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', 1.5, '$', 9), 9],
    'float object path set leaves scalar unchanged' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', 1.5, '$.a', 9), 1.5],
    'true root set treats SQL boolean as integer one' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', true, '$.a', 9), 1],
    'false root set treats SQL boolean as integer zero' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', false, '$.a', 9), 0],
    'argument vector integer input' => [static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('JSON_SET', [1, '$', 9]), 9],
    'argument vector float input unchanged path' => [static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('JSON_SET', [1.5, '$.a', 9]), 1.5],
    'jsonb set integer input returns blob' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('JSONB_SET', 1, '$', 9), 9],
    'jsonb insert integer input returns blob' => [static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('JSONB_INSERT', [1, '$.a', 9]), 1],
    'jsonb replace float input returns blob' => [static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('JSONB_REPLACE', [1.5, '$', 9]), 9],
    'multiple pairs after scalar root replacement see new object' => [static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', 1, '$', new SQLiteBlobValue(SQLiteJsonB::encode(['a' => 1])), '$.b', 2), ['a' => 1, 'b' => 2]],
];

foreach ($mutationCases as $name => [$callback, $expected]) {
    $tests['json scalar input set remove next13 mutation ' . $name] = static function (TestRunner $t) use ($callback, $decode, $expected): void {
        $t->same($expected, $decode($callback()));
    };
}

$removeCases = [
    'integer nonroot remove leaves scalar unchanged' => [static fn () => SQLiteJsonRemove::remove(1, '$.a'), 1],
    'integer root remove returns null' => [static fn () => SQLiteJsonRemove::remove(1, '$'), null],
    'integer array remove leaves scalar unchanged' => [static fn () => SQLiteJsonRemove::remove(1, '$[0]'), 1],
    'float nonroot remove leaves scalar unchanged' => [static fn () => SQLiteJsonRemove::remove(1.5, '$.a'), 1.5],
    'float root remove returns null' => [static fn () => SQLiteJsonRemove::remove(1.5, '$'), null],
    'true nonroot remove treats SQL boolean as integer one' => [static fn () => SQLiteJsonRemove::remove(true, '$.a'), 1],
    'false nonroot remove treats SQL boolean as integer zero' => [static fn () => SQLiteJsonRemove::remove(false, '$.a'), 0],
    'argument vector integer input' => [static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('JSON_REMOVE', [1, '$.a']), 1],
    'argument vector integer root removal' => [static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('JSON_REMOVE', [1, '$']), null],
    'argument vector float input' => [static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('JSON_REMOVE', [1.5, '$.a']), 1.5],
    'jsonb remove integer input returns blob' => [static fn () => SQLiteJsonRemove::removeSqlFunction('JSONB_REMOVE', 1, '$.a'), 1],
    'jsonb remove integer root returns null' => [static fn () => SQLiteJsonRemove::removeSqlFunction('JSONB_REMOVE', 1, '$'), null],
    'jsonb remove float input returns blob' => [static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('JSONB_REMOVE', [1.5, '$.a']), 1.5],
    'zero path integer json_remove returns canonical number' => [static fn () => SQLiteJsonRemove::removeSqlFunction('json_remove', 1), 1],
];

foreach ($removeCases as $name => [$callback, $expected]) {
    $tests['json scalar input set remove next13 remove ' . $name] = static function (TestRunner $t) use ($callback, $decode, $expected): void {
        $t->same($expected, $decode($callback()));
    };
}

$throwsCases = [
    'patch rejects infinite target' => static fn () => SQLiteJsonPatch::patch(INF, '{}'),
    'patch rejects infinite patch' => static fn () => SQLiteJsonPatch::patch('{}', INF),
    'remove rejects infinite input' => static fn () => SQLiteJsonRemove::remove(INF, '$.a'),
    'set rejects infinite input' => static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', INF, '$', 1),
    'mutation argument vector still rejects array input' => static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [[], '$', 1]),
    'remove argument vector still rejects array input' => static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [[], '$.a']),
    'patch argument vector still rejects object target through type boundary' => static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('json_patch', [[], '{}']),
    'patch argument vector still rejects object patch through type boundary' => static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('json_patch', ['{}', []]),
];

foreach ($throwsCases as $name => $callback) {
    $tests['json scalar input set remove next13 throws ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
