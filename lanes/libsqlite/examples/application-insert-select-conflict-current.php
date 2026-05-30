<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'home', 'option_value' => 'duplicate-home', 'autoload' => 'no'],
];

$archive = [
    ['archive_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'old-site', 'source_id' => 0],
    ['archive_id' => 11, 'option_name' => 'legacy', 'option_value' => 'old-legacy', 'source_id' => 0],
];

$ignoreSql = "INSERT OR IGNORE INTO archived_options (archive_id, option_name, option_value, source_id) SELECT option_id + 20, option_name, option_value, option_id FROM wp_options ORDER BY option_id";
$replaceSql = "INSERT OR REPLACE INTO archived_options (archive_id, option_name, option_value, source_id) SELECT option_id + 30, option_name, option_value, option_id FROM wp_options ORDER BY option_id";

$ignore = SQLiteInsertSelectSql::execute(
    $ignoreSql,
    ['wp_options' => $options, 'archived_options' => $archive],
    [],
    [['option_name']],
);
$replace = SQLiteInsertSelectSql::execute(
    $replaceSql,
    ['wp_options' => $options, 'archived_options' => $archive],
    [],
    [['option_name']],
);

echo json_encode([
    'applicationUse' => 'Preview INSERT INTO ... SELECT conflict handling for copied wp_options archive/import staging without requiring ext/sqlite.',
    'ignoreSql' => $ignoreSql,
    'ignoreChanges' => $ignore['changes'],
    'ignoredNames' => array_column($ignore['ignored_rows'], 'option_name'),
    'ignoreAfterNames' => array_column($ignore['after'], 'option_name'),
    'replaceSql' => $replaceSql,
    'replaceChanges' => $replace['changes'],
    'deletedNames' => array_column($replace['deleted_rows'], 'option_name'),
    'replaceAfterNames' => array_column($replace['after'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
