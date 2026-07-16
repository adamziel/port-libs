<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan;

$rows149 = static fn (): array => [
    ['rowid' => 1, 'load_policy' => 'auto', 'key_name' => 'module_alpha', 'key_value' => 'a:1', 'kind' => 'module'],
    ['rowid' => 2, 'load_policy' => 'auto', 'key_name' => 'module_beta', 'key_value' => 'a:2', 'kind' => 'module'],
    ['rowid' => 3, 'load_policy' => 'auto', 'key_name' => 'module_legacy', 'key_value' => 'old', 'kind' => 'module'],
    ['rowid' => 4, 'load_policy' => 'lazy', 'key_name' => 'Module_Cache', 'key_value' => 'a:3', 'kind' => 'module'],
    ['rowid' => 5, 'load_policy' => 'lazy', 'key_name' => 'module_forms', 'key_value' => 'a:4', 'kind' => 'module'],
    ['rowid' => 6, 'load_policy' => 'no', 'key_name' => 'module_mail', 'key_value' => 'a:5', 'kind' => 'module'],
    ['rowid' => 7, 'load_policy' => 'no', 'key_name' => 'module_security', 'key_value' => 'a:6', 'kind' => 'module'],
    ['rowid' => 8, 'load_policy' => 'yes', 'key_name' => 'module_seo', 'key_value' => 'a:7', 'kind' => 'module'],
    ['rowid' => 9, 'load_policy' => 'yes', 'key_name' => 'MODULE_ZETA', 'key_value' => 'a:8', 'kind' => 'module'],
];
$currentRows149 = static function () use ($rows149): array {
    $rows = $rows149();
    $rows[] = ['rowid' => 10, 'load_policy' => 'auto', 'key_name' => 'module_delta', 'key_value' => 'a:9', 'kind' => 'module'];
    $rows[] = ['rowid' => 11, 'load_policy' => 'no', 'key_name' => 'module_zeta', 'key_value' => 'a:10', 'kind' => 'module'];
    $rows[] = ['rowid' => 12, 'load_policy' => 'lazy', 'key_name' => 'module_old_cache', 'key_value' => 'stale', 'kind' => 'module', '__expr_lower_key_name_current-source-expression-range-recheck' => 'module_old_cache'];

    return $rows;
};
$stat4149 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'module_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'module_beta', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'module_legacy', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'module_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'lazy', 'suffix' => 'module_forms', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'module_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'module_security', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'module_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'yes', 'suffix' => 'module_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
];
$currentStat4149 = static function () use ($stat4149): array {
    $samples = $stat4149();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'module_delta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'no', 'suffix' => 'module_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'lazy', 'suffix' => 'module_old_cache', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};
$source149 = static function (array $overrides = []) use ($rows149, $stat4149): array {
    return $overrides + [
        'name' => 'prepared-main.app_settings@cookie1490',
        'schemaCookie' => 1490,
        'stat4Generation' => 71,
        'indexName' => 'idx_app_settings_load_policy_lower_key_range_current-source-expression-range-recheck',
        'rootPage' => 14901,
        'skippedColumn' => 'load_policy',
        'rangeColumn' => 'key_name',
        'rangeExpression' => 'lower(key_name)',
        'rangeExpressionColumn' => '__expr_lower_key_name_current-source-expression-range-recheck',
        'lowerInclusive' => 'module_',
        'upperBound' => 'module_t',
        'upperInclusive' => false,
        'collation' => 'BINARY',
        'coveringColumns' => ['load_policy', 'key_name', 'key_value', 'kind'],
        'rows' => $rows149(),
        'stat4Samples' => $stat4149(),
    ];
};
$currentSource149 = static function (array $overrides = []) use ($currentRows149, $currentStat4149): array {
    return $overrides + [
        'name' => 'current-main.app_settings@cookie1491',
        'schemaCookie' => 1491,
        'stat4Generation' => 72,
        'indexName' => 'idx_app_settings_load_policy_lower_key_range_current-source-expression-range-recheck',
        'rootPage' => 14909,
        'skippedColumn' => 'load_policy',
        'rangeColumn' => 'key_name',
        'rangeExpression' => 'lower(key_name)',
        'rangeExpressionColumn' => '__expr_lower_key_name_current-source-expression-range-recheck',
        'lowerInclusive' => 'module_d',
        'upperBound' => 'module_zeta',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['load_policy', 'key_name', 'key_value', 'kind'],
        'rows' => $currentRows149(),
        'stat4Samples' => $currentStat4149(),
    ];
};
$partial149 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'module'),
    new SQLiteIndexPredicate('key_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query149 = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'module'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'key_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(key_name)'], 'right' => 'module_'],
];
$order149 = [['expression' => 'load_policy'], ['expression' => 'lower(key_name)']];
$needed149 = ['key_name', 'key_value', 'kind'];
$expressionRangePlan = static fn (?array $prepared = null, ?array $current = null, ?array $order = null): array => SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan::materializeExpressionRangeRecheck(
    $prepared ?? $source149(),
    $current ?? $currentSource149(),
    $partial149,
    $query149,
    $order ?? $order149,
    $needed149,
);

