<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteOrOptimizationPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$reversePoint = static fn (mixed $value, string $column): array => ['operator' => '=', 'left' => $value, 'right' => ['column' => $column]];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$reverseRange = static fn (mixed $value, string $operator, string $column): array => ['operator' => $operator, 'left' => $value, 'right' => ['column' => $column]];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_option_name',
        'rootPage' => 31,
        'estimatedRows' => 20000,
        'coveringColumns' => ['option_id', 'option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_option_name ON wp_options(option_name)',
    ],
    [
        'name' => 'idx_autoload',
        'rootPage' => 32,
        'estimatedRows' => 18000,
        'coveringColumns' => ['option_id', 'option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_autoload ON wp_options(autoload)',
    ],
    [
        'name' => 'idx_blog_id',
        'rootPage' => 33,
        'estimatedRows' => 40000,
        'coveringColumns' => ['blog_id', 'option_id'],
        'sql' => 'CREATE INDEX idx_blog_id ON wp_options(blog_id)',
    ],
    [
        'name' => 'idx_option_value',
        'rootPage' => 34,
        'estimatedRows' => 30000,
        'coveringColumns' => ['option_id', 'option_value'],
        'sql' => 'CREATE INDEX idx_option_value ON wp_options(option_value)',
    ],
    [
        'name' => 'idx_name_covering',
        'rootPage' => 35,
        'estimatedRows' => 10000,
        'coveringColumns' => ['option_id', 'option_name', 'option_value', 'autoload'],
        'sql' => 'CREATE INDEX idx_name_covering ON wp_options(option_name, autoload, option_value)',
    ],
];

