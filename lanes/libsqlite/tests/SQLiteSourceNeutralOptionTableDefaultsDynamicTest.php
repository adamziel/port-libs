<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;
use PortLibs\LibSqlite\SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPartialIndexCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;
use PortLibs\LibSqlite\SQLiteTriggerReturningForeignKeySavepointPlan;
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
$jsonPathOptionDefaultSourceFiles = [
    $sourceRoot . '/SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteJsonPathIndexedUpdatePlan.php',
];
$triggerForeignKeyDefaultSourceFiles = [
    $sourceRoot . '/SQLiteTriggerForeignKeyReturningPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningForeignKeySavepointPlan.php',
];

$utf16KeyValuePlanLegacyDefaultMatches = static function () use ($libsqliteRoot): array {
    $reflection = new ReflectionClass(SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::class);
    $file = $reflection->getFileName();
    if ($file === false) {
        throw new RuntimeException('Unable to locate UTF-16 NOCASE LIKE RTRIM source file');
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    $method = $reflection->getMethod('keyValueRowKeyPlan');
    $source = implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1
    ));
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_option',
        'option_id',
        'option_name',
        'option_value',
        'autoload',
        'blog_id',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    if (preg_match_all($pattern, $source, $matches) < 1) {
        return [];
    }

    $relative = str_replace($libsqliteRoot . '/', '', $file);

    return array_map(static fn (string $match): string => "{$relative}: {$match}", $matches[0]);
};

$utf16LateKeyValuePlanLegacyDefaultMatches = static function () use ($libsqliteRoot): array {
    $reflection = new ReflectionClass(SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::class);
    $file = $reflection->getFileName();
    if ($file === false) {
        throw new RuntimeException('Unable to locate UTF-16 NOCASE LIKE RTRIM source file');
    }

    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    $start = strpos($contents, 'public static function keyValueRowKeyPreparedPatternSpacePlan(');
    $end = strpos($contents, 'public static function keyValueRowKeyNullPatternRebindPlan(', $start === false ? 0 : $start);
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Unable to isolate UTF-16 NOCASE LIKE RTRIM next217 through next233 source segment');
    }

    $source = substr($contents, $start, $end - $start);
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_option',
        'option_id',
        'option_name',
        'option_value',
        'autoload',
        'blog_id',
        'optionRowName',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    if (preg_match_all($pattern, $source, $matches) < 1) {
        return [];
    }

    $relative = str_replace($libsqliteRoot . '/', '', $file);

    return array_map(static fn (string $match): string => "{$relative}: {$match}", $matches[0]);
};

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

