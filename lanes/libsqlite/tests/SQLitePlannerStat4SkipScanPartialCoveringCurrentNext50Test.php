<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSkipScanCoveringStat4Plan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4 = static fn (): array => [
    ['prefix' => ['main', 'auto'], 'suffix' => '_site_transient_browser', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => ['main', 'auto'], 'suffix' => '_transient_feed', 'nEq' => 7, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => ['main', 'auto'], 'suffix' => 'plugin_alpha', 'nEq' => 3, 'nLt' => 9, 'nDLt' => 2],
    ['prefix' => ['main', 'yes'], 'suffix' => '_site_transient_timeout', 'nEq' => 5, 'nLt' => 12, 'nDLt' => 3],
    ['prefix' => ['main', 'yes'], 'suffix' => '_transient_timeout_feed', 'nEq' => 11, 'nLt' => 17, 'nDLt' => 4],
    ['prefix' => ['main', 'yes'], 'suffix' => 'siteurl', 'nEq' => 1, 'nLt' => 28, 'nDLt' => 5],
    ['prefix' => ['network', 'no'], 'suffix' => '_site_transient_update_plugins', 'nEq' => 13, 'nLt' => 29, 'nDLt' => 6],
    ['prefix' => ['network', 'no'], 'suffix' => '_transient_doing_cron', 'nEq' => 17, 'nLt' => 42, 'nDLt' => 7],
    ['prefix' => ['network', 'no'], 'suffix' => 'widget_recent-posts', 'nEq' => 4, 'nLt' => 59, 'nDLt' => 8],
    ['prefix' => ['network', 'yes'], 'suffix' => '_site_transient_update_themes', 'nEq' => 19, 'nLt' => 63, 'nDLt' => 9],
    ['prefix' => ['network', 'yes'], 'suffix' => '_transient_timeout_theme', 'nEq' => 23, 'nLt' => 82, 'nDLt' => 10],
    ['prefix' => ['network', 'yes'], 'suffix' => 'theme_mods_default', 'nEq' => 6, 'nLt' => 105, 'nDLt' => 11],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_blog_autoload_status_name_value_partial_stat4',
        'rootPage' => 950,
        'estimatedRows' => 64000,
        'distinctValues' => ['blog_scope' => 2, 'autoload' => 2],
        'stat4Samples' => $stat4(),
        'sql' => "CREATE INDEX idx_blog_autoload_status_name_value_partial_stat4 ON wp_options(blog_scope, autoload, status, option_name, option_value) WHERE status = 'active' AND option_name >= '_site_'",
    ],
    [
        'name' => 'idx_blog_autoload_status_name_partial_noncover',
        'rootPage' => 951,
        'estimatedRows' => 36000,
        'distinctValues' => ['blog_scope' => 2, 'autoload' => 2],
        'stat4Samples' => $stat4(),
        'sql' => "CREATE INDEX idx_blog_autoload_status_name_partial_noncover ON wp_options(blog_scope, autoload, status, option_name) WHERE status = 'active' AND option_name >= '_site_'",
    ],
    [
        'name' => 'idx_blog_autoload_status_name_value_partial_legacy',
        'rootPage' => 952,
        'estimatedRows' => 12000,
        'distinctValues' => ['blog_scope' => 2, 'autoload' => 2],
        'sql' => "CREATE INDEX idx_blog_autoload_status_name_value_partial_legacy ON wp_options(blog_scope, autoload, status, option_name, option_value) WHERE status = 'active' AND option_name >= '_site_'",
    ],
    [
        'name' => 'idx_blog_autoload_status_name_value_unproved',
        'rootPage' => 953,
        'estimatedRows' => 12000,
        'distinctValues' => ['blog_scope' => 2, 'autoload' => 2],
        'stat4Samples' => $stat4(),
        'sql' => "CREATE INDEX idx_blog_autoload_status_name_value_unproved ON wp_options(blog_scope, autoload, status, option_name, option_value) WHERE status = 'archived' AND option_name >= '_site_'",
    ],
];

$predicate = static fn (): array => $and(
    $point('status', 'active'),
    $range('option_name', '>=', '_transient_'),
);
$needed = ['status', 'option_name', 'option_value'];
$order = [['column' => 'blog_scope'], ['column' => 'autoload'], ['column' => 'status'], ['column' => 'option_name']];
$plan = static fn (array $indexesArg = null, array $predicateArg = null, array $orderArg = null, array $neededArg = null): ?array => SQLiteSkipScanCoveringStat4Plan::choose(
    $indexesArg ?? $indexes(),
    $predicateArg ?? $predicate(),
    $orderArg ?? $order,
    $neededArg ?? $needed,
);
$ranked = static fn (array $indexesArg = null, array $predicateArg = null, array $orderArg = null, array $neededArg = null): array => SQLiteSkipScanCoveringStat4Plan::rankedPlans(
    $indexesArg ?? $indexes(),
    $predicateArg ?? $predicate(),
    $orderArg ?? $order,
    $neededArg ?? $needed,
);

