<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$rows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'module_alpha', 'key_value' => '{"legacy":true}', 'payload' => '{"module":{"slug":"alpha","enabled":false,"rank":1,"tags":["cache"],"meta":{"channel":"stable"}}}'],
    ['setting_id' => 2, 'key_name' => 'module_beta', 'key_value' => '{"legacy":true}', 'payload' => '{"module":{"slug":"beta","enabled":true,"rank":2,"tags":["seo"],"meta":{"channel":"beta"}}}'],
    ['setting_id' => 3, 'key_name' => 'module_gamma', 'key_value' => '{"legacy":true}', 'payload' => '{"module":{"slug":"gamma","enabled":false,"rank":3,"tags":[],"meta":{"channel":"stable"}}}'],
    ['setting_id' => 4, 'key_name' => 'module_delta', 'key_value' => '{"legacy":true}', 'payload' => null],
];

$indexes = [
    ['name' => 'idx_payload_slug', 'column' => 'payload', 'path' => '$.module.slug', 'unique' => true],
    ['name' => 'idx_payload_enabled', 'column' => 'payload', 'path' => '$.module.enabled'],
    ['name' => 'idx_payload_rank', 'column' => 'payload', 'path' => '$.module.rank'],
    ['name' => 'idx_payload_tag_last', 'column' => 'payload', 'path' => '$.module.tags[#-1]', 'collation' => 'NOCASE'],
    ['name' => 'idx_payload_channel', 'column' => 'payload', 'path' => '$.module.meta.channel'],
    ['name' => 'idx_setting_legacy', 'column' => 'key_value', 'path' => '$.legacy'],
];

$planFor = static function (array $updates, ?array $sourceRows = null, ?array $sourceIndexes = null): array {
    return SQLiteJsonPathIndexedUpdatePlan::plan(
        $sourceRows ?? $GLOBALS['rows'](),
        $sourceIndexes ?? $GLOBALS['indexes'],
        $updates,
    );
};

$cases = [
    'payload slug current next' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.slug', 'value' => 'alpha-pro']]]], [['idx_payload_slug', 'alpha', 'alpha-pro']]],
    'payload enabled false true' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true]]]], [['idx_payload_enabled', 0, 1]]],
    'payload enabled true false' => [[['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => false]]]], [['idx_payload_enabled', 1, 0]]],
    'payload rank integer current next' => [[['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 20]]]], [['idx_payload_rank', 2, 20]]],
    'payload rank integer to text current next' => [[['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => '2']]]], [['idx_payload_rank', 2, '2']]],
    'payload last tag replace current next' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_replace', 'path' => '$.module.tags[#-1]', 'value' => 'cdn']]]], [['idx_payload_tag_last', 'cache', 'cdn']]],
    'payload append changes reverse index' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.tags[#]', 'value' => 'cdn']]]], [['idx_payload_tag_last', 'cache', 'cdn']]],
    'payload insert first tag from empty' => [[['rowid' => 3, 'column' => 'payload', 'mutations' => [['function' => 'json_insert', 'path' => '$.module.tags[0]', 'value' => 'media']]]], [['idx_payload_tag_last', null, 'media']]],
    'payload channel nested replace' => [[['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_replace', 'path' => '$.module.meta.channel', 'value' => 'stable']]]], [['idx_payload_channel', 'beta', 'stable']]],
    'payload channel object replacement' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.meta', 'value' => new SQLiteJsonSubtypeValue('{"channel":"nightly"}')]]]], [['idx_payload_channel', 'stable', 'nightly']]],
    'payload root replacement updates all payload indexes' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_replace', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"slug":"omega","enabled":true,"rank":9,"tags":["edge"],"meta":{"channel":"edge"}}}')]]]], [['idx_payload_slug', 'alpha', 'omega'], ['idx_payload_enabled', 0, 1], ['idx_payload_rank', 1, 9], ['idx_payload_tag_last', 'cache', 'edge'], ['idx_payload_channel', 'stable', 'edge']]],
    'payload set module empty deletes generated keys' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module', 'value' => new SQLiteJsonSubtypeValue('{}')]]]], [['idx_payload_slug', 'alpha', null], ['idx_payload_enabled', 0, null], ['idx_payload_rank', 1, null], ['idx_payload_tag_last', 'cache', null], ['idx_payload_channel', 'stable', null]]],
    'key value legacy still defaults when no column supplied' => [[['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.legacy', 'value' => false]]]], [['idx_setting_legacy', 1, 0]]],
    'payload update does not touch key value index' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 10]]]], [['idx_payload_rank', 1, 10]]],
    'key value update does not touch payload indexes' => [[['rowid' => 1, 'column' => 'key_value', 'mutations' => [['function' => 'json_set', 'path' => '$.legacy', 'value' => false]]]], [['idx_setting_legacy', 1, 0]]],
    'payload jsonb source canonicalized after update' => [[['rowid' => 5, 'column' => 'payload', 'mutations' => [['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 6]]]], [['idx_payload_rank', 5, 6]], [['setting_id' => 5, 'key_name' => 'module_blob', 'key_value' => '{}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['module' => ['slug' => 'blob', 'rank' => 5]]))]]],
    'payload jsonb mutation value updates nested channel' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.meta', 'value' => new SQLiteBlobValue(SQLiteJsonB::encode(['channel' => 'raw']))]]]], [['idx_payload_channel', 'stable', 'raw']]],
    'payload json5 subtype updates nested channel' => [[['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.meta', 'value' => new SQLiteJsonSubtypeValue('{channel:"json5",}')]]]], [['idx_payload_channel', 'beta', 'json5']]],
    'payload no-op assignment has no current next' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 1]]]], []],
    'payload missing rowid no current next' => [[['rowid' => 99, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 9]]]], []],
    'payload null source remains absent for nested path' => [[['rowid' => 4, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 9]]]], []],
    'payload root set on null remains absent' => [[['rowid' => 4, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"rank":9}}')]]]], []],
    'payload second update sees first current image' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 8]]], ['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 9]]]], [['idx_payload_rank', 1, 8], ['idx_payload_rank', 8, 9]]],
    'payload two row updates keep independent current next' => [[['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 11]]], ['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 22]]]], [['idx_payload_rank', 1, 11], ['idx_payload_rank', 2, 22]]],
    'payload numeric string rowid updates same row' => [[['rowid' => '1', 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 12]]]], [['idx_payload_rank', 1, 12]]],
];

