<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeSorterDistinctSourceTransitionPlan;

$beforeImport = [
    ['rowid' => 1, 'option_name' => 'SiteUrl', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 3, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 4, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 5, 'option_name' => null, 'autoload' => 'no', 'enabled' => 1],
];

$afterImport = [
    ['rowid' => 1, 'option_name' => 'SiteUrl', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 3, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'enabled' => 0],
    ['rowid' => 4, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'enabled' => 1],
    ['rowid' => 5, 'option_name' => null, 'autoload' => 'no', 'enabled' => 1],
    ['rowid' => 6, 'option_name' => 'transient_timeout', 'autoload' => 'no', 'enabled' => 1],
];

$plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan(
    $beforeImport,
    $afterImport,
    'option_name',
    'rowid',
    'rowid',
    'enabled',
    'G',
    ['NOCASE']
);

$summary = [
    'scenario' => 'application-vdbe-sorter-distinct-collation-current-source-next116',
    'applicationUse' => 'Copied wp_options imports can compare VDBE DISTINCT sorter tapes across source changes, preserving NOCASE duplicate classes while exposing inserted rows and changed representatives without ext/sqlite.',
    'currentValues' => $plan['currentValues'],
    'nextValues' => $plan['nextValues'],
    'inserted' => $plan['inserted'],
    'deleted' => $plan['deleted'],
    'changedRepresentatives' => $plan['changedRepresentatives'],
    'nextDuplicateSkips' => $plan['nextDuplicateSkips'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['currentValues'] === [5, 3, 1]);
    assert($summary['nextValues'] === [5, 4, 1, 6]);
    assert($summary['inserted'] === [6]);
    assert($summary['deleted'] === []);
    assert($summary['changedRepresentatives'][0]['current'] === 3);
    assert($summary['changedRepresentatives'][0]['next'] === 4);
    echo "application-vdbe-sorter-distinct-collation-current-source-next116 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
