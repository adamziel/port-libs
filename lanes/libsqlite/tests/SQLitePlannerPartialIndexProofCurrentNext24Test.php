<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expression = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];

$indexes = [
    [
        'name' => 'idx_lower_transient_band',
        'rootPage' => 81,
        'estimatedRows' => 420,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_lower_transient_band ON wp_options(lower(option_name)) WHERE option_name BETWEEN '_transient_' AND '_transient_timeout_zzzz'",
    ],
    [
        'name' => 'idx_upper_site_band',
        'rootPage' => 82,
        'estimatedRows' => 240,
        'coveringColumns' => ['option_name', 'blog_id'],
        'sql' => "CREATE INDEX idx_upper_site_band ON wp_options(upper(option_name) DESC) WHERE option_name >= 'site' AND option_name < 'sitz'",
    ],
    [
        'name' => 'idx_length_plugin_band',
        'rootPage' => 83,
        'estimatedRows' => 650,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_length_plugin_band ON wp_options(length(option_name)) WHERE option_name > 'plugin_' AND option_name <= 'plugin_zzzz'",
    ],
    [
        'name' => 'idx_int_autoload_band',
        'rootPage' => 84,
        'estimatedRows' => 500,
        'coveringColumns' => ['option_value', 'autoload', 'option_name'],
        'sql' => "CREATE INDEX idx_int_autoload_band ON wp_options(CAST(option_value AS INTEGER)) WHERE autoload BETWEEN 'a' AND 'z'",
    ],
];

$cases = [
    'lower point uses separate inclusive ordinary bounds for transient band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_transient_'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => '_transient_timeout_zzzz'],
            ],
        ],
        'idx_lower_transient_band',
        true,
        5,
        81,
    ],
    'lower point uses reversed lower ordinary bound for transient band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_timeout_feed'],
                ['operator' => '<=', 'left' => '_transient_', 'right' => $column('option_name')],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => '_transient_timeout_zzzz'],
            ],
        ],
        'idx_lower_transient_band',
        true,
        5,
        81,
    ],
    'lower point uses reversed upper ordinary bound for transient band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_timeout_feed'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_transient_'],
                ['operator' => '>=', 'left' => '_transient_timeout_zzzz', 'right' => $column('option_name')],
            ],
        ],
        'idx_lower_transient_band',
        true,
        5,
        81,
    ],
    'lower in list uses ordinary between term for transient band proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('lower', 'option_name'), 'values' => ['_transient_feed', '_transient_timeout_feed']],
                ['operator' => 'BETWEEN', 'left' => $column('option_name'), 'lower' => '_transient_', 'upper' => '_transient_timeout_zzzz'],
            ],
        ],
        'idx_lower_transient_band',
        true,
        12,
        81,
    ],
    'lower range uses tighter ordinary range for transient band proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '>=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_timeout_'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_transient_timeout_'],
                ['operator' => '<', 'left' => $column('option_name'), 'right' => '_transient_timeout_zzzy'],
            ],
        ],
        'idx_lower_transient_band',
        true,
        25,
        81,
    ],
    'upper point uses combined site bounds for descending partial index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'SITEURL'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'site'],
                ['operator' => '<', 'left' => $column('option_name'), 'right' => 'sitz'],
            ],
        ],
        'idx_upper_site_band',
        false,
        9,
        82,
    ],
    'upper range uses combined site bounds with descending order' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '<', 'left' => $expression('upper', 'option_name'), 'right' => 'SITZ'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'site'],
                ['operator' => '<', 'left' => $column('option_name'), 'right' => 'sitz'],
            ],
        ],
        'idx_upper_site_band',
        false,
        25,
        82,
    ],
    'length between uses combined plugin bounds for partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 8, 'upper' => 28],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'plugin_a'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => 'plugin_zzzz'],
            ],
        ],
        'idx_length_plugin_band',
        true,
        18,
        83,
    ],
    'integer cast point uses ordinary autoload bounds for partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('cast_integer', 'option_value'), 'right' => 42],
                ['operator' => '>=', 'left' => $column('autoload'), 'right' => 'a'],
                ['operator' => '<=', 'left' => $column('autoload'), 'right' => 'z'],
            ],
        ],
        'idx_int_autoload_band',
        true,
        5,
        84,
    ],
    'integer cast in list uses ordinary autoload between term' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('cast_integer', 'option_value'), 'values' => [1, 2, 3]],
                ['operator' => 'BETWEEN', 'left' => $column('autoload'), 'lower' => 'a', 'upper' => 'z'],
            ],
        ],
        'idx_int_autoload_band',
        true,
        12,
        84,
    ],
    'missing upper ordinary bound rejects transient partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_transient_'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
    'missing lower ordinary bound rejects transient partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => '_transient_timeout_zzzz'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
    'weaker lower bound rejects transient partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_site_'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => '_transient_timeout_zzzz'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
    'weaker upper bound rejects transient partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_transient_'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => '_transient_z'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
    'wrong ordinary column rejects transient partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
                ['operator' => '>=', 'left' => $column('autoload'), 'right' => '_transient_'],
                ['operator' => '<=', 'left' => $column('autoload'), 'right' => '_transient_timeout_zzzz'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
    'upper bound equality rejects exclusive site partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'SITEURL'],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'site'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => 'sitz'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
    'integer cast text value is still rejected after ordinary bounds prove partial' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('cast_integer', 'option_value'), 'right' => '42'],
                ['operator' => 'BETWEEN', 'left' => $column('autoload'), 'lower' => 'a', 'upper' => 'z'],
            ],
        ],
        null,
        false,
        null,
        null,
    ],
];