$tests = [
    'or optimization current next29 builds rowid union for mixed indexed terms' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'siteurl'), $point('autoload', 'yes')), ['option_id', 'option_name', 'autoload']);
        $t->same('or-index-union', $plan['strategy']);
    },
    'or optimization current next29 keeps one arm per indexed OR term' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']);
        $t->same(2, count($plan['arms']));
    },
    'or optimization current next29 records distinct indexes in union order' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']);
        $t->same(['idx_name_covering', 'idx_autoload'], $plan['indexes']);
    },
    'or optimization current next29 chooses lower cost covering index for name arm' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id', 'option_name', 'autoload']);
        $t->same('idx_name_covering', $plan['arms'][0]['index']);
    },
    'or optimization current next29 preserves selected root pages for each arm' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']);
        $t->same([35, 32], array_column($plan['arms'], 'rootPage'));
    },
    'or optimization current next29 reports rowid union and dedupe for mixed columns' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']);
        $t->same([true, true], [$plan['requiresRowidUnion'], $plan['deduplicatesRowids']]);
    },
    'or optimization current next29 treats equality arms as no residual predicate' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']);
        $t->same(false, $plan['residualPredicateRequired']);
    },
    'or optimization current next29 marks range arm residual predicate required' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($range('option_name', '>=', '_transient_'), $point('autoload', 'yes')), ['option_id']);
        $t->same(true, $plan['residualPredicateRequired']);
    },
    'or optimization current next29 plans BETWEEN arm with scalar bounds' => static function (TestRunner $t) use ($indexes, $between, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($between('option_name', '_site_', '_transient_'), $point('autoload', 'yes')), ['option_id']);
        $t->same('BETWEEN', $plan['arms'][0]['operator']);
    },
    'or optimization current next29 stores BETWEEN lower and upper values' => static function (TestRunner $t) use ($indexes, $between, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($between('option_name', '_site_', '_transient_'), $point('autoload', 'yes')), ['option_id']);
        $t->same(['lower' => '_site_', 'upper' => '_transient_'], $plan['arms'][0]['values']);
    },
    'or optimization current next29 accepts reversed range lower operand' => static function (TestRunner $t) use ($indexes, $reverseRange, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($reverseRange('_transient_', '<=', 'option_name'), $point('autoload', 'yes')), ['option_id']);
        $t->same('range->=', $plan['arms'][0]['operator']);
    },
    'or optimization current next29 accepts reversed range upper operand' => static function (TestRunner $t) use ($indexes, $reverseRange, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($reverseRange('theme_', '>', 'option_name'), $point('autoload', 'yes')), ['option_id']);
        $t->same('range-<', $plan['arms'][0]['operator']);
    },
    'or optimization current next29 returns no plan when one OR arm is unindexed' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', 'home'), $point('missing_column', 'x')), ['option_id']);
        $t->same([], $plans);
    },
    'or optimization current next29 returns no plan for unsupported LIKE arm' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', 'home'), ['operator' => 'LIKE', 'left' => ['column' => 'autoload'], 'right' => 'y%']), ['option_id']);
        $t->same([], $plans);
    },
    'or optimization current next29 returns no plan for non OR predicate' => static function (TestRunner $t) use ($indexes, $point): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $point('option_name', 'home'), ['option_id']);
        $t->same([], $plans);
    },
    'or optimization current next29 rewrites same column equality OR to IN' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_id', 'option_name']);
        $t->same('or-to-in', $plan['strategy']);
    },
    'or optimization current next29 OR to IN keeps deduped literal values' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('option_name', 'siteurl'), $point('option_name', 'home')), ['option_id']);
        $t->same(['home', 'siteurl'], $plan['values']);
    },
    'or optimization current next29 OR to IN does not require rowid union' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_id']);
        $t->same(false, $plan['requiresRowidUnion']);
    },
    'or optimization current next29 same column range remains rowid union' => static function (TestRunner $t) use ($indexes, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($range('option_name', '<', 'a'), $range('option_name', '>=', 'z')), ['option_id']);
        $t->same('or-index-union', $plan['strategy']);
    },
    'or optimization current next29 same column equality IN plan beats rowid union' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', 'a'), $point('option_name', 'b')), ['option_id']);
        $t->same(['or-to-in', 'or-index-union'], array_column($plans, 'strategy'));
    },
    'or optimization current next29 estimates point arm from index row count' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', 'yes'), $point('blog_id', 1)), ['option_id']);
        $t->same([180, 400], array_column($plan['arms'], 'estimatedRows'));
    },
    'or optimization current next29 estimates range arm wider than point arm' => static function (TestRunner $t) use ($indexes, $range, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($range('autoload', '>=', 'n'), $point('blog_id', 1)), ['option_id']);
        $t->same(2250, $plan['arms'][0]['estimatedRows']);
    },
    'or optimization current next29 estimates between arm with bounded fraction' => static function (TestRunner $t) use ($indexes, $between, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($between('option_value', '0', '9'), $point('blog_id', 1)), ['option_id']);
        $t->same(2500, $plan['arms'][0]['estimatedRows']);
    },
    'or optimization current next29 reports covering false when one arm lacks needed column' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('blog_id', 1)), ['option_value']);
        $t->same(false, $plan['covering']);
    },
    'or optimization current next29 reports covering true when all arms cover needed columns' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id', 'option_name']);
        $t->same(true, $plan['covering']);
    },
    'or optimization current next29 uses literal wrapper values' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', ['literal' => 'home']), $point('autoload', 'yes')), ['option_id']);
        $t->same('home', $plan['arms'][0]['values']);
    },
    'or optimization current next29 accepts boolean literal arms' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', true), $point('blog_id', 1)), ['option_id']);
        $t->same(true, $plan['arms'][0]['values']);
    },
    'or optimization current next29 accepts null literal equality arm' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', null), $point('blog_id', 1)), ['option_id']);
        $t->same(null, $plan['arms'][0]['values']);
    },
    'or optimization current next29 rejects computed expression arm' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or(['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'home'], $point('autoload', 'yes')), ['option_id']);
        $t->same([], $plans);
    },
    'or optimization current next29 validates OR term count' => static function (TestRunner $t) use ($indexes, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOrOptimizationPlan::rankedPlans($indexes(), ['operator' => 'OR', 'terms' => [$point('option_name', 'home')]], ['option_id']));
    },
    'or optimization current next29 validates OR term type' => static function (TestRunner $t) use ($indexes, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOrOptimizationPlan::rankedPlans($indexes(), ['operator' => 'OR', 'terms' => [$point('option_name', 'home'), 'bad']], ['option_id']));
    },
    'or optimization current next29 validates index sql presence' => static function (TestRunner $t) use ($point, $or): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOrOptimizationPlan::rankedPlans([['name' => 'bad']], $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']));
    },
    'or optimization current next29 validates estimated row count' => static function (TestRunner $t) use ($point, $or): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOrOptimizationPlan::rankedPlans([
            ['name' => 'bad', 'estimatedRows' => 0, 'sql' => 'CREATE INDEX bad ON wp_options(option_name)'],
        ], $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_id']));
    },
    'or optimization current next29 validates needed columns' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id', '']));
    },
    'or optimization current next29 rejects non scalar literal arrays' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', ['bad']), $point('autoload', 'yes')), ['option_id']));
    },
    'or optimization current next29 parses generated index name when omitted' => static function (TestRunner $t) use ($point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose([
            ['estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_generated_name ON wp_options(option_name)'],
        ], $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_name']);
        $t->same('idx_generated_name', $plan['index']);
    },
    'or optimization current next29 handles quoted index names' => static function (TestRunner $t) use ($point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose([
            ['estimatedRows' => 1000, 'sql' => 'CREATE INDEX "idx quoted name" ON wp_options(option_name)'],
        ], $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_name']);
        $t->same('idx quoted name', $plan['index']);
    },
    'or optimization current next29 ignores unsupported expression indexes' => static function (TestRunner $t) use ($point, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans([
            ['name' => 'idx_lower', 'sql' => 'CREATE INDEX idx_lower ON wp_options(lower(option_name))'],
        ], $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_name']);
        $t->same([], $plans);
    },
    'or optimization current next29 prefers lower estimated arm index among duplicates' => static function (TestRunner $t) use ($point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose([
            ['name' => 'idx_slow_name', 'estimatedRows' => 40000, 'sql' => 'CREATE INDEX idx_slow_name ON wp_options(option_name)'],
            ['name' => 'idx_fast_name', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_fast_name ON wp_options(option_name)'],
            ['name' => 'idx_auto', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_auto ON wp_options(autoload)'],
        ], $or($point('option_name', 'home'), $point('autoload', 'yes')), ['option_id']);
        $t->same('idx_fast_name', $plan['arms'][0]['index']);
    },
    'or optimization current next29 ranks union plans by cost' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', 'home'), $range('autoload', '>=', 'n')), ['option_id']);
        $t->same('or-index-union', $plans[0]['strategy']);
    },
    'or optimization current next29 deduplicates identical rowid union arms' => static function (TestRunner $t) use ($indexes, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($range('autoload', '>=', 'n'), $range('autoload', '>=', 'n')), ['option_id']);
        $t->same(1, count($plan['arms']));
    },
    'or optimization current next29 keeps different range bounds as separate arms' => static function (TestRunner $t) use ($indexes, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($range('autoload', '>=', 'n'), $range('autoload', '<', 'y')), ['option_id']);
        $t->same(2, count($plan['arms']));
    },
    'or optimization current next29 uses first index column only' => static function (TestRunner $t) use ($point, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans([
            ['name' => 'idx_blog_name', 'sql' => 'CREATE INDEX idx_blog_name ON wp_options(blog_id, option_name)'],
        ], $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_id']);
        $t->same([], $plans);
    },
    'or optimization current next29 plans Application autoload or transient lookup as union' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', 'yes'), $range('option_name', '>=', '_transient_')), ['option_id', 'option_name', 'autoload']);
        $t->same(['idx_autoload', 'idx_name_covering'], $plan['indexes']);
    },
    'or optimization current next29 preserves Application option name range value' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', 'yes'), $range('option_name', '>=', '_transient_')), ['option_id']);
        $t->same('_transient_', $plan['arms'][1]['values']);
    },
    'or optimization current next29 supports reversed equality predicate' => static function (TestRunner $t) use ($indexes, $reversePoint, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($reversePoint('siteurl', 'option_name'), $point('autoload', 'yes')), ['option_id']);
        $t->same('siteurl', $plan['arms'][0]['values']);
    },
    'or optimization current next29 rewrites reversed same column equality to IN' => static function (TestRunner $t) use ($indexes, $reversePoint, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($reversePoint('siteurl', 'option_name'), $point('option_name', 'home')), ['option_id']);
        $t->same(['siteurl', 'home'], $plan['values']);
    },
    'or optimization current next29 computes summed row estimate after dedupe' => static function (TestRunner $t) use ($indexes, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($range('autoload', '>=', 'n'), $range('autoload', '>=', 'n')), ['option_id']);
        $t->same(2250, $plan['estimatedRows']);
    },
    'or optimization current next29 computes summed row estimate across arms' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', 'yes'), $point('blog_id', 1)), ['option_id']);
        $t->same(580, $plan['estimatedRows']);
    },
    'or optimization current next29 exposes arm columns for rowid union' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', 'yes'), $point('blog_id', 1)), ['option_id']);
        $t->same(['autoload', 'blog_id'], array_column($plan['arms'], 'column'));
    },
    'or optimization current next29 exposes arm operators for rowid union' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('autoload', 'yes'), $range('blog_id', '>=', 2)), ['option_id']);
        $t->same(['point', 'range->='], array_column($plan['arms'], 'operator'));
    },
    'or optimization current next29 reports no rowid dedupe for OR to IN rewrite' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_id']);
        $t->same(false, $plan['deduplicatesRowids']);
    },
    'or optimization current next29 keeps IN rewrite covering metadata' => static function (TestRunner $t) use ($indexes, $point, $or): void {
        $plan = SQLiteOrOptimizationPlan::choose($indexes(), $or($point('option_name', 'home'), $point('option_name', 'siteurl')), ['option_id', 'option_value']);
        $t->same(true, $plan['covering']);
    },
    'or optimization current next29 rejects IN rewrite when same column has range arm' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes(), $or($point('option_name', 'home'), $range('option_name', '>=', '_')), ['option_id']);
        $t->same(['or-index-union'], array_column($plans, 'strategy'));
    },
];

return $tests;
