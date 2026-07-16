<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_priority', 'option_value' => 42, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_priority_real', 'option_value' => 4.5, 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'plugin_enabled', 'option_value' => true, 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_blob_slug', 'option_value' => new SQLiteBlobValue('plugin:blob'), 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'cache_key', 'option_value' => 'cache  ', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'cache_key_tab', 'option_value' => "cache\t", 'autoload' => 'yes'],
];

$preview = [
    'scenario' => 'application-select-like-glob-affinity-current-source-next109',
    'applicationUse' => 'Copied wp_options SELECT previews coerce numeric, boolean, and BLOB option_value operands for LIKE/GLOB while preserving SQLite RTRIM collation space-only behavior before imports run without ext/sqlite.',
    'likeNumericOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value LIKE '4%' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'globNumericOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value GLOB '4*' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'blobLikeOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value LIKE 'plugin:%' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'rtrimSpaceOnlyOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value COLLATE RTRIM = 'cache' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'rtrimTabOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value COLLATE RTRIM = 'cache\t' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'dependencies' => ['sqlite-select-predicate-like-glob-affinity', 'sqlite-rtrim-space-only-collation'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($preview['likeNumericOptionIds'] === [1, 2]);
    assert($preview['globNumericOptionIds'] === [1, 2]);
    assert($preview['blobLikeOptionIds'] === [4]);
    assert($preview['rtrimSpaceOnlyOptionIds'] === [5]);
    assert($preview['rtrimTabOptionIds'] === [6]);
    echo "application-select-like-glob-affinity-current-source-next109 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
