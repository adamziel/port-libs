<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$wpOptions = [
    [
        'option_id' => 1,
        'option_name' => 'site_navigation_tree',
        'autoload' => 'yes',
        'option_value' => '{"menus":[{"id":"root","children":[{"id":"blog","children":[{"id":"archive"}]},{"id":"shop"}]}]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'jsonb_navigation_tree',
        'autoload' => 'yes',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'menus' => [
                ['id' => 'media', 'children' => [['id' => 'gallery'], ['id' => 'video']]],
            ],
        ])),
    ],
];

$rows = SQLiteSelectSql::execute("
WITH RECURSIVE roots(option_id, root, depth) AS MATERIALIZED (
    SELECT host.option_id, menu.fullkey, 0
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT roots.option_id, child.fullkey, roots.depth + 1
    FROM roots
    JOIN wp_options AS host ON host.option_id = roots.option_id
    JOIN json_each(host.option_value, roots.root || '.children') AS child ON child.type = 'object'
    WHERE roots.depth < 3
)
SELECT roots.option_id AS option_id, roots.depth AS depth, node.atom AS node_id
FROM roots
JOIN wp_options AS host ON host.option_id = roots.option_id
JOIN json_each(host.option_value, roots.root) AS node ON node.key = 'id'
ORDER BY option_id, depth, node_id", ['wp_options' => $wpOptions]);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
