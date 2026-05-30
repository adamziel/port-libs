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
];

$sql = 'SELECT option_id, option_name || :suffix AS label, bytes + ? AS bumped FROM wp_options WHERE autoload = :autoload AND bytes BETWEEN ?2 AND @max_bytes ORDER BY bumped DESC, label LIMIT $limit';
$parameters = [
    0 => 1,
    1 => 9,
    ':suffix' => ':bound',
    '@max_bytes' => 24,
    '$limit' => 3,
    'autoload' => 'yes',
];
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options], $parameters);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options SELECT SQL text with SQLite bind parameters in projection, predicates, ORDER/LIMIT inputs, and mixed positional/named forms before imports without requiring ext/sqlite.',
    'sql' => $sql,
    'parameters' => $parameters,
    'selectedOptionIds' => array_column($rows, 'option_id'),
    'labels' => array_column($rows, 'label'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
