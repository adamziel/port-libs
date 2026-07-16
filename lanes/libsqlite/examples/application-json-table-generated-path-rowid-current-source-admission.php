<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 161,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_admission',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$nextOption = [
    'option_id' => 161,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_admission',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceAdmissionPlan(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source',
    'applicationUse' => 'Copied wp_options JSON rule diagnostics can admit generated-path and rowid constraints into a pinned current-source json_tree cursor while leaving visible type checks as residual filters.',
    'currentIdxStr' => $plan['currentGeneratedPathRowidCurrentSourceAdmission']['idxStr'],
    'nextIdxStr' => $plan['nextGeneratedPathRowidCurrentSourceAdmission']['idxStr'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceAdmission']['costClass'],
    'currentMatchedSeekRowids' => $plan['currentGeneratedPathRowidCurrentSourceAdmission']['matchedSeekRowids'],
    'currentOmitColumns' => $plan['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns'],
    'replanReasons' => $plan['generatedPathRowidCurrentSourceAdmissionReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid seek, and current-source planner profiles',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentIdxStr'] !== 'omit:path:LIKE|omit:id:IN') {
        fwrite(STDERR, "unexpected current idx string\n");
        exit(1);
    }
    if ($payload['currentMatchedSeekRowids'] !== [5, 6]) {
        fwrite(STDERR, "unexpected matched rowids\n");
        exit(1);
    }
    if ($payload['currentOmitColumns'] !== ['path', 'id']) {
        fwrite(STDERR, "unexpected omit columns\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-admission-usage-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing usage replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-cost-current-source self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
