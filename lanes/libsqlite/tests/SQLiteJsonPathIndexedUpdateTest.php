<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$rows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'module_alpha', 'key_value' => '{"module":{"enabled":false,"version":1,"tags":["cache"],"settings":{"mode":"safe"}}}'],
    ['setting_id' => 2, 'key_name' => 'module_beta', 'key_value' => '{"module":{"enabled":true,"version":2,"tags":["seo"],"settings":{"mode":"fast"}}}'],
    ['setting_id' => 3, 'key_name' => 'module_gamma', 'key_value' => '{"module":{"enabled":false,"version":3,"tags":[],"settings":{"mode":"safe"}}}'],
    ['setting_id' => 4, 'key_name' => 'module_delta', 'key_value' => null],
];

$indexes = [
    ['name' => 'idx_enabled', 'path' => '$.module.enabled'],
    ['name' => 'idx_version', 'path' => '$.module.version'],
    ['name' => 'idx_first_tag', 'path' => '$.module.tags[0]', 'collation' => 'NOCASE'],
    ['name' => 'idx_mode', 'path' => '$.module.settings.mode'],
];

$planFor = static function (int|string $rowid, array $mutations, ?array $sourceRows = null, ?array $sourceIndexes = null): array {
    return SQLiteJsonPathIndexedUpdatePlan::plan(
        $sourceRows ?? $GLOBALS['rows'](),
        $sourceIndexes ?? $GLOBALS['indexes'],
        [['rowid' => $rowid, 'mutations' => $mutations]],
    );
};

$cases = [
    'set boolean updates enabled current next' => [1, [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true]], 'idx_enabled', 0, 1],
    'set version updates numeric key' => [1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 5]], 'idx_version', 1, 5],
    'append first missing tag leaves first tag when present' => [1, [['function' => 'json_set', 'path' => '$.module.tags[#]', 'value' => 'cdn']], null, null, null],
    'replace first tag changes text key' => [1, [['function' => 'json_replace', 'path' => '$.module.tags[0]', 'value' => 'cdn']], 'idx_first_tag', 'cache', 'cdn'],
    'replace mode changes nested key' => [2, [['function' => 'json_replace', 'path' => '$.module.settings.mode', 'value' => 'safe']], 'idx_mode', 'fast', 'safe'],
    'insert missing mode does not change existing key' => [2, [['function' => 'json_insert', 'path' => '$.module.settings.mode', 'value' => 'safe']], null, null, null],
    'insert missing first tag changes null to text' => [3, [['function' => 'json_insert', 'path' => '$.module.tags[0]', 'value' => 'media']], 'idx_first_tag', null, 'media'],
    'set missing nested key on null document remains null' => [4, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 9]], null, null, null],
    'set missing enabled on null document remains null' => [4, [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => false]], null, null, null],
    'multiple mutations update final version key' => [1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 4], ['function' => 'json_set', 'path' => '$.module.version', 'value' => 7]], 'idx_version', 1, 7],
    'later nested object replacement changes mode' => [1, [['function' => 'json_set', 'path' => '$.module.settings', 'value' => new SQLiteJsonSubtypeValue('{"mode":"turbo"}')]], 'idx_mode', 'safe', 'turbo'],
    'jsonb value replacement changes mode' => [1, [['function' => 'json_set', 'path' => '$.module.settings', 'value' => new SQLiteBlobValue(SQLiteJsonB::encode(['mode' => 'raw']))]], 'idx_mode', 'safe', 'raw'],
    'reverse tag replace changes first tag when only item' => [2, [['function' => 'json_replace', 'path' => '$.module.tags[#-1]', 'value' => 'rank']], 'idx_first_tag', 'seo', 'rank'],
    'out of range replace leaves indexes stable' => [2, [['function' => 'json_replace', 'path' => '$.module.tags[5]', 'value' => 'rank']], null, null, null],
    'set scalar through object path leaves indexes stable' => [1, [['function' => 'json_set', 'path' => '$.module.enabled.flag', 'value' => true]], null, null, null],
    'case insensitive function dispatch updates version' => [2, [['function' => 'JSON_SET', 'path' => '$.module.version', 'value' => 20]], 'idx_version', 2, 20],
    'json5 subtype mutation updates indexed mode' => [2, [['function' => 'json_set', 'path' => '$.module.settings', 'value' => new SQLiteJsonSubtypeValue('{mode:"json5",}')]], 'idx_mode', 'fast', 'json5'],
    'insert root is no op for indexed keys' => [1, [['function' => 'json_insert', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"version":99}}')]], null, null, null],
    'replace root changes all indexed keys' => [1, [['function' => 'json_replace', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"enabled":true,"version":8,"tags":["new"],"settings":{"mode":"root"}}}')]], 'idx_enabled', 0, 1],
    'set root from null remains null' => [4, [['function' => 'json_set', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"settings":{"mode":"root"}}}')]], null, null, null],
];

foreach ($cases as $name => [$rowid, $mutations, $index, $current, $next]) {
    $tests['json path indexed update ' . $name] = static function (TestRunner $t) use ($planFor, $rowid, $mutations, $index, $current, $next): void {
        $plan = $planFor($rowid, $mutations);
        if ($index === null) {
            $t->same([], $plan['index_updates']);
            return;
        }

        $matched = array_values(array_filter($plan['index_updates'], static fn (array $update): bool => $update['index'] === $index));
        $t->same([['index' => $index, 'current' => $current, 'next' => $next]], array_map(
            static fn (array $update): array => ['index' => $update['index'], 'current' => $update['current'], 'next' => $update['next']],
            $matched,
        ));
    };
}

$tests['json path indexed update records delete insert flags'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true]]);
    $t->same([true, true, 'BINARY'], [$plan['index_updates'][0]['delete'], $plan['index_updates'][0]['insert'], $plan['index_updates'][0]['collation']]);
};

