<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 44],
    ['option_id' => 7, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 3],
];

$metadata = [
    ['option_name' => 'siteurl', 'priority' => 30, 'bucket' => 'core', 'keep' => 1],
    ['option_name' => 'home', 'priority' => 20, 'bucket' => 'core', 'keep' => 1],
    ['option_name' => 'blogname', 'priority' => 10, 'bucket' => 'content', 'keep' => 1],
    ['option_name' => '_transient_feed', 'priority' => 40, 'bucket' => 'transient', 'keep' => 0],
    ['option_name' => '_site_transient_update_plugins', 'priority' => 60, 'bucket' => 'transient', 'keep' => 0],
    ['option_name' => 'rewrite_rules', 'priority' => 50, 'bucket' => 'rewrite', 'keep' => 1],
];

$tables = [
    'wp_options' => $options,
    'option_meta' => $metadata,
];

$sql = "SELECT option_name, row_number() OVER ranked AS priority_rank, count(*) FILTER (WHERE (SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name) = 1) OVER recent AS recent_kept FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id), recent AS (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) ORDER BY priority_rank";
$rows = SQLiteSelectSql::execute($sql, $tables);

if (($argv[1] ?? null) === '--self-test') {
    $names = array_column($rows, 'option_name');
    $kept = array_column($rows, 'recent_kept');
    if ($names !== ['orphaned', 'blogname', 'home', 'siteurl', '_transient_feed', 'rewrite_rules', '_site_transient_update_plugins']) {
        fwrite(STDERR, "Unexpected named-window priority order\n");
        exit(1);
    }
    if ($kept !== [1, 2, 2, 1, 1, 1, 0]) {
        fwrite(STDERR, "Unexpected named-window filtered frame counts\n");
        exit(1);
    }
    echo "application-select-sql-named-window-subquery-current-source-next107 self-test passed\n";
    exit(0);
}

echo json_encode([
    'applicationUse' => 'Preview copied wp_options import rows with parser-level SQLite named WINDOW clauses whose partition/order/filter expressions use correlated scalar subqueries, without requiring ext/sqlite.',
    'sql' => $sql,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
