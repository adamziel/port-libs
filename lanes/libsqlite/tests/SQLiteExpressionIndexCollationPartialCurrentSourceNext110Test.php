<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static function (string $function, string $column, ?string $collation = null): array {
    $operand = ['function' => $function, 'column' => $column];
    if ($collation !== null) {
        $operand['collation'] = $collation;
    }

    return $operand;
};
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$columnPoint = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lowerNocase = $expr('lower', 'option_name', 'NOCASE');
$lowerBinary = $expr('lower', 'option_name', 'BINARY');
$upperNocase = $expr('upper', 'option_name', 'NOCASE');

$indexes = static fn (): array => [
    [
        'name' => 'wp_options_lower_name_nocase_partial110',
        'rootPage' => 1101,
        'estimatedRows' => 120,
        'coveringColumns' => ['autoload', 'option_id', 'option_value'],
        'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name', 'collation' => 'NOCASE']],
        'stat4Samples' => [
            ['neq' => '4 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Active_Plugins', 'yes']],
            ['neq' => '7 3', 'nlt' => '4 2', 'ndlt' => '1 1', 'sample' => ['PLUGIN_ALPHA', 'yes']],
            ['neq' => '5 2', 'nlt' => '11 5', 'ndlt' => '2 2', 'sample' => ['plugin_beta', 'yes']],
            ['neq' => '6 2', 'nlt' => '16 7', 'ndlt' => '3 3', 'sample' => ['Plugin_Gamma', 'yes']],
            ['neq' => '9 4', 'nlt' => '22 9', 'ndlt' => '4 4', 'sample' => ['Theme_Mods_TwentySix', 'yes']],
            ['neq' => '11 5', 'nlt' => '31 13', 'ndlt' => '5 5', 'sample' => ['transient_feed', 'no']],
        ],
        'sql' => "CREATE INDEX wp_options_lower_name_nocase_partial110 ON wp_options(lower(option_name) COLLATE NOCASE, autoload, option_id) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'wp_options_lower_name_binary_partial110',
        'rootPage' => 1102,
        'estimatedRows' => 120,
        'coveringColumns' => ['autoload', 'option_id'],
        'stat4Samples' => [
            ['neq' => 3, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['PLUGIN_ALPHA']],
            ['neq' => 5, 'nlt' => 3, 'ndlt' => 1, 'sample' => ['plugin_beta']],
            ['neq' => 8, 'nlt' => 8, 'ndlt' => 2, 'sample' => ['theme_mods_twentysix']],
        ],
        'sql' => "CREATE INDEX wp_options_lower_name_binary_partial110 ON wp_options(lower(option_name), autoload, option_id) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'wp_options_upper_name_nocase_partial110',
        'rootPage' => 1103,
        'estimatedRows' => 90,
        'coveringColumns' => ['autoload', 'option_id'],
        'stat4Samples' => [
            ['neq' => 6, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['PLUGIN_ALPHA']],
            ['neq' => 4, 'nlt' => 6, 'ndlt' => 1, 'sample' => ['PLUGIN_BETA']],
            ['neq' => 7, 'nlt' => 10, 'ndlt' => 2, 'sample' => ['THEME_MODS']],
        ],
        'sql' => "CREATE INDEX wp_options_upper_name_nocase_partial110 ON wp_options(upper(option_name) COLLATE NOCASE, autoload, option_id) WHERE autoload = 'yes' AND upper(option_name) >= 'PLUGIN_'",
    ],
];

$choose = static fn (array $predicate, array $orderBy = [], array $neededColumns = ['autoload', 'option_id'], array $neededExpressions = []) => SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexes(),
    $predicate,
    $orderBy,
    $neededColumns,
    $neededExpressions,
);
$bounded = static fn (array $predicate, array $orderBy = [], array $neededColumns = ['autoload', 'option_id']) => SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
    $indexes(),
    $predicate,
    $orderBy,
    $neededColumns,
);

