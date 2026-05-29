<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 175,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next175',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 7,
];
$next = [
    'option_id' => 175,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next175',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 8,
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCachePlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
);

$summary = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next175',
    'wordpressUse' => 'Copied wp_options plugin-rule diagnostics can reuse a pinned generated-path rowid json_tree cache only while the source generation, rowid argv scope, and xBestIndex fingerprint stay stable across an import preview.',
    'sourceGeneration' => $plan['currentGeneratedPathRowidCurrentSourceCache175']['sourceGeneration'],
    'cacheReusable' => $plan['currentGeneratedPathRowidCurrentSourceCache175']['cacheReusable'],
    'cacheDisposition' => $plan['currentGeneratedPathRowidCurrentSourceCache175']['cacheDisposition'],
    'costClass' => $plan['currentGeneratedPathRowidCurrentSourceCache175']['costClass'],
    'orderedOutputRowids' => $plan['currentGeneratedPathRowidCurrentSourceCache175']['orderedOutputRowids'],
    'nextSourceGeneration' => $plan['nextGeneratedPathRowidCurrentSourceCache175']['sourceGeneration'],
    'nextCacheDisposition' => $plan['nextGeneratedPathRowidCurrentSourceCache175']['cacheDisposition'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next175ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid-cost, current-source xBestIndex, and cache-generation planner metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['sourceGeneration'] === 'source_generation:7');
    assert($summary['cacheReusable'] === true);
    assert($summary['cacheDisposition'] === 'reuse-json-table-generated-path-rowid-cache');
    assert($summary['costClass'] === 'json-table-generated-path-rowid-cache-range-current-source');
    assert($summary['orderedOutputRowids'] === [6, 5]);
    assert($summary['nextSourceGeneration'] === 'source_generation:8');
    assert($summary['nextCacheDisposition'] === 'reprepare-json-table-generated-path-rowid-cache');
    assert(in_array('json-table-generated-path-rowid-cache-source-generation-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next175 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