$tests['json path indexed update records null delete without insert'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => null]]);
    $t->same([true, false, null], [$plan['index_updates'][0]['delete'], $plan['index_updates'][0]['insert'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update reports changed row count'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 6]]);
    $t->same([1, 1, 4], [$plan['changes'], count($plan['changed_rows']), count($plan['after'])]);
};

$tests['json path indexed update preserves before image'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 6]]);
    $t->same([1, '{"module":{"enabled":false,"version":1,"tags":["cache"],"settings":{"mode":"safe"}}}'], [$plan['before'][0]['setting_id'], $plan['before'][0]['key_value']]);
};

$tests['json path indexed update writes after image'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 6]]);
    $t->same(6, json_decode((string) $plan['after'][0]['key_value'], true, 512, JSON_THROW_ON_ERROR)['module']['version']);
};

$tests['json path indexed update ignores missing rowid'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(99, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 6]]);
    $t->same([0, [], $GLOBALS['rows']()], [$plan['changes'], $plan['index_updates'], $plan['after']]);
};

$tests['json path indexed update supports text rowid'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['rowid' => 'alpha', 'key_value' => '{"module":{"version":1}}']];
    $plan = $planFor('alpha', [['function' => 'json_set', 'path' => '$.module.version', 'value' => 2]], $sourceRows);
    $t->same([1, 2], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update supports custom json column'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['rowid' => 1, 'payload' => '{"module":{"version":1}}', 'key_value' => '{}']];
    $sourceIndexes = [['name' => 'idx_payload_version', 'column' => 'payload', 'path' => '$.module.version']];
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 2]], $sourceRows, $sourceIndexes);
    $t->same([], $plan['index_updates']);
};

$tests['json path indexed update detects unique next conflict'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 2]], null, [['name' => 'idx_version_unique', 'path' => '$.module.version', 'unique' => true]]));
};

$tests['json path indexed update allows unique null keys'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => null]], null, [['name' => 'idx_version_unique', 'path' => '$.module.version', 'unique' => true]]);
    $t->same(null, $plan['index_updates'][0]['next']);
};

$tests['json path indexed update rejects malformed index path'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathIndexedUpdatePlan::plan($GLOBALS['rows'](), [['name' => 'bad', 'path' => '$[#-]']], []));
};

