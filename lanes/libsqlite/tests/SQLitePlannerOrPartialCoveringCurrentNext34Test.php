<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expression = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];

$indexes = [
    [
        'name' => 'idx_lower_autoload_yes_cover',
        'rootPage' => 201,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_lower_autoload_yes_cover ON wp_options(lower(option_name), autoload) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_lower_autoload_no_cover',
        'rootPage' => 202,
        'estimatedRows' => 45,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_lower_autoload_no_cover ON wp_options(lower(option_name), autoload) WHERE autoload='no'",
    ],
    [
        'name' => 'idx_upper_plugin_cover',
        'rootPage' => 203,
        'estimatedRows' => 110,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_upper_plugin_cover ON wp_options(upper(option_name) DESC, autoload) WHERE option_name >= 'plugin_' AND option_name < 'plugin`'",
    ],
    [
        'name' => 'idx_length_core_cover',
        'rootPage' => 204,
        'estimatedRows' => 30,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_length_core_cover ON wp_options(length(option_name), autoload) WHERE option_name IN ('siteurl','home','blogname')",
    ],
    [
        'name' => 'idx_int_public_cover',
        'rootPage' => 205,
        'estimatedRows' => 150,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_int_public_cover ON wp_options(CAST(option_value AS INTEGER), autoload) WHERE autoload='yes' AND option_value IS NOT NULL",
    ],
    [
        'name' => 'idx_lower_autoload_no_short',
        'rootPage' => 206,
        'estimatedRows' => 5,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_lower_autoload_no_short ON wp_options(lower(option_name), autoload) WHERE autoload='no'",
    ],
];

$arm = static fn (string $autoload, string $name): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => $name],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => $autoload],
    ],
];
$upperPluginArm = static fn (string $lower, string $upper, string $value): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => 'BETWEEN', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'lower' => strtoupper($lower), 'upper' => strtoupper($upper)],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
        ['operator' => '<', 'left' => ['column' => 'option_name'], 'right' => 'plugin`'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => $value],
    ],
];
$lengthArm = static fn (array $names): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => 'IN', 'left' => ['function' => 'length', 'column' => 'option_name'], 'values' => [4, 7, 8]],
        ['operator' => 'IN', 'left' => ['column' => 'option_name'], 'values' => $names],
    ],
];
$intArm = static fn (int $value): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['function' => 'cast_integer', 'column' => 'option_value'], 'right' => $value],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];

$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];
$needed = ['option_name', 'autoload', 'option_value'];
$order = [['column' => 'autoload'], ['column' => 'option_name']];

$plan = static fn (array $predicate, array $neededColumns = null, array $orderBy = null): ?array => SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan(
    $indexes,
    $predicate,
    $orderBy ?? $order,
    $neededColumns ?? $needed,
);