$pluginPoint = $and($columnPoint('autoload', 'yes'), $point($lowerNocase, 'PLUGIN_ALPHA'));
$pluginWindow = $and($columnPoint('autoload', 'yes'), $range($lowerNocase, '>=', 'PLUGIN_'), $range($lowerNocase, '<', 'theme_'));

$tests = [
    'expression index collation partial current source next110 chooses nocase index for collated point' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same('wp_options_lower_name_nocase_partial110', $choose($pluginPoint)['name']);
    },
    'expression index collation partial current source next110 records index collation' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same('NOCASE', $choose($pluginPoint)['collation']);
    },
    'expression index collation partial current source next110 records query collation' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same('NOCASE', $choose($pluginPoint)['queryCollation']);
    },
    'expression index collation partial current source next110 marks collation matched' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same(true, $choose($pluginPoint)['collationMatched']);
    },
    'expression index collation partial current source next110 proves partial through nocase point' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same(true, $choose($pluginPoint)['partial']);
    },
    'expression index collation partial current source next110 uses nocase stat4 equality rows' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same(7, $choose($pluginPoint)['estimatedRows']);
    },
    'expression index collation partial current source next110 finds uppercase current sample' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same('PLUGIN_ALPHA', $choose($pluginPoint)['stat4MatchedCurrentNext'][0]['current']['key']);
    },
    'expression index collation partial current source next110 finds lowercase next sample' => static function (TestRunner $t) use ($choose, $pluginPoint): void {
        $t->same(null, $choose($pluginPoint)['stat4MatchedCurrentNext'][0]['next']);
    },
    'expression index collation partial current source next110 satisfies nocase expression order' => static function (TestRunner $t) use ($choose, $pluginPoint, $lowerNocase): void {
        $t->same(true, $choose($pluginPoint, [$lowerNocase, ['column' => 'autoload']])['orderBySatisfied']);
    },
    'expression index collation partial current source next110 rejects binary order on nocase expression' => static function (TestRunner $t) use ($choose, $pluginPoint, $lowerBinary): void {
        $t->same(false, $choose($pluginPoint, [$lowerBinary])['orderBySatisfied']);
    },
    'expression index collation partial current source next110 covers collated expression' => static function (TestRunner $t) use ($choose, $pluginPoint, $lowerNocase): void {
        $t->same(['lower(option_name)'], $choose($pluginPoint, [], ['autoload'], [$lowerNocase])['coveringExpressions']);
    },
    'expression index collation partial current source next110 rejects binary covering expression' => static function (TestRunner $t) use ($choose, $pluginPoint, $lowerBinary): void {
        $t->same(false, $choose($pluginPoint, [], ['autoload'], [$lowerBinary])['covering']);
    },
    'expression index collation partial current source next110 rejects binary query collation against nocase index' => static function (TestRunner $t) use ($choose, $and, $columnPoint, $point, $lowerBinary): void {
        $t->same('wp_options_lower_name_binary_partial110', $choose($and($columnPoint('autoload', 'yes'), $point($lowerBinary, 'plugin_beta')))['name']);
    },
    'expression index collation partial current source next110 rejects missing nocase partial proof on binary index' => static function (TestRunner $t) use ($choose, $and, $columnPoint, $point, $lowerBinary): void {
        $t->same(null, $choose($and($columnPoint('autoload', 'yes'), $point($lowerBinary, 'PLUGIN_ALPHA'))));
    },
    'expression index collation partial current source next110 keeps legacy omitted collation usable' => static function (TestRunner $t) use ($choose, $and, $columnPoint, $point, $expr): void {
        $t->same('wp_options_lower_name_nocase_partial110', $choose($and($columnPoint('autoload', 'yes'), $point($expr('lower', 'option_name'), 'PLUGIN_ALPHA')))['name']);
    },
    'expression index collation partial current source next110 chooses bounded nocase range' => static function (TestRunner $t) use ($bounded, $pluginWindow): void {
        $t->same('range-bounded', $bounded($pluginWindow)['operator']);
    },
    'expression index collation partial current source next110 estimates bounded nocase rows' => static function (TestRunner $t) use ($bounded, $pluginWindow): void {
        $t->same(18, $bounded($pluginWindow)['estimatedRows']);
    },
    'expression index collation partial current source next110 keeps lower boundary current sample' => static function (TestRunner $t) use ($bounded, $pluginWindow): void {
        $t->same('Active_Plugins', $bounded($pluginWindow)['stat4RangeCurrentNext']['lower']['current']['key']);
    },
    'expression index collation partial current source next110 keeps upper boundary next sample' => static function (TestRunner $t) use ($bounded, $pluginWindow): void {
        $t->same('Theme_Mods_TwentySix', $bounded($pluginWindow)['stat4RangeCurrentNext']['upper']['next']['key']);
    },
    'expression index collation partial current source next110 bounded range satisfies order' => static function (TestRunner $t) use ($bounded, $pluginWindow, $lowerNocase): void {
        $t->same(true, $bounded($pluginWindow, [$lowerNocase])['orderBySatisfied']);
    },
    'expression index collation partial current source next110 bounded range rejects rtrim order collation' => static function (TestRunner $t) use ($bounded, $pluginWindow, $expr): void {
        $t->same(false, $bounded($pluginWindow, [$expr('lower', 'option_name', 'RTRIM')])['orderBySatisfied']);
    },
];

