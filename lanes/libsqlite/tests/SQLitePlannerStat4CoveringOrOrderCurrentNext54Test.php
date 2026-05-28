<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4CoveringOrOrderPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$stat4 = static fn (): array => [
    ['prefix' => 'no', 'suffix' => '_site_transient_update_plugins', 'nEq' => 5, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => '_transient_doing_cron', 'nEq' => 7, 'nLt' => 5, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'rewrite_rules', 'nEq' => 3, 'nLt' => 12, 'nDLt' => 2],
    ['prefix' => 'yes', 'suffix' => 'active_plugins', 'nEq' => 2, 'nLt' => 15, 'nDLt' => 3],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 9, 'nLt' => 17, 'nDLt' => 4],
    ['prefix' => 'yes', 'suffix' => 'plugin_beta', 'nEq' => 4, 'nLt' => 26, 'nDLt' => 5],
    ['prefix' => 'yes', 'suffix' => 'siteurl', 'nEq' => 1, 'nLt' => 30, 'nDLt' => 6],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_autoload_blog_name_value_stat4',
        'rootPage' => 541,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 2],
        'stat4Samples' => $stat4(),
        'sql' => 'CREATE INDEX idx_autoload_blog_name_value_stat4 ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
    [
        'name' => 'idx_autoload_blog_name_noncover_stat4',
        'rootPage' => 542,
        'estimatedRows' => 12000,
        'distinctValues' => ['autoload' => 2],
        'stat4Samples' => $stat4(),
        'sql' => 'CREATE INDEX idx_autoload_blog_name_noncover_stat4 ON wp_options(autoload, blog_id, option_name)',
    ],
];