$tests = [];

foreach ($cases as $name => [$predicate, $expectedName, $expectedCovering, $expectedCostBase, $expectedRoot]) {
    $tests['sqlite planner partial index proof current next24 ' . $name] = static function (TestRunner $t) use ($indexes, $predicate, $expectedName, $expectedCovering, $expectedCostBase, $expectedRoot): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, [['column' => 'option_name', 'direction' => 'DESC']], ['option_name', 'autoload']);
        if ($expectedName === null) {
            $t->same(null, $plan);

            return;
        }

        $t->same($expectedName, $plan['name']);
        $t->same($expectedCovering, $plan['covering']);
        $t->same($expectedRoot, $plan['rootPage']);
        $t->same(true, $plan['partial']);
        $t->same($expectedCostBase, $plan['estimatedCost'] - $plan['estimatedRows'] + ($plan['partial'] ? 3 : 0) + ($plan['covering'] ? 5 : 0) + ($plan['orderBySatisfied'] ? 8 : 0));
    };
}

$transientValues = [
    '_transient_feed',
    '_transient_plugin',
    '_transient_timeout_feed',
    '_transient_timeout_plugin',
    '_transient_timeout_theme',
    '_transient_theme',
];

foreach ($transientValues as $index => $name) {
    $tests['sqlite planner partial index proof current next24 transient value ' . $index . ' is covered by combined bounds'] = static function (TestRunner $t) use ($indexes, $expression, $column, $name): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => $name],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => '_transient_'],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => '_transient_timeout_zzzz'],
            ],
        ], [], ['option_name', 'option_value']);

        $t->same('idx_lower_transient_band', $plan['name']);
        $t->same($name, $plan['values']);
    };
}

$outsideBounds = [
    ['0', '_transient_timeout_zzzz'],
    ['_transient_', '_transient_timeout_zzzzz'],
    ['_site_transient_', '_transient_timeout_zzzz'],
    ['_transient_', '_transient_timeout_zzzzz'],
    [null, '_transient_timeout_zzzz'],
    ['_transient_', null],
];

foreach ($outsideBounds as $index => [$lower, $upper]) {
    $tests['sqlite planner partial index proof current next24 unsafe transient bound ' . $index . ' is rejected'] = static function (TestRunner $t) use ($indexes, $expression, $column, $lower, $upper): void {
        $terms = [
            ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_feed'],
        ];
        if ($lower !== null) {
            $terms[] = ['operator' => '>=', 'left' => $column('option_name'), 'right' => $lower];
        }
        if ($upper !== null) {
            $terms[] = ['operator' => '<=', 'left' => $column('option_name'), 'right' => $upper];
        }

        $t->same(null, SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
            'operator' => 'AND',
            'terms' => $terms,
        ]));
    };
}

$siteNames = ['siteurl', 'site_admins', 'site_icon', 'site_logo', 'site_meta', 'site_public'];
foreach ($siteNames as $index => $name) {
    $tests['sqlite planner partial index proof current next24 site bound ' . $index . ' satisfies descending upper index'] = static function (TestRunner $t) use ($indexes, $expression, $column, $name): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => strtoupper($name)],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'site'],
                ['operator' => '<', 'left' => $column('option_name'), 'right' => 'sitz'],
            ],
        ], [['column' => 'option_name', 'direction' => 'DESC']], ['option_name', 'blog_id']);

        $t->same('idx_upper_site_band', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
    };
}