$tests = [
    'planner skipscan expression range current source current-source-expression-range-recheck status ready' => static fn (TestRunner $t) => $t->same('skipscan-expression-range-recheck-ready', $expressionRangePlan()['status']),
    'planner skipscan expression range current source current-source-expression-range-recheck selects current' => static fn (TestRunner $t) => $t->same('current', $expressionRangePlan()['selectedSource']),
    'planner skipscan expression range current source current-source-expression-range-recheck range fence changed' => static fn (TestRunner $t) => $t->same(true, $expressionRangePlan()['rangeFenceChanged']),
    'planner skipscan expression range current source current-source-expression-range-recheck base range rowids include zeta' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $expressionRangePlan()['currentSkipScanRowids']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit accepted rowids' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $expressionRangePlan()['expressionRangeAudit']['acceptedRowids']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit rejects none' => static fn (TestRunner $t) => $t->same([], $expressionRangePlan()['expressionRangeAudit']['rejectedRowids']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit expression' => static fn (TestRunner $t) => $t->same('lower(key_name)', $expressionRangePlan()['expressionRangeAudit']['expression']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit range column' => static fn (TestRunner $t) => $t->same('__expr_lower_key_name_current-source-expression-range-recheck', $expressionRangePlan()['expressionRangeAudit']['rangeColumn']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit source column' => static fn (TestRunner $t) => $t->same('key_name', $expressionRangePlan()['expressionRangeAudit']['sourceColumn']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit lower' => static fn (TestRunner $t) => $t->same('module_d', $expressionRangePlan()['expressionRangeAudit']['lowerInclusive']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit upper' => static fn (TestRunner $t) => $t->same('module_zeta', $expressionRangePlan()['expressionRangeAudit']['upperBound']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit upper inclusive' => static fn (TestRunner $t) => $t->same(true, $expressionRangePlan()['expressionRangeAudit']['upperInclusive']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit collation' => static fn (TestRunner $t) => $t->same('BINARY', $expressionRangePlan()['expressionRangeAudit']['collation']),
    'planner skipscan expression range current source current-source-expression-range-recheck audit tape count' => static fn (TestRunner $t) => $t->same(9, count($expressionRangePlan()['expressionRangeAudit']['auditTape'])),
    'planner skipscan expression range current source current-source-expression-range-recheck first audit rowid' => static fn (TestRunner $t) => $t->same(10, $expressionRangePlan()['expressionRangeAudit']['auditTape'][0]['rowid']),
    'planner skipscan expression range current source current-source-expression-range-recheck first audit source value' => static fn (TestRunner $t) => $t->same('module_delta', $expressionRangePlan()['expressionRangeAudit']['auditTape'][0]['sourceValue']),
    'planner skipscan expression range current source current-source-expression-range-recheck first audit expression value' => static fn (TestRunner $t) => $t->same('module_delta', $expressionRangePlan()['expressionRangeAudit']['auditTape'][0]['expressionValue']),
    'planner skipscan expression range current source current-source-expression-range-recheck first audit accepted' => static fn (TestRunner $t) => $t->same(true, $expressionRangePlan()['expressionRangeAudit']['auditTape'][0]['matched']),
    'planner skipscan expression range current source current-source-expression-range-recheck generated expression column wins' => static function (TestRunner $t) use ($expressionRangePlan): void {
        $byRowid = array_column($expressionRangePlan()['expressionRangeAudit']['auditTape'], null, 'rowid');
        $t->same('module_old_cache', $byRowid[12]['expressionValue']);
    },
    'planner skipscan expression range current source current-source-expression-range-recheck uppercase zeta computed lower' => static fn (TestRunner $t) => $t->same('module_zeta', $expressionRangePlan()['expressionRangeAudit']['auditTape'][8]['expressionValue']),
    'planner skipscan expression range current source current-source-expression-range-recheck selected recheck flag' => static fn (TestRunner $t) => $t->same(true, $expressionRangePlan()['selectedPlan']['currentSourceExpressionRangeRecheck']),
    'planner skipscan expression range current source current-source-expression-range-recheck selected opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $expressionRangePlan()['selectedPlan']['expressionRangeRecheckOpcode']),
    'planner skipscan expression range current source current-source-expression-range-recheck selected row count' => static fn (TestRunner $t) => $t->same(9, $expressionRangePlan()['selectedPlan']['expressionRangeRowCount']),
    'planner skipscan expression range current source current-source-expression-range-recheck selected rejected count' => static fn (TestRunner $t) => $t->same(0, $expressionRangePlan()['selectedPlan']['expressionRangeRejectedCount']),
    'planner skipscan expression range current source current-source-expression-range-recheck residual program count' => static fn (TestRunner $t) => $t->same(4, count($expressionRangePlan()['expressionRangeResidualProgram'])),
    'planner skipscan expression range current source current-source-expression-range-recheck residual reads expression column' => static fn (TestRunner $t) => $t->same('Column', $expressionRangePlan()['expressionRangeResidualProgram'][0]['opcode']),
    'planner skipscan expression range current source current-source-expression-range-recheck residual column name' => static fn (TestRunner $t) => $t->same('__expr_lower_key_name_current-source-expression-range-recheck', $expressionRangePlan()['expressionRangeResidualProgram'][0]['column']),
    'planner skipscan expression range current source current-source-expression-range-recheck residual recheck opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $expressionRangePlan()['expressionRangeResidualProgram'][1]['opcode']),
    'planner skipscan expression range current source current-source-expression-range-recheck residual recheck upper' => static fn (TestRunner $t) => $t->same('module_zeta', $expressionRangePlan()['expressionRangeResidualProgram'][1]['upper']),
    'planner skipscan expression range current source current-source-expression-range-recheck residual ifnot next' => static fn (TestRunner $t) => $t->same('Next', $expressionRangePlan()['expressionRangeResidualProgram'][2]['target']),
    'planner skipscan expression range current source current-source-expression-range-recheck residual result rows' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $expressionRangePlan()['expressionRangeResidualProgram'][3]['rowids']),
    'planner skipscan expression range current source current-source-expression-range-recheck cursor residual flag' => static fn (TestRunner $t) => $t->same(true, $expressionRangePlan()['cursorTape']['residualRecheck']),
    'planner skipscan expression range current source current-source-expression-range-recheck cursor rowids audited' => static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 11, 8, 9], $expressionRangePlan()['cursorTape']['rowids']),
    'planner skipscan expression range current source current-source-expression-range-recheck cursor rejected none' => static fn (TestRunner $t) => $t->same([], $expressionRangePlan()['cursorTape']['rejectedRowids']),
    'planner skipscan expression range current source current-source-expression-range-recheck cursor has residual opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $expressionRangePlan()['cursorTape']['program'][6]['opcode']),
    'planner skipscan expression range current source current-source-expression-range-recheck cursor final advances' => static fn (TestRunner $t) => $t->same('Next', $expressionRangePlan()['cursorTape']['program'][9]['opcode']),
    'planner skipscan expression range current source current-source-expression-range-recheck fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($expressionRangePlan()['currentSourceFence']['expressionRangeAuditSignature'])),
    'planner skipscan expression range current source current-source-expression-range-recheck fence opcode' => static fn (TestRunner $t) => $t->same('RecheckExpressionRange', $expressionRangePlan()['currentSourceFence']['expressionRangeRecheckOpcode']),
    'planner skipscan expression range current source current-source-expression-range-recheck fence accepted count' => static fn (TestRunner $t) => $t->same(9, $expressionRangePlan()['currentSourceFence']['acceptedExpressionRangeRows']),
    'planner skipscan expression range current source current-source-expression-range-recheck fence rejected count' => static fn (TestRunner $t) => $t->same(0, $expressionRangePlan()['currentSourceFence']['rejectedExpressionRangeRows']),
    'planner skipscan expression range current source current-source-expression-range-recheck detail clean' => static fn (TestRunner $t) => $t->contains('current-source-expression-range-recheck=clean', $expressionRangePlan()['detail']),
    'planner skipscan expression range current source current-source-expression-range-recheck selected detail' => static fn (TestRunner $t) => $t->contains('CURRENT-SOURCE EXPRESSION RANGE RECHECK current-source-expression-range-recheck', $expressionRangePlan()['selectedPlan']['detail']),
    'planner skipscan expression range current source current-source-expression-range-recheck dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-skipscan-expression-range-recheck', implode(',', $expressionRangePlan()['dependencies'])),
    'planner skipscan expression range current source current-source-expression-range-recheck dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $expressionRangePlan()['dependency_closure']),
    'planner skipscan expression range current source current-source-expression-range-recheck non overlap' => static fn (TestRunner $t) => $t->contains('selected skip-scan row expression values', $expressionRangePlan()['non_overlap']),
];

