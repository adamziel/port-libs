<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 4, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 3, 'meta_key' => 'ttl', 'meta_value' => 'short', 'weight' => 40],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'meta_value' => 'long', 'weight' => 40],
    ['meta_option_id' => 4, 'meta_key' => 'kind', 'meta_value' => 'update', 'weight' => 50],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 30) AS metadata_weight FROM wp_options WHERE EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING count(weight) >= 2) ORDER BY metadata_weight DESC, name",
    ['wp_options' => $options, 'option_meta' => $metadata],
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
