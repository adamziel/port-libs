<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPartialIndexCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';
$optionTableDefaultSourceFiles = [
    $sourceRoot . '/SQLiteMultiColumnRangePlan.php',
    $sourceRoot . '/SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php',
];

$legacyOptionTableDefaultMatches = static function () use ($optionTableDefaultSourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_option',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($optionTableDefaultSourceFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }
        if (preg_match_all($pattern, $contents, $fileMatches) < 1) {
            continue;
        }
        $relative = str_replace($libsqliteRoot . '/', '', $file);
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$match}";
        }
    }

    return $matches;
};

$settingsRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'site_title', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'no'],
];
$settingsIndex = [
    ['rowid' => 1, 'load_policy' => 'yes', 'key_name' => 'base_url'],
    ['rowid' => 2, 'load_policy' => 'yes', 'key_name' => 'site_title'],
];
$settingsPredicate = new SQLiteIndexPredicate('load_policy', SQLiteIndexPredicate::EQUALS, 'yes');

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x61000001, 0x61000002);
$checksum = SQLiteWal::checksumPair($prefix, false);
$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 1),
    $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)', 2),
    $record('trigger', 'app_settings_ai', 'app_settings', 0, "CREATE TRIGGER app_settings_ai AFTER UPDATE ON app_settings BEGIN INSERT INTO sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('index', 'app_settings_load_policy', 'app_settings', 5, new.key_value); END", 3),
]);
$schemaWal = [
    'main' => [
        'wal' => SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true),
        'database_bytes' => $page('main schema before') . $page('settings before'),
        'database_path' => '/tmp/app-settings.sqlite',
        'transactions' => [[
            'pages' => [1 => $page('main schema after'), 2 => $page('settings after')],
            'database_page_count' => 2,
            'commit' => true,
        ]],
        'watch_pages' => [1, 2],
    ],
];
$schemaCache = [
    'main' => ['schema_cookie' => 11, 'tables' => ['sqlite_schema', 'app_settings'], 'file' => '/tmp/app-settings.sqlite'],
];
$triggerNewRow = [
    'setting_id' => 4,
    'key_name' => 'module_registry',
    'key_value' => 'CREATE INDEX app_settings_load_policy ON app_settings(load_policy)',
    'load_policy' => 'yes',
];

return [
    'source-neutral option table default source files contain no hardcoded wp table defaults' => static fn (TestRunner $t) => $t->same([], $legacyOptionTableDefaultMatches()),
    'source-neutral defaults pragma table is app settings' => static function (TestRunner $t) use ($settingsRows, $settingsIndex, $settingsPredicate): void {
        $page = SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
            $settingsRows,
            $settingsIndex,
            $settingsPredicate,
            ['load_policy', 'key_name'],
        );

        $t->same('app_settings', $page['current_source']['table']);
        $t->same('idx_app_settings_partial', $page['current_source']['index']);
        $t->same(['load_policy', 'key_name'], $page['current_source']['index_columns']);
        $t->same('ok', $page['status']);
    },
    'source-neutral defaults attach prepared table is app settings' => static function (TestRunner $t) use ($catalog, $schemaWal, $schemaCache, $triggerNewRow): void {
        $plan = SQLiteAttachTempWalSchemaTriggerPlan::plan(
            catalog: $catalog,
            triggerName: 'app_settings_ai',
            schemaWal: $schemaWal,
            schemaCache: $schemaCache,
            newRow: $triggerNewRow,
        );

        $t->same(true, $plan['schema_cache']['prepared_tables']['app_settings']['found']);
        $t->same(true, $plan['schema_cache']['prepared_tables']['app_settings']['requires_reprepare']);
        $t->same(['main'], $plan['reprepare_schemas']);
        $t->same('planned', $plan['status']);
    },
    'multicolumn range fallback scan uses indexed application table name' => static function (TestRunner $t): void {
        $plan = SQLiteMultiColumnRangePlan::stat4RangeOrder(
            [[
                'name' => 'idx_app_settings_load_key',
                'rootPage' => 701,
                'estimatedRows' => 10,
                'sql' => 'CREATE INDEX idx_app_settings_load_key ON app_settings(load_policy, key_name)',
            ]],
            ['operator' => '=', 'left' => ['column' => 'missing_column'], 'right' => 'x'],
            [['column' => 'key_name']],
            ['key_name'],
        );

        $t->same('no-usable-plan', $plan['status']);
        $t->same('SCAN app_settings USE TEMP B-TREE FOR ORDER BY', $plan['detail']);
    },
    'trigger view returning defaults use application settings names' => static function (TestRunner $t): void {
        $plan = SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeViewSavepointReturningRollback(
            [['key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes']],
            [['key_name' => 'cache_policy', 'key_value' => 'fresh', 'load_policy' => 'yes']],
            [],
            [
                'name' => 'app_load_policy_view',
                'source' => 'main@app-view-current',
                'columns' => ['key_name', 'key_value', 'load_policy'],
                'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
            ],
            [
                'name' => 'app_load_policy_view',
                'source' => 'main@app-view-next',
                'columns' => ['key_name', 'key_value', 'load_policy'],
                'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
            ],
            [],
            ['key_name'],
        );

        $t->same('key_name', $plan['key']);
        $t->same('app_view_import', $plan['savepoint']);
        $t->same('app_settings_view_io_update', $plan['trigger']);
        $t->same(['base_url', 'cache_policy'], array_column($plan['after_savepoint'], 'key_name'));
    },
    'mapped view returning defaults use application view names' => static function (TestRunner $t): void {
        $plan = SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeMappedViewReturningSavepoint(
            [['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes']],
            [['import_id' => 11, 'tenant' => 1, 'name' => 'base_url', 'value' => 'https://new.test', 'policy' => 'yes']],
            [],
            ['import_id' => 'setting_id', 'tenant' => 'tenant_id', 'name' => 'key_name', 'value' => 'key_value', 'policy' => 'load_policy'],
            ['tenant_id', 'key_name'],
            [],
            [['expr' => 'new.key_name', 'as' => 'name']],
        );

        $t->same('app_view_returning', $plan['savepoint']);
        $t->same('app_setting_import_view', $plan['view']);
        $t->same(['base_url'], array_column($plan['returning_rows'], 'name'));
    },
    'utf16 source pattern diagnostic expression uses application settings names' => static function (TestRunner $t): void {
        $encode = static fn (string $value, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding);
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
            [['option_id' => 1, 'option_name_bytes' => $encode('module_cache', 2), 'text_encoding' => 2]],
            [['option_id' => 1, 'option_name_bytes' => $encode('module_cache', 3), 'text_encoding' => 3]],
            ['option_id' => 9, 'option_value_bytes' => $encode('module%', 2), 'text_encoding' => 2],
            ['option_id' => 9, 'option_value_bytes' => $encode('module%', 3), 'text_encoding' => 3],
        );

        $t->same('rtrim(key_name) COLLATE NOCASE LIKE (SELECT key_value FROM app_settings WHERE key_name = ?)', $plan['expression']);
    },
];