$pointCases = [
    'upper literal matches uppercase sample' => ['PLUGIN_ALPHA', 7, 'plugin_beta'],
    'lower literal matches uppercase sample under nocase' => ['plugin_alpha', 7, 'plugin_beta'],
    'mixed literal matches lowercase sample under nocase' => ['Plugin_Beta', 5, 'Plugin_Gamma'],
    'theme literal matches mixed sample under nocase' => ['theme_mods_twentysix', 9, 'transient_feed'],
    'missing plugin falls back to distinct estimate' => ['PLUGIN_DELTA', 20, null],
];
foreach ($pointCases as $label => [$value, $rows, $nextKey]) {
    $tests["expression index collation partial current source next110 {$label} rows"] = static function (TestRunner $t) use ($choose, $and, $columnPoint, $point, $lowerNocase, $value, $rows): void {
        $t->same($rows, $choose($and($columnPoint('autoload', 'yes'), $point($lowerNocase, $value)))['estimatedRows']);
    };
    $tests["expression index collation partial current source next110 {$label} next key"] = static function (TestRunner $t) use ($choose, $and, $columnPoint, $point, $lowerNocase, $value, $nextKey): void {
        $plan = $choose($and($columnPoint('autoload', 'yes'), $point($lowerNocase, $value)));
        $matched = array_values(array_filter($plan['stat4CurrentNext'], static fn (array $pair): bool => strcasecmp((string) $pair['current']['key'], (string) $value) === 0));
        $t->same($nextKey, $matched[0]['next']['key'] ?? null);
    };
}

