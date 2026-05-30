<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentFinalRangeEarly = [
    'option_id' => 10171032,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_final_range_early',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-10171032-a',
];
$nextFinalRangeEarly = array_replace($currentFinalRangeEarly, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-10171032-b',
]);

$finalRangeEarlyPlan = static function (int $next, ?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($currentFinalRangeEarly, $nextFinalRangeEarly): array {
    return SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
        'json_tree',
        $next,
        $current ?? $currentFinalRangeEarly,
        $nextSource ?? $nextFinalRangeEarly,
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
foreach (range(1017, 1032) as $next) {
    $tests["json table generated path rowid cost final early range records dependency {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->true(in_array("sqlite-json-table-generated-path-rowid-cost-current-source-next{$next}", $finalRangeEarlyPlan($next)['dependencies'], true));
    };
    $tests["json table generated path rowid cost final early range records stable dependency {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $finalRangeEarlyPlan($next)['dependencies'], true));
    };
    $tests["json table generated path rowid cost final early range exposes canonical reader policy {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same('cost-select-current-json-table-generated-path-rowid', $finalRangeEarlyPlan($next)['canonicalCurrentReaderPolicy']);
    };
    $tests["json table generated path rowid cost final early range exposes canonical reprepare policy {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $finalRangeEarlyPlan($next)['canonicalNextReaderPolicy']);
    };
    $tests["json table generated path rowid cost final early range pins reader policy {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same("cost-select-current-json-table-generated-path-rowid-next{$next}", $finalRangeEarlyPlan($next)['currentReaderPolicy']);
    };
    $tests["json table generated path rowid cost final early range reprepare policy {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same("reprepare-cost-select-next-json-table-generated-path-rowid-next{$next}", $finalRangeEarlyPlan($next)['nextReaderPolicy']);
    };
    $tests["json table generated path rowid cost final early range aliases current key {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same('$.rules', $finalRangeEarlyPlan($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost final early range aliases next key {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same('$.rules[1]', $finalRangeEarlyPlan($next)["nextGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost final early range aliases reason {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->true(in_array("json-table-generated-path-rowid-cost-selection-source-changed-next{$next}", $finalRangeEarlyPlan($next)["next{$next}ReplanReasons"], true));
    };
    $tests["json table generated path rowid cost final early range exposes stable reason {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed', $finalRangeEarlyPlan($next)['replanReasons'], true));
    };
    $tests["json table generated path rowid cost final early range preserves rowid cost {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $next): void {
        $t->same(1, $finalRangeEarlyPlan($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['estimatedCost']);
    };
    $tests["json table generated path rowid cost final early range stable reuses {$next}"] = static function (TestRunner $t) use ($finalRangeEarlyPlan, $currentFinalRangeEarly, $next): void {
        $t->same("reuse-cost-select-current-json-table-generated-path-rowid-next{$next}", $finalRangeEarlyPlan($next, $currentFinalRangeEarly, $currentFinalRangeEarly)['nextReaderPolicy']);
    };
}

$tests['json table generated path rowid cost final early range rejects out of range alias'] = static function (TestRunner $t) use ($finalRangeEarlyPlan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $finalRangeEarlyPlan(1065));
};

return $tests;
