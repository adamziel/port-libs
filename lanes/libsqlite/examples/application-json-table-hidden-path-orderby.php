<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 128,
    'option_name' => 'wp_plugin_hidden_path_orderby',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 128,
    'option_name' => 'wp_plugin_hidden_path_orderby',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [3, 12]],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'atom', 'direction' => 'DESC']],
);

$summary = [
    'operation' => 'json-table-hidden-path-orderby-current-source-next128',
    'optionName' => $current['option_name'],
    'currentCompositeSignature' => $plan['currentHiddenPathOrderBy']['compositeSignature'],
    'currentPathOrderPrefix' => $plan['currentHiddenPathOrderBy']['pathOrderPrefix'],
    'currentOrderSuffix' => $plan['currentHiddenPathOrderBy']['orderSuffix'],
    'currentOrderedRowids' => $plan['currentHiddenPathOrderBy']['orderedRowids'],
    'nextOrderedRowids' => $plan['nextHiddenPathOrderBy']['orderedRowids'],
    'currentEffectiveCost' => $plan['currentHiddenPathOrderBy']['effectiveEstimatedCost'],
    'nextEffectiveCost' => $plan['nextHiddenPathOrderBy']['effectiveEstimatedCost'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next128ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'applicationUse' => 'Copied wp_options plugin-settings JSON can keep hidden path and rowid constraints while detecting when ORDER BY path/atom output has to be resorted after an import source changes.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentOrderedRowids'] !== [3, 6, 9] || $summary['nextOrderedRowids'] !== [3, 6, 9, 12]) {
        fwrite(STDERR, "expected current/next hidden path ordered rowids\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-hidden-path-orderby-current-source-next128', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected next128 dependency marker\n");
        exit(1);
    }
    if (!in_array('json-table-hidden-path-order-output-changed', $summary['replanReasons'], true)) {
        fwrite(STDERR, "expected hidden path order output replan reason\n");
        exit(1);
    }
}
