<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSelection = [
    'option_id' => 4242,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_selection',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-selection-a',
];
$nextSelection = array_replace($currentSelection, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-selection-b',
]);

$planSelection = static function (?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($currentSelection, $nextSelection): array {
    return SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionPlan(
        'json_tree',
        $current ?? $currentSelection,
        $nextSource ?? $nextSelection,
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

return [
    'json table generated path rowid cost selection records stable dependency' => static function (TestRunner $t) use ($planSelection): void {
        $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $planSelection()['dependencies'], true));
    },
    'json table generated path rowid cost selection pins stable reader policy' => static function (TestRunner $t) use ($planSelection): void {
        $t->same('cost-select-current-json-table-generated-path-rowid', $planSelection()['currentReaderPolicy']);
    },
    'json table generated path rowid cost selection reprepare policy is stable' => static function (TestRunner $t) use ($planSelection): void {
        $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $planSelection()['nextReaderPolicy']);
    },
    'json table generated path rowid cost selection exposes current key' => static function (TestRunner $t) use ($planSelection): void {
        $t->same('$.rules', $planSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
    },
    'json table generated path rowid cost selection exposes next key' => static function (TestRunner $t) use ($planSelection): void {
        $t->same('$.rules[1]', $planSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
    },
    'json table generated path rowid cost selection exposes stable reason' => static function (TestRunner $t) use ($planSelection): void {
        $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-current-source-selection', $planSelection()['replanReasons'], true));
    },
    'json table generated path rowid cost selection preserves rowid cost' => static function (TestRunner $t) use ($planSelection): void {
        $t->same(1, $planSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']);
    },
    'json table generated path rowid cost selection stable reuses' => static function (TestRunner $t) use ($planSelection, $currentSelection): void {
        $t->same('reuse-cost-select-current-json-table-generated-path-rowid', $planSelection($currentSelection, $currentSelection)['nextReaderPolicy']);
    },
    'json table generated path rowid cost selection omits numbered cost-selection dependencies' => static function (TestRunner $t) use ($planSelection): void {
        $numberedCostSelectionDependencies = array_values(array_filter(
            $planSelection()['dependencies'],
            static fn (string $dependency): bool => preg_match('/generated-path-rowid-cost-current-source-next[0-9]+$/', $dependency) === 1,
        ));
        $t->same([], $numberedCostSelectionDependencies);
    },
];
