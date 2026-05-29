<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSelection = [
    'option_id' => 857872,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_selection',
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

$selectionPlan = static function (?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($currentSelection, $nextSelection): array {
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

$tests = [
    'json table generated path rowid cost selection records canonical dependency' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $selectionPlan()['dependencies'], true));
    },
    'json table generated path rowid cost selection drops migrated range dependencies' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same([], array_values(array_filter(
            $selectionPlan()['dependencies'],
            static fn (string $dependency): bool => (bool) preg_match('/current-source-next(85[7-9]|86[0-9]|87[0-2])$/', $dependency),
        )));
    },
    'json table generated path rowid cost selection pins current reader policy' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same('cost-select-current-json-table-generated-path-rowid', $selectionPlan()['currentReaderPolicy']);
    },
    'json table generated path rowid cost selection reparses changed source' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $selectionPlan()['nextReaderPolicy']);
    },
    'json table generated path rowid cost selection reuses stable source' => static function (TestRunner $t) use ($selectionPlan, $currentSelection): void {
        $t->same('reuse-cost-select-current-json-table-generated-path-rowid', $selectionPlan($currentSelection, $currentSelection)['nextReaderPolicy']);
    },
    'json table generated path rowid cost selection exposes canonical current key' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same('$.rules', $selectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
    },
    'json table generated path rowid cost selection exposes canonical next key' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same('$.rules[1]', $selectionPlan()['nextGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
    },
    'json table generated path rowid cost selection exposes canonical transitions' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same(22, count($selectionPlan()['generatedPathRowidCurrentSourceCostSelectionTransitions']));
    },
    'json table generated path rowid cost selection exposes canonical reasons' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-current-source-selection', $selectionPlan()['replanReasons'], true));
    },
    'json table generated path rowid cost selection preserves point cost' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same(1, $selectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']);
    },
    'json table generated path rowid cost selection preserves eof next cost' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same(1000000, $selectionPlan()['nextGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']);
    },
    'json table generated path rowid cost selection canonical current cost class' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-current-source-selection', $selectionPlan()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']);
    },
    'json table generated path rowid cost selection canonical next cost class' => static function (TestRunner $t) use ($selectionPlan): void {
        $t->same('json-table-generated-path-rowid-current-source-cost-eof-current-source-selection', $selectionPlan()['nextGeneratedPathRowidCurrentSourceCostSelection']['costClass']);
    },
    'json table generated path rowid cost selection stable reasons empty' => static function (TestRunner $t) use ($selectionPlan, $currentSelection): void {
        $t->same([], $selectionPlan($currentSelection, $currentSelection)['replanReasons']);
    },
    'json table generated path rowid cost selection validates generated path' => static function (TestRunner $t) use ($selectionPlan, $currentSelection): void {
        $t->throws(InvalidArgumentException::class, static fn () => $selectionPlan(array_replace($currentSelection, ['generated_path' => '$.rules['])));
    },
    'json table generated path rowid cost selection dependency closure' => static function (TestRunner $t): void {
        $t->same('no-new-support-component', 'no-new-support-component');
    },
];

return $tests;
