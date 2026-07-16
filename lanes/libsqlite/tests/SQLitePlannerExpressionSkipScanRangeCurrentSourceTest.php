<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan;

$rowscurrent = static fn (): array => [
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
$currentRowscurrent = static function () use ($rowscurrent): array {
    $rows = $rowscurrent();
    $rows[] = ['rowid' => 11, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:9', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'no', 'option_name' => 'plugin_zeta', 'option_value' => 'a:10', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 13, 'autoload' => 'lazy', 'option_name' => null, 'option_value' => 'null', 'kind' => 'plugin'];

    return $rows;
};
$stat4current = static fn (): array => [
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
$currentStat4current = static function () use ($stat4current): array {
    $samples = $stat4current();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};
$sourcecurrent = static function (array $overrides = []) use ($rowscurrent, $stat4current): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie-prepared',
        'schemaCookie' => 1430,
        'stat4Generation' => 61,
        'indexName' => 'idx_wp_options_autoload_lower_name_range_current',
        'rootPage' => 14301,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_current',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_t',
        'upperInclusive' => false,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rowscurrent(),
        'stat4Samples' => $stat4current(),
    ];
};
$currentSourcecurrent = static function (array $overrides = []) use ($currentRowscurrent, $currentStat4current): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie-current',
        'schemaCookie' => 1431,
        'stat4Generation' => 62,
        'indexName' => 'idx_wp_options_autoload_lower_name_range_current',
        'rootPage' => 14309,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_current',
        'lowerInclusive' => 'plugin_d',
        'upperBound' => 'plugin_zeta',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRowscurrent(),
        'stat4Samples' => $currentStat4current(),
    ];
};
$partialcurrent = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$querycurrent = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$ordercurrent = [
    ['expression' => 'autoload'],
    ['expression' => 'lower(option_name)'],
];
$neededcurrent = ['option_name', 'option_value', 'kind'];
$plancurrent = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $needed = null): array => SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materialize(
    $prepared ?? $sourcecurrent(),
    $current ?? $currentSourcecurrent(),
    $partialcurrent,
    $querycurrent,
    $order ?? $ordercurrent,
    $needed ?? $neededcurrent,
);

