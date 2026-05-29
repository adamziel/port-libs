<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows127 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Delta', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_epsilon', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => '_transient_plugin_alpha', 'option_value' => 'tmp', 'kind' => 'transient'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'option_value' => 'theme', 'kind' => 'theme'],
];

$currentRows127 = static function () use ($rows127): array {
    $rows = $rows127();
    $rows[] = ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$stat4127 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4127 = static function () use ($stat4127): array {
    $samples = $stat4127();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

    return $samples;
};

$source127 = static function (array $overrides = []) use ($rows127, $stat4127): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1270',
        'schemaCookie' => 1270,
        'stat4Generation' => 12,
        'indexName' => 'idx_wp_options_autoload_plugin_covering',
        'rootPage' => 44,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows127(),
        'stat4Samples' => $stat4127(),
    ];
};

$currentSource127 = static function (array $overrides = []) use ($currentRows127, $currentStat4127): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1271',
        'schemaCookie' => 1271,
        'stat4Generation' => 13,
        'indexName' => 'idx_wp_options_autoload_plugin_covering',
        'rootPage' => 47,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows127(),
        'stat4Samples' => $currentStat4127(),
    ];
};

$partial127 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);
$point127 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range127 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$query127 = [$point127('kind', 'plugin'), $range127('option_name', '>=', 'plugin_')];
$needed127 = ['option_name', 'option_value'];
$order127 = [
    ['expression' => 'kind'],
    ['expression' => 'option_name'],
];

$plan127 = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $needed = null, ?array $query = null): array => SQLiteSkipScanStat4PartialOrderPlan::partialCoveringSkipScan(
    $prepared ?? $source127(),
    $current ?? $currentSource127(),
    $partial127,
    $query ?? $query127,
    $order ?? $order127,
    $needed ?? $needed127,
);

$tests = [
    'planner partial covering skipscan current source current-source selects current when stale' => static fn (TestRunner $t) => $t->same('current', $plan127()['selectedSource']),
    'planner partial covering skipscan current source current-source marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan127()['stalePreparedStatement']),
    'planner partial covering skipscan current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan127()['reprepareRequired']),
    'planner partial covering skipscan current source current-source detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $plan127()['schemaCookieChanged']),
    'planner partial covering skipscan current source current-source detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $plan127()['stat4GenerationChanged']),
    'planner partial covering skipscan current source current-source keeps order expression signature stable' => static fn (TestRunner $t) => $t->same(false, $plan127()['orderExpressionSignatureChanged']),
    'planner partial covering skipscan current source current-source constant kind pruned from prepared order' => static fn (TestRunner $t) => $t->same(['kind'], $plan127()['preparedOrder']['constantExpressions']),
    'planner partial covering skipscan current source current-source constant kind pruned from current order' => static fn (TestRunner $t) => $t->same(['kind'], $plan127()['currentOrder']['constantExpressions']),
    'planner partial covering skipscan current source current-source reduced order is option name' => static fn (TestRunner $t) => $t->same([['column' => 'option_name', 'direction' => 'ASC']], $plan127()['selectedOrder']['orderBy']),
    'planner partial covering skipscan current source current-source selected status usable' => static fn (TestRunner $t) => $t->same('usable', $plan127()['status']),
    'planner partial covering skipscan current source current-source selected rowids include current rows' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan127()['selectedPlan']['rowids']),
    'planner partial covering skipscan current source current-source current source rowids include theta' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan127()['currentSource']['rowids']),
    'planner partial covering skipscan current source current-source prepared source omits current rows' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5, 7, 9], $plan127()['preparedSource']['rowids']),
    'planner partial covering skipscan current source current-source remains covering' => static fn (TestRunner $t) => $t->same(true, $plan127()['selectedPlan']['covering']),
    'planner partial covering skipscan current source current-source avoids table seek' => static fn (TestRunner $t) => $t->same(false, $plan127()['selectedPlan']['tableSeekRequired']),
    'planner partial covering skipscan current source current-source keeps block sort only for skipped prefix' => static fn (TestRunner $t) => $t->same(true, $plan127()['selectedPlan']['blockSortRequired']),
    'planner partial covering skipscan current source current-source sort break is autoload' => static fn (TestRunner $t) => $t->same(['autoload'], $plan127()['selectedPlan']['sortBreakColumns']),
    'planner partial covering skipscan current source current-source order mode partial current next' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan127()['selectedPlan']['orderByMode']),
    'planner partial covering skipscan current source current-source covering mode block sort' => static fn (TestRunner $t) => $t->same('covering-skipscan-block-sort', $plan127()['selectedPlan']['coveringMode']),
    'planner partial covering skipscan current source current-source current fence name' => static fn (TestRunner $t) => $t->same('current-main.wp_options@cookie1271', $plan127()['currentSourceFence']['name']),
    'planner partial covering skipscan current source current-source dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-partial-covering-skipscan-current-source'], $plan127()['dependencies']),
    'planner partial covering skipscan current source current-source detail reports constants' => static fn (TestRunner $t) => $t->contains('constants=1 uncovered=0', $plan127()['detail']),
    'planner partial covering skipscan current source current-source selected samples used' => static fn (TestRunner $t) => $t->same(8, $plan127()['selectedPlan']['stat4SamplesUsed']),
    'planner partial covering skipscan current source current-source selected cost' => static fn (TestRunner $t) => $t->same(46, $plan127()['selectedPlan']['estimatedCost']),
    'planner partial covering skipscan current source current-source selected covered row count' => static fn (TestRunner $t) => $t->same(8, $plan127()['selectedPlan']['coveredRowCount']),
    'planner partial covering skipscan current source current-source first covering row keeps option value' => static fn (TestRunner $t) => $t->same('a:1', $plan127()['selectedPlan']['currentNextCoveringRows'][0]['current']['covering']['option_value']),
    'planner partial covering skipscan current source current-source current next row keeps plugin beta' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan127()['selectedPlan']['currentNextCoveringRows'][0]['next']['covering']['option_name']),
    'planner partial covering skipscan current source current-source cursor reads option value' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value'], $plan127()['selectedPlan']['cursorProgram'][3]['columns']),
    'planner partial covering skipscan current source current-source current source auto range samples' => static fn (TestRunner $t) => $t->same(3, $plan127()['selectedPlan']['stat4CurrentNextByPrefix'][0]['rangeSamples']),
    'planner partial covering skipscan current source current-source current source no range samples' => static fn (TestRunner $t) => $t->same(2, $plan127()['selectedPlan']['stat4CurrentNextByPrefix'][2]['rangeSamples']),
];

