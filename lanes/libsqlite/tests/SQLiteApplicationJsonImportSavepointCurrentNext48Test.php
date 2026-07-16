<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonImportSavepointPlan;

$currentRows = static fn (): array => [
    [
        'setting_id' => 1,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"imports":0}',
        'load_policy' => 'yes',
        'page_number' => 2,
    ],
    [
        'setting_id' => 65,
        'key_name' => 'theme_mods_twenty',
        'key_value' => '{"colors":{"accent":"blue"}}',
        'load_policy' => 'yes',
        'page_number' => 3,
    ],
];

$mutations = static fn (): array => [
    [
        'statement' => 'insert_plugin_catalog',
        'key_name' => 'plugin_catalog',
        'on_missing' => 'insert',
        'insert_setting_id' => 130,
        'insert_load_policy' => 'no',
        'page_number' => 5,
        'initial_value' => '{}',
        'path' => '$.plugins',
        'value' => new SQLiteJsonSubtypeValue('["seo","cache"]'),
        'wal_frame_index' => 4,
    ],
    [
        'statement' => 'increment_imports',
        'key_name' => 'plugin_settings',
        'path' => '$.imports',
        'value' => 1,
        'wal_frame_index' => 5,
    ],
    [
        'statement' => 'insert_theme_palette',
        'key_name' => 'theme_palette',
        'on_missing' => 'insert',
        'insert_setting_id' => 141,
        'insert_load_policy' => 'yes',
        'page_number' => 6,
        'function' => 'jsonb_set',
        'initial_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['colors' => ['accent' => 'blue']])),
        'path' => '$.colors',
        'value' => new SQLiteJsonSubtypeValue('{"accent":{"name":"green","contrast":7}}'),
        'wal_frame_index' => 6,
    ],
    [
        'statement' => 'insert_broken_catalog',
        'key_name' => 'broken_catalog',
        'on_missing' => 'insert',
        'insert_setting_id' => 142,
        'page_number' => 7,
        'initial_value' => '{"broken":',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 7,
    ],
];

$plan = static fn (array $rows = null, array $steps = null, array $keys = []): array => SQLiteJsonImportSavepointPlan::plan(
    $rows ?? $currentRows(),
    $steps ?? $mutations(),
    array_replace(['page_size' => 512], $keys)
);

$decodeSetting = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
};

$finalRow = static function (array $plan, string $name): array {
    foreach ($plan['final_rows'] as $row) {
        if ($row['key_name'] === $name) {
            return $row;
        }
    }

    throw new RuntimeException("Missing final row {$name}");
};

