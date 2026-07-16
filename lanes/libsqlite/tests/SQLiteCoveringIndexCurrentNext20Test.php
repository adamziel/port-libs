<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$indexes = static fn (): array => [
    [
        'name' => 'idx_name',
        'rootPage' => 31,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_name ON wp_options(option_name)',
    ],
    [
        'name' => 'idx_name_autoload_value',
        'rootPage' => 32,
        'estimatedRows' => 1200,
        'sql' => 'CREATE INDEX idx_name_autoload_value ON wp_options(option_name, autoload, option_value)',
    ],
    [
        'name' => 'idx_autoload_name_desc',
        'rootPage' => 33,
        'estimatedRows' => 9000,
        'sql' => 'CREATE INDEX idx_autoload_name_desc ON wp_options(autoload, option_name DESC)',
    ],
    [
        'name' => 'idx_blog_autoload_name',
        'rootPage' => 34,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_blog_autoload_name ON wp_options(blog_id, autoload, option_name)',
    ],
    [
        'name' => 'idx_public_name_value',
        'rootPage' => 35,
        'estimatedRows' => 800,
        'sql' => "CREATE INDEX idx_public_name_value ON wp_options(option_name, option_value) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_nonnull_value_name',
        'rootPage' => 36,
        'estimatedRows' => 1500,
        'sql' => 'CREATE INDEX idx_nonnull_value_name ON wp_options(option_value, option_name) WHERE option_value IS NOT NULL',
    ],
];