$rangeCases = [
    'inclusive plugin to theme window' => [$pluginWindow, 18, 'PLUGIN_ALPHA'],
    'strict lower skips alpha' => [$and($columnPoint('autoload', 'yes'), $range($lowerNocase, '>', 'PLUGIN_ALPHA'), $range($lowerNocase, '<', 'theme_')), 11, 'plugin_beta'],
    'inclusive upper includes theme mods' => [$and($columnPoint('autoload', 'yes'), $range($lowerNocase, '>=', 'plugin_beta'), $range($lowerNocase, '<=', 'THEME_MODS_TWENTYSIX')), 20, 'plugin_beta'],
    'mixed-case lower boundary' => [$and($columnPoint('autoload', 'yes'), $range($lowerNocase, '>=', 'Plugin_Gamma'), $range($lowerNocase, '<', 'transient_')), 15, 'Plugin_Gamma'],
    'upper expression partial proof' => [$and($columnPoint('autoload', 'yes'), $range($upperNocase, '>=', 'plugin_'), $range($upperNocase, '<', 'theme_')), 10, 'PLUGIN_ALPHA'],
];
foreach ($rangeCases as $label => [$predicate, $rows, $firstMatched]) {
    $tests["expression index collation partial current source next110 range {$label} rows"] = static function (TestRunner $t) use ($bounded, $predicate, $rows): void {
        $t->same($rows, $bounded($predicate)['estimatedRows']);
    };
    $tests["expression index collation partial current source next110 range {$label} first matched"] = static function (TestRunner $t) use ($bounded, $predicate, $firstMatched): void {
        $t->same($firstMatched, $bounded($predicate)['stat4MatchedCurrentNext'][0]['current']['key']);
    };
}

$metadata = [
    'root page' => [static fn (array $p): int => $p['rootPage'], 1101],
    'type' => [static fn (array $p): string => $p['type'], 'lower'],
    'column' => [static fn (array $p): string => $p['column'], 'option_name'],
    'trailing column count' => [static fn (array $p): int => count($p['trailingColumns']), 2],
    'covering true' => [static fn (array $p): bool => $p['covering'], true],
    'stat4 used' => [static fn (array $p): bool => $p['stat4Used'], true],
    'matched sample count' => [static fn (array $p): int => $p['stat4MatchedSamples'], 1],
    'residual required' => [static fn (array $p): bool => $p['residualPredicateRequired'], true],
    'estimated cost' => [static fn (array $p): int => $p['estimatedCost'], 1],
    'descending false' => [static fn (array $p): bool => $p['descending'], false],
];
foreach ($metadata as $label => [$reader, $expected]) {
    $tests["expression index collation partial current source next110 metadata {$label}"] = static function (TestRunner $t) use ($choose, $pluginPoint, $reader, $expected): void {
        $t->same($expected, $reader($choose($pluginPoint, [['function' => 'lower', 'column' => 'option_name', 'collation' => 'NOCASE']], ['autoload', 'option_id'])));
    };
}

$rejections = [
    'collation mismatch point' => $and($columnPoint('autoload', 'yes'), $point($expr('lower', 'option_name', 'RTRIM'), 'plugin_alpha')),
    'missing autoload partial proof' => $point($lowerNocase, 'plugin_alpha'),
    'wrong expression function' => $and($columnPoint('autoload', 'yes'), $point($expr('upper', 'option_name', 'RTRIM'), 'PLUGIN_ALPHA')),
    'bounded binary cannot prove uppercase partial' => $and($columnPoint('autoload', 'yes'), $range($lowerBinary, '>=', 'PLUGIN_'), $range($lowerBinary, '<', 'theme_')),
];
foreach ($rejections as $label => $predicate) {
    $tests["expression index collation partial current source next110 rejects {$label}"] = static function (TestRunner $t) use ($choose, $bounded, $predicate): void {
        $t->same(null, $choose($predicate));
        $t->same(null, $bounded($predicate));
    };
}

$tests['expression index collation partial current source next110 rejects impossible reversed bounded nocase range'] = static function (TestRunner $t) use ($bounded, $and, $columnPoint, $range, $lowerNocase): void {
    $t->same(null, $bounded($and($columnPoint('autoload', 'yes'), $range($lowerNocase, '>=', 'theme_'), $range($lowerNocase, '<', 'PLUGIN_'))));
};

$tests['expression index collation partial current source next110 validates invalid operand collation'] = static function (TestRunner $t) use ($choose, $and, $columnPoint, $point): void {
    $t->same(null, $choose($and($columnPoint('autoload', 'yes'), $point(['function' => 'lower', 'column' => 'option_name', 'collation' => ''], 'plugin_alpha'))));
};

return $tests;
