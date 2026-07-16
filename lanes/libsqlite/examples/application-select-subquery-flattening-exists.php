<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'orphaned_option', 'autoload' => 'no'],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public'],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public'],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'public'],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private'],
];

$visibility = [
    ['visible_option_id' => 1, 'site_id' => 1, 'visibility' => 'front'],
    ['visible_option_id' => 2, 'site_id' => 1, 'visibility' => 'front'],
    ['visible_option_id' => 3, 'site_id' => 1, 'visibility' => 'front'],
    ['visible_option_id' => 4, 'site_id' => 1, 'visibility' => 'cron'],
    ['visible_option_id' => 1, 'site_id' => 2, 'visibility' => 'network'],
];

$tables = [
    'wp_options' => $options,
    'option_meta' => $metadata,
    'site_visibility' => $visibility,
];

$publicFrontSql = "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND m.meta_value = 'public' AND v.visibility = 'front') ORDER BY option_name";
$unroutedSql = "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id) ORDER BY option_name";

echo json_encode([
    'publicFrontOptions' => array_column(SQLiteSelectSql::execute($publicFrontSql, $tables), 'option_name'),
    'unroutedOptions' => array_column(SQLiteSelectSql::execute($unroutedSql, $tables), 'option_name'),
    'applicationUse' => 'Preview copied wp_options rows through correlated EXISTS subqueries whose inner SELECT uses joins, preserving SQLite subquery truthiness without flattening the joined metadata source into the outer query and without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
