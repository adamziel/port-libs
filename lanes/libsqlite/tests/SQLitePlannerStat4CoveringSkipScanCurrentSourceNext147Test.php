<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan;

$rows147 = static fn (): array => [
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
$currentRows147 = static function () use ($rows147): array {
    $rows = $rows147();
    $rows[] = ['rowid' => 10, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:9', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'lazy', 'option_name' => 'plugin_cache', 'option_value' => 'a:10', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'no', 'option_name' => 'PLUGIN_ZIP', 'option_value' => 'a:11', 'kind' => 'plugin'];

    return $rows;
};
$stat4147 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'lazy', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'plugin_shop', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
];
$currentStat4147 = static function () use ($stat4147): array {
    $samples = $stat4147();
    $samples[1] = ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 3, 'nLt' => 1, 'nDLt' => 1];
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'lazy', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_zip', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};
$source147 = static function (array $overrides = []) use ($rows147, $stat4147): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1470',
        'schemaCookie' => 1470,
        'stat4Generation' => 77,
        'indexName' => 'idx_wp_options_autoload_lower_name_covering_next147',
        'rootPage' => 14701,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next147',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zeta',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows147(),
        'stat4Samples' => $stat4147(),
    ];
};
$currentSource147 = static function (array $overrides = []) use ($currentRows147, $currentStat4147): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1471',
        'schemaCookie' => 1471,
        'stat4Generation' => 78,
        'indexName' => 'idx_wp_options_autoload_lower_name_covering_next147',
        'rootPage' => 14709,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next147',
        'lowerInclusive' => 'plugin_c',
        'upperBound' => 'plugin_zip',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows147(),
        'stat4Samples' => $currentStat4147(),
    ];
};
$partial147 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query147 = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$order147 = [
    ['expression' => 'autoload'],
    ['expression' => 'lower(option_name)'],
];
$needed147 = ['option_name', 'option_value', 'kind'];
$plan147 = static fn (?array $prepared = null, ?array $current = null, ?array $order = null, ?array $needed = null): array => SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan::materializeNext147(
    $prepared ?? $source147(),
    $current ?? $currentSource147(),
    $partial147,
    $query147,
    $order ?? $order147,
    $needed ?? $needed147,
);