foreach ($cases as $name => $case) {
    [$updates, $expected] = $case;
    $sourceRows = $case[2] ?? null;
    $tests['json path index mutation current next73 ' . $name] = static function (TestRunner $t) use ($planFor, $updates, $expected, $sourceRows): void {
        $plan = $planFor($updates, $sourceRows);
        $t->same($expected, array_map(
            static fn (array $update): array => [$update['index'], $update['current'], $update['next']],
            $plan['index_updates'],
        ));
    };
}

$tests['json path index mutation current next73 writes payload column image'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 44]]]]);
    $t->same([44, '{"legacy":true}'], [json_decode((string) $plan['after'][0]['payload'], true, 512, JSON_THROW_ON_ERROR)['module']['rank'], $plan['after'][0]['key_value']]);
};

$tests['json path index mutation current next73 changed row count follows matched payload row'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 44]]]]);
    $t->same([1, 1, 4], [$plan['changes'], count($plan['changed_rows']), count($plan['after'])]);
};

$tests['json path index mutation current next73 preserves before payload image'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 44]]]]);
    $t->same($GLOBALS['rows']()[0]['payload'], $plan['before'][0]['payload']);
};

$tests['json path index mutation current next73 preserves collation metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_replace', 'path' => '$.module.tags[#-1]', 'value' => 'cdn']]]]);
    $tag = array_values(array_filter($plan['index_updates'], static fn (array $update): bool => $update['index'] === 'idx_payload_tag_last'))[0];
    $t->same('NOCASE', $tag['collation']);
};

$tests['json path index mutation current next73 preserves unique metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.slug', 'value' => 'alpha-pro']]]]);
    $slug = array_values(array_filter($plan['index_updates'], static fn (array $update): bool => $update['index'] === 'idx_payload_slug'))[0];
    $t->same(true, $slug['unique']);
};

$tests['json path index mutation current next73 rejects duplicate unique payload slug'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.slug', 'value' => 'beta']]]]));
};

$tests['json path index mutation current next73 rejects non text mutation column'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'column' => 5, 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 4]]]]));
};

$tests['json path index mutation current next73 rejects empty mutation column'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'column' => '', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 4]]]]));
};

$tests['json path index mutation current next73 rejects non json payload value'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 4]]]], [['setting_id' => 1, 'payload' => []]]));
};

$tests['json path index mutation current next73 rejects malformed payload mutation path'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$[#-]', 'value' => 4]]]]));
};

$tests['json path index mutation current next73 rejects unknown payload mutation function'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_patch', 'path' => '$.module.rank', 'value' => 4]]]]));
};

$tests['json path index mutation current next73 quoted payload labels update index'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'payload' => '{"module.rank":1}', 'key_value' => '{}']];
    $sourceIndexes = [['name' => 'idx_quoted_payload', 'column' => 'payload', 'path' => '$."module.rank"']];
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$."module.rank"', 'value' => 2]]]], $sourceRows, $sourceIndexes);
    $t->same(['idx_quoted_payload', 1, 2], [$plan['index_updates'][0]['index'], $plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path index mutation current next73 keeps missing payload column as null'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 4]]]], [['setting_id' => 1, 'key_value' => '{}']]);
    $t->same([1, []], [$plan['changes'], $plan['index_updates']]);
};

$tests['json path index mutation current next73 reports payload path metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 44]]]]);
    $t->same(['payload', '$.module.rank'], [$plan['index_updates'][0]['path'] === '$.module.rank' ? 'payload' : 'unexpected', $plan['index_updates'][0]['path']]);
};

$tests['json path index mutation current next73 keeps application key names intact'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.module.rank', 'value' => 44]]]]);
    $t->same('module_beta', $plan['after'][1]['key_name']);
};

$tests['json path index mutation current next73 canonicalizes jsonb payload row image'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 5, 'key_name' => 'module_blob', 'key_value' => '{}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['module' => ['slug' => 'blob', 'rank' => 5]]))]];
    $plan = $planFor([['rowid' => 5, 'column' => 'payload', 'mutations' => [['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 6]]]], $sourceRows);
    $t->same('{"module":{"slug":"blob","rank":6}}', $plan['after'][0]['payload']);
};

return $tests;