$tests = [
    'planner or partial covering current next34 builds two arm plan' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same('or-partial-covering', $p['type']);
    },
    'planner or partial covering current next34 marks partial' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(true, $p['partial']);
    },
    'planner or partial covering current next34 marks covering' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(true, $p['covering']);
    },
    'planner or partial covering current next34 records arm count' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(2, $p['armCount']);
    },
    'planner or partial covering current next34 records sorted index names' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(['idx_lower_autoload_no_cover', 'idx_lower_autoload_yes_cover'], $p['indexNames']);
    },
    'planner or partial covering current next34 detects multi index union' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(false, $p['usesSingleIndex']);
    },
    'planner or partial covering current next34 requires rowid dedupe' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(true, $p['dedupeRowidsRequired']);
    },
    'planner or partial covering current next34 sums estimated rows' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(2, $p['estimatedRows']);
    },
    'planner or partial covering current next34 applies union seek cost penalty' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $armCost = array_sum(array_column($p['arms'], 'estimatedCost'));
        $t->same($armCost + 4, $p['estimatedCost']);
    },
    'planner or partial covering current next34 preserves arm positions' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same([0, 1], array_column($p['arms'], 'position'));
    },
    'planner or partial covering current next34 first arm uses autoload yes index' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same('idx_lower_autoload_yes_cover', $p['arms'][0]['name']);
    },
    'planner or partial covering current next34 second arm uses autoload no index' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same('idx_lower_autoload_no_cover', $p['arms'][1]['name']);
    },
    'planner or partial covering current next34 records first root page' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(201, $p['arms'][0]['rootPage']);
    },
    'planner or partial covering current next34 records second root page' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(202, $p['arms'][1]['rootPage']);
    },
    'planner or partial covering current next34 preserves first arm value' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same('siteurl', $p['arms'][0]['values']);
    },
    'planner or partial covering current next34 preserves second arm value' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same('_transient_feed', $p['arms'][1]['values']);
    },
    'planner or partial covering current next34 keeps trailing autoload column' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')));
        $t->same(['autoload'], $p['arms'][0]['trailingColumns']);
    },
    'planner or partial covering current next34 rejects missing partial proof arm' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $t->same(null, $plan($or($arm('yes', 'siteurl'), $arm('maybe', 'siteurl'))));
    },
    'planner or partial covering current next34 rejects non covering chosen arm' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $t->same(null, $plan($or($arm('yes', 'siteurl'), $arm('no', '_transient_feed')), ['option_name', 'autoload', 'option_value', 'blog_id']));
    },
    'planner or partial covering current next34 accepts shorter covering projection' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('no', '_transient_feed'), $arm('no', '_transient_timeout_feed')), ['option_name', 'autoload']);
        $t->same(true, $p['usesSingleIndex']);
    },
    'planner or partial covering current next34 single index still dedupes rowids' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('no', '_transient_feed'), $arm('no', '_transient_timeout_feed')), ['option_name', 'autoload']);
        $t->same(true, $p['dedupeRowidsRequired']);
    },
    'planner or partial covering current next34 single index name list has one item' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('no', '_transient_feed'), $arm('no', '_transient_timeout_feed')), ['option_name', 'autoload']);
        $t->same(['idx_lower_autoload_no_cover'], $p['indexNames']);
    },
    'planner or partial covering current next34 keeps deterministic tie-break for shorter projection' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $p = $plan($or($arm('no', '_transient_feed'), $arm('no', '_transient_timeout_feed')), ['option_name', 'autoload']);
        $t->same([202, 202], array_column($p['arms'], 'rootPage'));
    },
    'planner or partial covering current next34 rejects non or predicate' => static function (TestRunner $t) use ($plan, $arm): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan($arm('yes', 'siteurl')));
    },
    'planner or partial covering current next34 rejects empty or predicate' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan(['operator' => 'OR', 'terms' => []]));
    },
    'planner or partial covering current next34 rejects malformed arm' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan(['operator' => 'OR', 'terms' => ['bad']]));
    },
    'planner or partial covering current next34 plans upper plugin arm' => static function (TestRunner $t) use ($plan, $or, $arm, $upperPluginArm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $upperPluginArm('plugin_alpha', 'plugin_beta', 'yes')));
        $t->same('idx_upper_plugin_cover', $p['arms'][1]['name']);
    },
    'planner or partial covering current next34 upper plugin arm keeps between operator' => static function (TestRunner $t) use ($plan, $or, $arm, $upperPluginArm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $upperPluginArm('plugin_alpha', 'plugin_beta', 'yes')));
        $t->same('BETWEEN', $p['arms'][1]['operator']);
    },
    'planner or partial covering current next34 rejects upper plugin arm outside partial range' => static function (TestRunner $t) use ($plan, $or, $arm, $upperPluginArm): void {
        $unsafe = $upperPluginArm('plugin_alpha', 'plugin_beta', 'yes');
        array_pop($unsafe['terms']);
        array_pop($unsafe['terms']);
        $t->same(null, $plan($or($arm('yes', 'siteurl'), $unsafe)));
    },
    'planner or partial covering current next34 plans length in-list arm' => static function (TestRunner $t) use ($plan, $or, $arm, $lengthArm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $lengthArm(['siteurl', 'home'])));
        $t->same('idx_length_core_cover', $p['arms'][1]['name']);
    },
    'planner or partial covering current next34 length arm keeps in operator' => static function (TestRunner $t) use ($plan, $or, $arm, $lengthArm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $lengthArm(['siteurl', 'home'])));
        $t->same('IN', $p['arms'][1]['operator']);
    },
    'planner or partial covering current next34 rejects length arm with outside name' => static function (TestRunner $t) use ($plan, $or, $arm, $lengthArm): void {
        $t->same(null, $plan($or($arm('yes', 'siteurl'), $lengthArm(['siteurl', 'not_core']))));
    },
    'planner or partial covering current next34 plans integer cast arm' => static function (TestRunner $t) use ($plan, $or, $arm, $intArm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $intArm(42)));
        $t->same('idx_int_public_cover', $p['arms'][1]['name']);
    },
    'planner or partial covering current next34 integer cast arm keeps point operator' => static function (TestRunner $t) use ($plan, $or, $arm, $intArm): void {
        $p = $plan($or($arm('yes', 'siteurl'), $intArm(42)));
        $t->same('point', $p['arms'][1]['operator']);
    },
    'planner or partial covering current next34 rejects integer cast arm with text value' => static function (TestRunner $t) use ($plan, $or, $arm): void {
        $bad = [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => ['function' => 'cast_integer', 'column' => 'option_value'], 'right' => '42'],
                ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
            ],
        ];
        $t->same(null, $plan($or($arm('yes', 'siteurl'), $bad)));
    },
];

