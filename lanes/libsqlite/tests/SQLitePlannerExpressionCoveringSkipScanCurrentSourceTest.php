<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows132 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'Plugin_Beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Delta', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_epsilon', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => '_transient_plugin_alpha', 'option_value' => 'tmp', 'kind' => 'transient'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'option_value' => 'theme', 'kind' => 'theme'],
];

$currentRows132 = static function () use ($rows132): array {
    $rows = $rows132();
    $rows[] = ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$stat4132 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4132 = static function () use ($stat4132): array {
    $samples = $stat4132();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

    return $samples;
};

$source132 = static function (array $overrides = []) use ($rows132, $stat4132): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1320',
        'schemaCookie' => 1320,
        'stat4Generation' => 20,
        'indexName' => 'idx_wp_options_autoload_lower_name_covering_current-source',
        'rootPage' => 72,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'coveringExpressions' => ['lower(option_name)'],
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows132(),
        'stat4Samples' => $stat4132(),
    ];
};

$currentSource132 = static function (array $overrides = []) use ($currentRows132, $currentStat4132): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1321',
        'schemaCookie' => 1321,
        'stat4Generation' => 21,
        'indexName' => 'idx_wp_options_autoload_lower_name_covering_current-source',
        'rootPage' => 77,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'coveringExpressions' => ['lower(option_name)'],
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows132(),
        'stat4Samples' => $currentStat4132(),
    ];
};

$partial132 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$point132 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range132 = static fn (mixed $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$query132 = [
    $point132('kind', 'plugin'),
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    $range132(['expression' => 'lower(option_name)'], '>=', 'plugin_'),
];
$order132 = [
    ['expression' => 'kind'],
    ['expression' => 'lower(option_name)'],
];
$needed132 = ['option_name', 'option_value'];
$neededExpressions132 = ['lower(option_name)'];

$plan132 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $order = null,
    ?array $needed = null,
    ?array $query = null,
    ?array $neededExpressions = null,
): array => SQLiteSkipScanStat4PartialOrderPlan::expressionCoveringSkipScan(
    $prepared ?? $source132(),
    $current ?? $currentSource132(),
    $partial132,
    $query ?? $query132,
    $order ?? $order132,
    $needed ?? $needed132,
    $neededExpressions ?? $neededExpressions132,
);