$jsonPathLegacyDefaultMatches = static function () use ($jsonPathOptionDefaultSourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_option',
        'option_id',
        'option_name',
        'option_value',
        'autoload',
        'blog_id',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($jsonPathOptionDefaultSourceFiles as $file) {
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

$triggerForeignKeyLegacyDefaultMatches = static function () use ($triggerForeignKeyDefaultSourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_option',
        'option_id',
        'option_name',
        'option_value',
        'autoload',
        'blog_id',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($triggerForeignKeyDefaultSourceFiles as $file) {
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
    'utf16 key value byte-order plan source uses neutral setting defaults' => static fn (TestRunner $t) => $t->same([], $utf16KeyValuePlanLegacyDefaultMatches()),
    'utf16 late key value source uses neutral setting defaults' => static fn (TestRunner $t) => $t->same([], $utf16LateKeyValuePlanLegacyDefaultMatches()),
    'source-neutral json path defaults use generic setting keys in source' => static fn (TestRunner $t) => $t->same([], $jsonPathLegacyDefaultMatches()),
    'source-neutral trigger foreign key returning defaults use generic setting rowids in source' => static fn (TestRunner $t) => $t->same([], $triggerForeignKeyLegacyDefaultMatches()),
    'trigger foreign key returning default rowid is setting id' => static function (TestRunner $t): void {
        $parents = [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'cache_policy', 'key_value' => 'stale', 'load_policy' => 'no'],
        ];
        $children = [
            ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source'],
            ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source'],
        ];

        $plan = SQLiteTriggerForeignKeyReturningPlan::updateParents(
            $parents,
            $children,
            ['setting_id' => static fn (array $row): int => (int) $row['setting_id'] + 100],
            static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'cascade'],
            [],
            ['setting_id', 'key_name'],
        );

        $t->same([1], array_column($plan['yielded'], 'old_key'));
        $t->same([101], array_column($plan['yielded'], 'new_key'));
        $t->same([101, 2], array_column($plan['parent'], 'setting_id'));
        $t->same([101, 2], array_column($plan['child'], 'setting_id'));
        $t->same(['setting_id' => 101, 'key_name' => 'base_url'], $plan['yielded'][0]['returning']);
    },
    'trigger returning foreign key savepoint default rowids are setting ids' => static function (TestRunner $t): void {
        $parents = [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'cache_policy', 'key_value' => 'stale', 'load_policy' => 'no'],
        ];
        $children = [
            ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source'],
            ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source'],
        ];

        $plan = SQLiteTriggerReturningForeignKeySavepointPlan::currentNextYield(
            $parents,
            $children,
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
            [],
            [
                'operation' => 'update',
                'savepoint' => 'app_settings_import',
                'where' => static fn (array $row): bool => ($row['key_name'] ?? null) === 'cache_policy',
                'assignments' => ['setting_id' => 202],
                'returning' => ['setting_id', 'key_name', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']],
                'rollback_on_deferred_violation' => true,
                'page_images' => [2 => str_repeat('P', 128)],
                'dirty_pages' => [2 => str_repeat('D', 128)],
                'wal_start_frame' => 4,
                'wal_frames' => [['frame_index' => 5, 'page_number' => 2]],
            ],
        );

        $t->same('rolled-back', $plan['status']);
        $t->same([1, 202], $plan['current_rowids']);
        $t->same([1, 2], $plan['next_rowids']);
        $t->same(['setting_id' => 202, 'key_name' => 'cache_policy', 'old_setting_id' => 2], $plan['attempted_yielded'][0]['returning']);
        $t->same(true, $plan['yield_suppressed_by_rollback']);
    },
    'source-neutral json path compare defaults read setting key values' => static function (TestRunner $t): void {
        $plan = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare(
            [
                ['setting_id' => 1, 'key_name' => 'module_registry', 'key_value' => '{"modules":[{"slug":"cache"},{"slug":"forms"}]}'],
            ],
            [
                ['setting_id' => 1, 'key_name' => 'module_registry', 'key_value' => '{"modules":[{"slug":"cache"},{"slug":"search"}]}'],
            ],
            ['$.modules[#-1].slug'],
        );

        $t->same('module_registry', $plan['current']['rows'][1]['keyName']);
        $t->same('forms', $plan['current']['rows'][1]['paths']['$.modules[#-1].slug']['value']);
        $t->same('search', $plan['next']['rows'][1]['paths']['$.modules[#-1].slug']['value']);
        $t->same([1], $plan['current']['foundRowids']);
        $t->same(true, $plan['reprepareRequired']);
    },
    'source-neutral json path indexed update defaults mutate key value column' => static function (TestRunner $t): void {
        $plan = SQLiteJsonPathIndexedUpdatePlan::plan(
            [
                ['setting_id' => 1, 'key_name' => 'module_alpha', 'key_value' => '{"module":{"enabled":false,"version":1}}'],
                ['setting_id' => 2, 'key_name' => 'module_beta', 'key_value' => '{"module":{"enabled":true,"version":2}}'],
            ],
            [
                ['name' => 'idx_module_enabled', 'path' => '$.module.enabled'],
                ['name' => 'idx_module_version', 'path' => '$.module.version'],
            ],
            [
                ['rowid' => 1, 'mutations' => [
                    ['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true],
                    ['function' => 'json_set', 'path' => '$.module.version', 'value' => 3],
                ]],
            ],
        );

        $t->same(1, $plan['changes']);
        $t->same('{"module":{"enabled":true,"version":3}}', $plan['after'][0]['key_value']);
        $t->same(['idx_module_enabled', 'idx_module_version'], array_column($plan['index_updates'], 'index'));
        $t->same([0, 1], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
        $t->same([1, 3], [$plan['index_updates'][1]['current'], $plan['index_updates'][1]['next']]);
    },
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
            [['setting_id' => 1, 'key_name_bytes' => $encode('module_cache', 2), 'text_encoding' => 2]],
            [['setting_id' => 1, 'key_name_bytes' => $encode('module_cache', 3), 'text_encoding' => 3]],
            ['setting_id' => 9, 'key_value_bytes' => $encode('module%', 2), 'text_encoding' => 2],
            ['setting_id' => 9, 'key_value_bytes' => $encode('module%', 3), 'text_encoding' => 3],
        );

        $t->same('rtrim(key_name) COLLATE NOCASE LIKE (SELECT key_value FROM app_settings WHERE key_name = ?)', $plan['expression']);
    },
    'utf16 byte-order default expression uses application setting key names' => static function (TestRunner $t): void {
        $encode = static fn (string $value, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding);
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan(
            [['setting_id' => 1, 'key_name_bytes' => $encode('module_cache', 2), 'text_encoding' => 2]],
            [['setting_id' => 1, 'key_name_bytes' => $encode('module_cache', 3), 'text_encoding' => 3]],
            'module\_%',
            '\\',
        );

        $t->same('rtrim(key_name) COLLATE NOCASE /* UTF-16 source */', $plan['expression']);
        $t->same('ascii_lower(rtrim(key_name, space))', $plan['indexKey']);
        $t->same([1], $plan['changedByteOrderRowids']);
    },
];
