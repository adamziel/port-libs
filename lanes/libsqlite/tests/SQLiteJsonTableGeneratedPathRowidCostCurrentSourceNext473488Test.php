<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current473488 = [
    'option_id' => 473488,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next473488',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-473488-a',
];
$next473488 = array_replace($current473488, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-473488-b',
]);

$plan473488 = static function (int $next, ?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($current473488, $next473488): array {
    $method = 'currentSourceGeneratedPathRowidCostCurrentSourceNext' . $next;

    return SQLiteJsonTablePlan::$method(
        'json_tree',
        $current ?? $current473488,
        $nextSource ?? $next473488,
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
foreach (range(473, 488) as $next) {
    $tests["json table generated path rowid cost current source next{$next} records dependency"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->true(in_array("sqlite-json-table-generated-path-rowid-cost-current-source-next{$next}", $plan473488($next)['dependencies'], true));
    };
    $tests["json table generated path rowid cost current source next{$next} pins reader policy"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->same("cost-select-current-json-table-generated-path-rowid-next{$next}", $plan473488($next)['currentReaderPolicy']);
    };
    $tests["json table generated path rowid cost current source next{$next} reprepare policy"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->same("reprepare-cost-select-next-json-table-generated-path-rowid-next{$next}", $plan473488($next)['nextReaderPolicy']);
    };
    $tests["json table generated path rowid cost current source next{$next} aliases current key"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->same('$.rules', $plan473488($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost current source next{$next} aliases next key"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->same('$.rules[1]', $plan473488($next)["nextGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost current source next{$next} aliases reason"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->true(in_array("json-table-generated-path-rowid-cost-selection-source-changed-next{$next}", $plan473488($next)["next{$next}ReplanReasons"], true));
    };
    $tests["json table generated path rowid cost current source next{$next} preserves rowid cost"] = static function (TestRunner $t) use ($plan473488, $next): void {
        $t->same(1, $plan473488($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['estimatedCost']);
    };
    $tests["json table generated path rowid cost current source next{$next} stable reuses"] = static function (TestRunner $t) use ($plan473488, $current473488, $next): void {
        $t->same("reuse-cost-select-current-json-table-generated-path-rowid-next{$next}", $plan473488($next, $current473488, $current473488)['nextReaderPolicy']);
    };
}

$tests['json table generated path rowid cost current source next488 hands off to next489'] = static function (TestRunner $t) use ($plan473488): void {
    $t->same('$.rules', $plan473488(489)['currentGeneratedPathRowidCurrentSourceCostSelection489']['generatedPath']);
};

return $tests;
