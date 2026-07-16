<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeSorterDistinctCurrentSourceCursor;

$copiedOptions = [
    ['rowid' => 1, 'option_name' => 'SiteUrl', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 3, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 4, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 5, 'option_name' => 'plugin_cache_new', 'autoload' => 'no', 'enabled' => 1],
    ['rowid' => 6, 'option_name' => null, 'autoload' => 'no', 'enabled' => 1],
];

$cursor = new SQLiteVdbeSorterDistinctCurrentSourceCursor(
    $copiedOptions,
    'option_name',
    'rowid',
    'enabled',
    'G',
    ['NOCASE'],
    'schema-cookie-before-import'
);

$before = $cursor->snapshot();
$cursor->next();
$cursor->next();

$copiedOptions[2]['enabled'] = 0;
$copiedOptions[] = ['rowid' => 7, 'option_name' => 'transient_timeout', 'autoload' => 'no', 'enabled' => 1];
$cursor->refresh($copiedOptions, 'schema-cookie-after-import');
$after = $cursor->snapshot();

$summary = [
    'scenario' => 'application-vdbe-sorter-distinct-collation-current-source-next106',
    'applicationUse' => 'Copied wp_options import previews can refresh a VDBE DISTINCT sorter after schema/source changes while preserving the current collation key and avoiding stale case-folded duplicates without ext/sqlite.',
    'before' => $before,
    'after' => $after,
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['before']['values'] === [6, 3, 5, 1]);
    assert($summary['after']['sourceToken'] === 'schema-cookie-after-import');
    assert($summary['after']['currentKey'] === ['plugin_cache_new']);
    assert($summary['after']['currentValue'] === 5);
    assert($summary['after']['values'] === [6, 4, 5, 1, 7]);
    echo "application-vdbe-sorter-distinct-collation-current-source-next106 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
