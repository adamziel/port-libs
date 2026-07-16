<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan;

$rows = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_forms', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Mail', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_security', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_seo', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_shop', 'option_value' => 'a:7', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'option_value' => 'theme', 'kind' => 'theme'],
];
$currentRows = static function () use ($rows): array {
    $rows = $rows();
    $rows[] = ['rowid' => 10, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:9', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'lazy', 'option_name' => 'plugin_cache', 'option_value' => 'a:10', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'no', 'option_name' => 'PLUGIN_ZIP', 'option_value' => 'a:11', 'kind' => 'plugin'];

    return $rows;
};
$stat4 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'lazy', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'plugin_shop', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
];
$currentStat4 = static function () use ($stat4): array {
    $samples = $stat4();
    $samples[1] = ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 3, 'nLt' => 1, 'nDLt' => 1];
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'lazy', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_zip', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};
$source = static function (array $overrides = []) use ($rows, $stat4): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie-prepared',
        'schemaCookie' => 170,
        'stat4Generation' => 77,
        'indexName' => 'idx_wp_options_autoload_lower_name_covering',
        'rootPage' => 701,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zeta',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows(),
        'stat4Samples' => $stat4(),
    ];
};
$currentSource = static function (array $overrides = []) use ($currentRows, $currentStat4): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie-current',
        'schemaCookie' => 171,
        'stat4Generation' => 78,
        'indexName' => 'idx_wp_options_autoload_lower_name_covering',
        'rootPage' => 709,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_c',
        'upperBound' => 'plugin_zip',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows(),
        'stat4Samples' => $currentStat4(),
    ];
};
$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$orderBy = [
    ['expression' => 'autoload'],
    ['expression' => 'lower(option_name)'],
];
$needed = ['option_name', 'option_value', 'kind'];
$plan = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $neededColumns = null): array => SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan::materialize(
    $prepared ?? $source(),
    $current ?? $currentSource(),
    $partial,
    $query,
    $order ?? $orderBy,
    $neededColumns ?? $needed,
);

