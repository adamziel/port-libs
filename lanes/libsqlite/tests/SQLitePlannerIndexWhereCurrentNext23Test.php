<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$column = static fn (string $name): array => ['column' => $name];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$reverseRange = static fn (mixed $value, string $operator, string $column): array => ['operator' => $operator, 'left' => $value, 'right' => ['column' => $column]];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$orPredicateIndex = "CREATE INDEX idx_plugin_or_theme ON wp_options(option_name, autoload, option_value) WHERE option_name >= 'plugin_' OR option_name BETWEEN 'theme_' AND 'theme_\uffff'";

$indexes = static fn (): array => [
    [
        'name' => 'idx_name_plain',
        'rootPage' => 41,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_name_plain ON wp_options(option_name)',
    ],
    [
        'name' => 'idx_plugin_name_cover',
        'rootPage' => 42,
        'estimatedRows' => 600,
        'sql' => "CREATE INDEX idx_plugin_name_cover ON wp_options(option_name, autoload, option_value) WHERE option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_transient_name_cover',
        'rootPage' => 43,
        'estimatedRows' => 450,
        'sql' => "CREATE INDEX idx_transient_name_cover ON wp_options(option_name, autoload, option_value) WHERE option_name < 'theme_'",
    ],
    [
        'name' => 'idx_autoload_plugin_cover',
        'rootPage' => 44,
        'estimatedRows' => 750,
        'sql' => "CREATE INDEX idx_autoload_plugin_cover ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_public_nontransient_cover',
        'rootPage' => 45,
        'estimatedRows' => 900,
        'sql' => "CREATE INDEX idx_public_nontransient_cover ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes' AND option_name > '_transient_'",
    ],
    [
        'name' => 'idx_plugin_or_theme',
        'rootPage' => 46,
        'estimatedRows' => 700,
        'sql' => $orPredicateIndex,
    ],
    [
        'name' => 'idx_admin_upper',
        'rootPage' => 47,
        'estimatedRows' => 500,
        'sql' => "CREATE INDEX idx_admin_upper ON wp_options(option_name, autoload) WHERE option_name <= 'z_admin'",
    ],
];

