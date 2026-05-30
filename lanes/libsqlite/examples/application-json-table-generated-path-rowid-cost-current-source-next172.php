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
    'option_id' => 172,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next172',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 172,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next172',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSourceFence(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => '_rowid_']],
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next172',
    'applicationUse' => 'Copied wp_options plugin-rule previews retain the current json_tree generated-path rowid fence while a changed next source receives a fresh fence token.',
    'currentFenceToken' => $plan['currentGeneratedPathRowidSourceFence172']['sourceFenceToken'],
    'currentRetainsRows' => $plan['currentGeneratedPathRowidSourceFence172']['retainsCurrentRows'],
    'currentYieldRowids' => $plan['currentGeneratedPathRowidSourceFence172']['yieldRowids'],
    'nextFenceToken' => $plan['nextGeneratedPathRowidSourceFence172']['sourceFenceToken'],
    'nextResetRequired' => $plan['nextGeneratedPathRowidSourceFence172']['sourceResetRequired'],
    'nextStaleYieldBlocked' => $plan['nextGeneratedPathRowidSourceFence172']['staleYieldBlocked'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next172ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source yield, generated path, rowid, and xBestIndex profiles',
];

if (in_array('--self-test', $argv, true)) {
    if ($payload['currentYieldRowids'] !== [6]) {
        fwrite(STDERR, "unexpected current yield rowids\n");
        exit(1);
    }
    if ($payload['currentRetainsRows'] !== true) {
        fwrite(STDERR, "expected current rows to be retained\n");
        exit(1);
    }
    if ($payload['nextResetRequired'] !== true || $payload['nextStaleYieldBlocked'] !== true) {
        fwrite(STDERR, "expected next source reset and stale-yield block\n");
        exit(1);
    }
    if ($payload['currentFenceToken'] === $payload['nextFenceToken']) {
        fwrite(STDERR, "expected distinct current/next fence tokens\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-source-fence-token-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next172 token replan reason\n");
        exit(1);
    }
    echo "application-json-table-generated-path-rowid-cost-current-source-next172 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
