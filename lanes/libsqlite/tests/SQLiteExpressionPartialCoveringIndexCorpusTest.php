<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$tests = [];

$indexes = [
    [
        'name' => 'idx_lower_autoload_yes',
        'rootPage' => 31,
        'estimatedRows' => 240,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_lower_autoload_yes ON wp_options(lower(option_name)) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_lower_autoload_no',
        'rootPage' => 32,
        'estimatedRows' => 90,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_lower_autoload_no ON wp_options(lower(option_name)) WHERE autoload='no'",
    ],
    [
        'name' => 'idx_upper_autoload_set',
        'rootPage' => 33,
        'estimatedRows' => 120,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_upper_autoload_set ON wp_options(upper(option_name) DESC) WHERE autoload IS NOT NULL',
    ],
    [
        'name' => 'idx_length_core_names',
        'rootPage' => 34,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_length_core_names ON wp_options(length(option_name)) WHERE option_name IN ('siteurl','home','blogname')",
    ],
    [
        'name' => 'idx_length_name_band',
        'rootPage' => 35,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_length_name_band ON wp_options(length(option_name)) WHERE option_name BETWEEN \'a\' AND \'m\'',
    ],
    [
        'name' => 'idx_int_public',
        'rootPage' => 36,
        'estimatedRows' => 300,
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => "CREATE INDEX idx_int_public ON wp_options(CAST(option_value AS INTEGER)) WHERE autoload='yes' AND option_value IS NOT NULL",
    ],
    [
        'name' => 'idx_upper_blog_choice',
        'rootPage' => 37,
        'estimatedRows' => 400,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_upper_blog_choice ON wp_options(upper(option_name)) WHERE blog_id=1 OR blog_id=2',
    ],
];

$expression = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];