$autoloadCases = [
    ['yes', 'siteurl', 'idx_lower_autoload_yes_cover', 201],
    ['yes', 'home', 'idx_lower_autoload_yes_cover', 201],
    ['yes', 'blogname', 'idx_lower_autoload_yes_cover', 201],
    ['yes', 'rss_use_excerpt', 'idx_lower_autoload_yes_cover', 201],
    ['yes', 'widget_text', 'idx_lower_autoload_yes_cover', 201],
    ['yes', 'rewrite_rules', 'idx_lower_autoload_yes_cover', 201],
    ['no', '_transient_feed', 'idx_lower_autoload_no_cover', 202],
    ['no', '_transient_timeout_feed', 'idx_lower_autoload_no_cover', 202],
    ['no', '_site_transient_update_plugins', 'idx_lower_autoload_no_cover', 202],
    ['no', '_site_transient_timeout_update_plugins', 'idx_lower_autoload_no_cover', 202],
    ['no', 'recently_edited', 'idx_lower_autoload_no_cover', 202],
    ['no', 'uninstall_plugins', 'idx_lower_autoload_no_cover', 202],
];

foreach ($autoloadCases as $i => [$autoload, $name, $expectedIndex, $expectedRoot]) {
    $tests["planner or partial covering current next34 autoload arm {$i} chooses partial covering index"] = static function (TestRunner $t) use ($plan, $or, $arm, $autoload, $name, $expectedIndex): void {
        $p = $plan($or($arm($autoload, $name), $arm('yes', 'siteurl')));
        $t->same($expectedIndex, $p['arms'][0]['name']);
    };
    $tests["planner or partial covering current next34 autoload arm {$i} records root page"] = static function (TestRunner $t) use ($plan, $or, $arm, $autoload, $name, $expectedRoot): void {
        $p = $plan($or($arm($autoload, $name), $arm('yes', 'siteurl')));
        $t->same($expectedRoot, $p['arms'][0]['rootPage']);
    };
}

$mixedCases = [
    [$arm('yes', 'siteurl'), $lengthArm(['home']), ['idx_length_core_cover', 'idx_lower_autoload_yes_cover']],
    [$arm('no', '_transient_feed'), $upperPluginArm('plugin_alpha', 'plugin_gamma', 'no'), ['idx_lower_autoload_no_cover', 'idx_upper_plugin_cover']],
    [$intArm(7), $lengthArm(['blogname']), ['idx_int_public_cover', 'idx_length_core_cover']],
    [$upperPluginArm('plugin_cache', 'plugin_feed', 'yes'), $intArm(100), ['idx_int_public_cover', 'idx_upper_plugin_cover']],
    [$lengthArm(['siteurl', 'home', 'blogname']), $arm('yes', 'rewrite_rules'), ['idx_length_core_cover', 'idx_lower_autoload_yes_cover']],
];

foreach ($mixedCases as $i => [$left, $right, $expectedNames]) {
    $tests["planner or partial covering current next34 mixed arm {$i} records unique indexes"] = static function (TestRunner $t) use ($plan, $or, $left, $right, $expectedNames): void {
        $p = $plan($or($left, $right));
        $t->same($expectedNames, $p['indexNames']);
    };
    $tests["planner or partial covering current next34 mixed arm {$i} has two arms"] = static function (TestRunner $t) use ($plan, $or, $left, $right): void {
        $p = $plan($or($left, $right));
        $t->same(2, count($p['arms']));
    };
}

return $tests;
