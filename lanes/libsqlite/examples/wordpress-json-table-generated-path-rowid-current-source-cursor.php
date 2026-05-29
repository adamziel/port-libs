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
    'option_id' => 171,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next171',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 171,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next171',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCursorPlan(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next171',
    'wordpressUse' => 'Copied wp_options plugin-rule previews can keep a pinned json_tree() current-source cursor through xNext rowid/path yields, while the shifted next source is prepared through a fresh cursor.',
    'cursorOpcode' => $plan['currentGeneratedPathRowidCurrentSourceCursor']['cursorOpcode'],
    'yieldRowids' => $plan['currentGeneratedPathRowidCurrentSourceCursor']['yieldRowids'],
    'missingSeekRowids' => $plan['currentGeneratedPathRowidCurrentSourceCursor']['missingSeekRowids'],
    'yieldProgram' => $plan['currentGeneratedPathRowidCurrentSourceCursor']['yieldProgram'],
    'nextCursorOpcode' => $plan['nextGeneratedPathRowidCurrentSourceCursor']['cursorOpcode'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next171ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, ORDER, xFilter, and cursor-yield metadata helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['cursorOpcode'] === 'xNext-generated-path-rowid-current-source');
    assert($summary['yieldRowids'] === [6, 5]);
    assert($summary['missingSeekRowids'] === [42]);
    assert($summary['yieldProgram'][0]['opcode'] === 'xColumn-current-source-rowid-path');
    assert($summary['yieldProgram'][2]['opcode'] === 'xEof-current-source-rowid-path');
    assert($summary['nextCursorOpcode'] === 'xNext-generated-path-rowid-current-source-reprepare');
    assert(in_array('json-table-generated-path-rowid-current-source-cursor-rowset-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next171 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
