<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteNocaseGlobAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'Plugin_Beta', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_beta', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'theme_alpha', 'autoload' => 'yes'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_Beta', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_beta2', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_fresh', 'autoload' => 'yes'],
];

$summary = SQLiteNocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNamePlan(
    $currentRows,
    $nextRows,
    'plugin_*',
);

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['rangeUsable'] === false);
    assert($summary['fallbackReason'] === 'glob-range-requires-binary-collation');
    assert($summary['currentRowids'] === [1, 3]);
    assert($summary['nextRowids'] === [1, 2, 3, 5]);
    assert($summary['enteredRowids'] === [2, 5]);
    echo "wordpress-nocase-glob-affinity-current-source-next139 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-nocase-glob-affinity-current-source-next139',
    'wordpressUse' => 'Copied wp_options scans over a NOCASE option_name index must not reuse the bytewise GLOB prefix range; native PHP falls back to a residual scan so Plugin_Beta is rejected until the next source changes it to plugin_Beta.',
    'rangeUsable' => $summary['rangeUsable'],
    'fallbackReason' => $summary['fallbackReason'],
    'currentRowids' => $summary['currentRowids'],
    'nextRowids' => $summary['nextRowids'],
    'enteredRowids' => $summary['enteredRowids'],
    'residualRejected' => [
        'current' => $summary['currentResidualRejectedRowids'],
        'next' => $summary['nextResidualRejectedRowids'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