$predicate = static fn (): array => $or(
    $and($point('blog_id', 1), $range('option_name', '>=', 'plugin_')),
    $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_feed')),
);
$order = [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']];
$needed = ['blog_id', 'option_name', 'option_value'];
$plan = static fn (array $indexesArg = null, array $predicateArg = null, array $orderArg = null, array $neededArg = null): ?array => SQLiteStat4CoveringOrOrderPlan::choose(
    $indexesArg ?? $indexes(),
    $predicateArg ?? $predicate(),
    $orderArg ?? $order,
    $neededArg ?? $needed,
);

$tests = [
    'planner stat4 covering or order current next54 produces usable or plan' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['usable']);
    },
    'planner stat4 covering or order current next54 uses single covering index strategy' => static function (TestRunner $t) use ($plan): void {
        $t->same('stat4-covering-single-index-or', $plan()['strategy']);
    },
    'planner stat4 covering or order current next54 reports arm count' => static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan()['armCount']);
    },
    'planner stat4 covering or order current next54 names index once' => static function (TestRunner $t) use ($plan): void {
        $t->same(['idx_autoload_blog_name_value_stat4'], $plan()['indexNames']);
    },
    'planner stat4 covering or order current next54 reports one range column' => static function (TestRunner $t) use ($plan): void {
        $t->same(['option_name'], $plan()['rangeColumns']);
    },
    'planner stat4 covering or order current next54 is covering' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['covering']);
    },
    'planner stat4 covering or order current next54 uses stat4' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['stat4Used']);
    },
    'planner stat4 covering or order current next54 satisfies order by' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['orderBySatisfied']);
    },
    'planner stat4 covering or order current next54 needs rowid union' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['rowidUnionRequired']);
    },
    'planner stat4 covering or order current next54 can merge ordered arms' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['mergeOrderRequired']);
    },
    'planner stat4 covering or order current next54 avoids temp sort when all arms ordered' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan()['tempSortRequired']);
    },
    'planner stat4 covering or order current next54 counts current next samples' => static function (TestRunner $t) use ($plan): void {
        $t->same(14, $plan()['stat4CurrentNextCount']);
    },
    'planner stat4 covering or order current next54 detail names stat4 covering' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'STAT4 COVERING'));
    },
    'planner stat4 covering or order current next54 detail names merge order' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, str_contains($plan()['detail'], 'MERGE ORDER'));
    },
    'planner stat4 covering or order current next54 first arm ordinal' => static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan()['arms'][0]['ordinal']);
    },
    'planner stat4 covering or order current next54 second arm ordinal' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['arms'][1]['ordinal']);
    },
    'planner stat4 covering or order current next54 first arm range operator' => static function (TestRunner $t) use ($plan): void {
        $t->same('range->=', $plan()['arms'][0]['rangeConstraint']['operator']);
    },
    'planner stat4 covering or order current next54 second arm range operator' => static function (TestRunner $t) use ($plan): void {
        $t->same('BETWEEN', $plan()['arms'][1]['rangeConstraint']['operator']);
    },
    'planner stat4 covering or order current next54 first arm skips autoload' => static function (TestRunner $t) use ($plan): void {
        $t->same(['autoload'], $plan()['arms'][0]['skippedColumns']);
    },
    'planner stat4 covering or order current next54 second arm skips autoload' => static function (TestRunner $t) use ($plan): void {
        $t->same(['autoload'], $plan()['arms'][1]['skippedColumns']);
    },
    'planner stat4 covering or order current next54 first arm uses blog and name' => static function (TestRunner $t) use ($plan): void {
        $t->same(['blog_id', 'option_name'], $plan()['arms'][0]['usedColumns']);
    },
    'planner stat4 covering or order current next54 second arm uses blog and name' => static function (TestRunner $t) use ($plan): void {
        $t->same(['blog_id', 'option_name'], $plan()['arms'][1]['usedColumns']);
    },
    'planner stat4 covering or order current next54 first arm is covering' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['arms'][0]['covering']);
    },
    'planner stat4 covering or order current next54 second arm is covering' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['arms'][1]['covering']);
    },
    'planner stat4 covering or order current next54 first arm uses stat4' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['arms'][0]['stat4Used']);
    },
    'planner stat4 covering or order current next54 second arm uses stat4' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['arms'][1]['stat4Used']);
    },
    'planner stat4 covering or order current next54 first arm order satisfied' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['arms'][0]['orderBySatisfied']);
    },
    'planner stat4 covering or order current next54 second arm order satisfied' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['arms'][1]['orderBySatisfied']);
    },
    'planner stat4 covering or order current next54 first arm root page' => static function (TestRunner $t) use ($plan): void {
        $t->same(541, $plan()['arms'][0]['rootPage']);
    },
    'planner stat4 covering or order current next54 second arm root page' => static function (TestRunner $t) use ($plan): void {
        $t->same(541, $plan()['arms'][1]['rootPage']);
    },
    'planner stat4 covering or order current next54 first arm loop prefixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['no', 'yes'], array_column($plan()['arms'][0]['stat4LoopEstimates'], 'prefix'));
    },
    'planner stat4 covering or order current next54 second arm loop prefixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['no', 'yes'], array_column($plan()['arms'][1]['stat4LoopEstimates'], 'prefix'));
    },
    'planner stat4 covering or order current next54 first arm current suffixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['rewrite_rules', 'plugin_alpha'], array_column($plan()['arms'][0]['stat4LoopEstimates'], 'currentSuffix'));
    },
    'planner stat4 covering or order current next54 second arm current suffixes' => static function (TestRunner $t) use ($plan): void {
        $t->same(['_site_transient_update_plugins', 'active_plugins'], array_column($plan()['arms'][1]['stat4LoopEstimates'], 'currentSuffix'));
    },
    'planner stat4 covering or order current next54 current next first key' => static function (TestRunner $t) use ($plan): void {
        $t->same('no|_site_transient_update_plugins', $plan()['arms'][0]['stat4CurrentNext'][0]['current']['key']);
    },
    'planner stat4 covering or order current next54 current next first next key' => static function (TestRunner $t) use ($plan): void {
        $t->same('no|_transient_doing_cron', $plan()['arms'][0]['stat4CurrentNext'][0]['next']['key']);
    },
    'planner stat4 covering or order current next54 current next terminal next null' => static function (TestRunner $t) use ($plan): void {
        $t->same(null, $plan()['arms'][0]['stat4CurrentNext'][6]['next']);
    },
    'planner stat4 covering or order current next54 rejects non or predicate' => static function (TestRunner $t) use ($indexes, $and, $point, $range, $order, $needed): void {
        $t->same(null, SQLiteStat4CoveringOrOrderPlan::choose($indexes(), $and($point('blog_id', 1), $range('option_name', '>=', 'plugin_')), $order, $needed));
    },
    'planner stat4 covering or order current next54 rejects empty or terms' => static function (TestRunner $t) use ($indexes, $order, $needed): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4CoveringOrOrderPlan::choose($indexes(), ['operator' => 'OR', 'terms' => []], $order, $needed));
    },
    'planner stat4 covering or order current next54 rejects scalar or term' => static function (TestRunner $t) use ($indexes, $order, $needed): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4CoveringOrOrderPlan::choose($indexes(), ['operator' => 'OR', 'terms' => ['bad']], $order, $needed));
    },
    'planner stat4 covering or order current next54 rejects non covering arm' => static function (TestRunner $t) use ($indexes, $predicate, $order): void {
        $t->same(null, SQLiteStat4CoveringOrOrderPlan::choose($indexes(), $predicate(), $order, ['blog_id', 'option_name', 'missing_column']));
    },
    'planner stat4 covering or order current next54 rejects missing stat4 arm' => static function (TestRunner $t) use ($indexes, $predicate, $order, $needed): void {
        $bad = $indexes();
        unset($bad[0]['stat4Samples']);
        $t->same(null, SQLiteStat4CoveringOrOrderPlan::choose($bad, $predicate(), $order, $needed));
    },
    'planner stat4 covering or order current next54 rejects non skipscan arm' => static function (TestRunner $t) use ($indexes, $or, $and, $point, $range, $order, $needed): void {
        $predicate = $or($and($point('autoload', 'yes'), $point('blog_id', 1), $range('option_name', '>=', 'plugin_')));
        $t->same(null, SQLiteStat4CoveringOrOrderPlan::choose($indexes(), $predicate, $order, $needed));
    },
    'planner stat4 covering or order current next54 marks temp sort for suffix order' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, [['column' => 'option_name']]);
        $t->same(true, $p['tempSortRequired']);
    },
    'planner stat4 covering or order current next54 disables merge order for suffix order' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, [['column' => 'option_name']]);
        $t->same(false, $p['mergeOrderRequired']);
    },
    'planner stat4 covering or order current next54 keeps rowid union for suffix order' => static function (TestRunner $t) use ($plan): void {
        $p = $plan(null, null, [['column' => 'option_name']]);
        $t->same(true, $p['rowidUnionRequired']);
    },
    'planner stat4 covering or order current next54 accepts single or arm without rowid union' => static function (TestRunner $t) use ($plan, $or, $and, $point, $range): void {
        $p = $plan(null, $or($and($point('blog_id', 1), $range('option_name', '>=', 'plugin_'))));
        $t->same(false, $p['rowidUnionRequired']);
    },
    'planner stat4 covering or order current next54 single arm keeps one arm count' => static function (TestRunner $t) use ($plan, $or, $and, $point, $range): void {
        $p = $plan(null, $or($and($point('blog_id', 1), $range('option_name', '>=', 'plugin_'))));
        $t->same(1, $p['armCount']);
    },
    'planner stat4 covering or order current next54 validates needed column names through arm planner' => static function (TestRunner $t) use ($indexes, $predicate, $order): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4CoveringOrOrderPlan::choose($indexes(), $predicate(), $order, ['']));
    },
    'planner stat4 covering or order current next54 validates order column names through arm planner' => static function (TestRunner $t) use ($indexes, $predicate, $needed): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4CoveringOrOrderPlan::choose($indexes(), $predicate(), [['column' => '']], $needed));
    },
    'planner stat4 covering or order current next54 validates order direction through arm planner' => static function (TestRunner $t) use ($indexes, $predicate, $needed): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4CoveringOrOrderPlan::choose($indexes(), $predicate(), [['column' => 'autoload', 'direction' => 'SIDEWAYS']], $needed));
    },
];

$rangeCases = [
    ['>=', 'plugin_', 17],
    ['>', 'plugin_alpha', 8],
    ['<', '_transient_feed', 14],
    ['<=', 'plugin_beta', 27],
];
foreach ($rangeCases as [$operator, $value, $expectedMinimum]) {
    $tests["planner stat4 covering or order current next54 range {$operator} {$value} keeps positive estimate"] = static function (TestRunner $t) use ($plan, $or, $and, $point, $range, $operator, $value, $expectedMinimum): void {
        $p = $plan(null, $or($and($point('blog_id', 1), $range('option_name', $operator, $value))));
        $t->same(true, $p['estimatedRows'] >= $expectedMinimum);
    };
}

return $tests;
