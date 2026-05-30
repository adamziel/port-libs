<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
];

$sql = "INSERT INTO archived_options (option_id, option_name, option_value, autoload, source_id) SELECT option_id + :offset, option_name || ':copy', option_value, 'no', option_id FROM wp_options WHERE autoload = :autoload AND option_id >= 2 LIMIT 2";
$result = SQLiteInsertSelectSql::execute(
    $sql,
    [
        'wp_options' => $options,
        'archived_options' => [
            ['option_id' => 100, 'option_name' => 'archived_existing', 'option_value' => 'old', 'autoload' => 'no', 'source_id' => 0],
        ],
    ],
    [
        ':offset' => 1000,
        ':autoload' => 'yes',
    ],
);

echo json_encode([
    'applicationUse' => 'Preview INSERT INTO ... SELECT copies for wp_options archive/import staging without requiring ext/sqlite.',
    'sql' => $sql,
    'changes' => $result['changes'],
    'insertedNames' => array_column($result['inserted_rows'], 'option_name'),
    'afterCount' => count($result['after']),
    'rows' => $result['after'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
