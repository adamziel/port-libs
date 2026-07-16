<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$jsonExpr = static fn (string $function, string $column, string $path): array => ['function' => $function, 'column' => $column, 'path' => $path];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lowerName = $expr('lower', 'option_name');
$lengthValue = $expr('length', 'option_value');
$jsonKind = $jsonExpr('json_extract', 'option_value', '$.kind');

$indexes = static fn (): array => [
    [
        'name' => 'wp_options_lower_name_autoload',
        'rootPage' => 7401,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
        'stat4Samples' => [
            ['neq' => '8 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '9 3', 'nlt' => '8 2', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 'yes']],
            ['neq' => '7 2', 'nlt' => '17 5', 'ndlt' => '2 2', 'sample' => ['plugin_beta', 'yes']],
            ['neq' => '6 2', 'nlt' => '24 7', 'ndlt' => '3 3', 'sample' => ['plugin_zeta', 'no']],
            ['neq' => '11 4', 'nlt' => '30 9', 'ndlt' => '4 4', 'sample' => ['theme_mods_twentysix', 'yes']],
            ['neq' => '13 5', 'nlt' => '41 13', 'ndlt' => '5 5', 'sample' => ['transient_feed', 'no']],
        ],
        'sql' => "CREATE INDEX wp_options_lower_name_autoload ON wp_options(lower(option_name), autoload, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'wp_options_lower_name_plain',
        'rootPage' => 7402,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => 5, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['active_plugins']],
            ['neq' => 12, 'nlt' => 5, 'ndlt' => 1, 'sample' => ['plugin_alpha']],
            ['neq' => 10, 'nlt' => 17, 'ndlt' => 2, 'sample' => ['plugin_beta']],
            ['neq' => 9, 'nlt' => 27, 'ndlt' => 3, 'sample' => ['theme_mods_twentysix']],
        ],
        'sql' => 'CREATE INDEX wp_options_lower_name_plain ON wp_options(lower(option_name))',
    ],
    [
        'name' => 'wp_options_value_length_autoload',
        'rootPage' => 7403,
        'estimatedRows' => 60,
        'coveringColumns' => ['autoload', 'option_value'],
        'stat4Samples' => [
            ['neq' => [4, 2], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [4, 'yes']],
            ['neq' => [6, 2], 'nlt' => [4, 2], 'ndlt' => [1, 1], 'sample' => [8, 'yes']],
            ['neq' => [10, 3], 'nlt' => [10, 4], 'ndlt' => [2, 2], 'sample' => [12, 'no']],
            ['neq' => [3, 1], 'nlt' => [20, 7], 'ndlt' => [3, 3], 'sample' => [18, 'no']],
        ],
        'sql' => 'CREATE INDEX wp_options_value_length_autoload ON wp_options(length(option_value), autoload DESC)',
    ],
    [
        'name' => 'wp_options_json_kind_autoload',
        'rootPage' => 7404,
        'estimatedRows' => 70,
        'coveringColumns' => ['autoload', 'option_value'],
        'stat4Samples' => [
            ['neq' => 4, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['core']],
            ['neq' => 9, 'nlt' => 4, 'ndlt' => 1, 'sample' => ['plugin']],
            ['neq' => 5, 'nlt' => 13, 'ndlt' => 2, 'sample' => ['theme']],
        ],
        'sql' => "CREATE INDEX wp_options_json_kind_autoload ON wp_options(json_extract(option_value, '$.kind'), autoload)",
    ],
];

$bounded = static fn (array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): ?array => SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
    $indexes(),
    $predicate,
    $orderBy,
    $neededColumns,
    $neededExpressions,
);

$pluginWindowPredicate = $and(
    $point('autoload', 'yes'),
    $range($lowerName, '>=', 'plugin_'),
    $range($lowerName, '<', 'theme_')
);

