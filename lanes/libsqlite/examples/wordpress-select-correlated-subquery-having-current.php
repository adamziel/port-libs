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
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'weight' => 10],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'weight' => 20],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'weight' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'weight' => 20],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'weight' => 30],
    ['meta_option_id' => 3, 'meta_key' => 'ttl', 'weight' => 40],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'weight' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'weight' => 40],
    ['meta_option_id' => 4, 'meta_key' => 'kind', 'weight' => 50],
];

$rules = [
    ['option_id' => 1, 'blog_id' => 1, 'minimum_weight' => 25],
    ['option_id' => 1, 'blog_id' => 2, 'minimum_weight' => 40],
    ['option_id' => 2, 'blog_id' => 1, 'minimum_weight' => 25],
    ['option_id' => 3, 'blog_id' => 1, 'minimum_weight' => 60],
    ['option_id' => 4, 'blog_id' => 1, 'minimum_weight' => 90],
];

$rows = SQLiteSelectSql::execute(
    "SELECT wp_options.option_name AS name, site_rules.blog_id AS blog, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) AS matched_weight FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id ORDER BY wp_options.option_id, site_rules.blog_id",
    ['wp_options' => $options, 'option_meta' => $metadata, 'site_rules' => $rules],
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
