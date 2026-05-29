<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => '_transient_feed', 'option_value' => '2024-01-01 23:59:59.250+00:00', 'autoload' => 'no'],
    ['option_id' => 2, 'option_name' => '_site_transient_update_plugins', 'option_value' => '2024-01-01 03:30:00+02:30', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'plugin_cache_checked', 'option_value' => '1704067200.125', 'autoload' => 'yes'],
];

$sql = <<<SQL
SELECT
  option_name,
  datetime(option_value, 'subsec') AS normalized_at,
  unixepoch(option_value, 'subsec') AS normalized_epoch
FROM wp_options
WHERE option_name <> 'plugin_cache_checked' AND date(option_value) >= '2024-01-01'
ORDER BY normalized_at, option_id
SQL;

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);
$epochSql = "SELECT option_name, datetime(option_value, 'unixepoch', 'subsec') AS normalized_at, unixepoch(option_value, 'unixepoch', 'subsec') AS normalized_epoch FROM wp_options WHERE option_name = 'plugin_cache_checked'";
$epochRows = SQLiteSelectSql::execute($epochSql, ['wp_options' => $options]);

$summary = [
    'wordpressUse' => 'Normalize copied wp_options plugin/transient timestamps with SQLite date/time scalar functions, including timezone suffixes, unixepoch input, and subsecond output without requiring ext/sqlite.',
    'sql' => $sql,
    'epochSql' => $epochSql,
    'normalizedOrder' => array_column($rows, 'option_name'),
    'rows' => $rows,
    'epochRows' => $epochRows,
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['normalizedOrder'] === ['_site_transient_update_plugins', '_transient_feed']);
    assert($rows[0]['normalized_at'] === '2024-01-01 01:00:00.000');
    assert($rows[1]['normalized_at'] === '2024-01-01 23:59:59.250');
    assert($epochRows[0]['normalized_at'] === '2024-01-01 00:00:00.125');
    assert($epochRows[0]['normalized_epoch'] === 1704067200.125);
    echo "wordpress-select-sql-datetime-functions self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
