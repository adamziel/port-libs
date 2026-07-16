<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 194,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next194',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-194-a',
];
$next = [
    'option_id' => 194,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next194',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-194-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidPinnedSourcePlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'DESC']],
    null,
    null,
    ['id', 'fullkey', 'atom', 'value', 'type'],
    6,
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-pinned-source',
    'applicationUse' => 'Copied wp_options plugin-rule inspectors can pin an emitted json_tree generated-path rowid source only while the xColumn row, source generation, generated path, and final-cost fingerprints remain current.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentDisposition' => $plan['currentGeneratedPathRowidPinnedSource194']['sourceDisposition'],
    'currentOpcode' => $plan['currentGeneratedPathRowidPinnedSource194']['sourceOpcode'],
    'currentActiveFullkey' => $plan['currentGeneratedPathRowidPinnedSource194']['activeFullkey'],
    'currentRemainingRowids' => $plan['currentGeneratedPathRowidPinnedSource194']['remainingRowids'],
    'nextDisposition' => $plan['nextGeneratedPathRowidPinnedSource194']['sourceDisposition'],
    'nextCostClass' => $plan['nextGeneratedPathRowidPinnedSource194']['costClass'],
    'replanReasons' => $plan['next194ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table cursor, generated-path rowid cost, xColumn yield rows, and current-source fingerprints',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentDisposition'] !== 'pin-current-source-generated-path-rowid-next194') {
        fwrite(STDERR, "unexpected next194 current disposition\n");
        exit(1);
    }
    if ($payload['currentActiveFullkey'] !== '$.rules[1].priority') {
        fwrite(STDERR, "unexpected next194 active row\n");
        exit(1);
    }
    if ($payload['currentRemainingRowids'] !== [5]) {
        fwrite(STDERR, "unexpected next194 remaining rowids\n");
        exit(1);
    }
    if ($payload['nextCostClass'] !== 'json-table-generated-path-rowid-source-upstream-reprepare-next194') {
        fwrite(STDERR, "unexpected next194 reprepare cost class\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-source-changed-next194', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next194 source replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-pinned-source self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
