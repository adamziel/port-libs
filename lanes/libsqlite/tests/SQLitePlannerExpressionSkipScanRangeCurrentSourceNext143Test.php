<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan;

$rows143 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_legacy', 'option_value' => 'old', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Cache', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_forms', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_security', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'a:7', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'PLUGIN_ZETA', 'option_value' => 'a:8', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'option_value' => 'theme', 'kind' => 'theme'],
];
$currentRows143 = static function () use ($rows143): array {
    $rows = $rows143();
    $rows[] = ['rowid' => 11, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:9', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'no', 'option_name' => 'plugin_zeta', 'option_value' => 'a:10', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 13, 'autoload' => 'lazy', 'option_name' => null, 'option_value' => 'null', 'kind' => 'plugin'];

    return $rows;
};
$stat4143 = static fn (): array => [
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
$currentStat4143 = static function () use ($stat4143): array {
    $samples = $stat4143();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};
$source143 = static function (array $overrides = []) use ($rows143, $stat4143): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1430',
        'schemaCookie' => 1430,
        'stat4Generation' => 61,
        'indexName' => 'idx_wp_options_autoload_lower_name_range_next143',
        'rootPage' => 14301,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next143',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_t',
        'upperInclusive' => false,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows143(),
        'stat4Samples' => $stat4143(),
    ];
};
$currentSource143 = static function (array $overrides = []) use ($currentRows143, $currentStat4143): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1431',
        'schemaCookie' => 1431,
        'stat4Generation' => 62,
        'indexName' => 'idx_wp_options_autoload_lower_name_range_next143',
        'rootPage' => 14309,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next143',
        'lowerInclusive' => 'plugin_d',
        'upperBound' => 'plugin_zeta',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows143(),
        'stat4Samples' => $currentStat4143(),
    ];
};
$partial143 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query143 = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$order143 = [
    ['expression' => 'autoload'],
    ['expression' => 'lower(option_name)'],
];
$needed143 = ['option_name', 'option_value', 'kind'];
$plan143 = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $needed = null): array => SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materializeNext143(
    $prepared ?? $source143(),
    $current ?? $currentSource143(),
    $partial143,
    $query143,
    $order ?? $order143,
    $needed ?? $needed143,
);