$column = static fn (string $name): array => ['column' => $name];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$tests = [
    'covering index current next20 chooses covering option lookup over narrow index' => static function (TestRunner $t) use ($indexes, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $point('option_name', 'siteurl'), ['option_name', 'autoload', 'option_value']);
        $t->same('idx_name_autoload_value', $plan['name']);
        $t->same(true, $plan['covering']);
    },
    'covering index current next20 preserves selected root page' => static function (TestRunner $t) use ($indexes, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $point('option_name', 'home'), ['option_name', 'autoload']);
        $t->same(32, $plan['rootPage']);
    },
    'covering index current next20 records used equality prefix column' => static function (TestRunner $t) use ($indexes, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $point('option_name', 'blogname'), ['option_name']);
        $t->same(['option_name'], $plan['usedColumns']);
        $t->same(1, $plan['equalityPrefix']);
    },
    'covering index current next20 reports non covering narrow lookup' => static function (TestRunner $t) use ($indexes, $point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans($indexes(), $point('option_name', 'siteurl'), ['option_name', 'autoload', 'option_value']);
        $last = end($plans);
        $t->same('idx_name', $last['name']);
        $t->same(false, $last['covering']);
    },
    'covering index current next20 estimates point lookup from table statistics' => static function (TestRunner $t) use ($indexes, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $point('option_name', 'siteurl'), ['option_name', 'autoload']);
        $t->same(96, $plan['estimatedRows']);
    },
    'covering index current next20 leaves residual predicate marker for table filter' => static function (TestRunner $t) use ($indexes, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $point('option_name', 'siteurl'), ['option_name']);
        $t->same(true, $plan['residualPredicateRequired']);
    },
    'covering index current next20 chooses partial covering when predicate proves autoload yes' => static function (TestRunner $t) use ($indexes, $point, $and): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0], $indexes()[4]], $and($point('option_name', 'siteurl'), $point('autoload', 'yes')), ['option_name', 'option_value']);
        $t->same('idx_public_name_value', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'covering index current next20 rejects partial covering when autoload term differs' => static function (TestRunner $t) use ($indexes, $point, $and): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[4]], $and($point('option_name', 'siteurl'), $point('autoload', 'no')), ['option_name', 'option_value']);
        $t->same([], $plans);
    },
    'covering index current next20 accepts reversed equality for partial predicate' => static function (TestRunner $t) use ($indexes, $point, $and): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0], $indexes()[4]], $and($point('option_name', 'home'), ['operator' => '=', 'left' => 'yes', 'right' => ['column' => 'autoload']]), ['option_name', 'option_value']);
        $t->same('idx_public_name_value', $plan['name']);
    },
    'covering index current next20 accepts is not null partial predicate proof' => static function (TestRunner $t) use ($indexes, $column): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), ['operator' => 'IS NOT NULL', 'left' => $column('option_value')], ['option_value', 'option_name']);
        $t->same('idx_nonnull_value_name', $plan['name']);
    },
    'covering index current next20 rejects null equality for is not null partial predicate' => static function (TestRunner $t) use ($indexes, $point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[5]], $point('option_value', null), ['option_value', 'option_name']);
        $t->same([], $plans);
    },
    'covering index current next20 uses range after equality prefix' => static function (TestRunner $t) use ($indexes, $and, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('autoload', 'yes'), ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_']), ['autoload', 'option_name']);
        $t->same('idx_autoload_name_desc', $plan['name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'covering index current next20 estimates equality plus range selectivity' => static function (TestRunner $t) use ($indexes, $and, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('autoload', 'yes'), ['operator' => '<', 'left' => ['column' => 'option_name'], 'right' => 'z']), ['autoload', 'option_name']);
        $t->same(180, $plan['estimatedRows']);
    },
    'covering index current next20 satisfies descending order after equality prefix' => static function (TestRunner $t) use ($indexes, $and, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('autoload', 'yes'), ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_']), ['autoload', 'option_name'], [['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'covering index current next20 rejects ascending order against descending index column' => static function (TestRunner $t) use ($indexes, $and, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('autoload', 'yes'), ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_']), ['autoload', 'option_name'], [['column' => 'option_name', 'direction' => 'ASC']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'covering index current next20 satisfies multi column order after two equalities' => static function (TestRunner $t) use ($indexes, $and, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('blog_id', 1), $point('autoload', 'yes')), ['blog_id', 'autoload', 'option_name'], [['column' => 'option_name']]);
        $t->same('idx_blog_autoload_name', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'covering index current next20 records two equality prefix columns' => static function (TestRunner $t) use ($indexes, $and, $point): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('blog_id', 1), $point('autoload', 'yes')), ['blog_id', 'autoload']);
        $t->same(['blog_id', 'autoload'], $plan['usedColumns']);
        $t->same(2, $plan['equalityPrefix']);
    },
    'covering index current next20 stops prefix at first missing leading column' => static function (TestRunner $t) use ($indexes, $point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[3]], $point('autoload', 'yes'), ['autoload', 'option_name']);
        $t->same([], $plans);
    },
    'covering index current next20 uses in list as equality prefix' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), ['operator' => 'IN', 'left' => ['column' => 'option_name'], 'values' => ['home', 'siteurl', null]], ['option_name', 'autoload']);
        $t->same('idx_name_autoload_value', $plan['name']);
        $t->same(1, $plan['equalityPrefix']);
    },
    'covering index current next20 rejects all null in-list prefix' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans($indexes(), ['operator' => 'IN', 'left' => ['column' => 'option_name'], 'values' => [null, null]], ['option_name']);
        $t->same([], $plans);
    },
    'covering index current next20 uses between as range prefix' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), ['operator' => 'BETWEEN', 'left' => ['column' => 'option_name'], 'lower' => '_site_', 'upper' => '_transient_'], ['option_name']);
        $t->same('option_name', $plan['rangeColumn']);
        $t->same(300, $plan['estimatedRows']);
    },
    'covering index current next20 reverses range operand for constant less than column' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), ['operator' => '<', 'left' => 'm', 'right' => ['column' => 'option_name']], ['option_name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'covering index current next20 ranks order satisfied plan ahead when costs are close' => static function (TestRunner $t) use ($point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([
            ['name' => 'idx_plain', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_plain ON wp_options(autoload, option_name)'],
            ['name' => 'idx_desc', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_desc ON wp_options(autoload, option_name DESC)'],
        ], $point('autoload', 'yes'), ['autoload', 'option_name'], [['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same('idx_desc', $plans[0]['name']);
    },
    'covering index current next20 ranks covering plan ahead when row estimates tie' => static function (TestRunner $t) use ($point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([
            ['name' => 'idx_short', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_short ON wp_options(option_name)'],
            ['name' => 'idx_cover', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_cover ON wp_options(option_name, autoload)'],
        ], $point('option_name', 'siteurl'), ['option_name', 'autoload']);
        $t->same('idx_cover', $plans[0]['name']);
    },
    'covering index current next20 orders same cost plans by index name' => static function (TestRunner $t) use ($point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([
            ['name' => 'idx_b', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_b ON wp_options(option_name)'],
            ['name' => 'idx_a', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_a ON wp_options(option_name)'],
        ], $point('option_name', 'siteurl'), ['option_name']);
        $t->same(['idx_a', 'idx_b'], array_column($plans, 'name'));
    },
    'covering index current next20 ignores expression index definitions' => static function (TestRunner $t) use ($point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([
            ['name' => 'idx_expr', 'sql' => 'CREATE INDEX idx_expr ON wp_options(lower(option_name))'],
        ], $point('option_name', 'siteurl'), ['option_name']);
        $t->same([], $plans);
    },
    'covering index current next20 ignores unsupported like predicate' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans($indexes(), ['operator' => 'LIKE', 'left' => ['column' => 'option_name'], 'right' => 'site%'], ['option_name']);
        $t->same([], $plans);
    },
    'covering index current next20 rejects missing create index sql' => static function (TestRunner $t) use ($point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedPlans([['name' => 'bad']], $point('option_name', 'siteurl'), ['option_name']));
    },
    'covering index current next20 validates estimated row count' => static function (TestRunner $t) use ($point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedPlans([['name' => 'bad', 'estimatedRows' => 0, 'sql' => 'CREATE INDEX bad ON wp_options(option_name)']], $point('option_name', 'siteurl'), ['option_name']));
    },
    'covering index current next20 validates requested columns' => static function (TestRunner $t) use ($indexes, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedPlans($indexes(), $point('option_name', 'siteurl'), ['']));
    },
    'covering index current next20 validates order direction' => static function (TestRunner $t) use ($indexes, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedPlans($indexes(), $point('option_name', 'siteurl'), ['option_name'], [['column' => 'option_name', 'direction' => 'SIDEWAYS']]));
    },
];

return $tests;
