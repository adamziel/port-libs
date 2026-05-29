<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows129 = static fn (): array => [
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

$currentRows129 = static function () use ($rows129): array {
    $rows = $rows129();
    $rows[] = ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$stat4129 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4129 = static function () use ($stat4129): array {
    $samples = $stat4129();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

    return $samples;
};

$source129 = static function (array $overrides = []) use ($rows129, $stat4129): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1290',
        'schemaCookie' => 1290,
        'stat4Generation' => 14,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial',
        'rootPage' => 54,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows129(),
        'stat4Samples' => $stat4129(),
    ];
};

$currentSource129 = static function (array $overrides = []) use ($currentRows129, $currentStat4129): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1291',
        'schemaCookie' => 1291,
        'stat4Generation' => 15,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial',
        'rootPage' => 57,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows129(),
        'stat4Samples' => $currentStat4129(),
    ];
};

$partial129 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$point129 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range129 = static fn (mixed $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$query129 = [
    $point129('kind', 'plugin'),
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    $range129(['expression' => 'lower(option_name)'], '>=', 'plugin_'),
];
$order129 = [
    ['expression' => 'kind'],
    ['expression' => 'lower(option_name)'],
];
$needed129 = ['option_name', 'option_value'];

$plan129 = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $needed = null, ?array $query = null): array => SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan(
    $prepared ?? $source129(),
    $current ?? $currentSource129(),
    $partial129,
    $query ?? $query129,
    $order ?? $order129,
    $needed ?? $needed129,
);

$tests = [
    'planner partial expression skipscan current source current-source selects current when stale' => static fn (TestRunner $t) => $t->same('current', $plan129()['selectedSource']),
    'planner partial expression skipscan current source current-source marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan129()['stalePreparedStatement']),
    'planner partial expression skipscan current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan129()['reprepareRequired']),
    'planner partial expression skipscan current source current-source detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $plan129()['schemaCookieChanged']),
    'planner partial expression skipscan current source current-source detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $plan129()['stat4GenerationChanged']),
    'planner partial expression skipscan current source current-source keeps expression stable' => static fn (TestRunner $t) => $t->same(false, $plan129()['rangeExpressionChanged']),
    'planner partial expression skipscan current source current-source keeps expression column stable' => static fn (TestRunner $t) => $t->same(false, $plan129()['expressionColumnChanged']),
    'planner partial expression skipscan current source current-source keeps expression signature stable' => static fn (TestRunner $t) => $t->same(false, $plan129()['expressionSignatureChanged']),
    'planner partial expression skipscan current source current-source current fence expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan129()['currentSourceFence']['rangeExpression']),
    'planner partial expression skipscan current source current-source current fence expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan129()['currentSourceFence']['rangeExpressionColumn']),
    'planner partial expression skipscan current source current-source selected status usable' => static fn (TestRunner $t) => $t->same('usable', $plan129()['status']),
    'planner partial expression skipscan current source current-source selected rowids include mixed case current rows' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan129()['selectedPlan']['rowids']),
    'planner partial expression skipscan current source current-source prepared rowids omit current insertions' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5, 7, 9], $plan129()['preparedExpression']['rowids']),
    'planner partial expression skipscan current source current-source current rowids include theta' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan129()['currentExpression']['rowids']),
    'planner partial expression skipscan current source current-source lower materializes mixed case beta' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan129()['preparedExpression']['expressionKeys'][2]),
    'planner partial expression skipscan current source current-source lower materializes uppercase theta' => static fn (TestRunner $t) => $t->same('plugin_theta', $plan129()['currentExpression']['expressionKeys'][10]),
    'planner partial expression skipscan current source current-source selected expression flag' => static fn (TestRunner $t) => $t->same(true, $plan129()['selectedPlan']['expressionSkipScan']),
    'planner partial expression skipscan current source current-source selected expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan129()['selectedPlan']['rangeExpressionColumn']),
    'planner partial expression skipscan current source current-source selected range column is materialized expression' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan129()['selectedPlan']['rangeColumn']),
    'planner partial expression skipscan current source current-source selected range expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan129()['selectedPlan']['rangeExpression']),
    'planner partial expression skipscan current source current-source remains covering' => static fn (TestRunner $t) => $t->same(true, $plan129()['selectedPlan']['covering']),
    'planner partial expression skipscan current source current-source avoids table seek' => static fn (TestRunner $t) => $t->same(false, $plan129()['selectedPlan']['tableSeekRequired']),
    'planner partial expression skipscan current source current-source covering mode names expression' => static fn (TestRunner $t) => $t->same('covering-skipscan-block-sort-expression', $plan129()['selectedPlan']['coveringMode']),
    'planner partial expression skipscan current source current-source sort break is autoload' => static fn (TestRunner $t) => $t->same(['autoload'], $plan129()['selectedPlan']['sortBreakColumns']),
    'planner partial expression skipscan current source current-source order mode partial current next' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan129()['selectedPlan']['orderByMode']),
    'planner partial expression skipscan current source current-source selected cost' => static fn (TestRunner $t) => $t->same(46, $plan129()['selectedPlan']['estimatedCost']),
    'planner partial expression skipscan current source current-source selected estimated rows' => static fn (TestRunner $t) => $t->same(7, $plan129()['selectedPlan']['estimatedRows']),
    'planner partial expression skipscan current source current-source selected samples used' => static fn (TestRunner $t) => $t->same(8, $plan129()['selectedPlan']['stat4SamplesUsed']),
    'planner partial expression skipscan current source current-source covered row count' => static fn (TestRunner $t) => $t->same(8, $plan129()['selectedPlan']['coveredRowCount']),
    'planner partial expression skipscan current source current-source cursor reads needed payload' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', '__expr_lower_option_name'], $plan129()['selectedPlan']['cursorProgram'][3]['columns']),
    'planner partial expression skipscan current source current-source first covering row keeps original case' => static fn (TestRunner $t) => $t->same('Plugin_Beta', $plan129()['selectedPlan']['currentNextCoveringRows'][0]['next']['covering']['option_name']),
    'planner partial expression skipscan current source current-source current source auto range samples' => static fn (TestRunner $t) => $t->same(3, $plan129()['selectedPlan']['stat4CurrentNextByPrefix'][0]['rangeSamples']),
    'planner partial expression skipscan current source current-source current source no range samples' => static fn (TestRunner $t) => $t->same(2, $plan129()['selectedPlan']['stat4CurrentNextByPrefix'][2]['rangeSamples']),
    'planner partial expression skipscan current source current-source detail reports expression' => static fn (TestRunner $t) => $t->contains('PARTIAL EXPRESSION SKIP-SCAN current-main.wp_options@cookie1291 expr=lower(option_name)', $plan129()['detail']),
    'planner partial expression skipscan current source current-source selected detail reports expression range' => static fn (TestRunner $t) => $t->contains('EXPRESSION RANGE lower(option_name)', $plan129()['selectedPlan']['detail']),
    'planner partial expression skipscan current source current-source dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-partial-expression-skipscan-current-source'], $plan129()['dependencies']),
    'planner partial expression skipscan current source current-source dependency closure note' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan129()['dependency_closure']),
];