$cases = [
    'equality partial expression index is usable when autoload equality term matches' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'yes'],
            ],
        ],
        ['option_name', 'autoload'],
        [['column' => 'option_name']],
        'idx_lower_autoload_yes',
    ],
    'equality partial expression index is rejected when autoload term differs' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'maybe'],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'equality partial expression index can match reversed ordinary equality' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'home'],
                ['operator' => '=', 'left' => 'yes', 'right' => $column('autoload')],
            ],
        ],
        ['option_name', 'option_value'],
        [],
        'idx_lower_autoload_yes',
    ],
    'partial expression index keeps lower estimated rows when both yes and no predicates match their indexes' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_timeout'],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        'idx_lower_autoload_no',
    ],
    'is not null ordinary term unlocks upper expression partial index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '>=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN_'],
                ['operator' => '>', 'left' => $column('autoload'), 'right' => ''],
            ],
        ],
        ['option_name', 'autoload'],
        [['column' => 'option_name', 'direction' => 'DESC']],
        'idx_upper_autoload_set',
    ],
    'null ordinary value does not imply is-not-null partial predicate' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '>=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN_'],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => null],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'in-list partial expression index is usable for exact subset names' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('length', 'option_name'), 'values' => [4, 7]],
                ['operator' => 'IN', 'left' => $column('option_name'), 'values' => ['siteurl', 'home']],
            ],
        ],
        ['option_name', 'autoload'],
        [],
        'idx_length_core_names',
    ],
    'in-list partial expression index ignores nulls while proving subset names' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('length', 'option_name'), 'values' => [4, 7, null]],
                ['operator' => 'IN', 'left' => $column('option_name'), 'values' => ['home', null]],
            ],
        ],
        ['option_name'],
        [],
        'idx_length_core_names',
    ],
    'in-list partial expression index is rejected for names outside the partial set' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => $expression('length', 'option_name'), 'values' => [4, 7]],
                ['operator' => 'IN', 'left' => $column('option_name'), 'values' => ['siteurl', 'not_core']],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'between partial expression index is usable when ordinary point falls inside the band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 4, 'upper' => 9],
                ['operator' => '=', 'left' => $column('option_name'), 'right' => 'home'],
            ],
        ],
        ['option_name'],
        [],
        'idx_length_name_band',
    ],
    'between partial expression index is rejected when ordinary point is outside the band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 4, 'upper' => 9],
                ['operator' => '=', 'left' => $column('option_name'), 'right' => 'siteurl'],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'between partial expression index is usable when ordinary range tightens inside the band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 4, 'upper' => 9],
                ['operator' => 'BETWEEN', 'left' => $column('option_name'), 'lower' => 'blogname', 'upper' => 'home'],
            ],
        ],
        ['option_name'],
        [],
        'idx_length_name_band',
    ],
    'between partial expression index is rejected when ordinary range starts before the band' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'BETWEEN', 'left' => $expression('length', 'option_name'), 'lower' => 4, 'upper' => 9],
                ['operator' => 'BETWEEN', 'left' => $column('option_name'), 'lower' => '0', 'upper' => 'home'],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'and-connected partial expression index combines ordinary equality and expression non-null proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('cast_integer', 'option_value'), 'right' => 58796],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'yes'],
            ],
        ],
        ['option_value', 'autoload'],
        [],
        'idx_int_public',
    ],
    'and-connected partial expression index rejects missing ordinary equality proof' => [
        [
            'operator' => '=',
            'left' => $expression('cast_integer', 'option_value'),
            'right' => 58796,
        ],
        ['option_value'],
        [],
        null,
    ],
    'or-connected partial expression index accepts first matching ordinary equality arm' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'SITEURL'],
                ['operator' => '=', 'left' => $column('blog_id'), 'right' => 1],
            ],
        ],
        ['option_name'],
        [],
        'idx_upper_blog_choice',
    ],
    'or-connected partial expression index accepts second matching ordinary equality arm' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'HOME'],
                ['operator' => '=', 'left' => $column('blog_id'), 'right' => 2],
            ],
        ],
        ['option_name'],
        [],
        'idx_upper_blog_choice',
    ],
    'or-connected partial expression index rejects non-matching ordinary equality' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('upper', 'option_name'), 'right' => 'HOME'],
                ['operator' => '=', 'left' => $column('blog_id'), 'right' => 3],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'covering partial expression index wins over non-covering lower rows when needed columns are covered' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'yes'],
            ],
        ],
        ['option_name', 'autoload', 'option_value'],
        [['column' => 'option_name']],
        'idx_lower_autoload_yes',
    ],
    'non-covering partial expression index reports covering false when option_value is needed' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => '_transient_timeout'],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name', 'option_value'],
        [],
        'idx_lower_autoload_no',
    ],
    'descending partial expression index satisfies matching order direction' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '>=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN_'],
                ['operator' => '<=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN`'],
                ['operator' => '>', 'left' => $column('autoload'), 'right' => ''],
            ],
        ],
        ['option_name', 'autoload'],
        [['column' => 'option_name', 'direction' => 'DESC']],
        'idx_upper_autoload_set',
    ],
    'descending partial expression index does not satisfy ascending order direction' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '>=', 'left' => $expression('upper', 'option_name'), 'right' => 'PLUGIN_'],
                ['operator' => '>', 'left' => $column('autoload'), 'right' => ''],
            ],
        ],
        ['option_name', 'autoload'],
        [['column' => 'option_name', 'direction' => 'ASC']],
        'idx_upper_autoload_set',
    ],
    'partial expression index rejects wrong ordinary column proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => 'siteurl'],
                ['operator' => '=', 'left' => $column('blog_id'), 'right' => 'yes'],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'partial expression index rejects expression-only null search value' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $expression('lower', 'option_name'), 'right' => null],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'yes'],
            ],
        ],
        ['option_name'],
        [],
        null,
    ],
    'partial expression index accepts reversed ordinary range proof for is-not-null predicate' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '<', 'left' => 'z', 'right' => $expression('upper', 'option_name')],
                ['operator' => '<', 'left' => '', 'right' => $column('autoload')],
            ],
        ],
        ['option_name'],
        [],
        'idx_upper_autoload_set',
    ],
];

foreach ($cases as $name => [$predicate, $neededColumns, $orderBy, $expectedName]) {
    $tests['upstream expression partial covering index corpus ' . $name] = static function (TestRunner $t) use ($indexes, $predicate, $neededColumns, $orderBy, $expectedName): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, $orderBy, $neededColumns);
        $t->same($expectedName, $plan['name'] ?? null);
        if ($plan !== null) {
            $t->same(true, $plan['partial']);
            $t->same(true, $plan['residualPredicateRequired']);
        }
    };
}

return $tests;
