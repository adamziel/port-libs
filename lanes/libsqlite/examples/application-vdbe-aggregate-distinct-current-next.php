<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeAggregateDistinctCursor;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => '24', 'include' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'include' => 1],
    ['option_id' => 3, 'option_name' => 'BlogName', 'autoload' => 'yes', 'bytes' => 9, 'include' => 1],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'include' => 1],
    ['option_id' => 5, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'include' => 0],
    ['option_id' => 6, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'bytes' => 12.0, 'include' => 1],
];

$cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'option_name', 'include', 'C');
$summary = [
    'distinct_names_by_bytes' => $cursor->groupConcat('|'),
    'distinct_count' => $cursor->countValue(),
    'scan' => array_map(
        static fn (array $entry): array => [
            'key' => $entry['key'],
            'option_name' => $entry['value'],
            'source_option_id' => $entry['row']['option_id'],
        ],
        $cursor->remaining()
    ),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['distinct_names_by_bytes'] !== 'BlogName|plugin_cache|siteurl') {
        fwrite(STDERR, 'unexpected distinct aggregate order' . PHP_EOL);
        exit(1);
    }
    if ($summary['distinct_count'] !== 3) {
        fwrite(STDERR, 'unexpected distinct aggregate count' . PHP_EOL);
        exit(1);
    }

    echo 'application-vdbe-aggregate-distinct-current-next self-test passed' . PHP_EOL;
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
