<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan;

$rows137 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'auto', 'option_name' => 'Plugin_Alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'auto', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'kind' => 'core'],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'lazy', 'option_name' => 'Plugin_Cache', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'lazy', 'option_name' => 'plugin_forms', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'theme_mods', 'option_value' => 'theme', 'kind' => 'theme'],
    ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'a:6', 'kind' => 'plugin'],
];
$currentRows137 = static function () use ($rows137): array {
    $rows = $rows137();
    $rows[] = ['rowid' => 9, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'PLUGIN_SECURITY', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};
$stat4137 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'lazy', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'yes', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
];
$currentStat4137 = static function () use ($stat4137): array {
    $samples = $stat4137();
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];
    $samples[] = ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

    return $samples;
};
$source137 = static function (array $overrides = []) use ($rows137, $stat4137): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1370',
        'schemaCookie' => 1370,
        'stat4Generation' => 31,
        'indexName' => 'idx_wp_options_autoload_lower_name_next137',
        'rootPage' => 13701,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next137',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_z',
        'upperInclusive' => false,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind', 'blog_id'],
        'rows' => $rows137(),
        'stat4Samples' => $stat4137(),
    ];
};
$currentSource137 = static function (array $overrides = []) use ($currentRows137, $currentStat4137): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1371',
        'schemaCookie' => 1371,
        'stat4Generation' => 32,
        'indexName' => 'idx_wp_options_autoload_lower_name_next137',
        'rootPage' => 13719,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next137',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_z',
        'upperInclusive' => false,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind', 'blog_id'],
        'rows' => $currentRows137(),
        'stat4Samples' => $currentStat4137(),
    ];
};
$partial137 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query137 = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$order137 = [
    ['expression' => 'autoload'],
    ['expression' => 'lower(option_name)'],
];
$needed137 = ['option_name', 'option_value', 'kind'];
$plan137 = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan::materializeNext137(
    $prepared ?? $source137(),
    $current ?? $currentSource137(),
    $partial137,
    $query137,
    $order ?? $order137,
    $needed ?? $needed137,
);

