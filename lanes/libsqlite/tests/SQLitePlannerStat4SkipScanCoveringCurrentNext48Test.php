<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSkipScanCoveringStat4Plan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => '_site_transient_timeout', 'nEq' => 3, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => '_transient_feed', 'nEq' => 9, 'nLt' => 3, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'theme_mods_default', 'nEq' => 2, 'nLt' => 12, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => '_site_transient_update_plugins', 'nEq' => 5, 'nLt' => 14, 'nDLt' => 3],
    ['prefix' => 'no', 'suffix' => '_transient_doing_cron', 'nEq' => 7, 'nLt' => 19, 'nDLt' => 4],
    ['prefix' => 'no', 'suffix' => 'widget_recent-posts', 'nEq' => 4, 'nLt' => 26, 'nDLt' => 5],
    ['prefix' => 'yes', 'suffix' => '_site_transient_browser', 'nEq' => 2, 'nLt' => 30, 'nDLt' => 6],
    ['prefix' => 'yes', 'suffix' => '_transient_timeout_feed', 'nEq' => 11, 'nLt' => 32, 'nDLt' => 7],
    ['prefix' => 'yes', 'suffix' => 'siteurl', 'nEq' => 1, 'nLt' => 43, 'nDLt' => 8],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_autoload_blog_name_value_stat4',
        'rootPage' => 481,
        'estimatedRows' => 24000,
        'distinctValues' => ['autoload' => 3],
        'stat4Samples' => $stat4(),
        'sql' => 'CREATE INDEX idx_autoload_blog_name_value_stat4 ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
    [
        'name' => 'idx_autoload_blog_name_stat4_noncover',
        'rootPage' => 482,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 3],
        'stat4Samples' => $stat4(),
        'sql' => 'CREATE INDEX idx_autoload_blog_name_stat4_noncover ON wp_options(autoload, blog_id, option_name)',
    ],
    [
        'name' => 'idx_autoload_blog_name_value_legacy',
        'rootPage' => 483,
        'estimatedRows' => 6000,
        'distinctValues' => ['autoload' => 3],
        'sql' => 'CREATE INDEX idx_autoload_blog_name_value_legacy ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
];

