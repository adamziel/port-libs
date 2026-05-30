<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 16],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 120],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 64],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public'],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url'],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public'],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'private'],
    ['meta_option_id' => 3, 'meta_key' => 'ttl', 'meta_value' => 'short'],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'public'],
    ['meta_option_id' => 4, 'meta_key' => 'kind', 'meta_value' => 'theme'],
];

$visibility = [
    ['site_id' => 1, 'option_id' => 1, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 2, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 3, 'visibility' => 'cron'],
    ['site_id' => 2, 'option_id' => 1, 'visibility' => 'network'],
    ['site_id' => 2, 'option_id' => 4, 'visibility' => 'theme'],
];

$sql = "SELECT o.option_name AS name, v.visibility AS visibility, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') AS scope FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_value = 'public') ORDER BY name, visibility";
$rows = SQLiteSelectSql::execute($sql, [
    'wp_options' => $options,
    'option_meta' => $metadata,
    'site_visibility' => $visibility,
]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows joined to site visibility rows and filtered/projected through correlated SELECT subqueries, exercising parser-level SELECT subquery behavior over joined sources without requiring ext/sqlite.',
    'sql' => $sql,
    'selectedNames' => array_column($rows, 'name'),
    'scopes' => array_column($rows, 'scope'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
