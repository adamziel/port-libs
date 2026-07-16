<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentFinalRangeLate = [
    'option_id' => 10331048,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_final_range_late',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-10331048-a',
];
$nextFinalRangeLate = array_replace($currentFinalRangeLate, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-10331048-b',
]);

$finalRangeLatePlan = static function (int $next, ?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($currentFinalRangeLate, $nextFinalRangeLate): array {
    return SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
        'json_tree',
        $next,
        $current ?? $currentFinalRangeLate,
        $nextSource ?? $nextFinalRangeLate,
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

$tests = [];
foreach (range(1033, 1048) as $next) {
    $tests["json table generated path rowid cost final late range records dependency {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->true(in_array("sqlite-json-table-generated-path-rowid-cost-current-source-next{$next}", $finalRangeLatePlan($next)['dependencies'], true));
    };
    $tests["json table generated path rowid cost final late range records stable dependency {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $finalRangeLatePlan($next)['dependencies'], true));
    };
    $tests["json table generated path rowid cost final late range exposes canonical reader policy {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same('cost-select-current-json-table-generated-path-rowid', $finalRangeLatePlan($next)['canonicalCurrentReaderPolicy']);
    };
    $tests["json table generated path rowid cost final late range exposes canonical reprepare policy {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $finalRangeLatePlan($next)['canonicalNextReaderPolicy']);
    };
    $tests["json table generated path rowid cost final late range pins reader policy {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same("cost-select-current-json-table-generated-path-rowid-next{$next}", $finalRangeLatePlan($next)['currentReaderPolicy']);
    };
    $tests["json table generated path rowid cost final late range reprepare policy {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same("reprepare-cost-select-next-json-table-generated-path-rowid-next{$next}", $finalRangeLatePlan($next)['nextReaderPolicy']);
    };
    $tests["json table generated path rowid cost final late range aliases current key {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same('$.rules', $finalRangeLatePlan($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost final late range aliases next key {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same('$.rules[1]', $finalRangeLatePlan($next)["nextGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost final late range aliases reason {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->true(in_array("json-table-generated-path-rowid-cost-selection-source-changed-next{$next}", $finalRangeLatePlan($next)["next{$next}ReplanReasons"], true));
    };
    $tests["json table generated path rowid cost final late range exposes stable reason {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed', $finalRangeLatePlan($next)['replanReasons'], true));
    };
    $tests["json table generated path rowid cost final late range preserves rowid cost {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $next): void {
        $t->same(1, $finalRangeLatePlan($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['estimatedCost']);
    };
    $tests["json table generated path rowid cost final late range stable reuses {$next}"] = static function (TestRunner $t) use ($finalRangeLatePlan, $currentFinalRangeLate, $next): void {
        $t->same("reuse-cost-select-current-json-table-generated-path-rowid-next{$next}", $finalRangeLatePlan($next, $currentFinalRangeLate, $currentFinalRangeLate)['nextReaderPolicy']);
    };
}

$tests['json table generated path rowid cost final late range accepts successor alias'] = static function (TestRunner $t) use ($finalRangeLatePlan): void {
    $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next1049', $finalRangeLatePlan(1049)['dependencies'], true));
};

return $tests;