$tests += [
    'planner partial expression skipscan current source current-source reuses identical source' => static function (TestRunner $t) use ($source129, $plan129): void {
        $source = $source129();
        $t->same('prepared', $plan129($source, $source)['selectedSource']);
    },
    'planner partial expression skipscan current source current-source identical source is not stale' => static function (TestRunner $t) use ($source129, $plan129): void {
        $source = $source129();
        $t->same(false, $plan129($source, $source)['stalePreparedStatement']);
    },
    'planner partial expression skipscan current source current-source identical source row count' => static function (TestRunner $t) use ($source129, $plan129): void {
        $source = $source129();
        $t->same(6, $plan129($source, $source)['selectedPlan']['coveredRowCount']);
    },
    'planner partial expression skipscan current source current-source identical source detail reports reuse' => static function (TestRunner $t) use ($source129, $plan129): void {
        $source = $source129();
        $t->contains('REUSE PREPARED PARTIAL EXPRESSION SKIP-SCAN', $plan129($source, $source)['detail']);
    },
    'planner partial expression skipscan current source current-source changed expression fences reprepare' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['rangeExpression' => 'upper(option_name)', 'lowerInclusive' => 'PLUGIN_', 'upperBound' => 'PLUGIN_ZZZZ']);
        $t->same(true, $plan129($source129(), $current)['rangeExpressionChanged']);
    },
    'planner partial expression skipscan current source current-source changed expression still uses current' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['rangeExpression' => 'upper(option_name)', 'lowerInclusive' => 'PLUGIN_', 'upperBound' => 'PLUGIN_ZZZZ']);
        $t->same('current', $plan129($source129(), $current)['selectedSource']);
    },
    'planner partial expression skipscan current source current-source changed expression materializes upper' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['rangeExpression' => 'upper(option_name)', 'lowerInclusive' => 'PLUGIN_', 'upperBound' => 'PLUGIN_ZZZZ']);
        $t->same('PLUGIN_THETA', $plan129($source129(), $current)['currentExpression']['expressionKeys'][10]);
    },
    'planner partial expression skipscan current source current-source changed expression column is fenced' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['rangeExpressionColumn' => '__expr_name_folded']);
        $t->same(true, $plan129($source129(), $current)['expressionColumnChanged']);
    },
    'planner partial expression skipscan current source current-source exclusive upper removes boundary' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['upperBound' => 'plugin_gamma', 'upperInclusive' => false]);
        $t->same([2, 3, 4, 5, 9], $plan129($source129(), $current)['selectedPlan']['rowids']);
    },
    'planner partial expression skipscan current source current-source narrowed upper keeps gamma boundary' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['upperBound' => 'plugin_gamma']);
        $t->same([2, 3, 4, 5, 7, 9], $plan129($source129(), $current)['selectedPlan']['rowids']);
    },
    'planner partial expression skipscan current source current-source desc expression uses reverse cursor' => static function (TestRunner $t) use ($plan129): void {
        $p = $plan129(null, null, [['expression' => 'kind'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same('LastPrefix', $p['selectedPlan']['cursorProgram'][0]['opcode']);
    },
    'planner partial expression skipscan current source current-source desc expression reverse flag' => static function (TestRunner $t) use ($plan129): void {
        $p = $plan129(null, null, [['expression' => 'kind'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same(true, $p['selectedPlan']['reverseScan']);
    },
    'planner partial expression skipscan current source current-source full prefix expression order avoids block sort' => static function (TestRunner $t) use ($plan129): void {
        $p = $plan129(null, null, [['expression' => 'autoload'], ['expression' => 'lower(option_name)']]);
        $t->same(false, $p['selectedPlan']['blockSortRequired']);
    },
    'planner partial expression skipscan current source current-source full prefix expression mode' => static function (TestRunner $t) use ($plan129): void {
        $p = $plan129(null, null, [['expression' => 'autoload'], ['expression' => 'lower(option_name)']]);
        $t->same('full', $p['selectedPlan']['orderByMode']);
    },
    'planner partial expression skipscan current source current-source all constant order avoids block sort' => static function (TestRunner $t) use ($plan129): void {
        $p = $plan129(null, null, [['expression' => 'kind']]);
        $t->same(false, $p['selectedPlan']['blockSortRequired']);
    },
    'planner partial expression skipscan current source current-source missing payload forces table seek' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan129($source129(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner partial expression skipscan current source current-source missing payload rejects covering' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(false, $plan129($source129(), $current)['selectedPlan']['covering']);
    },
    'planner partial expression skipscan current source current-source missing payload names rejection' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $current = $currentSource129(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(['option_value'], $plan129($source129(), $current)['selectedPlan']['coveringRejectedColumns']);
    },
    'planner partial expression skipscan current source current-source supports trim expression materialization' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $prepared = $source129(['rangeExpression' => 'trim(option_name)']);
        $current = $currentSource129(['rangeExpression' => 'trim(option_name)']);
        $t->same('PLUGIN_THETA', $plan129($prepared, $current)['currentExpression']['expressionKeys'][10]);
    },
    'planner partial expression skipscan current source current-source supports length expression materialization' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $prepared = $source129(['rangeExpression' => 'length(option_name)', 'lowerInclusive' => 6, 'upperBound' => 20, 'stat4Samples' => [['prefix' => 'auto', 'suffix' => 12, 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1]]]);
        $current = $currentSource129(['rangeExpression' => 'length(option_name)', 'lowerInclusive' => 6, 'upperBound' => 20, 'stat4Samples' => [['prefix' => 'auto', 'suffix' => 12, 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1]]]);
        $t->same(12, $plan129($prepared, $current)['currentExpression']['expressionKeys'][1]);
    },
    'planner partial expression skipscan current source current-source validates expression text' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $bad = $source129(['rangeExpression' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan129($bad, $currentSource129()));
    },
    'planner partial expression skipscan current source current-source rejects unsupported expression' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $bad = $source129(['rangeExpression' => 'json_extract(option_value, "$.x")']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan129($bad, $currentSource129()));
    },
    'planner partial expression skipscan current source current-source validates expression column' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $bad = $source129(['rangeExpressionColumn' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan129($bad, $currentSource129()));
    },
    'planner partial expression skipscan current source current-source validates order expression' => static function (TestRunner $t) use ($plan129): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan129(null, null, [['expression' => '']]));
    },
    'planner partial expression skipscan current source current-source validates order direction' => static function (TestRunner $t) use ($plan129): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan129(null, null, [['expression' => 'lower(option_name)', 'direction' => 'SIDEWAYS']]));
    },
    'planner partial expression skipscan current source current-source validates needed column' => static function (TestRunner $t) use ($plan129): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan129(null, null, null, ['']));
    },
    'planner partial expression skipscan current source current-source validates stat4 counters' => static function (TestRunner $t) use ($source129, $currentSource129, $plan129): void {
        $bad = $currentSource129(['stat4Samples' => [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan129($source129(), $bad));
    },
];

return $tests;
