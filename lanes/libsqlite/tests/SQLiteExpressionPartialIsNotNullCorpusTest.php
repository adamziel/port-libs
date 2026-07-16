<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expression = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];

$indexes = [
    [
        'name' => 'idx_lower_autoload_present',
        'rootPage' => 41,
        'estimatedRows' => 900,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_lower_autoload_present ON wp_options(lower(option_name)) WHERE autoload IS NOT NULL',
    ],
    [
        'name' => 'idx_upper_blog_present',
        'rootPage' => 42,
        'estimatedRows' => 350,
        'coveringColumns' => ['option_name', 'blog_id'],
        'sql' => 'CREATE INDEX idx_upper_blog_present ON wp_options(upper(option_name) DESC) WHERE blog_id IS NOT NULL',
    ],
    [
        'name' => 'idx_length_locale_present',
        'rootPage' => 43,
        'estimatedRows' => 1200,
        'coveringColumns' => ['option_name', 'locale'],
        'sql' => 'CREATE INDEX idx_length_locale_present ON wp_options(length(option_name)) WHERE locale IS NOT NULL',
    ],
    [
        'name' => 'idx_int_site_present',
        'rootPage' => 44,
        'estimatedRows' => 700,
        'coveringColumns' => ['option_value', 'site_id'],
        'sql' => 'CREATE INDEX idx_int_site_present ON wp_options(CAST(option_value AS INTEGER)) WHERE site_id IS NOT NULL',
    ],
    [
        'name' => 'idx_lower_autoload_or_site',
        'rootPage' => 45,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'site_id'],
        'sql' => 'CREATE INDEX idx_lower_autoload_or_site ON wp_options(lower(option_name)) WHERE autoload IS NOT NULL OR site_id=1',
    ],
    [
        'name' => 'idx_lower_autoload_and_value',
        'rootPage' => 46,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_lower_autoload_and_value ON wp_options(lower(option_name)) WHERE autoload IS NOT NULL AND option_value IS NOT NULL',
    ],
];