$tests = [
    'planner expression skipscan range current source next143 status ready' => static fn (TestRunner $t) => $t->same('expression-skipscan-range-current-source-next143-ready', $plan143()['status']),
    'planner expression skipscan range current source next143 selects current' => static fn (TestRunner $t) => $t->same('current', $plan143()['selectedSource']),
    'planner expression skipscan range current source next143 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan143()['stalePreparedStatement']),
    'planner expression skipscan range current source next143 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan143()['reprepareRequired']),
    'planner expression skipscan range current source next143 range changed' => static fn (TestRunner $t) => $t->same(true, $plan143()['rangeFenceChanged']),
    'planner expression skipscan range current source next143 lower changed' => static fn (TestRunner $t) => $t->same(true, $plan143()['lowerBoundChanged']),
    'planner expression skipscan range current source next143 upper changed' => static fn (TestRunner $t) => $t->same(true, $plan143()['upperBoundChanged']),
    'planner expression skipscan range current source next143 inclusive changed' => static fn (TestRunner $t) => $t->same(true, $plan143()['upperInclusiveChanged']),
    'planner expression skipscan range current source next143 collation stable' => static fn (TestRunner $t) => $t->same(false, $plan143()['collationChanged']),
    'planner expression skipscan range current source next143 signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan143()['preparedRangeSignature'] === $plan143()['currentRangeSignature']),
    'planner expression skipscan range current source next143 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan143()['currentRangeSignature'])),
    'planner expression skipscan range current source next143 selected expression flag' => static fn (TestRunner $t) => $t->same(true, $plan143()['selectedPlan']['expressionSkipScan']),
    'planner expression skipscan range current source next143 selected range expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan143()['selectedPlan']['rangeExpression']),
    'planner expression skipscan range current source next143 selected range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name_next143', $plan143()['selectedPlan']['rangeExpressionColumn']),
    'planner expression skipscan range current source next143 current rowids' => static fn (TestRunner $t) => $t->same([11, 3, 5, 6, 7, 12, 8, 9], $plan143()['currentSkipScanRowids']),
    'planner expression skipscan range current source next143 prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5, 6, 7, 8], $plan143()['preparedSkipScanRowids']),
    'planner expression skipscan range current source next143 rejects rows below new lower' => static fn (TestRunner $t) => $t->same([1, 2, 4], $plan143()['rangeRejectedRowids']),
    'planner expression skipscan range current source next143 admits widened rows' => static fn (TestRunner $t) => $t->same([11, 12, 9], $plan143()['rangeAdmittedRowids']),
    'planner expression skipscan range current source next143 stable rows' => static fn (TestRunner $t) => $t->same([3, 5, 6, 7, 8], $plan143()['rangeStableRowids']),
    'planner expression skipscan range current source next143 uses skipscan' => static fn (TestRunner $t) => $t->same(true, $plan143()['selectedPlan']['usesSkipScan']),
    'planner expression skipscan range current source next143 loop prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan143()['selectedPlan']['loops'], 'prefix')),
    'planner expression skipscan range current source next143 loop current matched' => static fn (TestRunner $t) => $t->same([2, 1, 3, 2], array_column($plan143()['selectedPlan']['loops'], 'matched')),
    'planner expression skipscan range current source next143 loop delta auto' => static fn (TestRunner $t) => $t->same(['prefix' => 'auto', 'preparedMatched' => 3, 'currentMatched' => 2, 'matchedDelta' => -1, 'rejectedRowids' => [1, 2], 'admittedRowids' => [11]], $plan143()['rangeLoopDelta'][0]),
    'planner expression skipscan range current source next143 loop delta no' => static fn (TestRunner $t) => $t->same([12], $plan143()['rangeLoopDelta'][2]['admittedRowids']),
    'planner expression skipscan range current source next143 loop delta yes admits zeta' => static fn (TestRunner $t) => $t->same([9], $plan143()['rangeLoopDelta'][3]['admittedRowids']),
    'planner expression skipscan range current source next143 fence source' => static fn (TestRunner $t) => $t->same('current-main.wp_options@cookie1431', $plan143()['rangeFence']['source']),
    'planner expression skipscan range current source next143 fence lower' => static fn (TestRunner $t) => $t->same('plugin_d', $plan143()['rangeFence']['lowerInclusive']),
    'planner expression skipscan range current source next143 fence upper' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan143()['rangeFence']['upperBound']),
    'planner expression skipscan range current source next143 fence inclusive' => static fn (TestRunner $t) => $t->same(true, $plan143()['rangeFence']['upperInclusive']),
    'planner expression skipscan range current source next143 fence upper opcode' => static fn (TestRunner $t) => $t->same('IdxGT', $plan143()['rangeFence']['upperOpcode']),
    'planner expression skipscan range current source next143 fence loop count' => static fn (TestRunner $t) => $t->same(4, $plan143()['rangeFence']['loopCount']),
    'planner expression skipscan range current source next143 fence row count' => static fn (TestRunner $t) => $t->same(8, $plan143()['rangeFence']['rowCount']),
    'planner expression skipscan range current source next143 fence signature current' => static fn (TestRunner $t) => $t->same($plan143()['currentRangeSignature'], $plan143()['rangeFence']['rangeSignature']),
    'planner expression skipscan range current source next143 tape source' => static fn (TestRunner $t) => $t->same('current', $plan143()['cursorTape']['source']),
    'planner expression skipscan range current source next143 tape reprepare' => static fn (TestRunner $t) => $t->same('ReprepareIfRangeFenceStale', $plan143()['cursorTape']['program'][0]['opcode']),
    'planner expression skipscan range current source next143 tape seekscan' => static fn (TestRunner $t) => $t->same('SeekScan', $plan143()['cursorTape']['program'][1]['opcode']),
    'planner expression skipscan range current source next143 tape lower seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plan143()['cursorTape']['program'][2]['opcode']),
    'planner expression skipscan range current source next143 tape lower value' => static fn (TestRunner $t) => $t->same('plugin_d', $plan143()['cursorTape']['program'][2]['value']),
    'planner expression skipscan range current source next143 tape upper value' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan143()['cursorTape']['program'][3]['value']),
    'planner expression skipscan range current source next143 tape column reads' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name_next143'], $plan143()['cursorTape']['program'][4]['columns']),
    'planner expression skipscan range current source next143 tape next' => static fn (TestRunner $t) => $t->same('Next', $plan143()['cursorTape']['program'][5]['opcode']),
    'planner expression skipscan range current source next143 tape rowids' => static fn (TestRunner $t) => $t->same([11, 3, 5, 6, 7, 12, 8, 9], $plan143()['cursorTape']['rowids']),
    'planner expression skipscan range current source next143 covered count' => static fn (TestRunner $t) => $t->same(8, $plan143()['selectedPlan']['coveredRowCount']),
    'planner expression skipscan range current source next143 covering true' => static fn (TestRunner $t) => $t->same(true, $plan143()['selectedPlan']['covering']),
    'planner expression skipscan range current source next143 table seek false' => static fn (TestRunner $t) => $t->same(false, $plan143()['selectedPlan']['tableSeekRequired']),
    'planner expression skipscan range current source next143 detail marks changed' => static fn (TestRunner $t) => $t->contains('current-range-fence=changed', $plan143()['detail']),
    'planner expression skipscan range current source next143 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-expression-skipscan-range-current-source-next143', implode(',', $plan143()['dependencies'])),
    'planner expression skipscan range current source next143 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan143()['dependency_closure']),
    'planner expression skipscan range current source next143 non overlap' => static fn (TestRunner $t) => $t->contains('lower/upper/collation range bounds', $plan143()['non_overlap']),
    'planner expression skipscan range current source next143 identical range requires next stage' => static function (TestRunner $t) use ($source143, $plan143): void {
        $source = $source143();
        $t->same('requires-next-stage', $plan143($source, $source)['status']);
    },
    'planner expression skipscan range current source next143 exclusive current upper opcode' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $current = $currentSource143(['upperInclusive' => false]);
        $t->same('IdxGE', $plan143($source143(), $current)['rangeFence']['upperOpcode']);
    },
    'planner expression skipscan range current source next143 exclusive current excludes zeta' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $current = $currentSource143(['upperInclusive' => false]);
        $t->same(false, in_array(12, $plan143($source143(), $current)['currentSkipScanRowids'], true));
    },
    'planner expression skipscan range current source next143 nocase admits uppercase zeta' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $current = $currentSource143(['lowerInclusive' => 'PLUGIN_D', 'upperBound' => 'PLUGIN_ZETA', 'collation' => 'NOCASE']);
        $t->same(true, in_array(9, $plan143($source143(), $current)['currentSkipScanRowids'], true));
    },
    'planner expression skipscan range current source next143 collation change detected' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $current = $currentSource143(['collation' => 'NOCASE']);
        $t->same(true, $plan143($source143(), $current)['collationChanged']);
    },
    'planner expression skipscan range current source next143 missing covering still ready' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $current = $currentSource143(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same('expression-skipscan-range-current-source-next143-ready', $plan143($source143(), $current)['status']);
    },
    'planner expression skipscan range current source next143 missing covering needs table' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $current = $currentSource143(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan143($source143(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner expression skipscan range current source next143 validates current name' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $bad = $currentSource143(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan143($source143(), $bad));
    },
    'planner expression skipscan range current source next143 validates expression column' => static function (TestRunner $t) use ($source143, $currentSource143, $plan143): void {
        $bad = $currentSource143(['rangeExpressionColumn' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan143($source143(), $bad));
    },
];

return $tests;