$exclusive149 = static fn (): array => $expressionRangePlan(null, $currentSource149(['upperBound' => 'module_zeta', 'upperInclusive' => false]));
$tests['planner skipscan expression range current source current-source-expression-range-recheck exclusive status ready'] = static fn (TestRunner $t) => $t->same('skipscan-expression-range-recheck-ready', $exclusive149()['status']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck exclusive removes zeta rows'] = static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 8], $exclusive149()['expressionRangeAudit']['acceptedRowids']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck exclusive residual upper flag'] = static fn (TestRunner $t) => $t->same(false, $exclusive149()['expressionRangeResidualProgram'][1]['upperInclusive']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck exclusive cursor rowids'] = static fn (TestRunner $t) => $t->same([10, 3, 5, 12, 6, 7, 8], $exclusive149()['cursorTape']['rowids']);

$staleComputed149 = static function () use ($currentSource149, $expressionRangePlan): array {
    $current = $currentSource149();
    $current['rows'][] = ['rowid' => 13, 'load_policy' => 'no', 'key_name' => 'module_delta', 'key_value' => 'stale-generated', 'kind' => 'module', '__expr_lower_key_name_current-source-expression-range-recheck' => 'module_archive_delta'];
    $current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'module_archive_delta', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3];

    return $expressionRangePlan(null, $current);
};
$tests['planner skipscan expression range current source current-source-expression-range-recheck stale generated requires recheck'] = static fn (TestRunner $t) => $t->same('requires-current-source-range-recheck', $staleComputed149()['status']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck stale generated rejects row'] = static fn (TestRunner $t) => $t->same([13], $staleComputed149()['expressionRangeAudit']['rejectedRowids']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck stale generated cursor excludes row'] = static fn (TestRunner $t) => $t->same(false, in_array(13, $staleComputed149()['cursorTape']['rowids'], true));
$tests['planner skipscan expression range current source current-source-expression-range-recheck stale generated audit reason'] = static function (TestRunner $t) use ($staleComputed149): void {
    $byRowid = array_column($staleComputed149()['expressionRangeAudit']['auditTape'], null, 'rowid');
    $t->same('range-filtered', $byRowid[13]['reason']);
};
$tests['planner skipscan expression range current source current-source-expression-range-recheck stale generated filtered target'] = static fn (TestRunner $t) => $t->same([13], $staleComputed149()['expressionRangeResidualProgram'][2]['filteredRowids']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck stale generated rejected count'] = static fn (TestRunner $t) => $t->same(1, $staleComputed149()['selectedPlan']['expressionRangeRejectedCount']);

$nocase149 = static fn (): array => $expressionRangePlan(null, $currentSource149(['lowerInclusive' => 'MODULE_D', 'upperBound' => 'MODULE_ZETA', 'collation' => 'NOCASE']));
$tests['planner skipscan expression range current source current-source-expression-range-recheck nocase ready'] = static fn (TestRunner $t) => $t->same('skipscan-expression-range-recheck-ready', $nocase149()['status']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck nocase audit collation'] = static fn (TestRunner $t) => $t->same('NOCASE', $nocase149()['expressionRangeAudit']['collation']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck nocase accepts uppercase zeta'] = static fn (TestRunner $t) => $t->same(true, in_array(9, $nocase149()['expressionRangeAudit']['acceptedRowids'], true));

$reverse149 = static fn (): array => $expressionRangePlan(null, null, [['expression' => 'load_policy', 'direction' => 'DESC'], ['expression' => 'lower(key_name)', 'direction' => 'DESC']]);
$tests['planner skipscan expression range current source current-source-expression-range-recheck reverse residual target prev'] = static fn (TestRunner $t) => $t->same('Prev', $reverse149()['expressionRangeResidualProgram'][2]['target']);
$tests['planner skipscan expression range current source current-source-expression-range-recheck reverse cursor final prev'] = static fn (TestRunner $t) => $t->same('Prev', $reverse149()['cursorTape']['program'][9]['opcode']);

$tests['planner skipscan expression range current source current-source-expression-range-recheck validates bad rowid'] = static function (TestRunner $t) use ($currentSource149, $expressionRangePlan): void {
    $bad = $currentSource149();
    $bad['rows'][] = ['rowid' => -1, 'load_policy' => 'no', 'key_name' => 'module_bad', 'kind' => 'module'];
    $t->throws(InvalidArgumentException::class, static fn () => $expressionRangePlan(null, $bad));
};
$tests['planner skipscan expression range current source current-source-expression-range-recheck validates bad upper inclusive'] = static function (TestRunner $t) use ($currentSource149, $expressionRangePlan): void {
    $bad = $currentSource149(['upperInclusive' => 'true']);
    $t->throws(InvalidArgumentException::class, static fn () => $expressionRangePlan(null, $bad));
};
$tests['planner skipscan expression range current source current-source-expression-range-recheck validates bad expression column'] = static function (TestRunner $t) use ($currentSource149, $expressionRangePlan): void {
    $bad = $currentSource149(['rangeExpressionColumn' => '']);
    $t->throws(InvalidArgumentException::class, static fn () => $expressionRangePlan(null, $bad));
};

return $tests;
