<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current489504 = [
    'option_id' => 489504,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next489504',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-489504-a',
];
$next489504 = array_replace($current489504, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-489504-b',
]);

$plan489504 = static function (int $next, ?array $current = null, ?array $nextSource = null, ?array $orderBy = null) use ($current489504, $next489504): array {
    $method = 'currentSourceGeneratedPathRowidCostCurrentSourceNext' . $next;

    return SQLiteJsonTablePlan::$method(
        'json_tree',
        $current ?? $current489504,
        $nextSource ?? $next489504,
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
foreach (range(489, 504) as $next) {
    $tests["json table generated path rowid cost current source next{$next} records dependency"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->true(in_array("sqlite-json-table-generated-path-rowid-cost-current-source-next{$next}", $plan489504($next)['dependencies'], true));
    };
    $tests["json table generated path rowid cost current source next{$next} pins reader policy"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->same("cost-select-current-json-table-generated-path-rowid-next{$next}", $plan489504($next)['currentReaderPolicy']);
    };
    $tests["json table generated path rowid cost current source next{$next} reprepare policy"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->same("reprepare-cost-select-next-json-table-generated-path-rowid-next{$next}", $plan489504($next)['nextReaderPolicy']);
    };
    $tests["json table generated path rowid cost current source next{$next} aliases current key"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->same('$.rules', $plan489504($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost current source next{$next} aliases next key"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->same('$.rules[1]', $plan489504($next)["nextGeneratedPathRowidCurrentSourceCostSelection{$next}"]['generatedPath']);
    };
    $tests["json table generated path rowid cost current source next{$next} aliases reason"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->true(in_array("json-table-generated-path-rowid-cost-selection-source-changed-next{$next}", $plan489504($next)["next{$next}ReplanReasons"], true));
    };
    $tests["json table generated path rowid cost current source next{$next} preserves rowid cost"] = static function (TestRunner $t) use ($plan489504, $next): void {
        $t->same(1, $plan489504($next)["currentGeneratedPathRowidCurrentSourceCostSelection{$next}"]['estimatedCost']);
    };
    $tests["json table generated path rowid cost current source next{$next} stable reuses"] = static function (TestRunner $t) use ($plan489504, $current489504, $next): void {
        $t->same("reuse-cost-select-current-json-table-generated-path-rowid-next{$next}", $plan489504($next, $current489504, $current489504)['nextReaderPolicy']);
    };
}

$tests['json table generated path rowid cost current source next504 hands off to next505'] = static function (TestRunner $t) use ($plan489504): void {
    $t->same('$.rules', $plan489504(505)['currentGeneratedPathRowidCurrentSourceCostSelection505']['generatedPath']);
};

return $tests;