$tests = [
    'planner range cost rework current next74 keeps legacy lowest-cost operator single-sided' => static function (TestRunner $t) use ($indexes, $pluginWindowPredicate): void {
        $legacy = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
        $t->same('range-<', $legacy['operator']);
    },
    'planner range cost rework current next74 keeps legacy lowest-cost rows stable' => static function (TestRunner $t) use ($indexes, $pluginWindowPredicate): void {
        $legacy = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
        $t->same(30, $legacy['estimatedRows']);
    },
    'planner range cost rework current next74 chooses bounded operator explicitly' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same('range-bounded', $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['operator']);
    },
    'planner range cost rework current next74 estimates bounded stat4 rows' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(22, $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['estimatedRows']);
    },
    'planner range cost rework current next74 reports stat4 matched sample count' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(3, $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['stat4MatchedSamples']);
    },
    'planner range cost rework current next74 reports first matched current key' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same('plugin_alpha', $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['stat4MatchedCurrentNext'][0]['current']['key']);
    },
    'planner range cost rework current next74 reports matched next key' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same('plugin_beta', $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['stat4MatchedCurrentNext'][0]['next']['key']);
    },
    'planner range cost rework current next74 keeps global stat4 current key order' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same('active_plugins', $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['stat4CurrentNext'][0]['current']['key']);
    },
    'planner range cost rework current next74 satisfies expression order' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload'])['orderBySatisfied']);
    },
    'planner range cost rework current next74 satisfies expression tail order' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']], ['autoload'])['orderBySatisfied']);
    },
    'planner range cost rework current next74 rejects wrong tail direction' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(false, $bounded($pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload', 'direction' => 'DESC']], ['autoload'])['orderBySatisfied']);
    },
    'planner range cost rework current next74 marks covering columns' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate, [], ['autoload', 'option_value'])['covering']);
    },
    'planner range cost rework current next74 marks covering expression' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate, $lowerName): void {
        $t->same(['lower(option_name)'], $bounded($pluginWindowPredicate, [], ['autoload'], [$lowerName])['coveringExpressions']);
    },
    'planner range cost rework current next74 records partial proof' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate, [], ['autoload'])['partial']);
    },
    'planner range cost rework current next74 records residual guard' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate, [], ['autoload'])['residualPredicateRequired']);
    },
    'planner range cost rework current next74 exposes legacy unaffected marker' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate, [], ['autoload'])['legacyPlansUnaffected']);
    },
    'planner range cost rework current next74 keeps lower inclusive flag' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(true, $bounded($pluginWindowPredicate)['values']['lowerInclusive']);
    },
    'planner range cost rework current next74 keeps upper exclusive flag' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same(false, $bounded($pluginWindowPredicate)['values']['upperInclusive']);
    },
    'planner range cost rework current next74 keeps lower bound value' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same('plugin_', $bounded($pluginWindowPredicate)['values']['lower']);
    },
    'planner range cost rework current next74 keeps upper bound value' => static function (TestRunner $t) use ($bounded, $pluginWindowPredicate): void {
        $t->same('theme_', $bounded($pluginWindowPredicate)['values']['upper']);
    },
];

$rangeCases = [
    'strict lower excludes plugin alpha' => [$and($point('autoload', 'yes'), $range($lowerName, '>', 'plugin_alpha'), $range($lowerName, '<', 'theme_')), 10, 'plugin_beta'],
    'inclusive upper includes theme sample' => [$and($range($lowerName, '>=', 'plugin_beta'), $range($lowerName, '<=', 'theme_mods_twentysix')), 19, 'plugin_beta'],
    'plain index plugin window' => [$and($range($lowerName, '>=', 'plugin_'), $range($lowerName, '<', 'theme_')), 22, 'plugin_alpha'],
    'length bounded rows' => [$and($range($lengthValue, '>=', 5), $range($lengthValue, '<=', 12)), 16, 8],
    'length strict bounded rows' => [$and($range($lengthValue, '>', 4), $range($lengthValue, '<', 18)), 16, 8],
    'json text bounded rows' => [$and($range($jsonKind, '>=', 'plugin'), $range($jsonKind, '<=', 'theme')), 14, 'plugin'],
];

