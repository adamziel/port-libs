<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimLikeCurrentSourceNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin_cache '],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin_blob ')],
    ['option_id' => 4, 'option_name' => 'retry_count', 'option_value' => '42 retries'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin_cache'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 4, 'option_name' => 'retry_count', 'option_value' => 42],
    ['option_id' => 5, 'option_name' => 'fresh_plugin', 'option_value' => 'plugin_cache_new'],
];

$plan = SQLiteCastRtrimLikeCurrentSourceNextPlan::wordpressOptionValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin\\_cache',
    '\\',
);

$summary = [
    'scenario' => 'wordpress-cast-rtrim-like-current-source-next131',
    'wordpressUse' => 'Copied wp_options option_value scans can use an RTRIM expression range over CAST(option_value AS TEXT) while retaining the LIKE residual, so space-padded option payloads remain candidates but do not become exact LIKE matches during import diffing.',
    'range' => $plan['range'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'currentResidualRejectedRowids' => $plan['currentResidualRejectedRowids'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['range'] === ['lowerInclusive' => 'plugin_cache', 'upperBound' => 'plugin_cachf']);
    assert($summary['currentCandidateRowids'] === [1, 2]);
    assert($summary['currentResidualRejectedRowids'] === [2]);
    assert($summary['currentRowids'] === [1]);
    assert($summary['nextRowids'] === [1, 2]);
    assert($summary['enteredRowids'] === [2]);
    assert(in_array('matched-rowset', $summary['invalidationReasons'], true));
    echo "wordpress-cast-rtrim-like-current-source-next131 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
