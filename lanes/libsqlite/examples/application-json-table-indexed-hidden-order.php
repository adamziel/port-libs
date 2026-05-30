<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 41,
    'option_name' => 'wp_plugin_hidden_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 41,
    'option_name' => 'wp_plugin_hidden_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"next":true}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%].priority'],
    ],
    'scan_root',
    [['column' => 'json'], ['column' => 'id']],
);

$payload = [
    'scenario' => 'application-json-table-indexed-hidden-order',
    'applicationUse' => 'Copied wp_options JSON import previews can keep an indexed json_tree() constraint while detecting ORDER BY over hidden json/root source terms when the next option payload changes without changing visible priority rows.',
    'currentHiddenOrder' => $plan['currentIndexedHiddenOrder']['hiddenOrderBy'],
    'currentSelectedConstraint' => $plan['currentIndexedHiddenOrder']['selectedSignature'],
    'currentCostClass' => $plan['currentIndexedHiddenOrder']['costClass'],
    'currentFirstHiddenKey' => $plan['currentIndexedHiddenOrder']['firstHiddenKey'],
    'nextFirstHiddenKey' => $plan['nextIndexedHiddenOrder']['firstHiddenKey'],
    'replanReasons' => $plan['next122ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source planning, indexed visible-constraint costing, and hidden json/root source metadata',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentSelectedConstraint'] !== '3:fullkey:LIKE:"$.rules[%].priority"') {
        fwrite(STDERR, "unexpected selected JSON table constraint\n");
        exit(1);
    }
    if ($payload['currentCostClass'] !== 'json-table-indexed-hidden-order-sort-required') {
        fwrite(STDERR, "unexpected hidden order cost class\n");
        exit(1);
    }
    if ($payload['currentFirstHiddenKey'] === $payload['nextFirstHiddenKey']) {
        fwrite(STDERR, "hidden order key did not track source change\n");
        exit(1);
    }
    if (!in_array('json-table-hidden-order-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing hidden order source replan reason\n");
        exit(1);
    }

    echo "application-json-table-indexed-hidden-order self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