$tests = [
    'planner expression skipscan range current source status ready' => static fn (TestRunner $t) => $t->same('expression-skipscan-range-current-source-ready', $plancurrent()['status']),
    'planner expression skipscan range current source selects current' => static fn (TestRunner $t) => $t->same('current', $plancurrent()['selectedSource']),
    'planner expression skipscan range current source stale prepared' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['stalePreparedStatement']),
    'planner expression skipscan range current source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['reprepareRequired']),
    'planner expression skipscan range current source range changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['rangeFenceChanged']),
    'planner expression skipscan range current source lower changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['lowerBoundChanged']),
    'planner expression skipscan range current source upper changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['upperBoundChanged']),
    'planner expression skipscan range current source inclusive changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['upperInclusiveChanged']),
    'planner expression skipscan range current source collation stable' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['collationChanged']),
    'planner expression skipscan range current source signatures differ' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['preparedRangeSignature'] === $plancurrent()['currentRangeSignature']),
    'planner expression skipscan range current source signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plancurrent()['currentRangeSignature'])),
    'planner expression skipscan range current source selected expression flag' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['expressionSkipScan']),
    'planner expression skipscan range current source selected range expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plancurrent()['selectedPlan']['rangeExpression']),
    'planner expression skipscan range current source selected range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name_current', $plancurrent()['selectedPlan']['rangeExpressionColumn']),
    'planner expression skipscan range current source current rowids' => static fn (TestRunner $t) => $t->same([11, 3, 5, 6, 7, 12, 8, 9], $plancurrent()['currentSkipScanRowids']),
    'planner expression skipscan range current source prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5, 6, 7, 8], $plancurrent()['preparedSkipScanRowids']),
    'planner expression skipscan range current source rejects rows below new lower' => static fn (TestRunner $t) => $t->same([1, 2, 4], $plancurrent()['rangeRejectedRowids']),
    'planner expression skipscan range current source admits widened rows' => static fn (TestRunner $t) => $t->same([11, 12, 9], $plancurrent()['rangeAdmittedRowids']),
    'planner expression skipscan range current source stable rows' => static fn (TestRunner $t) => $t->same([3, 5, 6, 7, 8], $plancurrent()['rangeStableRowids']),
    'planner expression skipscan range current source uses skipscan' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['usesSkipScan']),
    'planner expression skipscan range current source loop prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plancurrent()['selectedPlan']['loops'], 'prefix')),
    'planner expression skipscan range current source loop current matched' => static fn (TestRunner $t) => $t->same([2, 1, 3, 2], array_column($plancurrent()['selectedPlan']['loops'], 'matched')),
    'planner expression skipscan range current source loop delta auto' => static fn (TestRunner $t) => $t->same(['prefix' => 'auto', 'preparedMatched' => 3, 'currentMatched' => 2, 'matchedDelta' => -1, 'rejectedRowids' => [1, 2], 'admittedRowids' => [11]], $plancurrent()['rangeLoopDelta'][0]),
    'planner expression skipscan range current source loop delta no' => static fn (TestRunner $t) => $t->same([12], $plancurrent()['rangeLoopDelta'][2]['admittedRowids']),
    'planner expression skipscan range current source loop delta yes admits zeta' => static fn (TestRunner $t) => $t->same([9], $plancurrent()['rangeLoopDelta'][3]['admittedRowids']),
    'planner expression skipscan range current source fence source' => static fn (TestRunner $t) => $t->same('current-main.wp_options@cookie-current', $plancurrent()['rangeFence']['source']),
    'planner expression skipscan range current source fence lower' => static fn (TestRunner $t) => $t->same('plugin_d', $plancurrent()['rangeFence']['lowerInclusive']),
    'planner expression skipscan range current source fence upper' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plancurrent()['rangeFence']['upperBound']),
    'planner expression skipscan range current source fence inclusive' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['rangeFence']['upperInclusive']),
    'planner expression skipscan range current source fence upper opcode' => static fn (TestRunner $t) => $t->same('IdxGT', $plancurrent()['rangeFence']['upperOpcode']),
    'planner expression skipscan range current source fence loop count' => static fn (TestRunner $t) => $t->same(4, $plancurrent()['rangeFence']['loopCount']),
    'planner expression skipscan range current source fence row count' => static fn (TestRunner $t) => $t->same(8, $plancurrent()['rangeFence']['rowCount']),
    'planner expression skipscan range current source fence signature current' => static fn (TestRunner $t) => $t->same($plancurrent()['currentRangeSignature'], $plancurrent()['rangeFence']['rangeSignature']),
    'planner expression skipscan range current source tape source' => static fn (TestRunner $t) => $t->same('current', $plancurrent()['cursorTape']['source']),
    'planner expression skipscan range current source tape reprepare' => static fn (TestRunner $t) => $t->same('ReprepareIfRangeFenceStale', $plancurrent()['cursorTape']['program'][0]['opcode']),
    'planner expression skipscan range current source tape seekscan' => static fn (TestRunner $t) => $t->same('SeekScan', $plancurrent()['cursorTape']['program'][1]['opcode']),
    'planner expression skipscan range current source tape lower seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plancurrent()['cursorTape']['program'][2]['opcode']),
    'planner expression skipscan range current source tape lower value' => static fn (TestRunner $t) => $t->same('plugin_d', $plancurrent()['cursorTape']['program'][2]['value']),
    'planner expression skipscan range current source tape upper value' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plancurrent()['cursorTape']['program'][3]['value']),
    'planner expression skipscan range current source tape column reads' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name_current'], $plancurrent()['cursorTape']['program'][4]['columns']),
    'planner expression skipscan range current source tape next' => static fn (TestRunner $t) => $t->same('Next', $plancurrent()['cursorTape']['program'][5]['opcode']),
    'planner expression skipscan range current source tape rowids' => static fn (TestRunner $t) => $t->same([11, 3, 5, 6, 7, 12, 8, 9], $plancurrent()['cursorTape']['rowids']),
    'planner expression skipscan range current source covered count' => static fn (TestRunner $t) => $t->same(8, $plancurrent()['selectedPlan']['coveredRowCount']),
    'planner expression skipscan range current source covering true' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['covering']),
    'planner expression skipscan range current source table seek false' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['selectedPlan']['tableSeekRequired']),
    'planner expression skipscan range current source detail marks changed' => static fn (TestRunner $t) => $t->contains('current-range-fence=changed', $plancurrent()['detail']),
    'planner expression skipscan range current source dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-expression-skipscan-range-current-source', implode(',', $plancurrent()['dependencies'])),
    'planner expression skipscan range current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plancurrent()['dependency_closure']),
    'planner expression skipscan range current source non overlap' => static fn (TestRunner $t) => $t->contains('lower/upper/collation range bounds', $plancurrent()['non_overlap']),
    'planner expression skipscan range current source identical range requires next stage' => static function (TestRunner $t) use ($sourcecurrent, $plancurrent): void {
        $source = $sourcecurrent();
        $t->same('requires-next-stage', $plancurrent($source, $source)['status']);
    },
    'planner expression skipscan range current source exclusive current upper opcode' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $current = $currentSourcecurrent(['upperInclusive' => false]);
        $t->same('IdxGE', $plancurrent($sourcecurrent(), $current)['rangeFence']['upperOpcode']);
    },
    'planner expression skipscan range current source exclusive current excludes zeta' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $current = $currentSourcecurrent(['upperInclusive' => false]);
        $t->same(false, in_array(12, $plancurrent($sourcecurrent(), $current)['currentSkipScanRowids'], true));
    },
    'planner expression skipscan range current source nocase admits uppercase zeta' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $current = $currentSourcecurrent(['lowerInclusive' => 'PLUGIN_D', 'upperBound' => 'PLUGIN_ZETA', 'collation' => 'NOCASE']);
        $t->same(true, in_array(9, $plancurrent($sourcecurrent(), $current)['currentSkipScanRowids'], true));
    },
    'planner expression skipscan range current source collation change detected' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $current = $currentSourcecurrent(['collation' => 'NOCASE']);
        $t->same(true, $plancurrent($sourcecurrent(), $current)['collationChanged']);
    },
    'planner expression skipscan range current source missing covering still ready' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $current = $currentSourcecurrent(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same('expression-skipscan-range-current-source-ready', $plancurrent($sourcecurrent(), $current)['status']);
    },
    'planner expression skipscan range current source missing covering needs table' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $current = $currentSourcecurrent(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plancurrent($sourcecurrent(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner expression skipscan range current source validates current name' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $bad = $currentSourcecurrent(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plancurrent($sourcecurrent(), $bad));
    },
    'planner expression skipscan range current source validates expression column' => static function (TestRunner $t) use ($sourcecurrent, $currentSourcecurrent, $plancurrent): void {
        $bad = $currentSourcecurrent(['rangeExpressionColumn' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plancurrent($sourcecurrent(), $bad));
    },
];

return $tests;
