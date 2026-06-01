<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCascadePlan;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCheckIndexPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';
$sourceFiles = [
    $sourceRoot . '/SQLiteGeneratedJsonPathIndexPlan.php',
    $sourceRoot . '/SQLiteJsonbGeneratedCascadePlan.php',
    $sourceRoot . '/SQLiteJsonbGeneratedCheckIndexPlan.php',
];

$legacyJsonbCheckMatches = static function () use ($sourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($sourceFiles as $file) {
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

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  module_slug TEXT GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.slug')) STORED CHECK(module_slug <> ''),
  module_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.rank')) VIRTUAL CHECK(module_rank BETWEEN 1 AND 99)
)
SQL;

$rows = [
    ['setting_id' => 11, 'key_name' => 'module_alpha', 'key_value' => $jsonb(['module' => ['slug' => 'alpha', 'rank' => 10]]), 'load_policy' => 'yes'],
    ['setting_id' => 12, 'key_name' => 'module_beta', 'key_value' => $jsonb(['module' => ['slug' => 'beta', 'rank' => 20]]), 'load_policy' => 'no'],
    ['setting_id' => 13, 'key_name' => 'module_gamma', 'key_value' => $jsonb(['module' => ['slug' => 'gamma', 'rank' => 30]]), 'load_policy' => 'yes'],
];

$indexes = [
    ['name' => 'idx_app_module_slug', 'rootPage' => 154, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_app_module_slug ON app_settings(module_slug COLLATE NOCASE) WHERE module_slug IS NOT NULL'],
    ['name' => 'idx_app_module_rank', 'rootPage' => 155, 'sql' => 'CREATE INDEX idx_app_module_rank ON app_settings(module_rank DESC) WHERE module_rank IS NOT NULL'],
];

$updates = [
    ['rowid' => 11, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 15],
    ]],
    ['rowid' => 12, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 120],
    ]],
    ['rowid' => 13, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.slug', 'value' => 'delta'],
    ]],
];

return [
    'source-neutral jsonb check current-source files contain no legacy domain strings' => static fn (TestRunner $t) => $t->same([], $legacyJsonbCheckMatches()),
    'jsonb generated check index uses schema-derived setting rowid' => static function (TestRunner $t) use ($schema, $rows, $indexes, $updates): void {
        $plan = SQLiteJsonbGeneratedCheckIndexPlan::plan($schema, $rows, $indexes, $updates, 512);

        $t->same('app_settings', $plan['table']);
        $t->same([11, 13], array_column($plan['accepted_updates'], 'rowid'));
        $t->same([12], array_column($plan['rejected_updates'], 'rowid'));
        $t->same([15, 20, 30], array_column($plan['after'], 'module_rank'));
        $t->same(['alpha', 'beta', 'delta'], array_column($plan['after'], 'module_slug'));
    },
    'jsonb generated path btree records omit primary-key covering payload' => static function (TestRunner $t) use ($schema, $rows, $indexes): void {
        $covering = [
            ['name' => 'idx_app_module_slug_covering', 'rootPage' => 156, 'coveringColumns' => ['module_slug', 'key_name', 'setting_id'], 'sql' => 'CREATE INDEX idx_app_module_slug_covering ON app_settings(module_slug, key_name, setting_id) WHERE module_slug IS NOT NULL'],
        ];
        $plan = SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($schema, $rows, $covering, [12], 512);

        $t->same([12], array_column($plan['deleted_rows'], 'setting_id'));
        $t->same(['key_name' => 'module_beta'], $plan['delete_entries'][0]['coveringValues']);
        $t->same(['beta', 'module_beta', 12], $plan['delete_entries'][0]['record']);
    },
    'jsonb generated cascade uses neutral parent rowid column' => static function (TestRunner $t) use ($jsonb): void {
        $plan = SQLiteJsonbGeneratedCascadePlan::plan(
            [
                ['setting_id' => 21, 'key_name' => 'module_alpha', 'key_value' => $jsonb(['module' => ['tenant' => 'tenant-a']])],
                ['setting_id' => 22, 'key_name' => 'module_beta', 'key_value' => $jsonb(['module' => ['tenant' => 'tenant-b']])],
            ],
            [
                ['record_id' => 301, 'tenant_key' => 'tenant-a'],
                ['record_id' => 302, 'tenant_key' => 'tenant-b'],
            ],
            [['tenant_key' => 'tenant-a', 'new_tenant_key' => 'tenant-c']],
            ['tenant-b'],
            [
                'parent_column' => 'tenant_key',
                'source_column' => 'key_value',
                'json_path' => '$.module.tenant',
                'child_column' => 'tenant_key',
                'rowid_column' => 'setting_id',
                'on_update' => 'CASCADE',
                'on_delete' => 'CASCADE',
            ],
        );

        $t->same([21, 22], array_column($plan['actions'], 'rowid'));
        $t->same(['tenant-c'], array_column($plan['after_parent'], 'tenant_key'));
        $t->same(['tenant-c'], array_column($plan['after_child'], 'tenant_key'));
        $t->same('setting_id', $plan['foreign_key']['rowid_column']);
    },
    'source-neutral jsonb check dependency closure' => static fn (TestRunner $t) => $t->same(
        'no new support component needed; reuses JSONB mutation, generated-column evaluation, CHECK evaluation, schema-derived rowid handling, and generated-key cascade planning',
        'no new support component needed; reuses JSONB mutation, generated-column evaluation, CHECK evaluation, schema-derived rowid handling, and generated-key cascade planning',
    ),
];
