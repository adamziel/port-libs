<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteVdbeSortCompare.php';
require_once __DIR__ . '/../src/SQLiteVdbeWindowAggregateCursor.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$copiedWpOptions = [
    ['rowid' => 501, 'blog_id' => '1', 'autoload' => 'yes', 'sort_key' => '02', 'option_name' => 'plugin_alpha', 'option_value_bytes' => 120, 'enabled' => 1],
    ['rowid' => 502, 'blog_id' => 1, 'autoload' => 'YES', 'sort_key' => 2, 'option_name' => 'plugin_beta', 'option_value_bytes' => 80, 'enabled' => '1'],
    ['rowid' => 503, 'blog_id' => 1, 'autoload' => 'yes', 'sort_key' => new SQLiteBlobValue('2'), 'option_name' => 'plugin_gamma', 'option_value_bytes' => 60, 'enabled' => 0],
    ['rowid' => 504, 'blog_id' => 1, 'autoload' => 'yes', 'sort_key' => '10', 'option_name' => 'plugin_delta', 'option_value_bytes' => 200, 'enabled' => 1],
    ['rowid' => 505, 'blog_id' => 2, 'autoload' => 'no', 'sort_key' => null, 'option_name' => '_transient_feed', 'option_value_bytes' => 30, 'enabled' => 1],
    ['rowid' => 506, 'blog_id' => 2, 'autoload' => 'NO', 'sort_key' => null, 'option_name' => '_transient_timeout_feed', 'option_value_bytes' => 8, 'enabled' => '0'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $copiedWpOptions,
    'option_value_bytes',
    ['blog_id'],
    ['autoload', 'sort_key'],
    'enabled',
    1,
    1,
    'C',
    ['BINARY'],
    'GC',
    ['NOCASE', 'BINARY'],
    [false, false],
    ['LAST', 'LAST']
);

$summary = [
    'scenario' => 'application-vdbe-sorter-window-affinity-current-next33',
    'firstPeerRowids' => $cursor->currentPeerSummary()['rowids'],
    'firstPeerValues' => $cursor->currentPeerValues(false),
    'firstPeerFilteredValues' => $cursor->currentPeerValues(true),
    'drainedPeerRowids' => array_map(static fn (array $row): array => $row['rowids'], $cursor->drainPeerSummaries()),
    'dependency' => 'native PHP VDBE sorter/window cursor over copied wp_options rows; no ext/sqlite required',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['firstPeerRowids'] !== [501, 502, 503]) {
        fwrite(STDERR, "unexpected first peer rowids\n");
        exit(1);
    }
    if ($summary['firstPeerFilteredValues'] !== [120, 80]) {
        fwrite(STDERR, "unexpected filtered peer values\n");
        exit(1);
    }
    echo "application-vdbe-sorter-window-affinity-current-next33 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