$tests = [
    'planner stat4 covering skipscan current source next147 status ready' => static fn (TestRunner $t) => $t->same('stat4-covering-skipscan-current-source-next147-ready', $plan147()['status']),
    'planner stat4 covering skipscan current source next147 selects current' => static fn (TestRunner $t) => $t->same('current', $plan147()['selectedSource']),
    'planner stat4 covering skipscan current source next147 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan147()['stalePreparedStatement']),
    'planner stat4 covering skipscan current source next147 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan147()['reprepareRequired']),
    'planner stat4 covering skipscan current source next147 stat4 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan147()['stat4SignatureChanged']),
    'planner stat4 covering skipscan current source next147 sample count changed' => static fn (TestRunner $t) => $t->same(true, $plan147()['stat4SampleCountChanged']),
    'planner stat4 covering skipscan current source next147 prefix order stable' => static fn (TestRunner $t) => $t->same(false, $plan147()['stat4PrefixOrderChanged']),
    'planner stat4 covering skipscan current source next147 covering signature stable' => static fn (TestRunner $t) => $t->same(false, $plan147()['coveringSignatureChanged']),
    'planner stat4 covering skipscan current source next147 range fence changed' => static fn (TestRunner $t) => $t->same(true, $plan147()['rangeFenceChanged']),
    'planner stat4 covering skipscan current source next147 collation changed' => static fn (TestRunner $t) => $t->same(true, $plan147()['collationChanged']),
    'planner stat4 covering skipscan current source next147 prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan147()['preparedStat4Signature'])),
    'planner stat4 covering skipscan current source next147 current signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan147()['currentStat4Signature'])),
    'planner stat4 covering skipscan current source next147 signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan147()['preparedStat4Signature'] === $plan147()['currentStat4Signature']),
    'planner stat4 covering skipscan current source next147 prepared sample count' => static fn (TestRunner $t) => $t->same(8, count($plan147()['preparedStat4Samples'])),
    'planner stat4 covering skipscan current source next147 current sample count' => static fn (TestRunner $t) => $t->same(11, count($plan147()['currentStat4Samples'])),
    'planner stat4 covering skipscan current source next147 added sample count' => static fn (TestRunner $t) => $t->same(3, count($plan147()['stat4SampleDelta']['added'])),
    'planner stat4 covering skipscan current source next147 changed sample count' => static fn (TestRunner $t) => $t->same(1, count($plan147()['stat4SampleDelta']['changed'])),
    'planner stat4 covering skipscan current source next147 removed sample count' => static fn (TestRunner $t) => $t->same(0, count($plan147()['stat4SampleDelta']['removed'])),
    'planner stat4 covering skipscan current source next147 added suffixes' => static fn (TestRunner $t) => $t->same(['plugin_delta', 'plugin_cache', 'plugin_zip'], array_column($plan147()['stat4SampleDelta']['added'], 'suffix')),
    'planner stat4 covering skipscan current source next147 changed beta neq' => static fn (TestRunner $t) => $t->same(3, $plan147()['stat4SampleDelta']['changed'][0]['current']['nEq']),
    'planner stat4 covering skipscan current source next147 prefix delta count' => static fn (TestRunner $t) => $t->same(4, count($plan147()['stat4PrefixDelta'])),
    'planner stat4 covering skipscan current source next147 auto prefix grew' => static fn (TestRunner $t) => $t->same(1, $plan147()['stat4PrefixDelta'][0]['sampleDelta']),
    'planner stat4 covering skipscan current source next147 lazy prefix grew' => static fn (TestRunner $t) => $t->same(1, $plan147()['stat4PrefixDelta'][1]['sampleDelta']),
    'planner stat4 covering skipscan current source next147 no prefix grew' => static fn (TestRunner $t) => $t->same(1, $plan147()['stat4PrefixDelta'][2]['sampleDelta']),
    'planner stat4 covering skipscan current source next147 yes prefix stable' => static fn (TestRunner $t) => $t->same(0, $plan147()['stat4PrefixDelta'][3]['sampleDelta']),
    'planner stat4 covering skipscan current source next147 current rowids' => static fn (TestRunner $t) => $t->same([10, 3, 11, 4, 5, 6, 7, 12, 8], $plan147()['currentSkipScanRowids']),
    'planner stat4 covering skipscan current source next147 rejected rowids' => static fn (TestRunner $t) => $t->same([1, 2], $plan147()['rangeRejectedRowids']),
    'planner stat4 covering skipscan current source next147 admitted rowids' => static fn (TestRunner $t) => $t->same([10, 11, 12], $plan147()['rangeAdmittedRowids']),
    'planner stat4 covering skipscan current source next147 stable rowids' => static fn (TestRunner $t) => $t->same([3, 4, 5, 6, 7, 8], $plan147()['rangeStableRowids']),
    'planner stat4 covering skipscan current source next147 selected covering' => static fn (TestRunner $t) => $t->same(true, $plan147()['selectedPlan']['covering']),
    'planner stat4 covering skipscan current source next147 avoids table seek' => static fn (TestRunner $t) => $t->same(false, $plan147()['selectedPlan']['tableSeekRequired']),
    'planner stat4 covering skipscan current source next147 selected uses skipscan' => static fn (TestRunner $t) => $t->same(true, $plan147()['selectedPlan']['usesSkipScan']),
    'planner stat4 covering skipscan current source next147 selected samples used' => static fn (TestRunner $t) => $t->same(11, $plan147()['selectedPlan']['stat4SamplesUsed']),
    'planner stat4 covering skipscan current source next147 selected covered count' => static fn (TestRunner $t) => $t->same(9, $plan147()['selectedPlan']['coveredRowCount']),
    'planner stat4 covering skipscan current source next147 stat4 fence source' => static fn (TestRunner $t) => $t->same('current-main.wp_options@cookie1471', $plan147()['stat4Fence']['source']),
    'planner stat4 covering skipscan current source next147 stat4 fence generation' => static fn (TestRunner $t) => $t->same(78, $plan147()['stat4Fence']['stat4Generation']),
    'planner stat4 covering skipscan current source next147 stat4 fence samples' => static fn (TestRunner $t) => $t->same(11, $plan147()['stat4Fence']['sampleCount']),
    'planner stat4 covering skipscan current source next147 stat4 fence row count' => static fn (TestRunner $t) => $t->same(9, $plan147()['stat4Fence']['rowCount']),
    'planner stat4 covering skipscan current source next147 stat4 fence covering count' => static fn (TestRunner $t) => $t->same(9, $plan147()['stat4Fence']['coveringRowCount']),
    'planner stat4 covering skipscan current source next147 stat4 prefix order' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], $plan147()['stat4Fence']['prefixOrder']),
    'planner stat4 covering skipscan current source next147 covering tape source' => static fn (TestRunner $t) => $t->same('current', $plan147()['coveringCursorTape']['source']),
    'planner stat4 covering skipscan current source next147 covering tape reprepare opcode' => static fn (TestRunner $t) => $t->same('ReprepareIfStat4FenceStale', $plan147()['coveringCursorTape']['program'][0]['opcode']),
    'planner stat4 covering skipscan current source next147 covering tape seekscan opcode' => static fn (TestRunner $t) => $t->same('SeekScan', $plan147()['coveringCursorTape']['program'][1]['opcode']),
    'planner stat4 covering skipscan current source next147 covering tape stat4 gate opcode' => static fn (TestRunner $t) => $t->same('Stat4SampleGate', $plan147()['coveringCursorTape']['program'][2]['opcode']),
    'planner stat4 covering skipscan current source next147 covering tape stat4 samples' => static fn (TestRunner $t) => $t->same(11, $plan147()['coveringCursorTape']['program'][2]['samples']),
    'planner stat4 covering skipscan current source next147 covering tape columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name_next147'], $plan147()['coveringCursorTape']['program'][3]['columns']),
    'planner stat4 covering skipscan current source next147 covering tape next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan147()['coveringCursorTape']['program'][4]['opcode']),
    'planner stat4 covering skipscan current source next147 covering tape rowids' => static fn (TestRunner $t) => $t->same([10, 3, 11, 4, 5, 6, 7, 12, 8], $plan147()['coveringCursorTape']['rowids']),
    'planner stat4 covering skipscan current source next147 payload preview count' => static fn (TestRunner $t) => $t->same(9, count($plan147()['coveringPayloadPreview'])),
    'planner stat4 covering skipscan current source next147 first payload rowid' => static fn (TestRunner $t) => $t->same(10, $plan147()['coveringPayloadPreview'][0]['rowid']),
    'planner stat4 covering skipscan current source next147 uppercase payload keeps source value' => static fn (TestRunner $t) => $t->same('PLUGIN_ZIP', $plan147()['coveringPayloadPreview'][7]['covering']['option_name']),
    'planner stat4 covering skipscan current source next147 detail marks stat4 changed' => static fn (TestRunner $t) => $t->contains('stat4-covering-fence=changed', $plan147()['detail']),
    'planner stat4 covering skipscan current source next147 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-covering-skipscan-current-source-next147', implode(',', $plan147()['dependencies'])),
    'planner stat4 covering skipscan current source next147 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan147()['dependency_closure']),
    'planner stat4 covering skipscan current source next147 non overlap' => static fn (TestRunner $t) => $t->contains('stale STAT4 covering skip-scan sample payload/order', $plan147()['non_overlap']),
    'planner stat4 covering skipscan current source next147 identical source needs next stage' => static function (TestRunner $t) use ($source147, $plan147): void {
        $source = $source147();
        $t->same('requires-next-stage', $plan147($source, $source)['status']);
    },
    'planner stat4 covering skipscan current source next147 identical source stat4 stable' => static function (TestRunner $t) use ($source147, $plan147): void {
        $source = $source147();
        $t->same(false, $plan147($source, $source)['stat4SignatureChanged']);
    },
    'planner stat4 covering skipscan current source next147 covering loss is detected' => static function (TestRunner $t) use ($source147, $currentSource147, $plan147): void {
        $current = $currentSource147(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan147($source147(), $current)['coveringSignatureChanged']);
    },
    'planner stat4 covering skipscan current source next147 covering loss requires table seek' => static function (TestRunner $t) use ($source147, $currentSource147, $plan147): void {
        $current = $currentSource147(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan147($source147(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner stat4 covering skipscan current source next147 reordered prefixes detected' => static function (TestRunner $t) use ($source147, $currentSource147, $currentStat4147, $plan147): void {
        $samples = $currentStat4147();
        $yes = array_splice($samples, 7, 1);
        array_unshift($samples, $yes[0]);
        $current = $currentSource147(['stat4Samples' => $samples]);
        $t->same(true, $plan147($source147(), $current)['stat4PrefixOrderChanged']);
    },
    'planner stat4 covering skipscan current source next147 desc order uses prev' => static function (TestRunner $t) use ($plan147): void {
        $p = $plan147(null, null, [['expression' => 'autoload', 'direction' => 'DESC'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same('Prev', $p['coveringCursorTape']['program'][4]['opcode']);
    },
    'planner stat4 covering skipscan current source next147 validates stat4 list' => static function (TestRunner $t) use ($source147, $currentSource147, $plan147): void {
        $bad = $currentSource147(['stat4Samples' => ['bad' => 'shape']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan147($source147(), $bad));
    },
    'planner stat4 covering skipscan current source next147 validates stat4 integers' => static function (TestRunner $t) use ($source147, $currentSource147, $currentStat4147, $plan147): void {
        $samples = $currentStat4147();
        $samples[0]['nEq'] = -1;
        $bad = $currentSource147(['stat4Samples' => $samples]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan147($source147(), $bad));
    },
    'planner stat4 covering skipscan current source next147 validates covering list' => static function (TestRunner $t) use ($source147, $currentSource147, $plan147): void {
        $bad = $currentSource147(['coveringColumns' => ['bad' => 'shape']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan147($source147(), $bad));
    },
    'planner stat4 covering skipscan current source next147 validates source name' => static function (TestRunner $t) use ($source147, $currentSource147, $plan147): void {
        $bad = $currentSource147(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan147($source147(), $bad));
    },
];

return $tests;
