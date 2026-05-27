<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSessionChangeset;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
];
$after = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'plugin_enabled', 'option_value' => '1', 'autoload' => 'no'],
];

$changeset = SQLiteSessionChangeset::diff(
    'wp_options',
    ['option_id', 'blog_id', 'option_name', 'option_value', 'autoload'],
    ['option_id'],
    $before,
    $after,
);
$payload = SQLiteSessionChangeset::encode([$changeset]);
$decoded = SQLiteSessionChangeset::decode($payload)[0];
$applied = SQLiteSessionChangeset::apply($before, $changeset);

echo json_encode([
    'table' => $decoded['table'],
    'operations' => array_column($decoded['changes'], 'op'),
    'applied' => count($applied['applied']),
    'conflicts' => count($applied['conflicts']),
    'option_names' => array_column($applied['rows'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