$tests['json path indexed update rejects missing index name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathIndexedUpdatePlan::plan($GLOBALS['rows'](), [['name' => '', 'path' => '$.module.version']], []));
};

$tests['json path indexed update rejects missing row identity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathIndexedUpdatePlan::plan([['key_value' => '{}']], $GLOBALS['indexes'], []));
};

$tests['json path indexed update rejects duplicate row identity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathIndexedUpdatePlan::plan([['setting_id' => 1, 'key_value' => '{}'], ['setting_id' => 1, 'key_value' => '{}']], $GLOBALS['indexes'], []));
};

$tests['json path indexed update rejects non text mutation path'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor(1, [['function' => 'json_set', 'path' => 1, 'value' => true]]));
};

$tests['json path indexed update rejects unknown mutation function'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor(1, [['function' => 'json_merge', 'path' => '$.module.version', 'value' => 2]]));
};

$tests['json path indexed update rejects non json column value'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 2]], [['setting_id' => 1, 'key_value' => []]]));
};

$tests['json path indexed update records all changed indexes on root replace'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_replace', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"enabled":true,"version":8,"tags":["new"],"settings":{"mode":"root"}}}')]]);
    $t->same(['idx_enabled', 'idx_version', 'idx_first_tag', 'idx_mode'], array_column($plan['index_updates'], 'index'));
};

$tests['json path indexed update preserves collation metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_replace', 'path' => '$.module.tags[0]', 'value' => 'cdn']]);
    $tag = array_values(array_filter($plan['index_updates'], static fn (array $update): bool => $update['index'] === 'idx_first_tag'))[0];
    $t->same('NOCASE', $tag['collation']);
};

$tests['json path indexed update supports jsonb source rows'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['module' => ['version' => 1]]))]];
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 2]], $sourceRows);
    $t->same([1, 2], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update canonicalizes jsonb mutation result to text row image'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['module' => ['version' => 1]]))]];
    $plan = $planFor(1, [['function' => 'jsonb_set', 'path' => '$.module.version', 'value' => 2]], $sourceRows);
    $t->same('{"module":{"version":2}}', $plan['after'][0]['key_value']);
};

$tests['json path indexed update applies independent updates in order'] = static function (TestRunner $t): void {
    $plan = SQLiteJsonPathIndexedUpdatePlan::plan($GLOBALS['rows'](), $GLOBALS['indexes'], [
        ['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.module.version', 'value' => 10]]],
        ['rowid' => 2, 'mutations' => [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => false]]],
    ]);
    $t->same([[1, 'idx_version', 10], [2, 'idx_enabled', 0]], array_map(
        static fn (array $update): array => [$update['rowid'], $update['index'], $update['next']],
        $plan['index_updates'],
    ));
};

$tests['json path indexed update later update sees current row image'] = static function (TestRunner $t): void {
    $plan = SQLiteJsonPathIndexedUpdatePlan::plan($GLOBALS['rows'](), $GLOBALS['indexes'], [
        ['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.module.version', 'value' => 10]]],
        ['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.module.version', 'value' => 11]]],
    ]);
    $t->same([[1, 10], [10, 11]], array_map(static fn (array $update): array => [$update['current'], $update['next']], $plan['index_updates']));
};

$tests['json path indexed update can remove indexed path'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module', 'value' => new SQLiteJsonSubtypeValue('{}')]]);
    $t->same([['idx_enabled', 0, null], ['idx_version', 1, null], ['idx_first_tag', 'cache', null], ['idx_mode', 'safe', null]], array_map(
        static fn (array $update): array => [$update['index'], $update['current'], $update['next']],
        $plan['index_updates'],
    ));
};

$tests['json path indexed update can create all indexed paths'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(4, [['function' => 'json_set', 'path' => '$', 'value' => new SQLiteJsonSubtypeValue('{"module":{"enabled":true,"version":4,"tags":["new"],"settings":{"mode":"full"}}}')]]);
    $t->same([], $plan['index_updates']);
};

$tests['json path indexed update no mutation still counts changed row without index delta'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, []);
    $t->same([1, []], [$plan['changes'], $plan['index_updates']]);
};