$tests = [
    'planner stat4 skipscan partial covering current next50 chooses partial stat4 covering index' => static function (TestRunner $t) use ($plan): void {
        $t->same('idx_blog_autoload_status_name_value_partial_stat4', $plan()['name']);
    },
    'planner stat4 skipscan partial covering current next50 records two skipped columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['blog_scope', 'autoload'], $plan()['skippedColumns']);
    },
    'planner stat4 skipscan partial covering current next50 keeps skip scan enabled' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['usesSkipScan']);
    },
    'planner stat4 skipscan partial covering current next50 proves partial predicate' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['partialPredicateImplied']);
    },
    'planner stat4 skipscan partial covering current next50 keeps partial flag' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['partial']);
    },
    'planner stat4 skipscan partial covering current next50 uses status equality prefix after skip' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['equalityPrefix']);
    },
    'planner stat4 skipscan partial covering current next50 uses option name range' => static function (TestRunner $t) use ($plan): void {
        $t->same('option_name', $plan()['rangeColumn']);
    },
    'planner stat4 skipscan partial covering current next50 range starts at fourth index column' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['currentIndexColumnOffset']);
    },
    'planner stat4 skipscan partial covering current next50 reports four skip loops' => static function (TestRunner $t) use ($plan): void {
        $t->same(4, $plan()['skipScanLoops']);
    },
    'planner stat4 skipscan partial covering current next50 reports multi skipped penalty' => static function (TestRunner $t) use ($plan): void {
        $t->same(42, $plan()['skipScanPenalty']);
    },
    'planner stat4 skipscan partial covering current next50 keeps used current columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['status', 'option_name'], $plan()['usedColumns']);
    },
    'planner stat4 skipscan partial covering current next50 records equality constraint' => static function (TestRunner $t) use ($plan): void {
        $t->same('status', $plan()['equalityConstraints'][0]['column']);
    },
    'planner stat4 skipscan partial covering current next50 records range operator' => static function (TestRunner $t) use ($plan): void {
        $t->same('range->=', $plan()['rangeConstraint']['operator']);
    },
    'planner stat4 skipscan partial covering current next50 records range value' => static function (TestRunner $t) use ($plan): void {
        $t->same('_transient_', $plan()['rangeConstraint']['values']);
    },
    'planner stat4 skipscan partial covering current next50 stays covering' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['covering']);
    },
    'planner stat4 skipscan partial covering current next50 avoids deferred table lookup' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan()['deferredTableLookup']);
    },
    'planner stat4 skipscan partial covering current next50 has no missing lookup columns' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['tableLookupColumns']);
    },
    'planner stat4 skipscan partial covering current next50 preserves covering payload columns' => static function (TestRunner $t) use ($plan, $needed): void {
        $t->same($needed, $plan()['coveringPayloadColumns']);
    },
    'planner stat4 skipscan partial covering current next50 uses all tuple samples' => static function (TestRunner $t) use ($plan): void {
        $t->same(12, $plan()['stat4SamplesUsed']);
    },
    'planner stat4 skipscan partial covering current next50 marks stat4 used' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['stat4Used']);
    },
    'planner stat4 skipscan partial covering current next50 estimates rows from suffix samples' => static function (TestRunner $t) use ($plan): void {
        $t->same(72, $plan()['estimatedRows']);
    },
    'planner stat4 skipscan partial covering current next50 discounts partial covering stat4 cost' => static function (TestRunner $t) use ($plan): void {
        $t->same(60, $plan()['estimatedCost']);
    },
    'planner stat4 skipscan partial covering current next50 satisfies full skipped prefix order' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['orderBySatisfied']);
    },
    'planner stat4 skipscan partial covering current next50 detail names partial' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'PARTIAL USING STAT4'));
    },
    'planner stat4 skipscan partial covering current next50 detail names both skipped columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'ANY(blog_scope,autoload)'));
    },
    'planner stat4 skipscan partial covering current next50 tuple current key is stable' => static function (TestRunner $t) use ($plan): void {
        $t->same('(main,auto)|_site_transient_browser', $plan()['stat4CurrentNext'][0]['current']['key']);
    },
    'planner stat4 skipscan partial covering current next50 tuple next key is stable' => static function (TestRunner $t) use ($plan): void {
        $t->same('(main,auto)|_transient_feed', $plan()['stat4CurrentNext'][0]['next']['key']);
    },
    'planner stat4 skipscan partial covering current next50 terminal tuple next is null' => static function (TestRunner $t) use ($plan): void {
        $t->same(null, $plan()['stat4CurrentNext'][11]['next']);
    },
    'planner stat4 skipscan partial covering current next50 loop prefixes are tuples' => static function (TestRunner $t) use ($plan): void {
        $t->same([['main', 'auto'], ['main', 'yes'], ['network', 'no'], ['network', 'yes']], array_column($plan()['stat4LoopEstimates'], 'prefix'));
    },
    'planner stat4 skipscan partial covering current next50 loop sample counts' => static function (TestRunner $t) use ($plan): void {
        $t->same([3, 3, 3, 3], array_column($plan()['stat4LoopEstimates'], 'sampleCount'));
    },
    'planner stat4 skipscan partial covering current next50 loop row estimates' => static function (TestRunner $t) use ($plan): void {
        $t->same([10, 12, 21, 29], array_column($plan()['stat4LoopEstimates'], 'estimatedRows'));
    },
    'planner stat4 skipscan partial covering current next50 loop current suffixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['_transient_feed', '_transient_timeout_feed', '_transient_doing_cron', '_transient_timeout_theme'], array_column($plan()['stat4LoopEstimates'], 'currentSuffix'));
    },
    'planner stat4 skipscan partial covering current next50 loop next suffixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['plugin_alpha', 'siteurl', 'widget_recent-posts', 'theme_mods_default'], array_column($plan()['stat4LoopEstimates'], 'nextSuffix'));
    },
    'planner stat4 skipscan partial covering current next50 ranks covering first' => static function (TestRunner $t) use ($ranked): void {
        $t->same('idx_blog_autoload_status_name_value_partial_stat4', $ranked()[0]['name']);
    },
    'planner stat4 skipscan partial covering current next50 ranks noncovering second' => static function (TestRunner $t) use ($ranked): void {
        $t->same('idx_blog_autoload_status_name_partial_noncover', $ranked()[1]['name']);
    },
    'planner stat4 skipscan partial covering current next50 ranks legacy third' => static function (TestRunner $t) use ($ranked): void {
        $t->same('idx_blog_autoload_status_name_value_partial_legacy', $ranked()[2]['name']);
    },
    'planner stat4 skipscan partial covering current next50 filters unproved archived partial index' => static function (TestRunner $t) use ($ranked): void {
        $t->same(false, in_array('idx_blog_autoload_status_name_value_unproved', array_column($ranked(), 'name'), true));
    },
    'planner stat4 skipscan partial covering current next50 noncovering defers lookup' => static function (TestRunner $t) use ($ranked): void {
        $t->same(true, $ranked()[1]['deferredTableLookup']);
    },
    'planner stat4 skipscan partial covering current next50 noncovering names option value lookup' => static function (TestRunner $t) use ($ranked): void {
        $t->same(['option_value'], $ranked()[1]['tableLookupColumns']);
    },
    'planner stat4 skipscan partial covering current next50 legacy lacks stat4' => static function (TestRunner $t) use ($ranked): void {
        $t->same(false, $ranked()[2]['stat4Used']);
    },
    'planner stat4 skipscan partial covering current next50 legacy keeps base skip estimate' => static function (TestRunner $t) use ($ranked): void {
        $t->same(240, $ranked()[2]['estimatedRows']);
    },
    'planner stat4 skipscan partial covering current next50 suffix only order not satisfied' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan(null, null, [['column' => 'option_name']])['orderBySatisfied']);
    },
    'planner stat4 skipscan partial covering current next50 missing payload column triggers table lookup' => static function (TestRunner $t) use ($plan): void {
        $t->same(['option_id'], $plan(null, null, null, ['status', 'option_name', 'option_id'])['tableLookupColumns']);
    },
    'planner stat4 skipscan partial covering current next50 point on first skipped column still needs later skip' => static function (TestRunner $t) use ($indexes, $point, $range, $and, $ranked): void {
        $plans = $ranked($indexes(), $and($point('blog_scope', 'main'), $point('status', 'active'), $range('option_name', '>=', '_transient_')));
        $t->same(['blog_scope', 'autoload'], $plans[0]['skippedColumns']);
    },
    'planner stat4 skipscan partial covering current next50 missing partial status proof rejects all candidates' => static function (TestRunner $t) use ($indexes, $range, $and, $ranked): void {
        $t->same([], $ranked($indexes(), $and($range('option_name', '>=', '_transient_'))));
    },
    'planner stat4 skipscan partial covering current next50 missing range proof rejects all candidates' => static function (TestRunner $t) use ($indexes, $point, $ranked): void {
        $t->same([], $ranked($indexes(), $point('status', 'active')));
    },
    'planner stat4 skipscan partial covering current next50 between uses bounded samples' => static function (TestRunner $t) use ($plan, $point, $between, $and): void {
        $t->same(74, $plan(null, $and($point('status', 'active'), $between('option_name', '_site_', '_transient_timeout_feed')))['estimatedRows']);
    },
    'planner stat4 skipscan partial covering current next50 less than range fails partial lower proof' => static function (TestRunner $t) use ($plan, $point, $range, $and): void {
        $t->same(null, $plan(null, $and($point('status', 'active'), $range('option_name', '<', '_transient_'))));
    },
    'planner stat4 skipscan partial covering current next50 greater than range uses tail samples' => static function (TestRunner $t) use ($plan, $point, $range, $and): void {
        $t->same(14, $plan(null, $and($point('status', 'active'), $range('option_name', '>', 'plugin_')))['estimatedRows']);
    },
    'planner stat4 skipscan partial covering current next50 out of range falls back per tuple prefix' => static function (TestRunner $t) use ($plan, $point, $range, $and): void {
        $t->same(39, $plan(null, $and($point('status', 'active'), $range('option_name', '>', 'zzzz')))['estimatedRows']);
    },
    'planner stat4 skipscan partial covering current next50 validates tuple sample shape' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = ['not-a-list' => []];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 skipscan partial covering current next50 validates tuple sample counter' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = [['prefix' => ['main', 'yes'], 'suffix' => '_transient_', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 skipscan partial covering current next50 validates tuple suffix presence' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = [['prefix' => ['main', 'yes'], 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
];

$prefixCases = [
    'main auto' => [['main', 'auto'], 3, 10, '_transient_feed', 'plugin_alpha'],
    'main yes' => [['main', 'yes'], 3, 12, '_transient_timeout_feed', 'siteurl'],
    'network no' => [['network', 'no'], 3, 21, '_transient_doing_cron', 'widget_recent-posts'],
    'network yes' => [['network', 'yes'], 3, 29, '_transient_timeout_theme', 'theme_mods_default'],
];

foreach ($prefixCases as $label => [$prefix, $sampleCount, $estimatedRows, $currentSuffix, $nextSuffix]) {
    $tests["planner stat4 skipscan partial covering current next50 {$label} tuple prefix"] = static function (TestRunner $t) use ($plan, $prefix, $label): void {
        $loops = array_column($plan()['stat4LoopEstimates'], null, 'sampleCount');
        $found = array_values(array_filter($plan()['stat4LoopEstimates'], static fn (array $loop): bool => $loop['prefix'] === $prefix));
        $t->same(true, $found !== [], $label);
    };
    $tests["planner stat4 skipscan partial covering current next50 {$label} sample count"] = static function (TestRunner $t) use ($plan, $prefix, $sampleCount): void {
        $loop = array_values(array_filter($plan()['stat4LoopEstimates'], static fn (array $item): bool => $item['prefix'] === $prefix))[0];
        $t->same($sampleCount, $loop['sampleCount']);
    };
    $tests["planner stat4 skipscan partial covering current next50 {$label} estimated rows"] = static function (TestRunner $t) use ($plan, $prefix, $estimatedRows): void {
        $loop = array_values(array_filter($plan()['stat4LoopEstimates'], static fn (array $item): bool => $item['prefix'] === $prefix))[0];
        $t->same($estimatedRows, $loop['estimatedRows']);
    };
    $tests["planner stat4 skipscan partial covering current next50 {$label} current suffix"] = static function (TestRunner $t) use ($plan, $prefix, $currentSuffix): void {
        $loop = array_values(array_filter($plan()['stat4LoopEstimates'], static fn (array $item): bool => $item['prefix'] === $prefix))[0];
        $t->same($currentSuffix, $loop['currentSuffix']);
    };
    $tests["planner stat4 skipscan partial covering current next50 {$label} next suffix"] = static function (TestRunner $t) use ($plan, $prefix, $nextSuffix): void {
        $loop = array_values(array_filter($plan()['stat4LoopEstimates'], static fn (array $item): bool => $item['prefix'] === $prefix))[0];
        $t->same($nextSuffix, $loop['nextSuffix']);
    };
}

return $tests;
