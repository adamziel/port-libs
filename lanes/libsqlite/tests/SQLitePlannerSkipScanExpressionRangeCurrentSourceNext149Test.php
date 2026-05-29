<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan;

$rows149 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_legacy', 'option_value' => 'old', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Cache', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_forms', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_security', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'a:7', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'PLUGIN_ZETA', 'option_value' => 'a:8', 'kind' => 'plugin'],
];
$currentRows149 = static function () use ($rows149): array {
    $rows = $rows149();
    $rows[] = ['rowid' => 10, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:9', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'plugin_zeta', 'option_value' => 'a:10', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'lazy', 'option_name' => 'plugin_old_cache', 'option_value' => 'stale', 'kind' => 'plugin', '__expr_lower_option_name_next149' => 'plugin_old_cache'];

    return $rows;
};
$stat4149 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_legacy', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'lazy', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
];
$currentStat4149 = static function () use ($stat4149): array {
    $samples = $stat4149();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'lazy', 'suffix' => 'plugin_old_cache', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};
$source149 = static function (array $overrides = []) use ($rows149, $stat4149): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1490',
        'schemaCookie' => 1490,
        'stat4Generation' => 71,
        'indexName' => 'idx_wp_options_autoload_lower_name_range_next149',
        'rootPage' => 14901,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next149',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_t',
        'upperInclusive' => false,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows149(),
        'stat4Samples' => $stat4149(),
    ];
};
$currentSource149 = static function (array $overrides = []) use ($currentRows149, $currentStat4149): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1491',
        'schemaCookie' => 1491,
        'stat4Generation' => 72,
        'indexName' => 'idx_wp_options_autoload_lower_name_range_next149',
        'rootPage' => 14909,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next149',
        'lowerInclusive' => 'plugin_d',
        'upperBound' => 'plugin_zeta',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows149(),
        'stat4Samples' => $currentStat4149(),
    ];
};
$partial149 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query149 = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$order149 = [['expression' => 'autoload'], ['expression' => 'lower(option_name)']];
$needed149 = ['option_name', 'option_value', 'kind'];
$plan149 = static fn (?array $prepared = null, ?array $current = null, ?array $order = null): array => SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan::materializeNext149(
    $prepared ?? $source149(),
    $current ?? $currentSource149(),
    $partial149,
    $query149,
    $order ?? $order149,
    $needed149,
);

