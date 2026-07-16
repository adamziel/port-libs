<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan;

$point136 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range136 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between136 = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$in136 = static fn (string $column, array $values): array => ['operator' => 'IN', 'left' => ['column' => $column], 'values' => $values];
$and136 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedRows136 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'core', 'option_name' => 'core_home', 'option_value' => 'home'],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1'],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'theme', 'option_name' => 'plugin_cache', 'option_value' => 'theme-cache'],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_forms', 'option_value' => 'a:2'],
    ['rowid' => 5, 'blog_id' => 2, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_network', 'option_value' => 'a:3'],
    ['rowid' => 6, 'blog_id' => 1, 'autoload' => 'no', 'kind' => 'plugin', 'option_name' => 'plugin_lazy', 'option_value' => 'a:4'],
];

$currentRows136 = static function () use ($preparedRows136): array {
    $rows = $preparedRows136();
    $rows[] = ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'mu-plugin', 'option_name' => 'plugin_mu', 'option_value' => 'mu'];
    $rows[] = ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_security', 'option_value' => 'a:5'];
    $rows[] = ['rowid' => 9, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_zeta', 'option_value' => 'a:6'];

    return $rows;
};

$source136 = static function (array $rows, array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-wp-options-partial-range-covering',
        'schemaCookie' => 1360,
        'stat4Generation' => 60,
        'rows' => $rows,
        'indexes' => [[
            'name' => 'idx_wp_options_plugin_kind_range_covering_partial_range_covering',
            'rootPage' => 13601,
            'estimatedRows' => 80,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
                ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
                ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_security']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_plugin_kind_range_covering_partial_range_covering ON wp_options(blog_id, autoload, option_name, option_value, kind, rowid) WHERE kind = 'plugin' AND autoload = 'yes' AND option_name >= 'plugin_'",
        ], [
            'name' => 'idx_wp_options_name_only_partial_range_covering',
            'rootPage' => 13602,
            'estimatedRows' => 300,
            'sql' => 'CREATE INDEX idx_wp_options_name_only_partial_range_covering ON wp_options(option_name)',
        ]],
    ];
};

$prepared136 = static fn (array $overrides = []) => $source136($preparedRows136(), $overrides);
$current136 = static fn (array $overrides = []) => $source136($currentRows136(), [
    'name' => 'current-wp-options-partial-range-covering',
    'schemaCookie' => 1361,
    'stat4Generation' => 61,
] + $overrides);
$predicate136 = static fn (): array => $and136(
    $point136('kind', 'plugin'),
    $point136('blog_id', 1),
    $point136('autoload', 'yes'),
    $range136('option_name', '>=', 'plugin_'),
    $range136('option_name', '<', 'plugin_z'),
);
$needed136 = ['option_name', 'option_value', 'kind', 'rowid'];
$plan136 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan::materializePartialRangeCovering(
    $prepared ?? $prepared136(),
    $current ?? $current136(),
    $predicate ?? $predicate136(),
    $needed ?? $needed136,
);

$fresh136 = static function () use ($prepared136, $plan136): array {
    $source = $prepared136();

    return $plan136($source, $source);
};
$betweenPlan136 = static fn (): array => $plan136(null, null, $and136(
    $point136('kind', 'plugin'),
    $point136('blog_id', 1),
    $point136('autoload', 'yes'),
    $between136('option_name', 'plugin_alpha', 'plugin_security'),
));
$inPlan136 = static fn (): array => $plan136(null, null, $and136(
    $point136('kind', 'plugin'),
    $point136('blog_id', 1),
    $in136('autoload', ['yes']),
    $range136('option_name', '>=', 'plugin_'),
    $range136('option_name', '<', 'plugin_z'),
));
$missingCovering136 = static fn (): array => $plan136(null, $current136([
    'indexes' => [[
        'name' => 'idx_wp_options_plugin_missing_value_partial_range_covering',
        'rootPage' => 13620,
        'estimatedRows' => 80,
        'sql' => "CREATE INDEX idx_wp_options_plugin_missing_value_partial_range_covering ON wp_options(blog_id, autoload, option_name, kind) WHERE kind = 'plugin' AND autoload = 'yes' AND option_name >= 'plugin_'",
    ]],
]));

