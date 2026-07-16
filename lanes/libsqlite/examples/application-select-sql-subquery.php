<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 3],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'public', 'meta_value' => '1'],
    ['meta_option_id' => 1, 'meta_key' => 'network', 'meta_value' => '1'],
    ['meta_option_id' => 3, 'meta_key' => 'public', 'meta_value' => '1'],
    ['meta_option_id' => 4, 'meta_key' => 'expired', 'meta_value' => '1'],
    ['meta_option_id' => 5, 'meta_key' => 'plugin', 'meta_value' => 'cache'],
    ['meta_option_id' => null, 'meta_key' => 'ignored-null', 'meta_value' => 'x'],
];

$publicSql = "SELECT option_id, option_name FROM wp_options WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'public') ORDER BY option_id";
$pluginSql = "SELECT option_name, bytes FROM wp_options WHERE option_id IN (SELECT meta_option_id FROM option_meta WHERE meta_key IN ('public', 'plugin')) ORDER BY bytes DESC, option_name";
$notInSql = "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta) ORDER BY option_id";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows filtered by bounded SQLite SELECT text subqueries, including correlated EXISTS and IN/NOT IN NULL behavior, without requiring ext/sqlite.',
    'publicSql' => $publicSql,
    'publicOptions' => array_column(SQLiteSelectSql::execute($publicSql, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
    'pluginSql' => $pluginSql,
    'pluginOptions' => array_column(SQLiteSelectSql::execute($pluginSql, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
    'notInSql' => $notInSql,
    'notInWithNullOptions' => array_column(SQLiteSelectSql::execute($notInSql, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
