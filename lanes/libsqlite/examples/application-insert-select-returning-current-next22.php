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

$archive = [
    ['archive_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'old-site', 'autoload' => 'no', 'source_id' => 100],
];

$result = SQLiteInsertSelectSql::execute(
    "INSERT OR IGNORE INTO archived_options (archive_id, option_name, option_value, autoload, source_id) "
        . "SELECT option_id + 100, option_name, option_value, 'no', option_id FROM wp_options ORDER BY option_id "
        . "RETURNING archive_id, option_name || ':' || source_id AS imported_option",
    ['wp_options' => $options, 'archived_options' => $archive],
    [],
    [['option_name']],
);

$summary = [
    'scenario' => 'application insert select returning current next22',
    'changes' => $result['changes'],
    'ignored' => array_column($result['ignored_rows'], 'option_name'),
    'returning' => $result['returning_rows'],
    'afterNames' => array_column($result['after'], 'option_name'),
    'applicationUse' => 'Preview copied wp_options archive/import rows with SQLite INSERT SELECT RETURNING semantics, including OR IGNORE conflicts and returned imported-row labels, without requiring ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['changes'] === 3);
    assert($summary['ignored'] === ['siteurl']);
    assert(array_column($summary['returning'], 'archive_id') === [102, 103, 104]);
    assert(array_column($summary['returning'], 'imported_option') === ['home:2', 'blogname:3', '_transient_feed:4']);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