$tests = [
    'planner partial range covering current source status ready' => static fn (TestRunner $t) => $t->same('partial-range-covering-current-source-ready', $plan136()['status']),
    'planner partial range covering current source selects current' => static fn (TestRunner $t) => $t->same('current', $plan136()['selectedSource']),
    'planner partial range covering current source stale statement' => static fn (TestRunner $t) => $t->same(true, $plan136()['stalePreparedStatement']),
    'planner partial range covering current source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan136()['reprepareRequired']),
    'planner partial range covering current source schema changed' => static fn (TestRunner $t) => $t->same(true, $plan136()['schemaCookieChanged']),
    'planner partial range covering current source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan136()['stat4GenerationChanged']),
    'planner partial range covering current source signature unchanged' => static fn (TestRunner $t) => $t->same(false, $plan136()['indexSignatureChanged']),
    'planner partial range covering current source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_plugin_kind_range_covering_partial_range_covering', $plan136()['selectedPlan']['name']),
    'planner partial range covering current source selected root' => static fn (TestRunner $t) => $t->same(13601, $plan136()['selectedPlan']['rootPage']),
    'planner partial range covering current source partial true' => static fn (TestRunner $t) => $t->same(true, $plan136()['selectedPlan']['partial']),
    'planner partial range covering current source covering true' => static fn (TestRunner $t) => $t->same(true, $plan136()['selectedPlan']['covering']),
    'planner partial range covering current source no skip scan' => static fn (TestRunner $t) => $t->same(false, $plan136()['selectedPlan']['usesSkipScan']),
    'planner partial range covering current source next source label' => static fn (TestRunner $t) => $t->same('covering-partial-index-current-source', $plan136()['selectedPlan']['nextSource']),
    'planner partial range covering current source range column' => static fn (TestRunner $t) => $t->same('option_name', $plan136()['selectedPlan']['rangeColumn']),
    'planner partial range covering current source lower bound' => static fn (TestRunner $t) => $t->same('plugin_', $plan136()['selectedPlan']['rangeLower']),
    'planner partial range covering current source upper bound' => static fn (TestRunner $t) => $t->same('plugin_z', $plan136()['selectedPlan']['rangeUpper']),
    'planner partial range covering current source lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan136()['selectedPlan']['lowerInclusive']),
    'planner partial range covering current source upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan136()['selectedPlan']['upperInclusive']),
    'planner partial range covering current source used columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name'], $plan136()['selectedPlan']['usedColumns']),
    'planner partial range covering current source equality prefix' => static fn (TestRunner $t) => $t->same(2, $plan136()['selectedPlan']['equalityPrefix']),
    'planner partial range covering current source residual required' => static fn (TestRunner $t) => $t->same(true, $plan136()['selectedPlan']['residualPredicateRequired']),
    'planner partial range covering current source predicate rechecked' => static fn (TestRunner $t) => $t->same(true, $plan136()['partialPredicateRechecked']),
    'planner partial range covering current source plan rechecked' => static fn (TestRunner $t) => $t->same(true, $plan136()['selectedPlan']['partialPredicateRechecked']),
    'planner partial range covering current source rows before predicate' => static fn (TestRunner $t) => $t->same([2, 3, 4, 7, 8], array_column($plan136()['coveredRowsBeforePredicate'], 'rowid')),
    'planner partial range covering current source filtered rowids' => static fn (TestRunner $t) => $t->same([3, 7], $plan136()['partialPredicateFilteredRowids']),
    'planner partial range covering current source filtered count' => static fn (TestRunner $t) => $t->same(2, $plan136()['predicateFilteredRowCount']),
    'planner partial range covering current source final rowids' => static fn (TestRunner $t) => $t->same([2, 4, 8], array_column($plan136()['coveredRows'], 'rowid')),
    'planner partial range covering current source final keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_forms', 'plugin_security'], array_column($plan136()['coveredRows'], 'rangeKey')),
    'planner partial range covering current source next rowid chain' => static fn (TestRunner $t) => $t->same([4, 8, null], array_column($plan136()['coveredRows'], 'nextRowid')),
    'planner partial range covering current source current next row pairs' => static fn (TestRunner $t) => $t->same([4, 8, null], array_map(static fn (array $pair): mixed => $pair['next']['rowid'] ?? null, $plan136()['currentSourceNextRows'])),
    'planner partial range covering current source payload keeps plugin kind' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_security', 'option_value' => 'a:5', 'kind' => 'plugin', 'rowid' => 8], $plan136()['coveredRows'][2]['covering']),
    'planner partial range covering current source excludes theme row payload' => static fn (TestRunner $t) => $t->same(false, in_array('theme-cache', array_column(array_column($plan136()['coveredRows'], 'covering'), 'option_value'), true)),
    'planner partial range covering current source excludes mu-plugin row payload' => static fn (TestRunner $t) => $t->same(false, in_array('mu', array_column(array_column($plan136()['coveredRows'], 'covering'), 'option_value'), true)),
    'planner partial range covering current source current fence cookie' => static fn (TestRunner $t) => $t->same(1361, $plan136()['currentSourceFence']['schemaCookie']),
    'planner partial range covering current source current fence stat4' => static fn (TestRunner $t) => $t->same(61, $plan136()['currentSourceFence']['stat4Generation']),
    'planner partial range covering current source needed signature' => static fn (TestRunner $t) => $t->same('option_name,option_value,kind,rowid', $plan136()['currentSourceFence']['neededColumnSignature']),
    'planner partial range covering current source predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan136()['currentSourceFence']['predicateSignature'])),
    'planner partial range covering current source index signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan136()['currentSourceFence']['indexSignature'])),
    'planner partial range covering current source cursor opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan136()['selectedPlan']['cursorProgram'][0]['opcode']),
    'planner partial range covering current source cursor seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan136()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner partial range covering current source cursor stop ge' => static fn (TestRunner $t) => $t->same('IdxGE', $plan136()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner partial range covering current source cursor predicate guard' => static fn (TestRunner $t) => $t->same('IfNot', $plan136()['selectedPlan']['cursorProgram'][8]['opcode']),
    'planner partial range covering current source cursor guard count' => static fn (TestRunner $t) => $t->same(2, $plan136()['selectedPlan']['cursorProgram'][8]['filteredRows']),
    'planner partial range covering current source cursor covering source' => static fn (TestRunner $t) => $t->same('covering-partial-index-current-source', $plan136()['selectedPlan']['cursorProgram'][9]['source']),
    'planner partial range covering current source detail says reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE PARTIAL RANGE COVERING CURRENT SOURCE', $plan136()['detail']),
    'planner partial range covering current source dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-partial-range-covering-current-source', $plan136()['dependencies'], true)),
    'planner partial range covering current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan136()['dependency_closure']),
    'planner partial range covering current source non overlap' => static fn (TestRunner $t) => $t->contains('filters covering current-source rows by full partial predicate terms', $plan136()['non_overlap']),
    'planner partial range covering current source fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh136()['selectedSource']),
    'planner partial range covering current source fresh not stale' => static fn (TestRunner $t) => $t->same(false, $fresh136()['stalePreparedStatement']),
    'planner partial range covering current source fresh rowids' => static fn (TestRunner $t) => $t->same([2, 4], array_column($fresh136()['coveredRows'], 'rowid')),
    'planner partial range covering current source between includes security' => static fn (TestRunner $t) => $t->same([2, 4, 8], array_column($betweenPlan136()['coveredRows'], 'rowid')),
    'planner partial range covering current source between stop gt' => static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan136()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner partial range covering current source in-list keeps plugin rows' => static fn (TestRunner $t) => $t->same([2, 4, 8], array_column($inPlan136()['coveredRows'], 'rowid')),
    'planner partial range covering current source in-list filters mu plugin' => static fn (TestRunner $t) => $t->same([3, 7], $inPlan136()['partialPredicateFilteredRowids']),
    'planner partial range covering current source missing covering next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingCovering136()['status']),
    'planner partial range covering current source missing covering next source rowid lookup' => static fn (TestRunner $t) => $t->same('table-rowid-lookup', $missingCovering136()['selectedPlan']['nextSource']),
    'planner partial range covering current source validates rows list' => static function (TestRunner $t) use ($plan136, $current136): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan136(null, $current136(['rows' => ['bad' => []]])));
    },
    'planner partial range covering current source validates row arrays' => static function (TestRunner $t) use ($plan136, $current136): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan136(null, $current136(['rows' => ['bad']])));
    },
    'planner partial range covering current source validates predicate terms' => static function (TestRunner $t) use ($plan136): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan136(null, null, ['operator' => 'AND', 'terms' => ['bad']]));
    },
];

return $tests;
