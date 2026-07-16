<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$inList = static fn (string $column, array $values): array => ['operator' => 'IN', 'left' => ['column' => $column], 'values' => $values];

$stat4Samples = [
    ['values' => ['autoload' => 'yes', 'option_name' => 'plugin_cache_alpha'], 'rows' => 6],
    ['values' => ['autoload' => 'yes', 'option_name' => 'plugin_cache_beta'], 'rows' => 7],
    ['values' => ['autoload' => 'yes', 'option_name' => 'plugin_settings'], 'rows' => 11],
    ['values' => ['autoload' => 'yes', 'option_name' => 'admin_theme_mods'], 'rows' => 49],
    ['values' => ['autoload' => 'no', 'option_name' => 'plugin_cache_gamma'], 'rows' => 31],
    ['values' => ['autoload' => 'yes', 'option_name' => '_transient_feed'], 'rows' => 42],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_autoload_partial_cover_order_stat4',
        'rootPage' => 381,
        'estimatedRows' => 12000,
        'stat4Samples' => $stat4Samples,
        'sql' => "CREATE INDEX idx_autoload_partial_cover_order_stat4 ON wp_options(autoload, option_name, option_id DESC, option_value) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_name_plain',
        'rootPage' => 382,
        'estimatedRows' => 900,
        'sql' => 'CREATE INDEX idx_name_plain ON wp_options(option_name, autoload, option_value)',
    ],
    [
        'name' => 'idx_autoload_partial_no_stat4',
        'rootPage' => 383,
        'estimatedRows' => 12000,
        'sql' => "CREATE INDEX idx_autoload_partial_no_stat4 ON wp_options(autoload, option_name, option_id DESC, option_value) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_plugin_partial_name_order',
        'rootPage' => 384,
        'estimatedRows' => 3000,
        'stat4Samples' => [
            ['values' => ['option_name' => 'plugin_cache_alpha'], 'rows' => 5],
            ['values' => ['option_name' => 'plugin_cache_beta'], 'rows' => 6],
            ['values' => ['option_name' => 'admin_theme_mods'], 'rows' => 70],
        ],
        'sql' => "CREATE INDEX idx_plugin_partial_name_order ON wp_options(option_name, option_id DESC, autoload, option_value) WHERE option_name >= 'plugin_'",
    ],
];

$predicate = static fn () => $and(
    $point('autoload', 'yes'),
    $range('option_name', '>=', 'plugin_cache'),
);

