<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSchemaJsonWalImportPlan;

$schemaSql = <<<'SQL'
CREATE TABLE app_settings (
  setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
  key_name TEXT NOT NULL UNIQUE,
  key_value TEXT NOT NULL,
  load_policy TEXT NOT NULL DEFAULT 'yes'
);
CREATE TABLE IF NOT EXISTS app_import_log (
  id INTEGER PRIMARY KEY,
  message TEXT NOT NULL
);
CREATE INDEX app_settings_load_policy_key_name ON app_settings(load_policy, key_name);
CREATE TRIGGER app_settings_ai AFTER INSERT ON app_settings BEGIN
  INSERT INTO app_import_log(message) VALUES('inserted setting');
END;
SQL;

$rows = static fn (): array => [
    [
        'setting_id' => 1,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"version":1}',
        'load_policy' => 'yes',
        'page_number' => 2,
    ],
    [
        'setting_id' => 65,
        'key_name' => 'theme_mods_twenty',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['colors' => ['accent' => 'blue']])),
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
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'theme_accent',
        'key_name' => 'theme_mods_twenty',
        'function' => 'jsonb_set',
        'path' => '$.colors.accent',
        'value' => new SQLiteJsonSubtypeValue('{"name":"green"}'),
        'wal_frame_index' => 2,
    ],
    [
        'statement' => 'broken_payload',
        'key_name' => 'broken_plugin_settings',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 3,
    ],
];