$tests = [
    'planner expression covering skipscan current source current-source selects current when stale' => static fn (TestRunner $t) => $t->same('current', $plan132()['selectedSource']),
    'planner expression covering skipscan current source current-source marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan132()['stalePreparedStatement']),
    'planner expression covering skipscan current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan132()['reprepareRequired']),
    'planner expression covering skipscan current source current-source detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $plan132()['schemaCookieChanged']),
    'planner expression covering skipscan current source current-source detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $plan132()['stat4GenerationChanged']),
    'planner expression covering skipscan current source current-source status usable' => static fn (TestRunner $t) => $t->same('usable', $plan132()['status']),
    'planner expression covering skipscan current source current-source dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-expression-covering-skipscan-current-source'], $plan132()['dependencies']),
    'planner expression covering skipscan current source current-source dependency closure note' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan132()['dependency_closure']),
    'planner expression covering skipscan current source current-source remains expression skipscan' => static fn (TestRunner $t) => $t->same(true, $plan132()['selectedPlan']['expressionSkipScan']),
    'planner expression covering skipscan current source current-source expression covering true' => static fn (TestRunner $t) => $t->same(true, $plan132()['selectedPlan']['expressionCovering']),
    'planner expression covering skipscan current source current-source remains covering' => static fn (TestRunner $t) => $t->same(true, $plan132()['selectedPlan']['covering']),
    'planner expression covering skipscan current source current-source avoids table seek' => static fn (TestRunner $t) => $t->same(false, $plan132()['selectedPlan']['tableSeekRequired']),
    'planner expression covering skipscan current source current-source no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan132()['selectedPlan']['deferredSeekOpcode']),
    'planner expression covering skipscan current source current-source covering mode names expression payload' => static fn (TestRunner $t) => $t->same('covering-skipscan-block-sort-expression-expr-covering', $plan132()['selectedPlan']['coveringMode']),
    'planner expression covering skipscan current source current-source detail names covering expressions' => static fn (TestRunner $t) => $t->contains('USING COVERING EXPRESSIONS', $plan132()['selectedPlan']['detail']),
    'planner expression covering skipscan current source current-source top detail records yes' => static fn (TestRunner $t) => $t->contains('expression-covering=yes', $plan132()['detail']),
    'planner expression covering skipscan current source current-source selected rowids include current inserts' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan132()['selectedPlan']['rowids']),
    'planner expression covering skipscan current source current-source covered row count' => static fn (TestRunner $t) => $t->same(8, $plan132()['selectedPlan']['coveredRowCount']),
    'planner expression covering skipscan current source current-source expression row count' => static fn (TestRunner $t) => $t->same(8, count($plan132()['selectedPlan']['expressionCoveringRows'])),
    'planner expression covering skipscan current source current-source lower payload beta' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan132()['selectedPlan']['expressionCoveringRows'][0]['next']['coveringExpressions']['lower(option_name)']),
    'planner expression covering skipscan current source current-source lower payload theta' => static fn (TestRunner $t) => $t->same('plugin_theta', $plan132()['selectedPlan']['expressionCoveringRows'][6]['current']['coveringExpressions']['lower(option_name)']),
    'planner expression covering skipscan current source current-source keeps original case payload' => static fn (TestRunner $t) => $t->same('Plugin_Beta', $plan132()['selectedPlan']['expressionCoveringRows'][0]['next']['covering']['option_name']),
    'planner expression covering skipscan current source current-source expression column alias' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan132()['selectedPlan']['expressionCoveringColumns']['lower(option_name)']),
    'planner expression covering skipscan current source current-source cursor exposes expression columns' => static fn (TestRunner $t) => $t->same(['lower(option_name)' => '__expr_lower_option_name'], $plan132()['selectedPlan']['cursorProgram'][3]['expressionColumns']),
    'planner expression covering skipscan current source current-source cursor appends expression alias' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', '__expr_lower_option_name'], $plan132()['selectedPlan']['cursorProgram'][3]['columns']),
    'planner expression covering skipscan current source current-source needed expression signature present' => static fn (TestRunner $t) => $t->same(true, is_string($plan132()['currentSourceFence']['neededExpressionSignature'])),
    'planner expression covering skipscan current source current-source covering expression signature present' => static fn (TestRunner $t) => $t->same(true, is_string($plan132()['currentSourceFence']['coveringExpressionSignature'])),
    'planner expression covering skipscan current source current-source selected cost preserved' => static fn (TestRunner $t) => $t->same(46, $plan132()['selectedPlan']['estimatedCost']),
    'planner expression covering skipscan current source current-source stat4 samples preserved' => static fn (TestRunner $t) => $t->same(8, $plan132()['selectedPlan']['stat4SamplesUsed']),
    'planner expression covering skipscan current source current-source partial order preserved' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan132()['selectedPlan']['orderByMode']),
    'planner expression covering skipscan current source current-source block sort preserved' => static fn (TestRunner $t) => $t->same(true, $plan132()['selectedPlan']['blockSortRequired']),
    'planner expression covering skipscan current source current-source sort break preserved' => static fn (TestRunner $t) => $t->same(['autoload'], $plan132()['selectedPlan']['sortBreakColumns']),
];