$tests = [
    'planner stat4 covering skipscan current source status ready' => static fn (TestRunner $t) => $t->same('stat4-covering-skipscan-current-source-ready', $plan()['status']),
    'planner stat4 covering skipscan current source selects current' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 covering skipscan current source stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 covering skipscan current source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 covering skipscan current source stat4 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4SignatureChanged']),
    'planner stat4 covering skipscan current source sample count changed' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4SampleCountChanged']),
    'planner stat4 covering skipscan current source prefix order stable' => static fn (TestRunner $t) => $t->same(false, $plan()['stat4PrefixOrderChanged']),
    'planner stat4 covering skipscan current source covering signature stable' => static fn (TestRunner $t) => $t->same(false, $plan()['coveringSignatureChanged']),
    'planner stat4 covering skipscan current source range fence changed' => static fn (TestRunner $t) => $t->same(true, $plan()['rangeFenceChanged']),
    'planner stat4 covering skipscan current source collation changed' => static fn (TestRunner $t) => $t->same(true, $plan()['collationChanged']),
    'planner stat4 covering skipscan current source prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['preparedStat4Signature'])),
    'planner stat4 covering skipscan current source current signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentStat4Signature'])),
    'planner stat4 covering skipscan current source signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan()['preparedStat4Signature'] === $plan()['currentStat4Signature']),
    'planner stat4 covering skipscan current source prepared sample count' => static fn (TestRunner $t) => $t->same(8, count($plan()['preparedStat4Samples'])),
    'planner stat4 covering skipscan current source current sample count' => static fn (TestRunner $t) => $t->same(11, count($plan()['currentStat4Samples'])),
    'planner stat4 covering skipscan current source added sample count' => static fn (TestRunner $t) => $t->same(3, count($plan()['stat4SampleDelta']['added'])),
    'planner stat4 covering skipscan current source changed sample count' => static fn (TestRunner $t) => $t->same(1, count($plan()['stat4SampleDelta']['changed'])),
    'planner stat4 covering skipscan current source removed sample count' => static fn (TestRunner $t) => $t->same(0, count($plan()['stat4SampleDelta']['removed'])),
    'planner stat4 covering skipscan current source added suffixes' => static fn (TestRunner $t) => $t->same(['plugin_delta', 'plugin_cache', 'plugin_zip'], array_column($plan()['stat4SampleDelta']['added'], 'suffix')),
    'planner stat4 covering skipscan current source changed beta neq' => static fn (TestRunner $t) => $t->same(3, $plan()['stat4SampleDelta']['changed'][0]['current']['nEq']),
    'planner stat4 covering skipscan current source prefix delta count' => static fn (TestRunner $t) => $t->same(4, count($plan()['stat4PrefixDelta'])),
    'planner stat4 covering skipscan current source auto prefix grew' => static fn (TestRunner $t) => $t->same(1, $plan()['stat4PrefixDelta'][0]['sampleDelta']),
    'planner stat4 covering skipscan current source lazy prefix grew' => static fn (TestRunner $t) => $t->same(1, $plan()['stat4PrefixDelta'][1]['sampleDelta']),
    'planner stat4 covering skipscan current source no prefix grew' => static fn (TestRunner $t) => $t->same(1, $plan()['stat4PrefixDelta'][2]['sampleDelta']),
    'planner stat4 covering skipscan current source yes prefix stable' => static fn (TestRunner $t) => $t->same(0, $plan()['stat4PrefixDelta'][3]['sampleDelta']),
    'planner stat4 covering skipscan current source current rowids' => static fn (TestRunner $t) => $t->same([10, 3, 11, 4, 5, 6, 7, 12, 8], $plan()['currentSkipScanRowids']),
    'planner stat4 covering skipscan current source rejected rowids' => static fn (TestRunner $t) => $t->same([1, 2], $plan()['rangeRejectedRowids']),
    'planner stat4 covering skipscan current source admitted rowids' => static fn (TestRunner $t) => $t->same([10, 11, 12], $plan()['rangeAdmittedRowids']),
    'planner stat4 covering skipscan current source stable rowids' => static fn (TestRunner $t) => $t->same([3, 4, 5, 6, 7, 8], $plan()['rangeStableRowids']),
    'planner stat4 covering skipscan current source selected covering' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'planner stat4 covering skipscan current source avoids table seek' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['tableSeekRequired']),
    'planner stat4 covering skipscan current source selected uses skipscan' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['usesSkipScan']),
    'planner stat4 covering skipscan current source selected samples used' => static fn (TestRunner $t) => $t->same(11, $plan()['selectedPlan']['stat4SamplesUsed']),
    'planner stat4 covering skipscan current source selected covered count' => static fn (TestRunner $t) => $t->same(9, $plan()['selectedPlan']['coveredRowCount']),
    'planner stat4 covering skipscan current source stat4 fence source' => static fn (TestRunner $t) => $t->same('current-main.wp_options@cookie-current', $plan()['stat4Fence']['source']),
    'planner stat4 covering skipscan current source stat4 fence generation' => static fn (TestRunner $t) => $t->same(78, $plan()['stat4Fence']['stat4Generation']),
    'planner stat4 covering skipscan current source stat4 fence samples' => static fn (TestRunner $t) => $t->same(11, $plan()['stat4Fence']['sampleCount']),
    'planner stat4 covering skipscan current source stat4 fence row count' => static fn (TestRunner $t) => $t->same(9, $plan()['stat4Fence']['rowCount']),
    'planner stat4 covering skipscan current source stat4 fence covering count' => static fn (TestRunner $t) => $t->same(9, $plan()['stat4Fence']['coveringRowCount']),
    'planner stat4 covering skipscan current source stat4 prefix order' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], $plan()['stat4Fence']['prefixOrder']),
    'planner stat4 covering skipscan current source covering tape source' => static fn (TestRunner $t) => $t->same('current', $plan()['coveringCursorTape']['source']),
    'planner stat4 covering skipscan current source covering tape reprepare opcode' => static fn (TestRunner $t) => $t->same('ReprepareIfStat4FenceStale', $plan()['coveringCursorTape']['program'][0]['opcode']),
    'planner stat4 covering skipscan current source covering tape seekscan opcode' => static fn (TestRunner $t) => $t->same('SeekScan', $plan()['coveringCursorTape']['program'][1]['opcode']),
    'planner stat4 covering skipscan current source covering tape stat4 gate opcode' => static fn (TestRunner $t) => $t->same('Stat4SampleGate', $plan()['coveringCursorTape']['program'][2]['opcode']),
    'planner stat4 covering skipscan current source covering tape stat4 samples' => static fn (TestRunner $t) => $t->same(11, $plan()['coveringCursorTape']['program'][2]['samples']),
    'planner stat4 covering skipscan current source covering tape columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name'], $plan()['coveringCursorTape']['program'][3]['columns']),
    'planner stat4 covering skipscan current source covering tape next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan()['coveringCursorTape']['program'][4]['opcode']),
    'planner stat4 covering skipscan current source covering tape rowids' => static fn (TestRunner $t) => $t->same([10, 3, 11, 4, 5, 6, 7, 12, 8], $plan()['coveringCursorTape']['rowids']),
    'planner stat4 covering skipscan current source payload preview count' => static fn (TestRunner $t) => $t->same(9, count($plan()['coveringPayloadPreview'])),
    'planner stat4 covering skipscan current source first payload rowid' => static fn (TestRunner $t) => $t->same(10, $plan()['coveringPayloadPreview'][0]['rowid']),
    'planner stat4 covering skipscan current source uppercase payload keeps source value' => static fn (TestRunner $t) => $t->same('PLUGIN_ZIP', $plan()['coveringPayloadPreview'][7]['covering']['option_name']),
    'planner stat4 covering skipscan current source detail marks stat4 changed' => static fn (TestRunner $t) => $t->contains('stat4-covering-fence=changed', $plan()['detail']),
    'planner stat4 covering skipscan current source dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-covering-skipscan-current-source', implode(',', $plan()['dependencies'])),
    'planner stat4 covering skipscan current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']),
    'planner stat4 covering skipscan current source non overlap' => static fn (TestRunner $t) => $t->contains('stale STAT4 covering skip-scan sample payload/order', $plan()['non_overlap']),
    'planner stat4 covering skipscan current source identical source needs next stage' => static function (TestRunner $t) use ($source, $plan): void {
        $source = $source();
        $t->same('requires-next-stage', $plan($source, $source)['status']);
    },
    'planner stat4 covering skipscan current source identical source stat4 stable' => static function (TestRunner $t) use ($source, $plan): void {
        $source = $source();
        $t->same(false, $plan($source, $source)['stat4SignatureChanged']);
    },
    'planner stat4 covering skipscan current source covering loss is detected' => static function (TestRunner $t) use ($source, $currentSource, $plan): void {
        $current = $currentSource(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan($source(), $current)['coveringSignatureChanged']);
    },
    'planner stat4 covering skipscan current source covering loss requires table seek' => static function (TestRunner $t) use ($source, $currentSource, $plan): void {
        $current = $currentSource(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan($source(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner stat4 covering skipscan current source reordered prefixes detected' => static function (TestRunner $t) use ($source, $currentSource, $currentStat4, $plan): void {
        $samples = $currentStat4();
        $yes = array_splice($samples, 7, 1);
        array_unshift($samples, $yes[0]);
        $current = $currentSource(['stat4Samples' => $samples]);
        $t->same(true, $plan($source(), $current)['stat4PrefixOrderChanged']);
    },
    'planner stat4 covering skipscan current source desc order uses prev' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, [['expression' => 'autoload', 'direction' => 'DESC'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same('Prev', $p['coveringCursorTape']['program'][4]['opcode']);
    },
    'planner stat4 covering skipscan current source validates stat4 list' => static function (TestRunner $t) use ($source, $currentSource, $plan): void {
        $bad = $currentSource(['stat4Samples' => ['bad' => 'shape']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan($source(), $bad));
    },
    'planner stat4 covering skipscan current source validates stat4 integers' => static function (TestRunner $t) use ($source, $currentSource, $currentStat4, $plan): void {
        $samples = $currentStat4();
        $samples[0]['nEq'] = -1;
        $bad = $currentSource(['stat4Samples' => $samples]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan($source(), $bad));
    },
    'planner stat4 covering skipscan current source validates covering list' => static function (TestRunner $t) use ($source, $currentSource, $plan): void {
        $bad = $currentSource(['coveringColumns' => ['bad' => 'shape']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan($source(), $bad));
    },
    'planner stat4 covering skipscan current source validates source name' => static function (TestRunner $t) use ($source, $currentSource, $plan): void {
        $bad = $currentSource(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan($source(), $bad));
    },
];

return $tests;