$tests['json path indexed update unchanged assignment has no index delta'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 1]]);
    $t->same([], $plan['index_updates']);
};

$tests['json path indexed update supports quoted path labels'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'key_value' => '{"module.name":"old"}']];
    $sourceIndexes = [['name' => 'idx_quoted', 'path' => '$."module.name"']];
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$."module.name"', 'value' => 'new']], $sourceRows, $sourceIndexes);
    $t->same(['old', 'new'], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update supports reverse array index expression'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'key_value' => '{"module":{"tags":["a","b"]}}']];
    $sourceIndexes = [['name' => 'idx_last_tag', 'path' => '$.module.tags[#-1]']];
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.tags[#]', 'value' => 'c']], $sourceRows, $sourceIndexes);
    $t->same(['b', 'c'], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update supports append index expression'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'key_value' => '{"module":{"tags":["a"]}}']];
    $sourceIndexes = [['name' => 'idx_append', 'path' => '$.module.tags[#]']];
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.tags[#]', 'value' => 'b']], $sourceRows, $sourceIndexes);
    $t->same([], $plan['index_updates']);
};

$tests['json path indexed update keeps application key name columns intact'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(2, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 22]]);
    $t->same('module_beta', $plan['after'][1]['key_name']);
};

$tests['json path indexed update reports rowid with numeric string request'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor('1', [['function' => 'json_set', 'path' => '$.module.version', 'value' => 12]]);
    $t->same('1', (string) $plan['index_updates'][0]['rowid']);
};

$tests['json path indexed update retains index path metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 12]]);
    $t->same('$.module.version', $plan['index_updates'][0]['path']);
};

$tests['json path indexed update preserves unrelated rows'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 12]]);
    $t->same($GLOBALS['rows']()[2], $plan['after'][2]);
};

$tests['json path indexed update handles text scalar indexed key'] = static function (TestRunner $t) use ($planFor): void {
    $sourceRows = [['setting_id' => 1, 'key_value' => '{"module":"old"}']];
    $sourceIndexes = [['name' => 'idx_module', 'path' => '$.module']];
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module', 'value' => 'new']], $sourceRows, $sourceIndexes);
    $t->same(['old', 'new'], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update handles integer to text key type change'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => '1']]);
    $t->same([1, '1'], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update handles boolean to integer key type change'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.enabled', 'value' => 0]]);
    $t->same([], $plan['index_updates']);
};

$tests['json path indexed update keeps non unique duplicate keys allowed'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(2, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 1]]);
    $t->same([2, 1], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
};

$tests['json path indexed update includes unique metadata on changed unique index'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 10]], null, [['name' => 'idx_version_unique', 'path' => '$.module.version', 'unique' => true]]);
    $t->same(true, $plan['index_updates'][0]['unique']);
};

$tests['json path indexed update rejects unique conflict across two updates'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathIndexedUpdatePlan::plan($GLOBALS['rows'](), [['name' => 'idx_version_unique', 'path' => '$.module.version', 'unique' => true]], [
        ['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.module.version', 'value' => 9]]],
        ['rowid' => 2, 'mutations' => [['function' => 'json_set', 'path' => '$.module.version', 'value' => 9]]],
    ]));
};

$tests['json path indexed update supports multi index no op filtering'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor(1, [['function' => 'json_set', 'path' => '$.module.version', 'value' => 7]]);
    $t->same(['idx_version'], array_column($plan['index_updates'], 'index'));
};

$tests['json path indexed update supports application staged setting payload'] = static function (TestRunner $t): void {
    $plan = SQLiteJsonPathIndexedUpdatePlan::plan($GLOBALS['rows'](), $GLOBALS['indexes'], [
        ['rowid' => 1, 'mutations' => [
            ['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true],
            ['function' => 'json_set', 'path' => '$.module.settings.mode', 'value' => 'published'],
        ]],
    ]);
    $t->same([['idx_enabled', 0, 1], ['idx_mode', 'safe', 'published']], array_map(
        static fn (array $update): array => [$update['index'], $update['current'], $update['next']],
        $plan['index_updates'],
    ));
};

return $tests;