$unsafeSiteBounds = [
    ['sitd', 'sitz'],
    ['site', 'sitz_'],
    ['site', 'sitz'],
    [null, 'sitz'],
    ['site', null],
];

foreach ($unsafeSiteBounds as $index => [$lower, $upper]) {
    $tests['sqlite planner partial index proof current next24 unsafe site bound ' . $index . ' is rejected'] = static function (TestRunner $t) use ($indexes, $expression, $column, $lower, $upper): void {
        $terms = [
            ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'SITEURL'],
        ];
        if ($lower !== null) {
            $terms[] = ['operator' => '>=', 'left' => $column('option_name'), 'right' => $lower];
        }
        if ($upper !== null) {
            $terms[] = ['operator' => '<=', 'left' => $column('option_name'), 'right' => $upper];
        }

        $t->same(null, SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
            'operator' => 'AND',
            'terms' => $terms,
        ]));
    };
}

$pluginRanges = [
    ['plugin_a', 'plugin_z'],
    ['plugin_cache', 'plugin_feed'],
    ['plugin_option', 'plugin_zzzz'],
    ['plugin_theme', 'plugin_zip'],
    ['plugin_update', 'plugin_zzzy'],
];

foreach ($pluginRanges as $index => [$lower, $upper]) {
    $tests['sqlite planner partial index proof current next24 plugin range ' . $index . ' proves length partial index'] = static function (TestRunner $t) use ($indexes, $expression, $column, $lower, $upper): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 8, 'upper' => 24],
                ['operator' => '>=', 'left' => $column('option_name'), 'right' => $lower],
                ['operator' => '<=', 'left' => $column('option_name'), 'right' => $upper],
            ],
        ], [], ['option_name', 'autoload']);

        $t->same('idx_length_plugin_band', $plan['name']);
        $t->same('BETWEEN', $plan['operator']);
    };
}

$autoloadRanges = [
    ['a', 'z', 1],
    ['m', 'z', 7],
    ['autoload', 'yes', 42],
    ['no', 'yes', 100],
    ['site', 'z', 250],
];

foreach ($autoloadRanges as $index => [$lower, $upper, $value]) {
    $tests['sqlite planner partial index proof current next24 autoload range ' . $index . ' proves integer cast partial index'] = static function (TestRunner $t) use ($indexes, $expression, $column, $lower, $upper, $value): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('cast_integer', 'option_value'), 'right' => $value],
                ['operator' => '>=', 'left' => $column('autoload'), 'right' => $lower],
                ['operator' => '<=', 'left' => $column('autoload'), 'right' => $upper],
            ],
        ], [], ['option_value', 'autoload']);

        $t->same('idx_int_autoload_band', $plan['name']);
        $t->same($value, $plan['values']);
    };
}

$mixedPlans = [
    ['_transient_feed', 'idx_lower_transient_band'],
    ['siteurl', 'idx_upper_site_band'],
    ['plugin_cache', 'idx_length_plugin_band'],
];

foreach ($mixedPlans as $index => [$name, $expectedIndex]) {
    $tests['sqlite planner partial index proof current next24 mixed current source proof ' . $index] = static function (TestRunner $t) use ($indexes, $expression, $column, $name, $expectedIndex): void {
        $predicate = match ($expectedIndex) {
            'idx_lower_transient_band' => [
                'operator' => 'AND',
                'terms' => [
                    ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => $name],
                    ['operator' => 'BETWEEN', 'left' => $column('option_name'), 'lower' => '_transient_', 'upper' => '_transient_timeout_zzzz'],
                ],
            ],
            'idx_upper_site_band' => [
                'operator' => 'AND',
                'terms' => [
                    ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => strtoupper($name)],
                    ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'site'],
                    ['operator' => '<', 'left' => $column('option_name'), 'right' => 'sitz'],
                ],
            ],
            default => [
                'operator' => 'AND',
                'terms' => [
                    ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 8, 'upper' => 24],
                    ['operator' => '>=', 'left' => $column('option_name'), 'right' => 'plugin_a'],
                    ['operator' => '<=', 'left' => $column('option_name'), 'right' => 'plugin_zzzz'],
                ],
            ],
        };

        $t->same($expectedIndex, SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, [], ['option_name'])['name']);
    };
}

return $tests;