$tests = [
    'planner skipscan expression range current source next149 status ready' => static fn (TestRunner $t) => $t->same('skipscan-expression-range-current-source-next149-ready', $plan149()['status']),
    'planner skipscan expression range current source next149 selects current' => static fn (TestRunner $t) => $t->same('current', $plan149()['selectedSource']),
    'planner skipscan expression range current source next149 range fence changed' => static fn (TestRunner $t) => $t->same(true, $plan149()['rangeFenceChanged']),
    'planner skipscan expression range current source next149 base range rowids include zeta' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $plan149()['currentSkipScanRowids']),
    'planner skipscan expression range current source next149 audit accepted rowids' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $plan149()['expressionRangeAudit']['acceptedRowids']),
    'planner skipscan expression range current source next149 audit rejects none' => static fn (TestRunner $t) => $t->same([], $plan149()['expressionRangeAudit']['rejectedRowids']),
    'planner skipscan expression range current source next149 audit expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan149()['expressionRangeAudit']['expression']),
    'planner skipscan expression range current source next149 audit range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name_next149', $plan149()['expressionRangeAudit']['rangeColumn']),
    'planner skipscan expression range current source next149 audit source column' => static fn (TestRunner $t) => $t->same('option_name', $plan149()['expressionRangeAudit']['sourceColumn']),
    'planner skipscan expression range current source next149 audit lower' => static fn (TestRunner $t) => $t->same('plugin_d', $plan149()['expressionRangeAudit']['lowerInclusive']),
    'planner skipscan expression range current source next149 audit upper' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan149()['expressionRangeAudit']['upperBound']),
    'planner skipscan expression range current source next149 audit upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan149()['expressionRangeAudit']['upperInclusive']),
    'planner skipscan expression range current source next149 audit collation' => static fn (TestRunner $t) => $t->same('BINARY', $plan149()['expressionRangeAudit']['collation']),
    'planner skipscan expression range current source next149 audit tape count' => static fn (TestRunner $t) => $t->same(9, count($plan149()['expressionRangeAudit']['auditTape'])),
    'planner skipscan expression range current source next149 first audit rowid' => static fn (TestRunner $t) => $t->same(10, $plan149()['expressionRangeAudit']['auditTape'][0]['rowid']),
    'planner skipscan expression range current source next149 first audit expression value' => static fn (TestRunner $t) => $t->same('plugin_delta', $plan149()['expressionRangeAudit']['auditTape'][0]['expressionValue']),
    'planner skipscan expression range current source next149 first audit accepted' => static fn (TestRunner $t) => $t->same(true, $plan149()['expressionRangeAudit']['auditTape'][0]['matched']),
    'planner skipscan expression range current source next149 generated expression column wins' => static function (TestRunner $t) use ($plan149): void {
        $byRowid = array_column($plan149()['expressionRangeAudit']['auditTape'], null, 'rowid');
        $t->same('plugin_old_cache', $byRowid[12]['expressionValue']);
    },
    'planner skipscan expression range current source next149 uppercase zeta computed lower' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan149()['expressionRangeAudit']['auditTape'][8]['expressionValue']),
    'planner skipscan expression range current source next149 selected recheck flag' => static fn (TestRunner $t) => $t->same(true, $plan149()['selectedPlan']['currentSourceExpressionRangeRecheck']),
    'planner skipscan expression range current source next149 selected opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $plan149()['selectedPlan']['expressionRangeRecheckOpcode']),
    'planner skipscan expression range current source next149 selected row count' => static fn (TestRunner $t) => $t->same(9, $plan149()['selectedPlan']['expressionRangeRowCount']),
    'planner skipscan expression range current source next149 selected rejected count' => static fn (TestRunner $t) => $t->same(0, $plan149()['selectedPlan']['expressionRangeRejectedCount']),
    'planner skipscan expression range current source next149 residual program count' => static fn (TestRunner $t) => $t->same(4, count($plan149()['expressionRangeResidualProgram'])),
    'planner skipscan expression range current source next149 residual reads expression column' => static fn (TestRunner $t) => $t->same('Column', $plan149()['expressionRangeResidualProgram'][0]['opcode']),
    'planner skipscan expression range current source next149 residual column name' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name_next149', $plan149()['expressionRangeResidualProgram'][0]['column']),
    'planner skipscan expression range current source next149 residual recheck opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $plan149()['expressionRangeResidualProgram'][1]['opcode']),
    'planner skipscan expression range current source next149 residual recheck upper' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan149()['expressionRangeResidualProgram'][1]['upper']),
    'planner skipscan expression range current source next149 residual ifnot next' => static fn (TestRunner $t) => $t->same('Next', $plan149()['expressionRangeResidualProgram'][2]['target']),
    'planner skipscan expression range current source next149 residual result rows' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $plan149()['expressionRangeResidualProgram'][3]['rowids']),
    'planner skipscan expression range current source next149 cursor residual flag' => static fn (TestRunner $t) => $t->same(true, $plan149()['cursorTape']['residualRecheck']),
    'planner skipscan expression range current source next149 cursor rowids audited' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $plan149()['cursorTape']['rowids']),
    'planner skipscan expression range current source next149 cursor rejected none' => static fn (TestRunner $t) => $t->same([], $plan149()['cursorTape']['rejectedRowids']),
    'planner skipscan expression range current source next149 cursor has residual opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $plan149()['cursorTape']['program'][6]['opcode']),
    'planner skipscan expression range current source next149 cursor final advances' => static fn (TestRunner $t) => $t->same('Next', $plan149()['cursorTape']['program'][9]['opcode']),
    'planner skipscan expression range current source next149 fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan149()['currentSourceFence']['expressionRangeAuditSignature'])),
    'planner skipscan expression range current source next149 fence opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $plan149()['currentSourceFence']['expressionRangeRecheckOpcode']),
    'planner skipscan expression range current source next149 fence accepted count' => static fn (TestRunner $t) => $t->same(9, $plan149()['currentSourceFence']['acceptedExpressionRangeRows']),
    'planner skipscan expression range current source next149 fence rejected count' => static fn (TestRunner $t) => $t->same(0, $plan149()['currentSourceFence']['rejectedExpressionRangeRows']),
    'planner skipscan expression range current source next149 detail clean' => static fn (TestRunner $t) => $t->contains('current-source-expression-range-recheck=clean', $plan149()['detail']),
    'planner skipscan expression range current source next149 selected detail' => static fn (TestRunner $t) => $t->contains('CURRENT-SOURCE EXPRESSION RANGE RECHECK next149', $plan149()['selectedPlan']['detail']),
    'planner skipscan expression range current source next149 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-skipscan-expression-range-current-source-next149', implode(',', $plan149()['dependencies'])),
    'planner skipscan expression range current source next149 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan149()['dependency_closure']),
    'planner skipscan expression range current source next149 non overlap' => static fn (TestRunner $t) => $t->contains('selected skip-scan row expression values', $plan149()['non_overlap']),
];