$tests = [
    'planner stat4 partial covering order current next38 chooses stat4 partial cover' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'], [['column' => 'option_name'], ['column' => 'option_id', 'direction' => 'DESC']]);
        $t->same('idx_autoload_partial_cover_order_stat4', $plan['name']);
    },
    'planner stat4 partial covering order current next38 marks stat4 used' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(true, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'])['stat4Used']);
    },
    'planner stat4 partial covering order current next38 records matched samples' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(3, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'])['stat4MatchedSamples']);
    },
    'planner stat4 partial covering order current next38 keeps fallback estimate before stat4' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(240, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'])['estimatedRowsBeforeStat4']);
    },
    'planner stat4 partial covering order current next38 replaces estimate with sample rows' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(24, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'])['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 preserves partial proof' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(true, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name'])['partial']);
    },
    'planner stat4 partial covering order current next38 preserves covering result' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(true, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'])['covering']);
    },
    'planner stat4 partial covering order current next38 preserves current range column' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same('option_name', SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name'])['rangeColumn']);
    },
    'planner stat4 partial covering order current next38 preserves equality prefix' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(1, SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name'])['equalityPrefix']);
    },
    'planner stat4 partial covering order current next38 keeps used columns in current next order' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(['autoload', 'option_name'], SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name'])['usedColumns']);
    },
    'planner stat4 partial covering order current next38 satisfies order after equality prefix' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id'], [['column' => 'option_name'], ['column' => 'option_id', 'direction' => 'DESC']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner stat4 partial covering order current next38 rejects skipped order term' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteCoveringIndexPlan::choose(array_slice($indexes(), 0, 3), $predicate(), ['autoload', 'option_name', 'option_id'], [['column' => 'option_id', 'direction' => 'DESC']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner stat4 partial covering order current next38 ranks stat4 plan before plain fallback' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[0], $indexes()[1]], $predicate(), ['autoload', 'option_name', 'option_value']);
        $t->same(['idx_autoload_partial_cover_order_stat4', 'idx_name_plain'], array_column($plans, 'name'));
    },
    'planner stat4 partial covering order current next38 leaves no stat4 fallback plan marked unused' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[2]], $predicate(), ['autoload', 'option_name', 'option_value']);
        $t->same(false, $plans[0]['stat4Used']);
    },
    'planner stat4 partial covering order current next38 keeps fallback estimate when samples absent' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[2]], $predicate(), ['autoload', 'option_name', 'option_value']);
        $t->same(240, $plans[0]['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 ignores samples outside equality prefix' => static function (TestRunner $t) use ($indexes, $and, $point, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $and($point('autoload', 'no'), $range('option_name', '>=', 'plugin_cache')), ['autoload', 'option_name']);
        $t->same(null, $plan);
    },
    'planner stat4 partial covering order current next38 rejects unproved partial before stat4' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[0]], $range('option_name', '>=', 'plugin_cache'), ['autoload', 'option_name']);
        $t->same([], $plans);
    },
    'planner stat4 partial covering order current next38 supports name-first partial stat4 range' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[3]], $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload', 'option_value']);
        $t->same(11, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 name-first matched sample count' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[3]], $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload', 'option_value']);
        $t->same(2, $plan['stat4MatchedSamples']);
    },
    'planner stat4 partial covering order current next38 name-first satisfies option id desc order' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[3]], $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload'], [['column' => 'option_name'], ['column' => 'option_id', 'direction' => 'DESC']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner stat4 partial covering order current next38 clamps stat4 estimate to fallback' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_small', 'estimatedRows' => 20, 'stat4Samples' => [['values' => ['option_name' => 'plugin_cache'], 'rows' => 99]], 'sql' => "CREATE INDEX idx_small ON wp_options(option_name, autoload) WHERE option_name >= 'plugin_'"],
        ], $range('option_name', '>=', 'plugin_cache'), ['option_name']);
        $t->same(5, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 reports stat4 unused when no sample matches' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_sparse', 'estimatedRows' => 1000, 'stat4Samples' => [['values' => ['option_name' => 'admin_theme_mods'], 'rows' => 9]], 'sql' => "CREATE INDEX idx_sparse ON wp_options(option_name, autoload) WHERE option_name >= 'plugin_'"],
        ], $range('option_name', '>=', 'plugin_cache'), ['option_name']);
        $t->same(false, $plan['stat4Used']);
    },
    'planner stat4 partial covering order current next38 keeps fallback when no sample matches' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_sparse', 'estimatedRows' => 1000, 'stat4Samples' => [['values' => ['option_name' => 'admin_theme_mods'], 'rows' => 9]], 'sql' => "CREATE INDEX idx_sparse ON wp_options(option_name, autoload) WHERE option_name >= 'plugin_'"],
        ], $range('option_name', '>=', 'plugin_cache'), ['option_name']);
        $t->same(250, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 matches between lower upper samples' => static function (TestRunner $t) use ($indexes, $and, $point, $between): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $and($point('autoload', 'yes'), $between('option_name', 'plugin_cache_alpha', 'plugin_cache_beta')), ['autoload', 'option_name']);
        $t->same(13, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 between sample count' => static function (TestRunner $t) use ($indexes, $and, $point, $between): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $and($point('autoload', 'yes'), $between('option_name', 'plugin_cache_alpha', 'plugin_cache_beta')), ['autoload', 'option_name']);
        $t->same(2, $plan['stat4MatchedSamples']);
    },
    'planner stat4 partial covering order current next38 matches in list samples' => static function (TestRunner $t) use ($indexes, $and, $point, $inList): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $and($point('autoload', 'yes'), $inList('option_name', ['plugin_cache_alpha', 'plugin_settings'])), ['autoload', 'option_name']);
        $t->same(17, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 in list sample count' => static function (TestRunner $t) use ($indexes, $and, $point, $inList): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $and($point('autoload', 'yes'), $inList('option_name', ['plugin_cache_alpha', 'plugin_settings'])), ['autoload', 'option_name']);
        $t->same(2, $plan['stat4MatchedSamples']);
    },
    'planner stat4 partial covering order current next38 range less than matches transient only after upper partial' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_transient_stat4', 'estimatedRows' => 2000, 'stat4Samples' => [
                ['values' => ['option_name' => '_transient_feed'], 'rows' => 8],
                ['values' => ['option_name' => 'siteurl'], 'rows' => 44],
            ], 'sql' => "CREATE INDEX idx_transient_stat4 ON wp_options(option_name, autoload) WHERE option_name < 'option_'"],
        ], $range('option_name', '<', 'option_'), ['option_name']);
        $t->same(8, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 inclusive upper range includes edge sample' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_upper_stat4', 'estimatedRows' => 2000, 'stat4Samples' => [
                ['values' => ['option_name' => 'plugin_z'], 'rows' => 12],
            ], 'sql' => "CREATE INDEX idx_upper_stat4 ON wp_options(option_name, autoload) WHERE option_name <= 'plugin_z'"],
        ], $range('option_name', '<=', 'plugin_z'), ['option_name']);
        $t->same(12, $plan['estimatedRows']);
    },
    'planner stat4 partial covering order current next38 exclusive upper range excludes edge sample' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_upper_stat4', 'estimatedRows' => 2000, 'stat4Samples' => [
                ['values' => ['option_name' => 'plugin_z'], 'rows' => 12],
            ], 'sql' => "CREATE INDEX idx_upper_stat4 ON wp_options(option_name, autoload) WHERE option_name <= 'plugin_z'"],
        ], $range('option_name', '<', 'plugin_z'), ['option_name']);
        $t->same(false, $plan['stat4Used']);
    },
    'planner stat4 partial covering order current next38 validates stat4 sample list' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::choose([
            ['name' => 'bad', 'estimatedRows' => 100, 'stat4Samples' => ['bad' => []], 'sql' => "CREATE INDEX bad ON wp_options(autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'"],
        ], $predicate(), ['autoload']));
    },
    'planner stat4 partial covering order current next38 validates stat4 sample rows' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::choose([
            ['name' => 'bad', 'estimatedRows' => 100, 'stat4Samples' => [['values' => ['autoload' => 'yes', 'option_name' => 'plugin_cache'], 'rows' => 0]], 'sql' => "CREATE INDEX bad ON wp_options(autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'"],
        ], $predicate(), ['autoload']));
    },
    'planner stat4 partial covering order current next38 validates stat4 sample values' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::choose([
            ['name' => 'bad', 'estimatedRows' => 100, 'stat4Samples' => [['values' => 'nope', 'rows' => 1]], 'sql' => "CREATE INDEX bad ON wp_options(autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'"],
        ], $predicate(), ['autoload']));
    },
    'planner stat4 partial covering order current next38 validates missing sample column' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::choose([
            ['name' => 'bad', 'estimatedRows' => 100, 'stat4Samples' => [['values' => ['autoload' => 'yes'], 'rows' => 1]], 'sql' => "CREATE INDEX bad ON wp_options(autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'"],
        ], $predicate(), ['autoload']));
    },
    'planner stat4 partial covering order current next38 validates sample column name' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::choose([
            ['name' => 'bad', 'estimatedRows' => 100, 'stat4Samples' => [['values' => [0 => 'yes', 'option_name' => 'plugin_cache'], 'rows' => 1]], 'sql' => "CREATE INDEX bad ON wp_options(autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'"],
        ], $predicate(), ['autoload']));
    },
    'planner stat4 partial covering order current next38 validates sample literal type' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::choose([
            ['name' => 'bad', 'estimatedRows' => 100, 'stat4Samples' => [['values' => ['autoload' => 'yes', 'option_name' => ['bad']], 'rows' => 1]], 'sql' => "CREATE INDEX bad ON wp_options(autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'"],
        ], $predicate(), ['autoload']));
    },
];