$tests += [
    'planner expression covering skipscan current source current-source reuses identical source' => static function (TestRunner $t) use ($source132, $plan132): void {
        $source = $source132();
        $t->same('prepared', $plan132($source, $source)['selectedSource']);
    },
    'planner expression covering skipscan current source current-source identical source expression rows' => static function (TestRunner $t) use ($source132, $plan132): void {
        $source = $source132();
        $t->same(6, count($plan132($source, $source)['selectedPlan']['expressionCoveringRows']));
    },
    'planner expression covering skipscan current source current-source identical source is not stale' => static function (TestRunner $t) use ($source132, $plan132): void {
        $source = $source132();
        $t->same(false, $plan132($source, $source)['stalePreparedStatement']);
    },
    'planner expression covering skipscan current source current-source missing expression needs table' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['length(option_name)']]);
        $t->same(true, $plan132($source132(), $current, null, null, null, ['upper(option_name)'])['selectedPlan']['tableSeekRequired']);
    },
    'planner expression covering skipscan current source current-source missing expression rejects covering' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['length(option_name)']]);
        $t->same(false, $plan132($source132(), $current, null, null, null, ['upper(option_name)'])['selectedPlan']['expressionCovering']);
    },
    'planner expression covering skipscan current source current-source missing expression names rejection' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['length(option_name)']]);
        $t->same(['upper(option_name)'], $plan132($source132(), $current, null, null, null, ['upper(option_name)'])['selectedPlan']['expressionCoveringRejected']);
    },
    'planner expression covering skipscan current source current-source missing expression mode' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['length(option_name)']]);
        $t->same('skipscan-expression-covering-table-seek', $plan132($source132(), $current, null, null, null, ['upper(option_name)'])['selectedPlan']['coveringMode']);
    },
    'planner expression covering skipscan current source current-source missing expression detail' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['length(option_name)']]);
        $t->contains('EXPRESSION PROJECTION NEEDS TABLE', $plan132($source132(), $current, null, null, null, ['upper(option_name)'])['selectedPlan']['detail']);
    },
    'planner expression covering skipscan current source current-source range expression is implicitly covered' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => []]);
        $t->same(true, $plan132($source132(), $current)['selectedPlan']['expressionCovering']);
    },
    'planner expression covering skipscan current source current-source changed expression uses current alias' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['rangeExpression' => 'upper(option_name)', 'rangeExpressionColumn' => '__expr_upper_option_name', 'coveringExpressions' => ['upper(option_name)'], 'lowerInclusive' => 'PLUGIN_', 'upperBound' => 'PLUGIN_ZZZZ']);
        $p = $plan132($source132(), $current, null, null, null, ['upper(option_name)']);
        $t->same('__expr_upper_option_name', $p['selectedPlan']['expressionCoveringColumns']['upper(option_name)']);
    },
    'planner expression covering skipscan current source current-source changed expression upper payload' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['rangeExpression' => 'upper(option_name)', 'rangeExpressionColumn' => '__expr_upper_option_name', 'coveringExpressions' => ['upper(option_name)'], 'lowerInclusive' => 'PLUGIN_', 'upperBound' => 'PLUGIN_ZZZZ']);
        $p = $plan132($source132(), $current, null, null, null, ['upper(option_name)']);
        $t->same('PLUGIN_THETA', $p['selectedPlan']['expressionCoveringRows'][6]['current']['coveringExpressions']['upper(option_name)']);
    },
    'planner expression covering skipscan current source current-source desc keeps reverse cursor' => static function (TestRunner $t) use ($plan132): void {
        $p = $plan132(null, null, [['expression' => 'kind'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same('LastPrefix', $p['selectedPlan']['cursorProgram'][0]['opcode']);
    },
    'planner expression covering skipscan current source current-source full prefix order avoids block sort' => static function (TestRunner $t) use ($plan132): void {
        $p = $plan132(null, null, [['expression' => 'autoload'], ['expression' => 'lower(option_name)']]);
        $t->same(false, $p['selectedPlan']['blockSortRequired']);
    },
    'planner expression covering skipscan current source current-source exclusive upper removes theta' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['upperBound' => 'plugin_theta', 'upperInclusive' => false]);
        $t->same(false, in_array(11, $plan132($source132(), $current)['selectedPlan']['rowids'], true));
    },
    'planner expression covering skipscan current source current-source validates needed expression' => static function (TestRunner $t) use ($plan132): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan132(null, null, null, null, null, ['']));
    },
    'planner expression covering skipscan current source current-source validates source expression list' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $bad = $currentSource132(['coveringExpressions' => ['lower(option_name)', '']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan132($source132(), $bad));
    },
    'planner expression covering skipscan current source current-source hashed alias for non range expression' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['lower(option_name)', 'length(option_name)']]);
        $p = $plan132($source132(), $current, null, null, null, ['length(option_name)']);
        $t->same(true, str_starts_with($p['selectedPlan']['expressionCoveringColumns']['length(option_name)'], '__expr_'));
    },
    'planner expression covering skipscan current source current-source length payload from covered expression' => static function (TestRunner $t) use ($source132, $currentSource132, $plan132): void {
        $current = $currentSource132(['coveringExpressions' => ['lower(option_name)', 'length(option_name)']]);
        $p = $plan132($source132(), $current, null, null, null, ['length(option_name)']);
        $t->same(12, $p['selectedPlan']['expressionCoveringRows'][0]['current']['coveringExpressions']['length(option_name)']);
    },
    'planner expression covering skipscan current source current-source validates unsupported expression' => static function (TestRunner $t) use ($plan132): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan132(null, null, null, null, null, ['json_extract(option_value, "$.x")']));
    },
];

return $tests;