$tests = [
    'planner stat4 expression skipscan current source next137 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-skipscan-current-source-next137-ready', $plan137()['status']),
    'planner stat4 expression skipscan current source next137 selects current' => static fn (TestRunner $t) => $t->same('current', $plan137()['selectedSource']),
    'planner stat4 expression skipscan current source next137 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan137()['stalePreparedStatement']),
    'planner stat4 expression skipscan current source next137 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan137()['reprepareRequired']),
    'planner stat4 expression skipscan current source next137 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan137()['schemaCookieChanged']),
    'planner stat4 expression skipscan current source next137 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan137()['stat4GenerationChanged']),
    'planner stat4 expression skipscan current source next137 stat4 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan137()['stat4SignatureChanged']),
    'planner stat4 expression skipscan current source next137 expression stable' => static fn (TestRunner $t) => $t->same(false, $plan137()['rangeExpressionChanged']),
    'planner stat4 expression skipscan current source next137 expression column stable' => static fn (TestRunner $t) => $t->same(false, $plan137()['expressionColumnChanged']),
    'planner stat4 expression skipscan current source next137 selected expression flag' => static fn (TestRunner $t) => $t->same(true, $plan137()['selectedPlan']['expressionSkipScan']),
    'planner stat4 expression skipscan current source next137 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_autoload_lower_name_next137', $plan137()['selectedPlan']['indexName']),
    'planner stat4 expression skipscan current source next137 selected range expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan137()['selectedPlan']['rangeExpression']),
    'planner stat4 expression skipscan current source next137 selected range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name_next137', $plan137()['selectedPlan']['rangeExpressionColumn']),
    'planner stat4 expression skipscan current source next137 uses skip scan' => static fn (TestRunner $t) => $t->same(true, $plan137()['selectedPlan']['usesSkipScan']),
    'planner stat4 expression skipscan current source next137 loop count' => static fn (TestRunner $t) => $t->same(4, $plan137()['currentSourceFence']['skipScanLoopCount']),
    'planner stat4 expression skipscan current source next137 samples used' => static fn (TestRunner $t) => $t->same(8, $plan137()['selectedPlan']['stat4SamplesUsed']),
    'planner stat4 expression skipscan current source next137 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan137()['selectedPlan']['estimatedRows']),
    'planner stat4 expression skipscan current source next137 estimated cost' => static fn (TestRunner $t) => $t->same(37, $plan137()['selectedPlan']['estimatedCost']),
    'planner stat4 expression skipscan current source next137 row estimate delta' => static fn (TestRunner $t) => $t->same(0, $plan137()['skipScanRowEstimateDelta']),
    'planner stat4 expression skipscan current source next137 cost delta' => static fn (TestRunner $t) => $t->same(0, $plan137()['skipScanCostDelta']),
    'planner stat4 expression skipscan current source next137 prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 6, 8], $plan137()['preparedSkipScanRowids']),
    'planner stat4 expression skipscan current source next137 current rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 6, 9, 8], $plan137()['currentSkipScanRowids']),
    'planner stat4 expression skipscan current source next137 admits security row' => static fn (TestRunner $t) => $t->same([9], $plan137()['currentSkipScanAdmittedRowids']),
    'planner stat4 expression skipscan current source next137 rejects none under same bound' => static fn (TestRunner $t) => $t->same([], $plan137()['staleSkipScanRejectedRowids']),
    'planner stat4 expression skipscan current source next137 stable rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 6, 8], $plan137()['stableSkipScanRowids']),
    'planner stat4 expression skipscan current source next137 prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan137()['preparedStat4Signature'])),
    'planner stat4 expression skipscan current source next137 signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan137()['preparedStat4Signature'] === $plan137()['currentStat4Signature']),
    'planner stat4 expression skipscan current source next137 fence opcode seekscan' => static fn (TestRunner $t) => $t->same('SeekScan', $plan137()['currentSourceFence']['skipScanOpcode']),
    'planner stat4 expression skipscan current source next137 fence range opcode exclusive' => static fn (TestRunner $t) => $t->same('IdxGE', $plan137()['currentSourceFence']['rangeRecheckOpcode']),
    'planner stat4 expression skipscan current source next137 fence stat4 signature' => static fn (TestRunner $t) => $t->same($plan137()['currentStat4Signature'], $plan137()['currentSourceFence']['stat4Signature']),
    'planner stat4 expression skipscan current source next137 tape source current' => static fn (TestRunner $t) => $t->same('current', $plan137()['cursorTape']['source']),
    'planner stat4 expression skipscan current source next137 tape starts reprepare' => static fn (TestRunner $t) => $t->same('ReprepareIfStale', $plan137()['cursorTape']['program'][0]['opcode']),
    'planner stat4 expression skipscan current source next137 tape seekscan second' => static fn (TestRunner $t) => $t->same('SeekScan', $plan137()['cursorTape']['program'][1]['opcode']),
    'planner stat4 expression skipscan current source next137 tape range opcode third' => static fn (TestRunner $t) => $t->same('IdxGE', $plan137()['cursorTape']['program'][2]['opcode']),
    'planner stat4 expression skipscan current source next137 tape column reads needed' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name_next137'], $plan137()['cursorTape']['program'][3]['columns']),
    'planner stat4 expression skipscan current source next137 tape next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan137()['cursorTape']['program'][4]['opcode']),
    'planner stat4 expression skipscan current source next137 tape current cost' => static fn (TestRunner $t) => $t->same(37, $plan137()['cursorTape']['estimatedCost']),
    'planner stat4 expression skipscan current source next137 tape current rows' => static fn (TestRunner $t) => $t->same(5, $plan137()['cursorTape']['estimatedRows']),
    'planner stat4 expression skipscan current source next137 prepared loop count' => static fn (TestRunner $t) => $t->same(4, count($plan137()['cursorTape']['preparedLoops'])),
    'planner stat4 expression skipscan current source next137 current loop count' => static fn (TestRunner $t) => $t->same(4, count($plan137()['cursorTape']['currentLoops'])),
    'planner stat4 expression skipscan current source next137 no loop gained row' => static fn (TestRunner $t) => $t->same([6, 9], $plan137()['cursorTape']['currentLoops'][2]['rowids']),
    'planner stat4 expression skipscan current source next137 auto prefix current' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan137()['stat4PrefixDelta'][0]['currentCurrent']['suffix']),
    'planner stat4 expression skipscan current source next137 auto prefix sample stable' => static fn (TestRunner $t) => $t->same(0, $plan137()['stat4PrefixDelta'][0]['rangeSamplesDelta']),
    'planner stat4 expression skipscan current source next137 no prefix sample grows' => static fn (TestRunner $t) => $t->same(1, $plan137()['stat4PrefixDelta'][2]['rangeSamplesDelta']),
    'planner stat4 expression skipscan current source next137 no prefix current next changes' => static fn (TestRunner $t) => $t->same('plugin_security', $plan137()['stat4PrefixDelta'][2]['currentNext']['suffix']),
    'planner stat4 expression skipscan current source next137 yes prefix zeta outside range' => static fn (TestRunner $t) => $t->same(0, $plan137()['stat4PrefixDelta'][3]['rangeSamplesDelta']),
    'planner stat4 expression skipscan current source next137 upper exclusive excludes zeta' => static fn (TestRunner $t) => $t->same(false, in_array(10, $plan137()['currentSkipScanRowids'], true)),
    'planner stat4 expression skipscan current source next137 upper inclusive admits zeta' => static function (TestRunner $t) use ($source137, $currentSource137, $plan137): void {
        $current = $currentSource137(['upperBound' => 'plugin_zeta', 'upperInclusive' => true]);
        $t->same(true, in_array(10, $plan137($source137(), $current)['currentSkipScanRowids'], true));
    },
    'planner stat4 expression skipscan current source next137 upper inclusive opcode' => static function (TestRunner $t) use ($source137, $currentSource137, $plan137): void {
        $current = $currentSource137(['upperBound' => 'plugin_zeta', 'upperInclusive' => true]);
        $t->same('IdxGT', $plan137($source137(), $current)['currentSourceFence']['rangeRecheckOpcode']);
    },
    'planner stat4 expression skipscan current source next137 identical source requires next stage' => static function (TestRunner $t) use ($source137, $plan137): void {
        $source = $source137();
        $t->same('requires-next-stage', $plan137($source, $source)['status']);
    },
    'planner stat4 expression skipscan current source next137 identical source not stale' => static function (TestRunner $t) use ($source137, $plan137): void {
        $source = $source137();
        $t->same(false, $plan137($source, $source)['stalePreparedStatement']);
    },
    'planner stat4 expression skipscan current source next137 current schema only still stages' => static function (TestRunner $t) use ($source137, $currentSource137, $stat4137, $plan137): void {
        $current = $currentSource137(['stat4Generation' => 31, 'stat4Samples' => $stat4137()]);
        $t->same('requires-next-stage', $plan137($source137(), $current)['status']);
    },
    'planner stat4 expression skipscan current source next137 missing covering needs table' => static function (TestRunner $t) use ($source137, $currentSource137, $plan137): void {
        $current = $currentSource137(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan137($source137(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner stat4 expression skipscan current source next137 missing covering remains ready' => static function (TestRunner $t) use ($source137, $currentSource137, $plan137): void {
        $current = $currentSource137(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same('stat4-expression-skipscan-current-source-next137-ready', $plan137($source137(), $current)['status']);
    },
    'planner stat4 expression skipscan current source next137 desc order reverses tape' => static function (TestRunner $t) use ($plan137): void {
        $p = $plan137(null, null, [['expression' => 'autoload', 'direction' => 'DESC'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same('Prev', $p['cursorTape']['program'][4]['opcode']);
    },
    'planner stat4 expression skipscan current source next137 desc order reverse flag' => static function (TestRunner $t) use ($plan137): void {
        $p = $plan137(null, null, [['expression' => 'autoload', 'direction' => 'DESC'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same(true, $p['selectedPlan']['reverseScan']);
    },
    'planner stat4 expression skipscan current source next137 block sort elided for full order' => static fn (TestRunner $t) => $t->same(false, $plan137()['selectedPlan']['blockSortRequired']),
    'planner stat4 expression skipscan current source next137 partial order block sort' => static function (TestRunner $t) use ($plan137): void {
        $p = $plan137(null, null, [['expression' => 'lower(option_name)']]);
        $t->same(true, $p['selectedPlan']['blockSortRequired']);
    },
    'planner stat4 expression skipscan current source next137 partial order tape block sort' => static function (TestRunner $t) use ($plan137): void {
        $p = $plan137(null, null, [['expression' => 'lower(option_name)']]);
        $t->same(true, $p['cursorTape']['blockSortRequired']);
    },
    'planner stat4 expression skipscan current source next137 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-expression-skipscan-current-source-next137', implode(',', $plan137()['dependencies'])),
    'planner stat4 expression skipscan current source next137 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan137()['dependency_closure']),
    'planner stat4 expression skipscan current source next137 non overlap' => static fn (TestRunner $t) => $t->contains('STAT4 stale-source selection', $plan137()['non_overlap']),
    'planner stat4 expression skipscan current source next137 validates current name' => static function (TestRunner $t) use ($source137, $currentSource137, $plan137): void {
        $bad = $currentSource137(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan137($source137(), $bad));
    },
    'planner stat4 expression skipscan current source next137 validates stat4 counter' => static function (TestRunner $t) use ($source137, $currentSource137, $plan137): void {
        $bad = $currentSource137(['stat4Samples' => [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan137($source137(), $bad));
    },
];

return $tests;