$exclusive149 = static fn (): array => $plan149(null, $currentSource149(['upperBound' => 'plugin_zeta', 'upperInclusive' => false]));
$tests['planner skipscan expression range current source next149 exclusive status ready'] = static fn (TestRunner $t) => $t->same('skipscan-expression-range-current-source-next149-ready', $exclusive149()['status']);
$tests['planner skipscan expression range current source next149 exclusive removes zeta rows'] = static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 8], $exclusive149()['expressionRangeAudit']['acceptedRowids']);
$tests['planner skipscan expression range current source next149 exclusive residual upper flag'] = static fn (TestRunner $t) => $t->same(false, $exclusive149()['expressionRangeResidualProgram'][1]['upperInclusive']);
$tests['planner skipscan expression range current source next149 exclusive cursor rowids'] = static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 8], $exclusive149()['cursorTape']['rowids']);

$staleComputed149 = static function () use ($currentSource149, $plan149): array {
    $current = $currentSource149();
    $current['rows'][] = ['rowid' => 13, 'autoload' => 'no', 'option_name' => 'plugin_delta', 'option_value' => 'stale-generated', 'kind' => 'plugin', '__expr_lower_option_name_next149' => 'theme_mods_delta'];
    $current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'theme_mods_delta', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3];

    return $plan149(null, $current);
};
$tests['planner skipscan expression range current source next149 stale generated requires recheck'] = static fn (TestRunner $t) => $t->same('requires-current-source-range-recheck', $staleComputed149()['status']);
$tests['planner skipscan expression range current source next149 stale generated rejects row'] = static fn (TestRunner $t) => $t->same([13], $staleComputed149()['expressionRangeAudit']['rejectedRowids']);
$tests['planner skipscan expression range current source next149 stale generated cursor excludes row'] = static fn (TestRunner $t) => $t->same(false, in_array(13, $staleComputed149()['cursorTape']['rowids'], true));
$tests['planner skipscan expression range current source next149 stale generated audit reason'] = static function (TestRunner $t) use ($staleComputed149): void {
    $byRowid = array_column($staleComputed149()['expressionRangeAudit']['auditTape'], null, 'rowid');
    $t->same('range-filtered', $byRowid[13]['reason']);
};
$tests['planner skipscan expression range current source next149 stale generated filtered target'] = static fn (TestRunner $t) => $t->same([13], $staleComputed149()['expressionRangeResidualProgram'][2]['filteredRowids']);
$tests['planner skipscan expression range current source next149 stale generated rejected count'] = static fn (TestRunner $t) => $t->same(1, $staleComputed149()['selectedPlan']['expressionRangeRejectedCount']);

$nocase149 = static fn (): array => $plan149(null, $currentSource149(['lowerInclusive' => 'PLUGIN_D', 'upperBound' => 'PLUGIN_ZETA', 'collation' => 'NOCASE']));
$tests['planner skipscan expression range current source next149 nocase ready'] = static fn (TestRunner $t) => $t->same('skipscan-expression-range-current-source-next149-ready', $nocase149()['status']);
$tests['planner skipscan expression range current source next149 nocase audit collation'] = static fn (TestRunner $t) => $t->same('NOCASE', $nocase149()['expressionRangeAudit']['collation']);
$tests['planner skipscan expression range current source next149 nocase accepts uppercase zeta'] = static fn (TestRunner $t) => $t->same(true, in_array(9, $nocase149()['expressionRangeAudit']['acceptedRowids'], true));

$reverse149 = static fn (): array => $plan149(null, null, [['expression' => 'autoload', 'direction' => 'DESC'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
$tests['planner skipscan expression range current source next149 reverse residual target prev'] = static fn (TestRunner $t) => $t->same('Prev', $reverse149()['expressionRangeResidualProgram'][2]['target']);
$tests['planner skipscan expression range current source next149 reverse cursor final prev'] = static fn (TestRunner $t) => $t->same('Prev', $reverse149()['cursorTape']['program'][9]['opcode']);

$tests['planner skipscan expression range current source next149 validates bad rowid'] = static function (TestRunner $t) use ($currentSource149, $plan149): void {
    $bad = $currentSource149();
    $bad['rows'][] = ['rowid' => -1, 'autoload' => 'no', 'option_name' => 'plugin_bad', 'kind' => 'plugin'];
    $t->throws(InvalidArgumentException::class, static fn () => $plan149(null, $bad));
};
$tests['planner skipscan expression range current source next149 validates bad upper inclusive'] = static function (TestRunner $t) use ($currentSource149, $plan149): void {
    $bad = $currentSource149(['upperInclusive' => 'true']);
    $t->throws(InvalidArgumentException::class, static fn () => $plan149(null, $bad));
};
$tests['planner skipscan expression range current source next149 validates bad expression column'] = static function (TestRunner $t) use ($currentSource149, $plan149): void {
    $bad = $currentSource149(['rangeExpressionColumn' => '']);
    $t->throws(InvalidArgumentException::class, static fn () => $plan149(null, $bad));
};

return $tests;