$tests = [
    'planner index where current next23 uses partial range lower bound for covering scan' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload', 'option_value']);
        $t->same('idx_plugin_name_cover', $plan['name']);
        $t->same(true, $plan['partial']);
        $t->same('option_name', $plan['rangeColumn']);
        $t->same(true, $plan['covering']);
    },
    'planner index where current next23 rejects range that does not imply lower bound partial' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[1]], $range('option_name', '>=', 'option_'), ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 accepts strict lower bound proving inclusive partial' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[1]], $range('option_name', '>', 'plugin_'), ['option_name', 'autoload']);
        $t->same('idx_plugin_name_cover', $plan['name']);
        $t->same(150, $plan['estimatedRows']);
        $t->same(['option_name'], $plan['usedColumns']);
    },
    'planner index where current next23 rejects equal strict lower bound for strict partial' => static function (TestRunner $t) use ($range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([
            ['name' => 'idx_strict_plugin', 'estimatedRows' => 600, 'sql' => "CREATE INDEX idx_strict_plugin ON wp_options(option_name, autoload) WHERE option_name > 'plugin_'"],
        ], $range('option_name', '>=', 'plugin_'), ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 accepts higher lower bound for strict partial' => static function (TestRunner $t) use ($range): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_strict_plugin', 'estimatedRows' => 600, 'sql' => "CREATE INDEX idx_strict_plugin ON wp_options(option_name, autoload) WHERE option_name > 'plugin_'"],
        ], $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload']);
        $t->same('idx_strict_plugin', $plan['name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'planner index where current next23 uses partial upper bound for transient prefix scan' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $range('option_name', '<', 'siteurl'), ['option_name', 'autoload', 'option_value']);
        $t->same('idx_transient_name_cover', $plan['name']);
        $t->same(true, $plan['partial']);
        $t->same(113, $plan['estimatedRows']);
    },
    'planner index where current next23 rejects broad upper range that can escape partial' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[2]], $range('option_name', '<', 'zzzz'), ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 accepts inclusive upper range proving strict partial' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[2]], $range('option_name', '<=', 'settings'), ['option_name', 'autoload']);
        $t->same('idx_transient_name_cover', $plan['name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'planner index where current next23 handles reversed lower operand from constant comparison' => static function (TestRunner $t) use ($indexes, $reverseRange): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $reverseRange('plugin_', '<=', 'option_name'), ['option_name', 'autoload']);
        $t->same('idx_plugin_name_cover', $plan['name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'planner index where current next23 handles reversed upper operand from constant comparison' => static function (TestRunner $t) use ($indexes, $reverseRange): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $reverseRange('theme_', '>', 'option_name'), ['option_name', 'autoload']);
        $t->same('idx_transient_name_cover', $plan['name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'planner index where current next23 proves and-connected equality plus range partial' => static function (TestRunner $t) use ($indexes, $and, $point, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose($indexes(), $and($point('autoload', 'yes'), $range('option_name', '>=', 'plugin_cache')), ['autoload', 'option_name', 'option_value']);
        $t->same('idx_autoload_plugin_cover', $plan['name']);
        $t->same(['autoload', 'option_name'], $plan['usedColumns']);
        $t->same(15, $plan['estimatedRows']);
    },
    'planner index where current next23 rejects and partial when equality side is missing' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[3]], $range('option_name', '>=', 'plugin_cache'), ['autoload', 'option_name']);
        $t->same([], $plans);
    },
    'planner index where current next23 rejects and partial when range side is too broad' => static function (TestRunner $t) use ($indexes, $and, $point, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[3]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'option_')), ['autoload', 'option_name']);
        $t->same([], $plans);
    },
    'planner index where current next23 proves strict range inside and partial' => static function (TestRunner $t) use ($indexes, $and, $point, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[4]], $and($point('autoload', 'yes'), $range('option_name', '>', 'ajax_')), ['autoload', 'option_name']);
        $t->same('idx_public_nontransient_cover', $plan['name']);
        $t->same(true, $plan['covering']);
    },
    'planner index where current next23 rejects strict range equal to partial boundary' => static function (TestRunner $t) use ($indexes, $and, $point, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[4]], $and($point('autoload', 'yes'), $range('option_name', '>=', '_transient_')), ['autoload', 'option_name']);
        $t->same([], $plans);
    },
    'planner index where current next23 uses between range to prove lower-bound partial' => static function (TestRunner $t) use ($indexes, $between): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[1]], $between('option_name', 'plugin_cache', 'plugin_z'), ['option_name', 'autoload']);
        $t->same('idx_plugin_name_cover', $plan['name']);
        $t->same(150, $plan['estimatedRows']);
    },
    'planner index where current next23 rejects between lower bound before partial boundary' => static function (TestRunner $t) use ($indexes, $between): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[1]], $between('option_name', 'option_', 'plugin_z'), ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 uses in-list values to prove range partial when all values qualify' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[1]], ['operator' => 'IN', 'left' => ['column' => 'option_name'], 'values' => ['plugin_cache', 'plugin_theme', 'plugin_widget']], ['option_name', 'autoload']);
        $t->same('idx_plugin_name_cover', $plan['name']);
        $t->same(1, $plan['equalityPrefix']);
    },
    'planner index where current next23 rejects in-list values that include row outside range partial' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[1]], ['operator' => 'IN', 'left' => ['column' => 'option_name'], 'values' => ['plugin_cache', 'admin_email']], ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 accepts or partial through first range arm' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[5]], $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload', 'option_value']);
        $t->same('idx_plugin_or_theme', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'planner index where current next23 accepts or partial through between arm' => static function (TestRunner $t) use ($indexes, $between): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[5]], $between('option_name', 'theme_mods_', 'theme_mods_z'), ['option_name', 'autoload']);
        $t->same('idx_plugin_or_theme', $plan['name']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'planner index where current next23 rejects or partial when no arm is implied' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[5]], $range('option_name', '<', 'admin_'), ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 proves inclusive upper-bound partial' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[6]], $range('option_name', '<=', 'updates'), ['option_name', 'autoload']);
        $t->same('idx_admin_upper', $plan['name']);
        $t->same(true, $plan['covering']);
    },
    'planner index where current next23 rejects exclusive upper-bound equal to inclusive partial edge' => static function (TestRunner $t): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([
            ['name' => 'idx_lower_admin', 'estimatedRows' => 500, 'sql' => "CREATE INDEX idx_lower_admin ON wp_options(option_name, autoload) WHERE option_name < 'z_admin'"],
        ], ['operator' => '<=', 'left' => ['column' => 'option_name'], 'right' => 'z_admin'], ['option_name', 'autoload']);
        $t->same([], $plans);
    },
    'planner index where current next23 accepts tighter upper-bound for exclusive partial edge' => static function (TestRunner $t): void {
        $plan = SQLiteCoveringIndexPlan::choose([
            ['name' => 'idx_lower_admin', 'estimatedRows' => 500, 'sql' => "CREATE INDEX idx_lower_admin ON wp_options(option_name, autoload) WHERE option_name < 'z_admin'"],
        ], ['operator' => '<=', 'left' => ['column' => 'option_name'], 'right' => 'updates'], ['option_name', 'autoload']);
        $t->same('idx_lower_admin', $plan['name']);
        $t->same(125, $plan['estimatedRows']);
    },
    'planner index where current next23 keeps plain fallback when partial range is unproved' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteCoveringIndexPlan::choose([$indexes()[0], $indexes()[1]], $range('option_name', '>=', 'option_'), ['option_name']);
        $t->same('idx_name_plain', $plan['name']);
        $t->same(false, $plan['partial']);
    },
    'planner index where current next23 still ranks proved partial covering ahead of plain fallback' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$indexes()[0], $indexes()[1]], $range('option_name', '>=', 'plugin_cache'), ['option_name', 'autoload', 'option_value']);
        $t->same(['idx_plugin_name_cover', 'idx_name_plain'], array_column($plans, 'name'));
        $t->same(true, $plans[0]['covering']);
    },
    'planner index where current next23 validates scalar range literal while checking partial proof' => static function (TestRunner $t) use ($indexes): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedPlans([$indexes()[1]], ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => ['bad']], ['option_name']));
    },
];

return $tests;