$cases = [
    'explicit ordinary is not null term unlocks lower expression partial index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'ordinary is not null term unlocks descending upper expression index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '>=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN_'],
                ['operator' => 'IS NOT NULL', 'left' => $column('blog_id')],
            ],
        ],
        'idx_upper_blog_present',
        42,
        false,
        true,
    ],
    'ordinary is not null term unlocks length expression range index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 4, 'upper' => 12],
                ['operator' => 'IS NOT NULL', 'left' => $column('locale')],
            ],
        ],
        'idx_length_locale_present',
        43,
        false,
        false,
    ],
    'ordinary is not null term unlocks integer cast expression index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('cast_integer', 'option_value'), 'values' => [1, 2, 3]],
                ['operator' => 'IS NOT NULL', 'left' => $column('site_id')],
            ],
        ],
        'idx_int_site_present',
        44,
        false,
        false,
    ],
    'and-connected partial expression index needs every is not null proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'home'],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
                ['operator' => 'IS NOT NULL', 'left' => $column('option_value')],
            ],
        ],
        'idx_lower_autoload_and_value',
        46,
        true,
        false,
    ],
    'or-connected partial expression index accepts is not null arm' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'blogname'],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'or-connected partial expression index still accepts equality arm' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'blogname'],
                ['operator' => '=', 'left' => $column('site_id'), 'right' => 1],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'wrong ordinary is not null column does not prove partial predicate' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => 'IS NOT NULL', 'left' => $column('missing_column')],
            ],
        ],
        null,
        null,
        false,
        false,
    ],
    'missing second is not null term rejects and-connected partial index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'home'],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'is null term does not prove is not null partial predicate' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => 'IS NULL', 'left' => $column('autoload')],
            ],
        ],
        null,
        null,
        false,
        false,
    ],
    'function operand is not accepted as ordinary is not null proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => 'IS NOT NULL', 'left' => $expression('lower', 'autoload')],
            ],
        ],
        null,
        null,
        false,
        false,
    ],
    'nested and ordinary is not null proof is flattened for partial expression index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'AND', 'terms' => [
                    ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                    ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
                ]],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'lower expression in-list can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('lower', 'option_name'), 'values' => ['siteurl', 'home', null]],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'lower expression between can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('lower', 'option_name'), 'lower' => 'a', 'upper' => 'z'],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'reversed lower expression equality can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => 'home', 'right' => $expression('lower', 'option_name')],
                ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            ],
        ],
        'idx_lower_autoload_or_site',
        45,
        true,
        false,
    ],
    'upper expression upper bound can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '<=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN`'],
                ['operator' => 'IS NOT NULL', 'left' => $column('blog_id')],
            ],
        ],
        'idx_upper_blog_present',
        42,
        false,
        true,
    ],
    'reversed upper expression range can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '<', 'left' => 'PLUGIN_', 'right' => $expression('upper', 'option_name')],
                ['operator' => 'IS NOT NULL', 'left' => $column('blog_id')],
            ],
        ],
        'idx_upper_blog_present',
        42,
        false,
        true,
    ],
    'length expression in-list ignores null search values with explicit partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('length', 'option_name'), 'values' => [4, 7, null]],
                ['operator' => 'IS NOT NULL', 'left' => $column('locale')],
            ],
        ],
        'idx_length_locale_present',
        43,
        false,
        false,
    ],
    'length expression point can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('length', 'option_name'), 'right' => 7],
                ['operator' => 'IS NOT NULL', 'left' => $column('locale')],
            ],
        ],
        'idx_length_locale_present',
        43,
        false,
        false,
    ],
    'integer expression between can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('cast_integer', 'option_value'), 'lower' => 1, 'upper' => 60000],
                ['operator' => 'IS NOT NULL', 'left' => $column('site_id')],
            ],
        ],
        'idx_int_site_present',
        44,
        false,
        false,
    ],
    'reversed integer expression range can use explicit is not null partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '<=', 'left' => 60000, 'right' => $expression('cast_integer', 'option_value')],
                ['operator' => 'IS NOT NULL', 'left' => $column('site_id')],
            ],
        ],
        'idx_int_site_present',
        44,
        false,
        false,
    ],
    'and-connected partial expression index rejects only one is not null proof when cheaper index is absent' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('lower', 'option_name'), 'values' => ['home']],
                ['operator' => 'IS NOT NULL', 'left' => $column('option_value')],
            ],
        ],
        null,
        null,
        false,
        false,
    ],
    'ordinary equality on a different column does not substitute for is not null proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'HOME'],
                ['operator' => '=', 'left' => $column('blog_id'), 'right' => null],
            ],
        ],
        null,
        null,
        false,
        false,
    ],
    'or-connected partial expression index rejects unrelated is not null proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'home'],
                ['operator' => 'IS NOT NULL', 'left' => $column('blog_id')],
            ],
        ],
        null,
        null,
        false,
        false,
    ],
];

$tests = [];

foreach ($cases as $name => [$predicate, $expectedName, $expectedRootPage, $expectedCovering, $expectedOrder]) {
    $tests['upstream expression partial is-not-null planner ' . $name] = static function (TestRunner $t) use ($indexes, $predicate, $expectedName, $expectedRootPage, $expectedCovering, $expectedOrder): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
            $indexes,
            $predicate,
            [['column' => 'option_name', 'direction' => 'DESC']],
            ['option_name', 'autoload']
        );

        $t->same($expectedName, $plan['name'] ?? null);
        $t->same($expectedRootPage, $plan['rootPage'] ?? null);
        if ($plan !== null) {
            $t->same(true, $plan['partial']);
            $t->same(true, $plan['residualPredicateRequired']);
            $t->same($expectedCovering, $plan['covering']);
            $t->same($expectedOrder, $plan['orderBySatisfied']);
        }
    };
}

$tests['upstream expression partial is-not-null planner rejects malformed ordinary term lists'] = static function (TestRunner $t) use ($indexes, $expression, $column): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
            ['operator' => 'IS NOT NULL', 'left' => $column('autoload')],
            'not-a-predicate',
        ],
    ]));
    $t->same(null, SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, [
        'operator' => 'IS NOT NULL',
        'left' => $column('autoload'),
    ]));
};

return $tests;