$predicate = static fn (): array => $and(
    $point('blog_id', 1),
    $range('option_name', '>=', '_transient_'),
);
$needed = ['blog_id', 'option_name', 'option_value'];
$order = [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']];
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
    'planner stat4 skipscan covering current next48 chooses covering stat4 index' => static function (TestRunner $t) use ($plan): void {
        $t->same('idx_autoload_blog_name_value_stat4', $plan()['name']);
    },
    'planner stat4 skipscan covering current next48 keeps skip scan flag' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['usesSkipScan']);
    },
    'planner stat4 skipscan covering current next48 records skipped leading column' => static function (TestRunner $t) use ($plan): void {
        $t->same(['autoload'], $plan()['skippedColumns']);
    },
    'planner stat4 skipscan covering current next48 records current range column' => static function (TestRunner $t) use ($plan): void {
        $t->same('option_name', $plan()['rangeColumn']);
    },
    'planner stat4 skipscan covering current next48 records equality prefix after skip' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['equalityPrefix']);
    },
    'planner stat4 skipscan covering current next48 keeps current index column offset' => static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan()['currentIndexColumnOffset']);
    },
    'planner stat4 skipscan covering current next48 marks covering true' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['covering']);
    },
    'planner stat4 skipscan covering current next48 avoids deferred table lookup' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan()['deferredTableLookup']);
    },
    'planner stat4 skipscan covering current next48 has no table lookup columns' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['tableLookupColumns']);
    },
    'planner stat4 skipscan covering current next48 preserves payload columns' => static function (TestRunner $t) use ($plan, $needed): void {
        $t->same($needed, $plan()['coveringPayloadColumns']);
    },
    'planner stat4 skipscan covering current next48 uses stat4 samples' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['stat4Used']);
    },
    'planner stat4 skipscan covering current next48 counts stat4 samples' => static function (TestRunner $t) use ($plan): void {
        $t->same(9, $plan()['stat4SamplesUsed']);
    },
    'planner stat4 skipscan covering current next48 estimates rows from in range samples' => static function (TestRunner $t) use ($plan): void {
        $t->same(34, $plan()['estimatedRows']);
    },
    'planner stat4 skipscan covering current next48 discounts covering and stat4 cost' => static function (TestRunner $t) use ($plan): void {
        $t->same(17, $plan()['estimatedCost']);
    },
    'planner stat4 skipscan covering current next48 keeps order satisfied' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['orderBySatisfied']);
    },
    'planner stat4 skipscan covering current next48 detail names covering index' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'USING COVERING INDEX idx_autoload_blog_name_value_stat4'));
    },
    'planner stat4 skipscan covering current next48 detail names stat4' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'USING STAT4'));
    },
    'planner stat4 skipscan covering current next48 detail names order satisfied' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'ORDER BY SATISFIED'));
    },
    'planner stat4 skipscan covering current next48 current next first current key' => static function (TestRunner $t) use ($plan): void {
        $t->same('auto|_site_transient_timeout', $plan()['stat4CurrentNext'][0]['current']['key']);
    },
    'planner stat4 skipscan covering current next48 current next first next key' => static function (TestRunner $t) use ($plan): void {
        $t->same('auto|_transient_feed', $plan()['stat4CurrentNext'][0]['next']['key']);
    },
    'planner stat4 skipscan covering current next48 terminal current next has null next' => static function (TestRunner $t) use ($plan): void {
        $t->same(null, $plan()['stat4CurrentNext'][8]['next']);
    },
    'planner stat4 skipscan covering current next48 loop estimates per prefix' => static function (TestRunner $t) use ($plan): void {
        $t->same(['auto', 'no', 'yes'], array_column($plan()['stat4LoopEstimates'], 'prefix'));
    },
    'planner stat4 skipscan covering current next48 loop sample counts' => static function (TestRunner $t) use ($plan): void {
        $t->same([3, 3, 3], array_column($plan()['stat4LoopEstimates'], 'sampleCount'));
    },
    'planner stat4 skipscan covering current next48 loop row estimates' => static function (TestRunner $t) use ($plan): void {
        $t->same([11, 11, 12], array_column($plan()['stat4LoopEstimates'], 'estimatedRows'));
    },
    'planner stat4 skipscan covering current next48 loop current suffixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['_transient_feed', '_transient_doing_cron', '_transient_timeout_feed'], array_column($plan()['stat4LoopEstimates'], 'currentSuffix'));
    },
    'planner stat4 skipscan covering current next48 loop next suffixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['theme_mods_default', 'widget_recent-posts', 'siteurl'], array_column($plan()['stat4LoopEstimates'], 'nextSuffix'));
    },
    'planner stat4 skipscan covering current next48 ranks covering before noncovering' => static function (TestRunner $t) use ($ranked): void {
        $t->same('idx_autoload_blog_name_value_stat4', $ranked()[0]['name']);
    },
    'planner stat4 skipscan covering current next48 ranks noncovering second' => static function (TestRunner $t) use ($ranked): void {
        $t->same('idx_autoload_blog_name_stat4_noncover', $ranked()[1]['name']);
    },
    'planner stat4 skipscan covering current next48 ranks legacy third' => static function (TestRunner $t) use ($ranked): void {
        $t->same('idx_autoload_blog_name_value_legacy', $ranked()[2]['name']);
    },
    'planner stat4 skipscan covering current next48 noncovering defers table lookup' => static function (TestRunner $t) use ($ranked): void {
        $t->same(true, $ranked()[1]['deferredTableLookup']);
    },
    'planner stat4 skipscan covering current next48 noncovering names missing value' => static function (TestRunner $t) use ($ranked): void {
        $t->same(['option_value'], $ranked()[1]['tableLookupColumns']);
    },
    'planner stat4 skipscan covering current next48 legacy lacks stat4 usage' => static function (TestRunner $t) use ($ranked): void {
        $t->same(false, $ranked()[2]['stat4Used']);
    },
    'planner stat4 skipscan covering current next48 legacy keeps base row estimate' => static function (TestRunner $t) use ($ranked): void {
        $t->same(120, $ranked()[2]['estimatedRows']);
    },
    'planner stat4 skipscan covering current next48 suffix only order not satisfied' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, [['column' => 'option_name']]);
        $t->same(false, $p['orderBySatisfied']);
    },
    'planner stat4 skipscan covering current next48 suffix only order keeps covering' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, [['column' => 'option_name']]);
        $t->same(true, $p['covering']);
    },
    'planner stat4 skipscan covering current next48 wrong needed column requires table lookup' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, null, ['blog_id', 'option_name', 'option_id']);
        $t->same(false, $p['covering']);
    },
    'planner stat4 skipscan covering current next48 missing autoload lookup column' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, null, ['blog_id', 'option_name', 'option_id']);
        $t->same(['option_id'], $p['tableLookupColumns']);
    },
    'planner stat4 skipscan covering current next48 rejects no skip scan candidates' => static function (TestRunner $t) use ($indexes, $point, $range, $and, $ranked): void {
        $plans = $ranked($indexes(), $and($point('autoload', 'yes'), $point('blog_id', 1), $range('option_name', '>=', '_transient_')));
        $t->same([], $plans);
    },
    'planner stat4 skipscan covering current next48 rejects point only suffix' => static function (TestRunner $t) use ($indexes, $point, $and, $ranked): void {
        $plans = $ranked($indexes(), $and($point('blog_id', 1), $point('option_name', 'siteurl')));
        $t->same([], $plans);
    },
    'planner stat4 skipscan covering current next48 supports between estimate' => static function (TestRunner $t) use ($plan, $point, $between, $and): void {
        $p = $plan(null, $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_feed')));
        $t->same(26, $p['estimatedRows']);
    },
    'planner stat4 skipscan covering current next48 between keeps stat4 true' => static function (TestRunner $t) use ($plan, $point, $between, $and): void {
        $p = $plan(null, $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_feed')));
        $t->same(true, $p['stat4Used']);
    },
    'planner stat4 skipscan covering current next48 upper range estimate uses lower samples' => static function (TestRunner $t) use ($plan, $point, $range, $and): void {
        $p = $plan(null, $and($point('blog_id', 1), $range('option_name', '<', '_transient_')));
        $t->same(10, $p['estimatedRows']);
    },
    'planner stat4 skipscan covering current next48 greater range estimate uses tail samples' => static function (TestRunner $t) use ($plan, $point, $range, $and): void {
        $p = $plan(null, $and($point('blog_id', 1), $range('option_name', '>', 'theme_')));
        $t->same(8, $p['estimatedRows']);
    },
    'planner stat4 skipscan covering current next48 out of sample range falls back per prefix' => static function (TestRunner $t) use ($plan, $point, $range, $and): void {
        $p = $plan(null, $and($point('blog_id', 1), $range('option_name', '>', 'zzzz')));
        $t->same(10, $p['estimatedRows']);
    },
    'planner stat4 skipscan covering current next48 keeps root page' => static function (TestRunner $t) use ($plan): void {
        $t->same(481, $plan()['rootPage']);
    },
    'planner stat4 skipscan covering current next48 keeps used columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['blog_id', 'option_name'], $plan()['usedColumns']);
    },
    'planner stat4 skipscan covering current next48 keeps range operator' => static function (TestRunner $t) use ($plan): void {
        $t->same('range->=', $plan()['rangeConstraint']['operator']);
    },
    'planner stat4 skipscan covering current next48 keeps range value' => static function (TestRunner $t) use ($plan): void {
        $t->same('_transient_', $plan()['rangeConstraint']['values']);
    },
    'planner stat4 skipscan covering current next48 reports three loops' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['skipScanLoops']);
    },
    'planner stat4 skipscan covering current next48 reports skip penalty' => static function (TestRunner $t) use ($plan): void {
        $t->same(30, $plan()['skipScanPenalty']);
    },
    'planner stat4 skipscan covering current next48 validates sample list shape' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = ['bad' => []];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 skipscan covering current next48 validates sample row shape' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 skipscan covering current next48 validates sample counter' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = [['prefix' => 'yes', 'suffix' => 'siteurl', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 skipscan covering current next48 validates missing suffix' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        $bad[0]['stat4Samples'] = [['prefix' => 'yes', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 skipscan covering current next48 validates needed column names' => static function (TestRunner $t) use ($indexes, $predicate, $order): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanCoveringStat4Plan::choose($indexes(), $predicate(), $order, ['']));
    },
];

$rangeCases = [
    ['>=', '_site_', 44],
    ['>', '_transient_feed', 18],
    ['<=', '_transient_feed', 26],
    ['<', '_site_transient_update_plugins', 10],
];

foreach ($rangeCases as [$operator, $value, $expected]) {
    $tests["planner stat4 skipscan covering current next48 range {$operator} {$value} estimates {$expected}"] = static function (TestRunner $t) use ($plan, $point, $range, $and, $operator, $value, $expected): void {
        $p = $plan(null, $and($point('blog_id', 1), $range('option_name', $operator, $value)));
        $t->same($expected, $p['estimatedRows']);
        $t->same(true, $p['covering']);
    };
}

return $tests;
