<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonAggregate;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionRows = [
    ['siteurl', 'https://example.test', 1, 1],
    ['home', 'https://example.test/home', 1, 0],
    ['plugin_rules', '{"seo":true}', 2, 1],
    ['theme_mods', 'twentytwentyfive', 3, null],
    ['active_plugins', '["seo/cache.php"]', 3, 1],
];

$arrayRows = [];
$objectRows = [];
foreach ($optionRows as [$name, $value, $orderKey, $include]) {
    $arrayRows[] = [$name, $orderKey, $include];
    $objectRows[] = [$name, $value, $orderKey, $include];
}

$summary = [
    'arrayNoOthers' => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1),
    'arrayExcludeGroup' => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1, 'GROUP'),
    'objectExcludeCurrent' => SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 1, 1, 'CURRENT ROW'),
    'applicationUse' => 'Copied wp_options JSON aggregate window FILTER regression preserves one output frame per input row while filtered rows only stop contributing to aggregate payloads.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert(count($summary['arrayNoOthers']) === 5);
    assert($summary['arrayNoOthers'][1] === '["siteurl","plugin_rules"]');
    assert($summary['arrayExcludeGroup'][3] === '["plugin_rules"]');
    assert($summary['objectExcludeCurrent'][4] === '{}');
    echo "application-json-aggregate-window-filter-regression self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