$rangeCases = [
    ['>=', 'plugin_cache_alpha', 24, 3],
    ['>', 'plugin_cache_alpha', 18, 2],
    ['<=', 'plugin_cache_beta', 13, 2],
    ['<', 'plugin_cache_beta', 6, 1],
];

foreach ($rangeCases as [$operator, $value, $expectedRows, $expectedSamples]) {
    $tests["planner stat4 partial covering order current next38 range {$operator} sample estimate"] = static function (TestRunner $t) use ($indexes, $and, $point, $range, $operator, $value, $expectedRows, $expectedSamples): void {
        $terms = [$point('autoload', 'yes'), $range('option_name', $operator, $value)];
        if ($operator === '<=' || $operator === '<') {
            $terms[] = $range('option_name', '>=', 'plugin_');
        }
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $and(...$terms), ['autoload', 'option_name']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same($expectedSamples, $plan['stat4MatchedSamples']);
    };
}

$orderCases = [
    'option name only' => [[['column' => 'option_name']], true],
    'option name option id desc' => [[['column' => 'option_name'], ['column' => 'option_id', 'direction' => 'DESC']], true],
    'option name option id asc' => [[['column' => 'option_name'], ['column' => 'option_id']], false],
    'autoload prefix already constrained' => [[['column' => 'autoload'], ['column' => 'option_name']], false],
    'option value gap' => [[['column' => 'option_value']], false],
];

foreach ($orderCases as $label => [$orderBy, $expected]) {
    $tests["planner stat4 partial covering order current next38 order {$label}"] = static function (TestRunner $t) use ($indexes, $predicate, $orderBy, $expected): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $predicate(), ['autoload', 'option_name', 'option_id', 'option_value'], $orderBy);
        $t->same($expected, $plan['orderBySatisfied']);
    };
}

$neededCases = [
    'autoload name' => [['autoload', 'option_name'], true],
    'autoload name id value' => [['autoload', 'option_name', 'option_id', 'option_value'], true],
    'missing site id' => [['autoload', 'site_id'], false],
    'missing blog id' => [['blog_id'], false],
];

foreach ($neededCases as $label => [$neededColumns, $expected]) {
    $tests["planner stat4 partial covering order current next38 covering {$label}"] = static function (TestRunner $t) use ($indexes, $predicate, $neededColumns, $expected): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0]], $predicate(), $neededColumns);
        $t->same($expected, $plan['covering']);
    };
}

return $tests;