$cases = [
    'partial rollback status after failed inserted json row' => static fn (): mixed => $plan()['status'],
    'three statements apply before failed insert rollback' => static fn (): mixed => count($plan()['applied']),
    'one inserted malformed json statement fails' => static fn (): mixed => count($plan()['failed']),
    'first applied statement inserts catalog' => static fn (): mixed => $plan()['applied'][0]['statement'],
    'second applied statement updates existing settings' => static fn (): mixed => $plan()['applied'][1]['statement'],
    'third applied statement inserts palette' => static fn (): mixed => $plan()['applied'][2]['statement'],
    'insert catalog is marked inserted' => static fn (): mixed => $plan()['applied'][0]['inserted_setting'],
    'existing settings update is not marked inserted' => static fn (): mixed => $plan()['applied'][1]['inserted_setting'],
    'insert palette is marked inserted' => static fn (): mixed => $plan()['applied'][2]['inserted_setting'],
    'inserted catalog key is retained in final rows' => static fn (): mixed => $finalRow($plan(), 'plugin_catalog')['setting_id'],
    'inserted catalog load_policy defaults from mutation' => static fn (): mixed => $finalRow($plan(), 'plugin_catalog')['load_policy'],
    'inserted catalog page is explicit' => static fn (): mixed => $finalRow($plan(), 'plugin_catalog')['page_number'],
    'inserted catalog json subtype array is decoded' => static fn (): mixed => $decodeSetting($finalRow($plan(), 'plugin_catalog')['key_value'])['plugins'],
    'existing settings import counter updates' => static fn (): mixed => $decodeSetting($finalRow($plan(), 'plugin_settings')['key_value'])['imports'],
    'existing settings enabled value is preserved' => static fn (): mixed => $decodeSetting($finalRow($plan(), 'plugin_settings')['key_value'])['enabled'],
    'inserted palette key id is explicit' => static fn (): mixed => $finalRow($plan(), 'theme_palette')['setting_id'],
    'inserted palette load_policy is explicit' => static fn (): mixed => $finalRow($plan(), 'theme_palette')['load_policy'],
    'inserted palette stays jsonb blob' => static fn (): mixed => $finalRow($plan(), 'theme_palette')['key_value'] instanceof SQLiteBlobValue,
    'inserted palette nested subtype name decodes' => static fn (): mixed => $decodeSetting($finalRow($plan(), 'theme_palette')['key_value'])['colors']['accent']['name'],
    'inserted palette nested subtype contrast decodes' => static fn (): mixed => $decodeSetting($finalRow($plan(), 'theme_palette')['key_value'])['colors']['accent']['contrast'],
    'failed inserted key is not present after statement rollback' => static fn (): mixed => array_column($plan()['final_rows'], 'key_name'),
    'failed statement name is retained' => static fn (): mixed => $plan()['failed'][0]['statement'],
    'failed inserted key name is retained' => static fn (): mixed => $plan()['failed'][0]['key_name'],
    'failed inserted json row restored its allocated page' => static fn (): mixed => $plan()['failed'][0]['rollback']['restored_page_numbers'],
    'failed inserted json row discarded only its wal frame' => static fn (): mixed => array_column($plan()['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'),
    'failed inserted json rollback keeps savepoint active' => static fn (): mixed => $plan()['failed'][0]['rollback']['savepoint_active_after'],
    'failed inserted json rollback clears journal' => static fn (): mixed => $plan()['failed'][0]['rollback']['statement_journal_cleared'],
    'statement plans include only applied statements' => static fn (): mixed => count($plan()['statement_plans']),
    'insert catalog rollback frame starts before explicit wal frame' => static fn (): mixed => $plan()['statement_plans'][0]['rollback_to_wal_frame'],
    'settings rollback frame follows catalog frame' => static fn (): mixed => $plan()['statement_plans'][1]['rollback_to_wal_frame'],
    'palette rollback frame follows settings frame' => static fn (): mixed => $plan()['statement_plans'][2]['rollback_to_wal_frame'],
    'insert catalog statement restores page five' => static fn (): mixed => $plan()['statement_plans'][0]['restored_page_numbers'],
    'settings statement restores page two' => static fn (): mixed => $plan()['statement_plans'][1]['restored_page_numbers'],
    'palette statement restores page six' => static fn (): mixed => $plan()['statement_plans'][2]['restored_page_numbers'],
    'savepoint pending pages exclude failed insert page' => static fn (): mixed => $plan()['savepoint_state'][1]['page_numbers'],
    'statement journals exclude failed insert journal' => static fn (): mixed => array_column($plan()['statement_journals'], 'name'),
    'rollback to savepoint restores inserted and updated pages' => static fn (): mixed => $plan()['rollback_to_savepoint']['restored_page_numbers'],
    'rollback to savepoint has no missing images for inserted pages' => static fn (): mixed => $plan()['rollback_to_savepoint']['missing_page_numbers'],
    'wal rollback discards applied frames only' => static fn (): mixed => array_column($plan()['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'),
    'wal rollback discards inserted and updated pages' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['discarded_page_numbers'],
    'commit plan includes inserted and updated pages' => static fn (): mixed => $plan()['commit']['committed_page_numbers'],
    'database expands through inserted palette page' => static fn (): mixed => strlen($plan()['database_bytes']),
    'database change flag is true' => static fn (): mixed => $plan()['database_changed'],
    'implicit inserted key id follows current max id' => static fn (): mixed => $finalRow($plan($currentRows(), [
        ['key_name' => 'implicit_catalog', 'on_missing' => 'insert', 'path' => '$.enabled', 'value' => true],
    ]), 'implicit_catalog')['setting_id'],
    'implicit inserted key page follows rowid bucket' => static fn (): mixed => $finalRow($plan($currentRows(), [
        ['key_name' => 'implicit_catalog', 'on_missing' => 'insert', 'path' => '$.enabled', 'value' => true],
    ]), 'implicit_catalog')['page_number'],
    'implicit inserted load_policy defaults to no' => static fn (): mixed => $finalRow($plan($currentRows(), [
        ['key_name' => 'implicit_catalog', 'on_missing' => 'insert', 'path' => '$.enabled', 'value' => true],
    ]), 'implicit_catalog')['load_policy'],
    'implicit inserted json value mutates from empty object' => static fn (): mixed => $decodeSetting($finalRow($plan($currentRows(), [
        ['key_name' => 'implicit_catalog', 'on_missing' => 'insert', 'path' => '$.enabled', 'value' => true],
    ]), 'implicit_catalog')['key_value'])['enabled'],
    'missing key without insert mode still fails' => static fn (): mixed => $plan($currentRows(), [
        ['statement' => 'missing_plain', 'key_name' => 'plain_missing', 'path' => '$.enabled', 'value' => true],
    ])['failed'][0]['statement'],
    'duplicate inserted key id rolls back statement' => static fn (): mixed => $plan($currentRows(), [
        ['statement' => 'duplicate_id', 'key_name' => 'duplicate_catalog', 'on_missing' => 'insert', 'insert_setting_id' => 65, 'path' => '$.enabled', 'value' => true],
    ])['failed'][0]['statement'],
    'invalid inserted key id rolls back statement' => static fn (): mixed => $plan($currentRows(), [
        ['statement' => 'bad_id', 'key_name' => 'bad_catalog', 'on_missing' => 'insert', 'insert_setting_id' => 0, 'path' => '$.enabled', 'value' => true],
    ])['failed'][0]['statement'],
    'invalid inserted load_policy rolls back statement' => static fn (): mixed => $plan($currentRows(), [
        ['statement' => 'bad_load_policy', 'key_name' => 'bad_catalog', 'on_missing' => 'insert', 'insert_load_policy' => 1, 'path' => '$.enabled', 'value' => true],
    ])['failed'][0]['statement'],
    'invalid inserted page rolls back statement' => static fn (): mixed => $plan($currentRows(), [
        ['statement' => 'bad_page', 'key_name' => 'bad_catalog', 'on_missing' => 'insert', 'page_number' => 0, 'path' => '$.enabled', 'value' => true],
    ])['failed'][0]['statement'],
    'inserted jsonb initial value can be updated as json text function' => static fn (): mixed => $decodeSetting($finalRow($plan($currentRows(), [
        ['key_name' => 'jsonb_initial', 'on_missing' => 'insert', 'initial_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['count' => 1])), 'path' => '$.count', 'value' => 2],
    ]), 'jsonb_initial')['key_value'])['count'],
    'inserted json replace leaves missing path unchanged' => static fn (): mixed => $decodeSetting($finalRow($plan($currentRows(), [
        ['key_name' => 'replace_insert', 'on_missing' => 'insert', 'function' => 'json_replace', 'initial_value' => '{"count":1}', 'path' => '$.missing', 'value' => 2],
    ]), 'replace_insert')['key_value']),
    'dependencies include next48 insert savepoint marker' => static fn (): mixed => in_array('sqlite-application-json-import-savepoint-insert-current-next48', $plan()['dependencies'], true),
];

$expected = [
    'partial rollback status after failed inserted json row' => 'partial_rollback',
    'three statements apply before failed insert rollback' => 3,
    'one inserted malformed json statement fails' => 1,
    'first applied statement inserts catalog' => 'insert_plugin_catalog',
    'second applied statement updates existing settings' => 'increment_imports',
    'third applied statement inserts palette' => 'insert_theme_palette',
    'insert catalog is marked inserted' => true,
    'existing settings update is not marked inserted' => false,
    'insert palette is marked inserted' => true,
    'inserted catalog key is retained in final rows' => 130,
    'inserted catalog load_policy defaults from mutation' => 'no',
    'inserted catalog page is explicit' => 5,
    'inserted catalog json subtype array is decoded' => ['seo', 'cache'],
    'existing settings import counter updates' => 1,
    'existing settings enabled value is preserved' => false,
    'inserted palette key id is explicit' => 141,
    'inserted palette load_policy is explicit' => 'yes',
    'inserted palette stays jsonb blob' => true,
    'inserted palette nested subtype name decodes' => 'green',
    'inserted palette nested subtype contrast decodes' => 7,
    'failed inserted key is not present after statement rollback' => ['plugin_settings', 'theme_mods_twenty', 'plugin_catalog', 'theme_palette'],
    'failed statement name is retained' => 'insert_broken_catalog',
    'failed inserted key name is retained' => 'broken_catalog',
    'failed inserted json row restored its allocated page' => [7],
    'failed inserted json row discarded only its wal frame' => [7],
    'failed inserted json rollback keeps savepoint active' => true,
    'failed inserted json rollback clears journal' => true,
    'statement plans include only applied statements' => 3,
    'insert catalog rollback frame starts before explicit wal frame' => 0,
    'settings rollback frame follows catalog frame' => 4,
    'palette rollback frame follows settings frame' => 5,
    'insert catalog statement restores page five' => [5],
    'settings statement restores page two' => [2],
    'palette statement restores page six' => [6],
    'savepoint pending pages exclude failed insert page' => [2, 5, 6],
    'statement journals exclude failed insert journal' => ['insert_plugin_catalog', 'increment_imports', 'insert_theme_palette'],
    'rollback to savepoint restores inserted and updated pages' => [2, 5, 6],
    'rollback to savepoint has no missing images for inserted pages' => [],
    'wal rollback discards applied frames only' => [4, 5, 6],
    'wal rollback discards inserted and updated pages' => [2, 5, 6],
    'commit plan includes inserted and updated pages' => [2, 5, 6],
    'database expands through inserted palette page' => 3072,
    'database change flag is true' => true,
    'implicit inserted key id follows current max id' => 66,
    'implicit inserted key page follows rowid bucket' => 3,
    'implicit inserted load_policy defaults to no' => 'no',
    'implicit inserted json value mutates from empty object' => true,
    'missing key without insert mode still fails' => 'missing_plain',
    'duplicate inserted key id rolls back statement' => 'duplicate_id',
    'invalid inserted key id rolls back statement' => 'bad_id',
    'invalid inserted load_policy rolls back statement' => 'bad_load_policy',
    'invalid inserted page rolls back statement' => 'bad_page',
    'inserted jsonb initial value can be updated as json text function' => 2,
    'inserted json replace leaves missing path unchanged' => ['count' => 1],
    'dependencies include next48 insert savepoint marker' => true,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application json import savepoint current next48 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
