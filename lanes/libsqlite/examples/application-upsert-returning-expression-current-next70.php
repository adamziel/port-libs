<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'old'],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'old'],
    ['option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7, 'touched' => 'old'],
];

$sql = "INSERT INTO wp_options(option_name, option_value, autoload, hits, touched) VALUES "
    . "('siteurl','https://new.test','yes',3,'import'),"
    . "('home','https://home-new.test','yes',3,'import'),"
    . "('runtime_cache','enabled','no',1,'import') "
    . 'ON CONFLICT(option_name) DO UPDATE SET '
    . 'option_value = excluded.option_value, '
    . 'autoload = excluded.autoload, '
    . 'hits = wp_options.hits + excluded.hits, '
    . "touched = wp_options.touched || '>' || excluded.touched "
    . 'WHERE wp_options.hits >= 5 '
    . "RETURNING option_name AS name, hits + 1 AS next_hits, option_name || ':' || touched AS label";

$result = SQLiteUpsertReturningSql::execute($sql, ['wp_options' => $options], [['option_name']]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT DO UPDATE RETURNING expressions over final inserted/updated rows without requiring ext/sqlite.',
    'changes' => $result['changes'],
    'returning' => $result['returning'],
    'skippedNames' => array_column($result['skipped_rows'], 'option_name'),
    'afterNames' => array_column($result['after'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
