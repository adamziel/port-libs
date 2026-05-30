<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteGlobCursor;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl ', 'option_value' => 'space padded duplicate', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => "siteurl\t", 'option_value' => 'tab padded distinct key', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "siteurl\n", 'option_value' => 'newline padded distinct key', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_å ', 'option_value' => 'unicode space padded', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => "plugin_å\t", 'option_value' => 'unicode tab padded distinct key', 'autoload' => 'no'],
];

$caseRows = SQLiteSelectSql::execute(
    "SELECT option_id, CASE option_name COLLATE RTRIM WHEN 'siteurl' THEN 'same-key' ELSE 'distinct' END AS rtrim_bucket FROM wp_options ORDER BY option_id",
    ['wp_options' => $rows],
);

$cursor = new SQLiteGlobCursor(
    array_map(
        static fn (array $row): array => [
            'key' => $row['option_name'],
            'rowid' => $row['option_id'],
            'payload' => $row,
        ],
        $rows,
    ),
    'siteurl',
    'RTRIM',
);

$summary = [
    'scenario' => 'copied wp_options RTRIM collation current next76',
    'rtrimSpaceEquals' => SQLiteAffinityComparison::compare('siteurl ', 'siteurl', 'TEXT', 'NONE', 'RTRIM') === 0,
    'rtrimTabDistinct' => SQLiteAffinityComparison::compare("siteurl\t", 'siteurl', 'TEXT', 'NONE', 'RTRIM') > 0,
    'rtrimNewlineDistinct' => SQLiteAffinityComparison::compare("siteurl\n", 'siteurl', 'TEXT', 'NONE', 'RTRIM') > 0,
    'caseBuckets' => array_column($caseRows, 'rtrim_bucket', 'option_id'),
    'exactGlobMatchedRowids' => array_column($cursor->matchedRows(), 'rowid'),
    'dependency' => 'no new support component; reuses native affinity comparison, SELECT SQL CASE dispatch, and LIKE current/next cursor paths',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['rtrimSpaceEquals'] !== true || $summary['rtrimTabDistinct'] !== true || $summary['rtrimNewlineDistinct'] !== true) {
        throw new RuntimeException('RTRIM comparison parity failed');
    }
    if ($summary['caseBuckets'] !== [1 => 'same-key', 2 => 'same-key', 3 => 'distinct', 4 => 'distinct', 5 => 'distinct', 6 => 'distinct']) {
        throw new RuntimeException('RTRIM SELECT CASE parity failed');
    }
    if ($summary['exactGlobMatchedRowids'] !== [1]) {
        throw new RuntimeException('RTRIM GLOB current/next parity failed');
    }

    echo "wordpress-rtrim-collation-current-next76 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
