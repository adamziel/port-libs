<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'option_value' => null, 'autoload' => null, 'bytes' => 3],
];

$autoloadSql = "SELECT option_name, bytes FROM wp_options WHERE bytes BETWEEN 9 AND 24 AND autoload IS 'yes' ORDER BY bytes DESC, option_name";
$transientSql = "SELECT option_name FROM wp_options WHERE option_name LIKE '!_%' ESCAPE '!' AND option_name GLOB '_*transient*' ORDER BY option_name DESC";
$cleanupSql = "SELECT option_id, option_name FROM wp_options WHERE autoload IS NULL OR option_name NOT GLOB '_*' ORDER BY option_id";
$groupSql = "SELECT autoload, count(*) AS rows, sum(bytes) AS byte_sum FROM wp_options WHERE option_name NOT GLOB 'orphan*' GROUP BY autoload HAVING sum(bytes) BETWEEN 50 AND 130 AND autoload IS NOT NULL ORDER BY byte_sum DESC";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options SELECT SQL text predicates for BETWEEN, IS, GLOB, and LIKE ESCAPE result semantics without requiring ext/sqlite.',
    'autoloadSql' => $autoloadSql,
    'autoloadedNames' => array_column(SQLiteSelectSql::execute($autoloadSql, ['wp_options' => $options]), 'option_name'),
    'transientSql' => $transientSql,
    'transientNames' => array_column(SQLiteSelectSql::execute($transientSql, ['wp_options' => $options]), 'option_name'),
    'cleanupSql' => $cleanupSql,
    'cleanupNames' => array_column(SQLiteSelectSql::execute($cleanupSql, ['wp_options' => $options]), 'option_name'),
    'groupSql' => $groupSql,
    'groups' => SQLiteSelectSql::execute($groupSql, ['wp_options' => $options]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
