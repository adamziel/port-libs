<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentCostReplay = [
    'option_id' => 1064,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_selection_replay',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-cost-selection-replay-a',
];
$nextCostReplay = array_replace($currentCostReplay, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-cost-selection-replay-b',
]);

$planCostReplay = static function (?array $current = null, ?array $nextSource = null, ?array $orderBy = null): array {
    global $currentCostReplay, $nextCostReplay;

    return SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionPlan(
        'json_tree',
        $current ?? $currentCostReplay,
        $nextSource ?? $nextCostReplay,
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
    'json table generated path rowid cost selection replay records canonical dependency' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $planCostReplay()['dependencies'], true));
    },
    'json table generated path rowid cost selection replay omits numbered cost selection dependency' => static function (TestRunner $t) use ($planCostReplay): void {
        $numbered = array_values(array_filter(
            $planCostReplay()['dependencies'],
            static fn (string $dependency): bool => str_starts_with($dependency, 'sqlite-json-table-generated-path-rowid-cost-current-source-next'),
        ));

        $t->same([], $numbered);
    },
    'json table generated path rowid cost selection replay pins reader policy' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->same('cost-select-current-json-table-generated-path-rowid', $planCostReplay()['currentReaderPolicy']);
    },
    'json table generated path rowid cost selection replay reparses changed source' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $planCostReplay()['nextReaderPolicy']);
    },
    'json table generated path rowid cost selection replay exposes current generated path' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->same('$.rules', $planCostReplay()['currentGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
    },
    'json table generated path rowid cost selection replay exposes next generated path' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->same('$.rules[1]', $planCostReplay()['nextGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']);
    },
    'json table generated path rowid cost selection replay keeps point cost' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->same(1, $planCostReplay()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']);
    },
    'json table generated path rowid cost selection replay has canonical source-change reason' => static function (TestRunner $t) use ($planCostReplay): void {
        $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed', $planCostReplay()['replanReasons'], true));
    },
    'json table generated path rowid cost selection replay stable source reuses' => static function (TestRunner $t) use ($planCostReplay, $currentCostReplay): void {
        $t->same('reuse-cost-select-current-json-table-generated-path-rowid', $planCostReplay($currentCostReplay, $currentCostReplay)['nextReaderPolicy']);
    },
];