$tests += [
    'planner partial covering skipscan current source current-source reuses identical source' => static function (TestRunner $t) use ($source127, $plan127): void {
        $source = $source127();
        $t->same('prepared', $plan127($source, $source)['selectedSource']);
    },
    'planner partial covering skipscan current source current-source identical source is not stale' => static function (TestRunner $t) use ($source127, $plan127): void {
        $source = $source127();
        $t->same(false, $plan127($source, $source)['stalePreparedStatement']);
    },
    'planner partial covering skipscan current source current-source identical source row count' => static function (TestRunner $t) use ($source127, $plan127): void {
        $source = $source127();
        $t->same(6, $plan127($source, $source)['selectedPlan']['coveredRowCount']);
    },
    'planner partial covering skipscan current source current-source identical source detail reports reuse' => static function (TestRunner $t) use ($source127, $plan127): void {
        $source = $source127();
        $t->contains('REUSE PREPARED PARTIAL COVERING SKIP-SCAN ORDER EXPRESSIONS', $plan127($source, $source)['detail']);
    },
    'planner partial covering skipscan current source current-source quoted option column reduces to same order' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => '"kind"'], ['expression' => '"option_name"']]);
        $t->same([['column' => 'option_name', 'direction' => 'ASC']], $p['selectedOrder']['orderBy']);
    },
    'planner partial covering skipscan current source current-source bracket option column reduces to same order' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => '[kind]'], ['expression' => '[option_name]']]);
        $t->same(['[kind]'], $p['selectedOrder']['constantExpressions']);
    },
    'planner partial covering skipscan current source current-source desc expression uses reverse scan' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'kind'], ['expression' => 'option_name', 'direction' => 'DESC']]);
        $t->same(true, $p['selectedPlan']['reverseScan']);
    },
    'planner partial covering skipscan current source current-source desc expression keeps last prefix cursor' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'kind'], ['expression' => 'option_name', 'direction' => 'DESC']]);
        $t->same('LastPrefix', $p['selectedPlan']['cursorProgram'][0]['opcode']);
    },
    'planner partial covering skipscan current source current-source all constant order avoids block sort' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'kind']]);
        $t->same(false, $p['selectedPlan']['blockSortRequired']);
    },
    'planner partial covering skipscan current source current-source all constant order uses covering skipscan' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'kind']]);
        $t->same('covering-skipscan', $p['selectedPlan']['coveringMode']);
    },
    'planner partial covering skipscan current source current-source all constant order mode none' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'kind']]);
        $t->same('none', $p['selectedPlan']['orderByMode']);
    },
    'planner partial covering skipscan current source current-source uncovered expression forces table seek' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'lower(option_name)']]);
        $t->same(true, $p['selectedPlan']['tableSeekRequired']);
    },
    'planner partial covering skipscan current source current-source uncovered expression rejects covering' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'lower(option_name)']]);
        $t->same(false, $p['selectedPlan']['covering']);
    },
    'planner partial covering skipscan current source current-source uncovered expression is named' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'lower(option_name)']]);
        $t->same(['lower(option_name)'], $p['selectedOrder']['uncoveredExpressions']);
    },
    'planner partial covering skipscan current source current-source uncovered expression switches covering mode' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'lower(option_name)']]);
        $t->same('skipscan-expression-order-table-seek', $p['selectedPlan']['coveringMode']);
    },
    'planner partial covering skipscan current source current-source uncovered expression detail' => static function (TestRunner $t) use ($plan127): void {
        $p = $plan127(null, null, [['expression' => 'lower(option_name)']]);
        $t->contains('ORDER BY EXPRESSION NEEDS TABLE', $p['selectedPlan']['detail']);
    },
    'planner partial covering skipscan current source current-source expression index column stays covering' => static function (TestRunner $t) use ($source127, $currentSource127, $plan127): void {
        $prepared = $source127(['coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind', 'lower(option_name)']]);
        $current = $currentSource127(['coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind', 'lower(option_name)']]);
        $p = $plan127($prepared, $current, [['expression' => 'lower(option_name)']]);
        $t->same(true, $p['selectedPlan']['covering']);
    },
    'planner partial covering skipscan current source current-source expression index column has no reduced raw order' => static function (TestRunner $t) use ($source127, $currentSource127, $plan127): void {
        $prepared = $source127(['coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind', 'lower(option_name)']]);
        $current = $currentSource127(['coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind', 'lower(option_name)']]);
        $p = $plan127($prepared, $current, [['expression' => 'lower(option_name)']]);
        $t->same([], $p['selectedOrder']['orderBy']);
    },
    'planner partial covering skipscan current source current-source missing option value rejects covering' => static function (TestRunner $t) use ($source127, $currentSource127, $plan127): void {
        $current = $currentSource127(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(false, $plan127($source127(), $current)['selectedPlan']['covering']);
    },
    'planner partial covering skipscan current source current-source missing option value requires table seek' => static function (TestRunner $t) use ($source127, $currentSource127, $plan127): void {
        $current = $currentSource127(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan127($source127(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner partial covering skipscan current source current-source missing option value names rejection' => static function (TestRunner $t) use ($source127, $currentSource127, $plan127): void {
        $current = $currentSource127(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(['option_value'], $plan127($source127(), $current)['selectedPlan']['coveringRejectedColumns']);
    },
    'planner partial covering skipscan current source current-source changed query constant changes order signature' => static function (TestRunner $t) use ($point127, $range127, $plan127): void {
        $query = [$point127('kind', 'plugin'), $point127('autoload', 'yes'), $range127('option_name', '>=', 'plugin_')];
        $p = $plan127(null, null, [['expression' => 'autoload'], ['expression' => 'option_name']], null, $query);
        $t->same(['autoload'], $p['selectedOrder']['constantExpressions']);
    },
    'planner partial covering skipscan current source current-source autoload constant leaves suffix order' => static function (TestRunner $t) use ($point127, $range127, $plan127): void {
        $query = [$point127('kind', 'plugin'), $point127('autoload', 'yes'), $range127('option_name', '>=', 'plugin_')];
        $p = $plan127(null, null, [['expression' => 'autoload'], ['expression' => 'option_name']], null, $query);
        $t->same([['column' => 'option_name', 'direction' => 'ASC']], $p['selectedOrder']['orderBy']);
    },
    'planner partial covering skipscan current source current-source validates expression text' => static function (TestRunner $t) use ($plan127): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan127(null, null, [['expression' => '']]));
    },
    'planner partial covering skipscan current source current-source validates direction' => static function (TestRunner $t) use ($plan127): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan127(null, null, [['expression' => 'option_name', 'direction' => 'SIDEWAYS']]));
    },
    'planner partial covering skipscan current source current-source validates needed columns' => static function (TestRunner $t) use ($plan127): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan127(null, null, null, ['']));
    },
    'planner partial covering skipscan current source current-source validates source stat4 counters' => static function (TestRunner $t) use ($source127, $currentSource127, $plan127): void {
        $bad = $currentSource127(['stat4Samples' => [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan127($source127(), $bad));
    },
];

return $tests;
