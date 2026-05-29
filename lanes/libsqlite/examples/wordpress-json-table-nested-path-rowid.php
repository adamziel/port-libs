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
    'option_id' => 133,
    'option_name' => 'wp_plugin_nested_path_rowid',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 133,
    'option_name' => 'wp_plugin_nested_path_rowid',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3,"enabled":true},{"slug":"cache","priority":8,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true},{"slug":"spam","priority":1,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 2],
    ],
    [['column' => 'id']],
);

$summary = [
    'operation' => 'json-table-nested-path-rowid-current-source-next133',
    'optionName' => $current['option_name'],
    'currentRoot' => $plan['currentNestedPathRowid']['root'],
    'nextRoot' => $plan['nextNestedPathRowid']['root'],
    'rowidConstraint' => $plan['currentNestedPathRowid']['rowidConstraintSignature'],
    'currentScopedRowids' => $plan['currentNestedPathRowid']['scopedRowids'],
    'nextScopedRowids' => $plan['nextNestedPathRowid']['scopedRowids'],
    'currentRelativeFullkeys' => $plan['currentNestedPathRowid']['relativeFullkeys'],
    'nextRelativeFullkeys' => $plan['nextNestedPathRowid']['relativeFullkeys'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next133ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'wordpressUse' => 'Copied wp_options plugin JSON can keep a json_tree() rowid predicate scoped to the current nested root while the next plugin group points at a different nested path; this records when rowid/fullkey tapes can be reused and when the cursor must be replanned.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentRoot'] !== '$.plugin.groups[0].rules' || $summary['nextRoot'] !== '$.plugin.groups[1].rules') {
        fwrite(STDERR, "expected nested path rowid roots\n");
        exit(1);
    }
    if ($summary['currentScopedRowids'] !== [2] || $summary['nextScopedRowids'] !== [2]) {
        fwrite(STDERR, "expected rowid 2 scoped under both nested roots\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-nested-path-rowid-current-source-next133', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected next133 dependency marker\n");
        exit(1);
    }
}
