<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonImportSavepointPlan;

$currentRows = static fn (): array => [
    [
        'setting_id' => 1,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"version":1,"modules":["core"]}',
        'load_policy' => 'yes',
        'page_number' => 2,
    ],
    [
        'setting_id' => 65,
        'key_name' => 'theme_mods_twenty',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['colors' => ['accent' => 'blue'], 'layout' => 'wide'])),
        'load_policy' => 'yes',
        'page_number' => 3,
    ],
    [
        'setting_id' => 130,
        'key_name' => 'broken_plugin_settings',
        'key_value' => '{"enabled":',
        'load_policy' => 'no',
        'page_number' => 4,
    ],
];

$mutations = static fn (): array => [
    [
        'statement' => 'enable_plugin',
        'key_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'theme_accent',
        'key_name' => 'theme_mods_twenty',
        'function' => 'jsonb_set',
        'path' => '$.colors.accent',
        'value' => new SQLiteJsonSubtypeValue('{"name":"green","contrast":7}'),
        'wal_frame_index' => 2,
    ],
    [
        'statement' => 'broken_payload',
        'key_name' => 'broken_plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 3,
    ],
];

$plan = static fn (array $keys = [], ?array $rows = null, ?array $steps = null): array => SQLiteJsonImportSavepointPlan::plan(
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

$cases = [
    'status reports partial rollback when one json statement fails' => static fn (): mixed => $plan()['status'],
    'transaction name defaults to application json import' => static fn (): mixed => $plan()['transaction'],
    'savepoint name defaults to current json batch' => static fn (): mixed => $plan()['savepoint'],
    'page size is preserved' => static fn (): mixed => $plan()['page_size'],
    'two json statements are applied' => static fn (): mixed => count($plan()['applied']),
    'one malformed json statement fails' => static fn (): mixed => count($plan()['failed']),
    'first applied statement name is preserved' => static fn (): mixed => $plan()['applied'][0]['statement'],
    'second applied statement name is preserved' => static fn (): mixed => $plan()['applied'][1]['statement'],
    'first applied key is plugin settings' => static fn (): mixed => $plan()['applied'][0]['key_name'],
    'second applied key is theme mods' => static fn (): mixed => $plan()['applied'][1]['key_name'],
    'first applied page number is current row page' => static fn (): mixed => $plan()['applied'][0]['page_number'],
    'second applied page number is current row page' => static fn (): mixed => $plan()['applied'][1]['page_number'],
    'first applied wal frame is explicit' => static fn (): mixed => $plan()['applied'][0]['wal_frame_index'],
    'second applied wal frame is explicit' => static fn (): mixed => $plan()['applied'][1]['wal_frame_index'],
    'json_set text output canonicalizes enabled true' => static fn (): mixed => $decodeSetting($plan()['applied'][0]['key_value'])['enabled'],
    'json_set text output preserves version' => static fn (): mixed => $decodeSetting($plan()['applied'][0]['key_value'])['version'],
    'json_set text output preserves modules' => static fn (): mixed => $decodeSetting($plan()['applied'][0]['key_value'])['modules'],
    'jsonb_set keeps jsonb blob output' => static fn (): mixed => $plan()['applied'][1]['key_value'] instanceof SQLiteBlobValue,
    'jsonb_set nested subtype value is decoded' => static fn (): mixed => $decodeSetting($plan()['applied'][1]['key_value'])['colors']['accent']['name'],
    'jsonb_set nested subtype value keeps contrast' => static fn (): mixed => $decodeSetting($plan()['applied'][1]['key_value'])['colors']['accent']['contrast'],
    'jsonb_set preserves sibling layout' => static fn (): mixed => $decodeSetting($plan()['applied'][1]['key_value'])['layout'],
    'failed statement name is preserved' => static fn (): mixed => $plan()['failed'][0]['statement'],
    'failed key name is preserved' => static fn (): mixed => $plan()['failed'][0]['key_name'],
    'failed statement records json error text' => static fn (): mixed => str_contains($plan()['failed'][0]['error'], 'JSON'),
    'failed statement database restoration is explicit' => static fn (): mixed => $plan()['failed'][0]['database_restored'],
    'failed rollback targets current savepoint' => static fn (): mixed => $plan()['failed'][0]['rollback']['savepoint'],
    'failed rollback restores broken key page only' => static fn (): mixed => $plan()['failed'][0]['rollback']['restored_page_numbers'],
    'failed rollback discards failed wal frame only' => static fn (): mixed => array_column($plan()['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'),
    'failed rollback keeps transaction active' => static fn (): mixed => $plan()['failed'][0]['rollback']['transaction_active_after'],
    'failed rollback clears statement journal' => static fn (): mixed => $plan()['failed'][0]['rollback']['statement_journal_cleared'],
    'statement plans are retained for applied statements' => static fn (): mixed => count($plan()['statement_plans']),
    'first statement plan status is applied' => static fn (): mixed => $plan()['statement_plans'][0]['status'],
    'second statement plan status is applied' => static fn (): mixed => $plan()['statement_plans'][1]['status'],
    'first statement plan rollback frame starts at zero' => static fn (): mixed => $plan()['statement_plans'][0]['rollback_to_wal_frame'],
    'second statement plan rollback frame starts after first statement' => static fn (): mixed => $plan()['statement_plans'][1]['rollback_to_wal_frame'],
    'first statement plan restore page is key page' => static fn (): mixed => $plan()['statement_plans'][0]['restored_page_numbers'],
    'second statement plan restore page is key page' => static fn (): mixed => $plan()['statement_plans'][1]['restored_page_numbers'],
    'final rows retain row count' => static fn (): mixed => count($plan()['final_rows']),
    'final text row contains enabled true' => static fn (): mixed => $decodeSetting($plan()['final_rows'][0]['key_value'])['enabled'],
    'final jsonb row contains nested accent' => static fn (): mixed => $decodeSetting($plan()['final_rows'][1]['key_value'])['colors']['accent']['name'],
    'failed final row remains malformed original text' => static fn (): mixed => $plan()['final_rows'][2]['key_value'],
    'database bytes changed after applied statements' => static fn (): mixed => $plan()['database_changed'],
    'savepoint state has transaction frame' => static fn (): mixed => $plan()['savepoint_state'][0]['name'],
    'savepoint state has current savepoint frame' => static fn (): mixed => $plan()['savepoint_state'][1]['name'],
    'savepoint frame tracks applied pages after failed statement rollback' => static fn (): mixed => $plan()['savepoint_state'][1]['page_numbers'],
    'failed statement journal is cleared from state' => static fn (): mixed => array_column($plan()['statement_journals'], 'name'),
    'rollback to savepoint restores applied pages' => static fn (): mixed => $plan()['rollback_to_savepoint']['restored_page_numbers'],
    'rollback to savepoint has no missing page images' => static fn (): mixed => $plan()['rollback_to_savepoint']['missing_page_numbers'],
    'rollback to savepoint keeps transaction active' => static fn (): mixed => $plan()['rollback_to_savepoint']['transaction_active_after'],
    'wal rollback to savepoint discards applied frames' => static fn (): mixed => array_column($plan()['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'),
    'wal rollback to savepoint points at frame before savepoint' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['rollback_to_frame'],
    'commit plan includes transaction and savepoint' => static fn (): mixed => $plan()['commit']['committed_frame_names'],
    'commit plan commits applied pages only' => static fn (): mixed => $plan()['commit']['committed_page_numbers'],
    'commit plan releases one savepoint' => static fn (): mixed => $plan()['commit']['released_savepoint_count'],
    'dependencies include json mutation' => static fn (): mixed => in_array('sqlite-json-mutation-current', $plan()['dependencies'], true),
    'dependencies include statement journal' => static fn (): mixed => in_array('sqlite-savepoint-statement-journal-current', $plan()['dependencies'], true),
    'all valid mutations report ready' => static fn (): mixed => $plan([], $currentRows(), [
        ['key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true],
    ])['status'],
    'implicit wal frame increments from one' => static fn (): mixed => $plan([], $currentRows(), [
        ['key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true],
        ['key_name' => 'plugin_settings', 'path' => '$.version', 'value' => 2],
    ])['applied'][1]['wal_frame_index'],
    'missing key rolls back its statement' => static fn (): mixed => $plan([], $currentRows(), [
        ['statement' => 'missing', 'key_name' => 'missing_key', 'path' => '$.enabled', 'value' => true],
    ])['failed'][0]['statement'],
    'invalid page size is rejected' => static function () use ($currentRows, $mutations): mixed {
        try {
            SQLiteJsonImportSavepointPlan::plan($currentRows(), $mutations(), ['page_size' => 513]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'duplicate current key names are rejected' => static function () use ($currentRows, $mutations): mixed {
        $rows = $currentRows();
        $rows[] = $rows[0] + ['setting_id' => 200];
        try {
            SQLiteJsonImportSavepointPlan::plan($rows, $mutations());
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'decreasing wal frame is rejected through statement rollback' => static fn (): mixed => $plan([], $currentRows(), [
        ['key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true, 'wal_frame_index' => 2],
        ['key_name' => 'plugin_settings', 'path' => '$.version', 'value' => 2, 'wal_frame_index' => 1],
    ])['failed'][0]['statement'],
    'custom savepoint name is preserved' => static fn (): mixed => $plan(['savepoint' => 'plugin_json'])['savepoint'],
    'custom transaction name is preserved' => static fn (): mixed => $plan(['transaction' => 'app_import'])['transaction'],
];

$expected = [
    'status reports partial rollback when one json statement fails' => 'partial_rollback',
    'transaction name defaults to application json import' => 'application_json_import',
    'savepoint name defaults to current json batch' => 'current_json_batch',
    'page size is preserved' => 512,
    'two json statements are applied' => 2,
    'one malformed json statement fails' => 1,
    'first applied statement name is preserved' => 'enable_plugin',
    'second applied statement name is preserved' => 'theme_accent',
    'first applied key is plugin settings' => 'plugin_settings',
    'second applied key is theme mods' => 'theme_mods_twenty',
    'first applied page number is current row page' => 2,
    'second applied page number is current row page' => 3,
    'first applied wal frame is explicit' => 1,
    'second applied wal frame is explicit' => 2,
    'json_set text output canonicalizes enabled true' => true,
    'json_set text output preserves version' => 1,
    'json_set text output preserves modules' => ['core'],
    'jsonb_set keeps jsonb blob output' => true,
    'jsonb_set nested subtype value is decoded' => 'green',
    'jsonb_set nested subtype value keeps contrast' => 7,
    'jsonb_set preserves sibling layout' => 'wide',
    'failed statement name is preserved' => 'broken_payload',
    'failed key name is preserved' => 'broken_plugin_settings',
    'failed statement records json error text' => true,
    'failed statement database restoration is explicit' => true,
    'failed rollback targets current savepoint' => 'current_json_batch',
    'failed rollback restores broken key page only' => [4],
    'failed rollback discards failed wal frame only' => [3],
    'failed rollback keeps transaction active' => true,
    'failed rollback clears statement journal' => true,
    'statement plans are retained for applied statements' => 2,
    'first statement plan status is applied' => 'applied',
    'second statement plan status is applied' => 'applied',
    'first statement plan rollback frame starts at zero' => 0,
    'second statement plan rollback frame starts after first statement' => 1,
    'first statement plan restore page is key page' => [2],
    'second statement plan restore page is key page' => [3],
    'final rows retain row count' => 3,
    'final text row contains enabled true' => true,
    'final jsonb row contains nested accent' => 'green',
    'failed final row remains malformed original text' => '{"enabled":',
    'database bytes changed after applied statements' => true,
    'savepoint state has transaction frame' => 'application_json_import',
    'savepoint state has current savepoint frame' => 'current_json_batch',
    'savepoint frame tracks applied pages after failed statement rollback' => [2, 3],
    'failed statement journal is cleared from state' => ['enable_plugin', 'theme_accent'],
    'rollback to savepoint restores applied pages' => [2, 3],
    'rollback to savepoint has no missing page images' => [],
    'rollback to savepoint keeps transaction active' => true,
    'wal rollback to savepoint discards applied frames' => [1, 2],
    'wal rollback to savepoint points at frame before savepoint' => 0,
    'commit plan includes transaction and savepoint' => ['application_json_import', 'current_json_batch'],
    'commit plan commits applied pages only' => [2, 3],
    'commit plan releases one savepoint' => 1,
    'dependencies include json mutation' => true,
    'dependencies include statement journal' => true,
    'all valid mutations report ready' => 'ready',
    'implicit wal frame increments from one' => 2,
    'missing key rolls back its statement' => 'missing',
    'invalid page size is rejected' => 'rejected',
    'duplicate current key names are rejected' => 'rejected',
    'decreasing wal frame is rejected through statement rollback' => 'json_import_2',
    'custom savepoint name is preserved' => 'plugin_json',
    'custom transaction name is preserved' => 'app_import',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application json import savepoint current next31 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
