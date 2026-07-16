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
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'public', 'meta_value' => '1'],
    ['meta_option_id' => 1, 'meta_key' => 'network', 'meta_value' => '1'],
    ['meta_option_id' => 3, 'meta_key' => 'public', 'meta_value' => '1'],
    ['meta_option_id' => 4, 'meta_key' => 'expired', 'meta_value' => '1'],
    ['meta_option_id' => 5, 'meta_key' => 'plugin', 'meta_value' => 'cache'],
];

$projectionSql = "SELECT option_id, option_name, (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id ORDER BY meta_key DESC) AS first_meta FROM wp_options ORDER BY option_id";
$orderSql = "SELECT option_id, option_name FROM wp_options ORDER BY coalesce((SELECT meta_key FROM option_meta WHERE meta_option_id = option_id ORDER BY meta_key DESC), 'zz'), option_id DESC LIMIT 4";
$labelSql = "SELECT option_name || ':' || coalesce((SELECT meta_value FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'plugin'), 'missing') AS label FROM wp_options WHERE option_id IN (1, 5) ORDER BY label";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows enriched by correlated scalar SELECT subqueries in projection, expression ORDER BY, and composed labels without requiring ext/sqlite.',
    'projectionSql' => $projectionSql,
    'projectedMetadata' => SQLiteSelectSql::execute($projectionSql, ['wp_options' => $options, 'option_meta' => $metadata]),
    'orderSql' => $orderSql,
    'orderedOptions' => array_column(SQLiteSelectSql::execute($orderSql, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
    'labelSql' => $labelSql,
    'labels' => array_column(SQLiteSelectSql::execute($labelSql, ['wp_options' => $options, 'option_meta' => $metadata]), 'label'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