foreach ($rangeCases as $label => [$predicate, $expectedRows, $expectedFirstKey]) {
    $tests["planner range cost rework current next74 {$label} estimated rows"] = static function (TestRunner $t) use ($bounded, $predicate, $expectedRows): void {
        $t->same($expectedRows, $bounded($predicate)['estimatedRows']);
    };
    $tests["planner range cost rework current next74 {$label} first matched key"] = static function (TestRunner $t) use ($bounded, $predicate, $expectedFirstKey): void {
        $t->same($expectedFirstKey, $bounded($predicate)['stat4MatchedCurrentNext'][0]['current']['key']);
    };
}

$rejectionCases = [
    'no upper bound' => $and($point('autoload', 'yes'), $range($lowerName, '>=', 'plugin_')),
    'no lower bound' => $and($range($lowerName, '<', 'theme_')),
    'mismatched expression type' => $and($range($lowerName, '>=', 'plugin_'), $range($expr('upper', 'option_name'), '<', 'THEME_')),
    'mismatched json path' => $and($range($jsonKind, '>=', 'plugin'), $range($jsonExpr('json_extract', 'option_value', '$.missing'), '<', 'theme')),
    'reversed impossible bounds' => $and($range($lowerName, '>=', 'theme_'), $range($lowerName, '<', 'plugin_')),
];

foreach ($rejectionCases as $label => $predicate) {
    $tests["planner range cost rework current next74 rejects {$label}"] = static function (TestRunner $t) use ($bounded, $predicate): void {
        $t->same(null, $bounded($predicate, [], ['autoload']));
    };
}

$tests['planner range cost rework current next74 rejects partial index missing ordinary proof'] = static function (TestRunner $t) use ($indexes, $and, $range, $lowerName): void {
    $t->same(null, SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
        [$indexes()[0]],
        $and($range($lowerName, '>=', 'plugin_'), $range($lowerName, '<', 'theme_')),
        [],
        ['autoload']
    ));
};

$ranked = static fn (): array => SQLiteSelectExpressionIndexPlan::boundedRangePlans($indexes(), $pluginWindowPredicate, [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
$rankMetadata = [
    'ranked plan count' => [static fn (array $plans): int => count($plans), 2],
    'ranked first name' => [static fn (array $plans): ?string => $plans[0]['name'] ?? null, 'wp_options_lower_name_autoload'],
    'ranked first root page' => [static fn (array $plans): ?int => $plans[0]['rootPage'] ?? null, 7401],
    'ranked first type' => [static fn (array $plans): string => $plans[0]['type'], 'lower'],
    'ranked first column' => [static fn (array $plans): string => $plans[0]['column'], 'option_name'],
    'ranked trailing column count' => [static fn (array $plans): int => count($plans[0]['trailingColumns']), 2],
    'ranked cost' => [static fn (array $plans): int => $plans[0]['estimatedCost'], 22],
    'ranked stat4 estimate' => [static fn (array $plans): int => $plans[0]['stat4Estimate'], 22],
    'ranked stat4 used' => [static fn (array $plans): bool => $plans[0]['stat4Used'], true],
    'ranked collation' => [static fn (array $plans): string => $plans[0]['collation'], 'BINARY'],
    'ranked descending' => [static fn (array $plans): bool => $plans[0]['descending'], false],
    'ranked second name' => [static fn (array $plans): ?string => $plans[1]['name'] ?? null, 'wp_options_lower_name_plain'],
];

foreach ($rankMetadata as $label => [$reader, $expected]) {
    $tests["planner range cost rework current next74 {$label}"] = static function (TestRunner $t) use ($ranked, $reader, $expected): void {
        $t->same($expected, $reader($ranked()));
    };
}

$tests['planner range cost rework current next74 validates bad stat4 samples'] = static function (TestRunner $t) use ($pluginWindowPredicate): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([[
        'name' => 'bad_stat4',
        'estimatedRows' => 10,
        'stat4Samples' => [['neq' => 0, 'nlt' => 0, 'sample' => ['plugin_alpha']]],
        'sql' => "CREATE INDEX bad_stat4 ON wp_options(lower(option_name))",
    ]], $pluginWindowPredicate));
};

return $tests;
