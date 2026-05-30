<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteBlobValue.php';
require_once dirname(__DIR__) . '/src/SQLiteJson5Parser.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonCanonical.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonInspection.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonPath.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonExtract.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonMutation.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonB.php';
require_once dirname(__DIR__) . '/src/SQLiteVarint.php';
require_once dirname(__DIR__) . '/src/SQLiteRecord.php';
require_once dirname(__DIR__) . '/src/SQLiteIndexCell.php';
require_once dirname(__DIR__) . '/src/SQLiteIndexLeafPage.php';
require_once dirname(__DIR__) . '/src/SQLiteIndexPredicate.php';
require_once dirname(__DIR__) . '/src/SQLiteIndexColumn.php';
require_once dirname(__DIR__) . '/src/SQLiteCreateIndex.php';
require_once dirname(__DIR__) . '/src/SQLiteGeneratedColumnDependencyPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteGeneratedJsonPathIndexPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$rows = [
    ['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'rank' => 30]]), 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'rank' => 20]]), 'autoload' => 'yes'],
    ['option_id' => 103, 'option_name' => 'plugin_gamma_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'rank' => 10]]), 'autoload' => 'no'],
];

$indexes = [
    ['name' => 'idx_plugin_slug_unique', 'rootPage' => 51, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug_unique ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_rank_desc', 'rootPage' => 52, 'sql' => 'CREATE INDEX idx_plugin_rank_desc ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
];

$plan = SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, $rows, $indexes, [101, 103]);

$summary = [
    'deleted_option_ids' => array_column($plan['deleted_rows'], 'option_id'),
    'next_option_ids' => array_column($plan['next'], 'option_id'),
    'index_delete_count' => count($plan['index_deletes']),
    'btree_action_count' => $plan['btree_action_count'],
    'slug_keys_after_delete' => array_column($plan['btree_indexes']['idx_plugin_slug_unique']['next_entries'], 'key'),
    'rank_keys_after_delete' => array_column($plan['btree_indexes']['idx_plugin_rank_desc']['next_entries'], 'key'),
    'dependencies' => ['sqlite-jsonb-generated-index-delete-current-next51'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['deleted_option_ids'] !== [101, 103]) {
        fwrite(STDERR, "unexpected deleted option ids\n");
        exit(1);
    }
    if ($summary['next_option_ids'] !== [102]) {
        fwrite(STDERR, "unexpected next option ids\n");
        exit(1);
    }
    if ($summary['index_delete_count'] !== 4 || $summary['btree_action_count'] !== 4) {
        fwrite(STDERR, "unexpected index delete count\n");
        exit(1);
    }
    echo "application-jsonb-index-delete-current-next51 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
