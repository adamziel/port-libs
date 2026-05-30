<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSelectionSource = [
    'option_id' => 8601,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_selection',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-selection-a',
];
$nextSelectionSource = array_replace($currentSelectionSource, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-selection-b',
]);

$selectionPlan = static function (?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($currentSelectionSource, $nextSelectionSource): array {
    return SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionPlan(
        'json_tree',
        $current ?? $currentSelectionSource,
        $nextSource ?? $nextSelectionSource,
        'option_value',
        'generated_path',
        [
            ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
            ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7]],
            ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 7]],
        ],
        'scan_root',
        $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
        1,
        null,
        1,
        ['rowid', '_rowid_', 'oid', 'path', 'fullkey', 'value'],
    );
};
$dynamicSelectionPlan = static function () use ($currentSelectionSource, $nextSelectionSource): array {
    return SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(
        276,
        'json_tree',
        $currentSelectionSource,
        $nextSelectionSource,
        'option_value',
        'generated_path',
        [
            ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
            ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7]],
            ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 7]],
        ],
        'scan_root',
        [['column' => 'rowid', 'direction' => 'ASC']],
        1,
        null,
        1,
        ['rowid', '_rowid_', 'oid', 'path', 'fullkey', 'value'],
    );
};

$tests = [];
$tests['json table generated path rowid cost selection records stable dependency'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $selectionPlan()['dependencies'], true));
};
$tests['json table generated path rowid cost selection pins stable reader policy'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->same('cost-select-current-json-table-generated-path-rowid', $selectionPlan()['currentReaderPolicy']);
};
$tests['json table generated path rowid cost selection stable reprepare policy'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $selectionPlan()['nextReaderPolicy']);
};
$tests['json table generated path rowid cost selection exposes current key'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->same('$.rules', $selectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
};
$tests['json table generated path rowid cost selection exposes next key'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->same('$.rules[1]', $selectionPlan()['nextGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
};
$tests['json table generated path rowid cost selection exposes stable reason'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->true(in_array('source-json-changed', $selectionPlan()['replanReasons'], true));
};
$tests['json table generated path rowid cost selection preserves rowid cost'] = static function (TestRunner $t) use ($selectionPlan): void {
    $t->same(1, $selectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']);
};
$tests['json table generated path rowid cost selection stable source reuses'] = static function (TestRunner $t) use ($selectionPlan, $currentSelectionSource): void {
    $t->same('reuse-cost-select-current-json-table-generated-path-rowid', $selectionPlan($currentSelectionSource, $currentSelectionSource)['nextReaderPolicy']);
};
$tests['json table generated path rowid cost selection dynamic variant exposes stable current alias'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->same($dynamicSelectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection276'], $dynamicSelectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection']);
};
$tests['json table generated path rowid cost selection dynamic variant preserves numbered current key'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->same('$.rules', $dynamicSelectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection276']['generatedPath']);
};
$tests['json table generated path rowid cost selection dynamic variant records stable dependency alias'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $dynamicSelectionPlan()['dependencies'], true));
};
$tests['json table generated path rowid cost selection dynamic variant records numbered dependency alias'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next276', $dynamicSelectionPlan()['dependencies'], true));
};
$tests['json table generated path rowid cost selection dynamic variant exposes canonical reader policy alias'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->same('cost-select-current-json-table-generated-path-rowid', $dynamicSelectionPlan()['canonicalCurrentReaderPolicy']);
};
$tests['json table generated path rowid cost selection dynamic variant preserves numbered reader policy'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->same('cost-select-current-json-table-generated-path-rowid-next276', $dynamicSelectionPlan()['currentReaderPolicy']);
};
$tests['json table generated path rowid cost selection dynamic variant exposes stable reasons'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed', $dynamicSelectionPlan()['replanReasons'], true));
};
$tests['json table generated path rowid cost selection dynamic variant preserves numbered reasons'] = static function (TestRunner $t) use ($dynamicSelectionPlan): void {
    $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next276', $dynamicSelectionPlan()['next276ReplanReasons'], true));
};

return $tests;
