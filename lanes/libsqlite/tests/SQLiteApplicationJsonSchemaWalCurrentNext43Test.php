<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteJsonSchemaWalPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x43434343;
$salt2 = 0x43435656;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header and app_settings root before json schema import')
    . $page('current active_plugins json option before import')
    . $page('current theme_mods json option before import')
    . $page('current load_policy index before import');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 43, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$baseWalBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('wal draft active_plugins before json schema import'));
    $bytes = $appendFrame($bytes, $seed, 3, 4, $page('wal committed theme_mods before json schema import'));

    return $bytes;
};

$schemaSql = <<<'SQL'
CREATE TABLE app_settings (
  setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
  key_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  key_value TEXT NOT NULL DEFAULT '',
  load_policy TEXT NOT NULL DEFAULT 'yes',
  CHECK (json_valid(key_value) OR key_name NOT IN ('plugin_json_settings','theme_mods_twentytwentyfour'))
);
CREATE UNIQUE INDEX app_settings_key_name ON app_settings(key_name COLLATE NOCASE);
CREATE INDEX app_settings_load_policy_name ON app_settings(load_policy, key_name);
CREATE VIEW app_json_settings AS SELECT key_name, json_extract(key_value, '$') AS body FROM app_settings;
SQL;

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'active_plugins', 'key_value' => '["classic-editor/classic-editor.php"]', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'theme_mods_twentytwentyfour', 'key_value' => '{"nav_menu_locations":[]}', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'blog_public', 'key_value' => '1', 'load_policy' => 'no'],
];
$importRows = static fn (): array => [
    ['key_name' => 'plugin_json_settings', 'key_value' => '{"enabled":true,"mode":"safe"}', 'load_policy' => 'no'],
    ['key_name' => 'theme_mods_twentytwentyfour', 'key_value' => '{"custom_css_post_id":12}', 'load_policy' => 'yes'],
    ['key_name' => 'broken_plugin_json', 'key_value' => '{"enabled":', 'load_policy' => 'no'],
    ['key_name' => 'blog_public', 'key_value' => '0', 'load_policy' => 'no'],
];
$jsonNames = ['plugin_json_settings', 'theme_mods_twentytwentyfour', 'broken_plugin_json'];
$plan = static fn (): array => SQLiteJsonSchemaWalPlan::currentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $schemaSql,
    $currentRows(),
    $importRows(),
    [2, 3, 4, 5, 6],
    $jsonNames,
    ['schema_version' => 43, 'data_version' => 7, 'next_rootpage' => 9],
);
$nextWal = static fn (): SQLiteWal => SQLiteWal::parse($plan()['wal_import']['append']['wal_bytes'], null, true);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'planned'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'application_json_schema_wal_current_next'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'schema object names' => [static fn (): mixed => $plan()['schema_object_names'], ['app_settings', 'app_settings_key_name', 'app_settings_load_policy_name', 'app_json_settings']],
    'schema statement count' => [static fn (): mixed => $plan()['schema']['statement_count'], 4],
    'schema applied count' => [static fn (): mixed => $plan()['schema']['applied_count'], 4],
    'schema skipped count' => [static fn (): mixed => $plan()['schema']['skipped_count'], 0],
    'schema version after' => [static fn (): mixed => $plan()['schema_version_after'], 47],
    'data version after' => [static fn (): mixed => $plan()['data_version_after'], 8],
    'schema transaction begin' => [static fn (): mixed => $plan()['schema']['transaction']['begin'], 'BEGIN IMMEDIATE'],
    'schema table rootpage' => [static fn (): mixed => $plan()['schema']['objects'][0]['rootpage'], 9],
    'schema key name index rootpage' => [static fn (): mixed => $plan()['schema']['objects'][1]['rootpage'], 10],
    'schema load_policy index rootpage' => [static fn (): mixed => $plan()['schema']['objects'][2]['rootpage'], 11],
    'schema view rootpage' => [static fn (): mixed => $plan()['schema']['objects'][3]['rootpage'], 0],
    'json key names preserved' => [static fn (): mixed => $plan()['json_key_names'], $jsonNames],
    'accepted import count' => [static fn (): mixed => $plan()['accepted_import_count'], 3],
    'rejected import count' => [static fn (): mixed => $plan()['rejected_import_count'], 1],
    'rejected name' => [static fn (): mixed => $plan()['rejected_rows'][0]['key_name'], 'broken_plugin_json'],
    'rejected reason' => [static fn (): mixed => $plan()['rejected_rows'][0]['reason'], 'malformed_json_key_value'],
    'rejected json error' => [static fn (): mixed => $plan()['rejected_rows'][0]['error'], 'Syntax error'],
    'wal import status' => [static fn (): mixed => $plan()['wal_import']['status'], 'planned'],
    'wal inserted names' => [static fn (): mixed => $plan()['inserted_key_names'], ['plugin_json_settings']],
    'wal updated names' => [static fn (): mixed => $plan()['updated_key_names'], ['blog_public', 'theme_mods_twentytwentyfour']],
    'load_policy names' => [static fn (): mixed => $plan()['load_policy_yes_key_names'], ['active_plugins', 'theme_mods_twentytwentyfour']],
    'wal database page count' => [static fn (): mixed => $plan()['wal_database_page_count'], 6],
    'wal last commit frame' => [static fn (): mixed => $plan()['wal_last_commit_frame'], 7],
    'current reader sources' => [static fn (): mixed => $plan()['current_reader_sources'], ['wal', 'wal', 'database', 'error', 'error']],
    'next reader sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'next frame indexes' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [3, 4, 5, 6, 7]],
    'wal append frame count' => [static fn (): mixed => $plan()['wal_import']['append']['appended_frame_count'], 5],
    'wal append start frame' => [static fn (): mixed => $plan()['wal_import']['append']['start_frame'], 3],
    'wal append end frame' => [static fn (): mixed => $plan()['wal_import']['append']['end_frame'], 7],
    'wal append commit count' => [static fn (): mixed => $plan()['wal_import']['append']['committed_transaction_count'], 1],
    'wal page active plugins retained' => [static fn (): mixed => str_contains($plan()['wal_import']['next_reader'][0]['image'], 'classic-editor'), true],
    'wal page blog public updated' => [static fn (): mixed => str_contains($plan()['wal_import']['next_reader'][1]['image'], '"key_value":"0"'), true],
    'wal page json settings inserted' => [static fn (): mixed => str_contains($plan()['wal_import']['next_reader'][2]['image'], 'plugin_json_settings'), true],
    'wal page json settings value' => [static fn (): mixed => str_contains($plan()['wal_import']['next_reader'][2]['image'], '\\"mode\\":\\"safe\\"'), true],
    'wal page theme mods updated' => [static fn (): mixed => str_contains($plan()['wal_import']['next_reader'][3]['image'], 'custom_css_post_id'), true],
    'wal page rejected value absent' => [static fn (): mixed => !str_contains($plan()['wal_import']['append']['wal_bytes'], 'broken_plugin_json'), true],
    'wal load_policy index excludes json settings' => [static fn (): mixed => !str_contains($plan()['wal_import']['next_reader'][4]['image'], 'plugin_json_settings'), true],
    'dependency has schema bulk import' => [static fn (): mixed => in_array('sqlite-schema-bulk-import', $plan()['dependencies'], true), true],
    'dependency has wal import' => [static fn (): mixed => in_array('application-settings-wal-import-current-next', $plan()['dependencies'], true), true],
    'dependency has current slice' => [static fn (): mixed => in_array('application-json-schema-wal-current-next', $plan()['dependencies'], true), true],
    'next wal frame count' => [static fn (): mixed => $nextWal()->frameCount(), 7],
    'next wal last commit' => [static fn (): mixed => $nextWal()->lastCommitFrame()?->index, 7],
    'next wal uncommitted count' => [static fn (): mixed => $nextWal()->uncommittedFrameCount(), 0],
    'next wal page three image has blog public' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 3, 7)['image'], 'blog_public'), true],
    'next wal page four image has plugin json' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 4, 7)['image'], 'plugin_json_settings'), true],
    'next wal page six image has load_policy index' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 6, 7)['image'], 'app_settings_load_policy'), true],
    'current wal cannot see inserted page' => [static function () use ($baseWal, $databaseBytes): mixed {
        try {
            $baseWal()->readerSnapshotPageImage($databaseBytes, 5, 2);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }

        return 'unexpected';
    }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['application json schema wal current next43 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['application json schema wal current next43 rejects invalid inputs'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $schemaSql, $currentRows, $importRows, $jsonNames): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonSchemaWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, 'CREATE TABLE app_posts(id INTEGER);', $currentRows(), $importRows(), [2], $jsonNames));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonSchemaWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $schemaSql, $currentRows(), $importRows(), [2], []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonSchemaWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $schemaSql, $currentRows(), $importRows(), [2], ['']));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonSchemaWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $schemaSql, $currentRows(), [['key_name' => 'broken_plugin_json', 'key_value' => '{"bad":']], [2], ['broken_plugin_json']));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonSchemaWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $schemaSql, $currentRows(), [['key_name' => '', 'key_value' => '{}']], [2], $jsonNames));
};

return $tests;