$plan = static fn (array $options = [], ?array $inputRows = null, ?array $inputMutations = null, ?string $sql = null, array $existing = []): array => SQLiteSchemaJsonWalImportPlan::plan(
    $sql ?? $schemaSql,
    $inputRows ?? $rows(),
    $inputMutations ?? $mutations(),
    $existing,
    array_replace_recursive([
        'database_path' => '/tmp/app-current-next41.sqlite',
        'page_size' => 512,
        'schema' => ['schema_version' => 7, 'data_version' => 3],
    ], $options)
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status reports partial json rollback' => static fn (): mixed => $plan()['status'],
    'database path is preserved' => static fn (): mixed => $plan()['database_path'],
    'page size is preserved' => static fn (): mixed => $plan()['page_size'],
    'schema applied count includes table index trigger roots' => static fn (): mixed => $plan()['schema_applied_count'],
    'json applied count excludes failed statement' => static fn (): mixed => $plan()['json_applied_count'],
    'json failed count is recorded' => static fn (): mixed => $plan()['json_failed_count'],
    'yielded count includes schema and json events' => static fn (): mixed => $plan()['yielded_count'],
    'schema plan keeps schema version before' => static fn (): mixed => $plan()['schema']['schema_version_before'],
    'schema cookie after increments per object' => static fn (): mixed => $plan()['schema_cookie']['after'],
    'schema cookie changed flag is true' => static fn (): mixed => $plan()['schema_cookie']['changed'],
    'data cookie before is preserved' => static fn (): mixed => $plan()['data_cookie']['before'],
    'data cookie after increments once for schema' => static fn (): mixed => $plan()['data_cookie']['after'],
    'data cookie changed by json database bytes' => static fn (): mixed => $plan()['data_cookie']['changed'],
    'schema frames count only table and index roots' => static fn (): mixed => count(array_filter($plan()['wal_frames'], static fn (array $frame): bool => $frame['kind'] === 'schema')),
    'json frames count only applied json writes' => static fn (): mixed => count(array_filter($plan()['wal_frames'], static fn (array $frame): bool => $frame['kind'] === 'json_setting')),
    'failed frames count only discarded json writes' => static fn (): mixed => count($plan()['failed_wal_frames']),
    'wal frame count excludes discarded failed frame' => static fn (): mixed => $plan()['wal_frame_count'],
    'next wal frame accounts for discarded failed frame' => static fn (): mixed => $plan()['next_wal_frame'],
    'first wal frame is schema' => static fn (): mixed => $plan()['wal_frames'][0]['kind'],
    'first wal frame object is app_import_log' => static fn (): mixed => $plan()['wal_frames'][0]['object'],
    'first wal frame page starts after current pages' => static fn (): mixed => $plan()['wal_frames'][0]['page_number'],
    'last schema frame is commit marker' => static fn (): mixed => $plan()['wal_frames'][2]['commit'],
    'first json frame follows schema frames' => static fn (): mixed => $plan()['wal_frames'][3]['frame_index'],
    'first json frame keeps statement' => static fn (): mixed => $plan()['wal_frames'][3]['statement'],
    'first json frame maps source wal frame' => static fn (): mixed => $plan()['wal_frames'][3]['source_wal_frame'],
    'second json frame keeps path' => static fn (): mixed => $plan()['wal_frames'][4]['json_path'],
    'second json frame is commit marker' => static fn (): mixed => $plan()['wal_frames'][4]['commit'],
    'failed wal frame keeps discarded kind' => static fn (): mixed => $plan()['failed_wal_frames'][0]['kind'],
    'failed wal frame is not committed' => static fn (): mixed => $plan()['failed_wal_frames'][0]['committed'],
    'failed wal frame maps source frame' => static fn (): mixed => $plan()['failed_wal_frames'][0]['source_wal_frame'],
    'checkpoint admission is ready for partial rollback' => static fn (): mixed => $plan()['checkpoint_admission']['ready'],
    'checkpoint admission reason names ready frames' => static fn (): mixed => $plan()['checkpoint_admission']['reason'],
    'checkpoint admission frame count matches wal frame count' => static fn (): mixed => $plan()['checkpoint_admission']['frame_count'],
    'checkpoint admission dirty page count matches dirty pages' => static fn (): mixed => $plan()['checkpoint_admission']['dirty_page_count'],
    'checkpoint admission requires exclusive lock' => static fn (): mixed => $plan()['checkpoint_admission']['requires_exclusive_lock'],
    'autocheckpoint is not reached by default' => static fn (): mixed => $plan()['checkpoint_admission']['wal_autocheckpoint_reached'],
    'small autocheckpoint is reached' => static fn (): mixed => $plan(['wal_autocheckpoint' => 2])['checkpoint_admission']['wal_autocheckpoint_reached'],
    'dirty pages merge schema and json pages' => static fn (): mixed => $plan()['dirty_pages'],
    'commit order writes schema first' => static fn (): mixed => $plan()['commit_order'][0],
    'commit order writes json pages second' => static fn (): mixed => $plan()['commit_order'][1],
    'commit order syncs wal' => static fn (): mixed => in_array('sync_wal', $plan()['commit_order'], true),
    'commit order updates schema cookie' => static fn (): mixed => in_array('update_schema_cookie', $plan()['commit_order'], true),
    'schema yield first phase' => static fn (): mixed => $plan()['yielded'][0]['phase'],
    'schema yield first status' => static fn (): mixed => $plan()['yielded'][0]['status'],
    'json applied yield names setting' => static fn (): mixed => $plan()['yielded'][4]['key_name'],
    'json failed yield is rolled back' => static fn (): mixed => $plan()['yielded'][6]['status'],
    'json failed yield names statement' => static fn (): mixed => $plan()['yielded'][6]['statement'],
    'schema skipped yield is included' => static fn (): mixed => $plan([], $rows(), [], 'CREATE TABLE IF NOT EXISTS app_settings(id INTEGER);', ['app_settings' => []])['yielded'][0]['status'],
    'noop status when schema and json are empty' => static fn (): mixed => $plan([], $rows(), [], '', [])['status'],
    'ready status when all json is valid' => static fn (): mixed => $plan([], $rows(), [
        ['statement' => 'enable_plugin', 'key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true],
    ])['status'],
    'ready plan has no failed frames' => static fn (): mixed => $plan([], $rows(), [
        ['statement' => 'enable_plugin', 'key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true],
    ])['failed_wal_frames'],
    'json plan final rows are exposed' => static fn (): mixed => $valueAt($plan(), 'json.final_rows.count'),
    'schema ordered names are exposed' => static fn (): mixed => $plan()['schema']['ordered_names'],
    'dependencies include schema bulk import' => static fn (): mixed => in_array('sqlite-application-schema-bulk-import', $plan()['dependencies'], true),
    'dependencies include json savepoint import' => static fn (): mixed => in_array('sqlite-application-json-import-savepoint', $plan()['dependencies'], true),
    'dependencies include WAL import yield' => static fn (): mixed => in_array('sqlite-wal-import-yield', $plan()['dependencies'], true),
    'custom json transaction option is passed through' => static fn (): mixed => $plan(['json' => ['transaction' => 'app_schema_json']])['json']['transaction'],
    'custom json savepoint option is passed through' => static fn (): mixed => $plan(['json' => ['savepoint' => 'plugin_batch']])['json']['savepoint'],
    'custom schema next rootpage is honored' => static fn (): mixed => $plan(['schema' => ['next_rootpage' => 20]])['wal_frames'][0]['page_number'],
    'invalid relative database path is rejected' => static function () use ($plan): mixed {
        try {
            $plan(['database_path' => 'relative.sqlite']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'invalid page size is rejected' => static function () use ($plan): mixed {
        try {
            $plan(['page_size' => 513]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'negative wal autocheckpoint is rejected' => static function () use ($plan): mixed {
        try {
            $plan(['wal_autocheckpoint' => -1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
];

$expected = [
    'status reports partial json rollback' => 'partial_json_rollback',
    'database path is preserved' => '/tmp/app-current-next41.sqlite',
    'page size is preserved' => 512,
    'schema applied count includes table index trigger roots' => 4,
    'json applied count excludes failed statement' => 2,
    'json failed count is recorded' => 1,
    'yielded count includes schema and json events' => 7,
    'schema plan keeps schema version before' => 7,
    'schema cookie after increments per object' => 11,
    'schema cookie changed flag is true' => true,
    'data cookie before is preserved' => 3,
    'data cookie after increments once for schema' => 4,
    'data cookie changed by json database bytes' => true,
    'schema frames count only table and index roots' => 3,
    'json frames count only applied json writes' => 2,
    'failed frames count only discarded json writes' => 1,
    'wal frame count excludes discarded failed frame' => 5,
    'next wal frame accounts for discarded failed frame' => 7,
    'first wal frame is schema' => 'schema',
    'first wal frame object is app_import_log' => 'app_import_log',
    'first wal frame page starts after current pages' => 6,
    'last schema frame is commit marker' => true,
    'first json frame follows schema frames' => 4,
    'first json frame keeps statement' => 'enable_plugin',
    'first json frame maps source wal frame' => 1,
    'second json frame keeps path' => '$.colors.accent',
    'second json frame is commit marker' => true,
    'failed wal frame keeps discarded kind' => 'discarded_json_setting',
    'failed wal frame is not committed' => false,
    'failed wal frame maps source frame' => 3,
    'checkpoint admission is ready for partial rollback' => true,
    'checkpoint admission reason names ready frames' => 'schema_json_import_frames_ready',
    'checkpoint admission frame count matches wal frame count' => 5,
    'checkpoint admission dirty page count matches dirty pages' => 5,
    'checkpoint admission requires exclusive lock' => true,
    'autocheckpoint is not reached by default' => false,
    'small autocheckpoint is reached' => true,
    'dirty pages merge schema and json pages' => [2, 3, 5, 6, 7],
    'commit order writes schema first' => 'write_schema_pages',
    'commit order writes json pages second' => 'write_json_setting_pages',
    'commit order syncs wal' => true,
    'commit order updates schema cookie' => true,
    'schema yield first phase' => 'schema',
    'schema yield first status' => 'applied',
    'json applied yield names setting' => 'plugin_settings',
    'json failed yield is rolled back' => 'rolled_back',
    'json failed yield names statement' => 'broken_payload',
    'schema skipped yield is included' => 'skipped',
    'noop status when schema and json are empty' => 'noop',
    'ready status when all json is valid' => 'ready',
    'ready plan has no failed frames' => [],
    'json plan final rows are exposed' => 3,
    'schema ordered names are exposed' => ['app_import_log', 'app_settings', 'app_settings_load_policy_key_name', 'app_settings_ai'],
    'dependencies include schema bulk import' => true,
    'dependencies include json savepoint import' => true,
    'dependencies include WAL import yield' => true,
    'custom json transaction option is passed through' => 'app_schema_json',
    'custom json savepoint option is passed through' => 'plugin_batch',
    'custom schema next rootpage is honored' => 21,
    'invalid relative database path is rejected' => 'rejected',
    'invalid page size is rejected' => 'rejected',
    'negative wal autocheckpoint is rejected' => 'rejected',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application schema json wal import current next41 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
