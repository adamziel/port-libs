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
    'option_id' => 182,
    'option_name' => 'wp_plugin_generated_path_rowid_xnext_resume',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 21,
];
$next = [
    'option_id' => 182,
    'option_name' => 'wp_plugin_generated_path_rowid_xnext_resume',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"security","priority":9},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 22,
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBatchedXNextPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 42]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
    4,
    7,
    1,
);

$summary = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-xnext-resume',
    'wordpressUse' => 'Copied wp_options plugin-rule import previews can admit one batched xNext step from a pinned generated-path rowid cursor, while a changed next-source fence restarts xFilter before stale JSON rows are yielded.',
    'admissionState' => $plan['currentGeneratedPathRowidCurrentSourceXNext182']['admissionState'],
    'xNextOpcode' => $plan['currentGeneratedPathRowidCurrentSourceXNext182']['xNextOpcode'],
    'nextRowid' => $plan['currentGeneratedPathRowidCurrentSourceXNext182']['nextRowid'],
    'deliverableRowids' => $plan['currentGeneratedPathRowidCurrentSourceXNext182']['deliverableRowids'],
    'blockedRowids' => $plan['currentGeneratedPathRowidCurrentSourceXNext182']['blockedRowids'],
    'nextAdmissionState' => $plan['nextGeneratedPathRowidCurrentSourceXNext182']['admissionState'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next182ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid cache/yield metadata and xFilter/xNext planner evidence',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['admissionState'] === 'admit-batched-current-source-xnext');
    assert($summary['xNextOpcode'] === 'xNext-current-generated-path-rowid');
    assert($summary['nextRowid'] === 6);
    assert($summary['deliverableRowids'] === [6]);
    assert($summary['blockedRowids'] === [5]);
    assert($summary['nextAdmissionState'] === 'restart-next-source-before-xnext');
    assert($summary['nextReaderPolicy'] === 'restart-next-json-table-generated-path-rowid-cost-current-source-next182-xfilter');
    assert(in_array('json-table-generated-path-rowid-xnext-source-fence-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-generated-path-rowid-xnext-resume self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
