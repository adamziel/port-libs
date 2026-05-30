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
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 0],
];

$meta = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'meta_value' => 'short', 'weight' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 5, 'meta_key' => 'ttl', 'meta_value' => 'long', 'weight' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'kind', 'meta_value' => 'update', 'weight' => 50],
];

$limitSql = "SELECT option_id, option_name AS name FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) >= 30 ORDER BY sum(weight) DESC)";
$limitRows = SQLiteSelectSql::execute($limitSql, ['wp_options' => $options, 'option_meta' => $meta]);

$offsetSql = "SELECT option_id, option_name AS name FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) >= 30 ORDER BY sum(weight) DESC)";
$offsetRows = SQLiteSelectSql::execute($offsetSql, ['wp_options' => $options, 'option_meta' => $meta]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options SELECT SQL where LIMIT/OFFSET scalar subqueries use grouped aggregate metadata sources and hide internal ORDER BY columns before SQLite-style scalar width checks.',
    'limitSql' => $limitSql,
    'limitOptionIds' => array_column($limitRows, 'option_id'),
    'limitRows' => $limitRows,
    'offsetSql' => $offsetSql,
    'offsetOptionIds' => array_column($offsetRows, 'option_id'),
    'offsetRows' => $offsetRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
