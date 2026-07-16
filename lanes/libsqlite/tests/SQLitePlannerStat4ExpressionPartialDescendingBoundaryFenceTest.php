<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq180 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull180 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between180 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared180 = static fn (): array => [
    'name' => 'prepared-wp-options-desc-stat4-expression-partial-next180',
    'schemaCookie' => 1800,
    'stat4Generation' => 70,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_partial_stat4_next180',
        'rootPage' => 18001,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current180 = static function () use ($prepared180): array {
    $source = $prepared180();
    $source['name'] = 'current-wp-options-desc-stat4-expression-partial-next180';
    $source['schemaCookie'] = 1808;
    $source['stat4Generation'] = 88;
    $source['indexes'][0]['rootPage'] = 18088;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_seo', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return $source;
};

$terms180 = static fn (): array => [
    $between180('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq180('autoload', 'yes'),
    $notNull180('option_name'),
];
$plan180 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4DescendingBoundaryFence(
    $prepared ?? $prepared180(),
    $current ?? $current180(),
    $terms ?? $terms180(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
);
$fresh180 = static function () use ($prepared180, $plan180): array {
    $source = $prepared180();
    $source['indexes'][0]['descending'] = true;

    return $plan180($source, $source);
};
$ascending180 = static function () use ($current180, $plan180): array {
    $current = $current180();
    $current['indexes'][0]['descending'] = false;

    return $plan180(null, $current);
};
$open180 = static function () use ($current180, $plan180): array {
    $current = $current180();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => ($row['rowid'] ?? null) !== 60));

    return $plan180(null, $current);
};

$tests = [
    'planner stat4 expression partial current source next180 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next180-ready', $plan180()['status']),
    'planner stat4 expression partial current source next180 selected current' => static fn (TestRunner $t) => $t->same('current', $plan180()['selectedSource']),
    'planner stat4 expression partial current source next180 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan180()['stalePreparedStatement']),
    'planner stat4 expression partial current source next180 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan180()['reprepareRequired']),
    'planner stat4 expression partial current source next180 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_desc_partial_stat4_next180', $plan180()['selectedPlan']['name']),
    'planner stat4 expression partial current source next180 root page' => static fn (TestRunner $t) => $t->same(18088, $plan180()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next180 desc flag' => static fn (TestRunner $t) => $t->same(true, $plan180()['selectedPlan']['descending']),
    'planner stat4 expression partial current source next180 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan180()['selectedPlan']['next180Ready']),
    'planner stat4 expression partial current source next180 direction' => static fn (TestRunner $t) => $t->same('DESC', $plan180()['selectedPlan']['next180ScanDirection']),
    'planner stat4 expression partial current source next180 seek opcode' => static fn (TestRunner $t) => $t->same('SeekLE', $plan180()['selectedPlan']['next180SeekOpcode']),
    'planner stat4 expression partial current source next180 step opcode' => static fn (TestRunner $t) => $t->same('Prev', $plan180()['selectedPlan']['next180StepOpcode']),
    'planner stat4 expression partial current source next180 matched rowids desc' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 40, 10], $plan180()['matchedRowids']),
    'planner stat4 expression partial current source next180 matched keys desc' => static fn (TestRunner $t) => $t->same(['plugin_zulu', 'plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_cache', 'plugin_alpha'], $plan180()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next180 selected rowids desc' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 40, 10], $plan180()['selectedPlan']['next180DescendingRowids']),
    'planner stat4 expression partial current source next180 selected keys desc' => static fn (TestRunner $t) => $t->same(['plugin_zulu', 'plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_cache', 'plugin_alpha'], $plan180()['selectedPlan']['next180DescendingKeys']),
    'planner stat4 expression partial current source next180 first payload' => static fn (TestRunner $t) => $t->same('zulu', $plan180()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next180 duplicate key stable rowid order' => static fn (TestRunner $t) => $t->same([20, 21], $plan180()['selectedPlan']['next180Segments'][3]['rowids']),
    'planner stat4 expression partial current source next180 segment keys' => static fn (TestRunner $t) => $t->same(['plugin_zulu', 'plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_cache', 'plugin_alpha'], array_column($plan180()['descendingFence']['segments'], 'key')),
    'planner stat4 expression partial current source next180 segment counts' => static fn (TestRunner $t) => $t->same([1, 1, 1, 2, 1, 1], array_column($plan180()['descendingFence']['segments'], 'count')),
    'planner stat4 expression partial current source next180 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan180()['matchedRowids'], true)),
    'planner stat4 expression partial current source next180 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan180()['matchedRowids'], true)),
    'planner stat4 expression partial current source next180 excludes null' => static fn (TestRunner $t) => $t->same(false, in_array(90, $plan180()['matchedRowids'], true)),
    'planner stat4 expression partial current source next180 lower boundary inherited' => static fn (TestRunner $t) => $t->same([10], $plan180()['betweenFence']['lowerBoundaryRowids']),
    'planner stat4 expression partial current source next180 upper boundary inherited' => static fn (TestRunner $t) => $t->same([60], $plan180()['betweenFence']['upperBoundaryRowids']),
    'planner stat4 expression partial current source next180 first key fence' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan180()['descendingFence']['firstKey']),
    'planner stat4 expression partial current source next180 last key fence' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan180()['descendingFence']['lastKey']),
    'planner stat4 expression partial current source next180 fence rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 40, 10], $plan180()['descendingFence']['rowids']),
    'planner stat4 expression partial current source next180 fence signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan180()['descendingFence']['descendingSignature'])),
    'planner stat4 expression partial current source next180 stat4 signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan180()['stat4Fence']['next180DescendingSignature'])),
    'planner stat4 expression partial current source next180 base status' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next177-ready', $plan180()['stat4Fence']['next180BaseStatus']),
    'planner stat4 expression partial current source next180 cursor seek desc' => static fn (TestRunner $t) => $t->same('SeekLE', $plan180()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next180 cursor upper guard desc' => static fn (TestRunner $t) => $t->same('IdxGE', $plan180()['cursorProgram'][2]['opcode']),
    'planner stat4 expression partial current source next180 cursor prev appended' => static fn (TestRunner $t) => $t->same('Prev', $plan180()['cursorProgram'][array_key_last($plan180()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next180 cursor prev rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 40, 10], $plan180()['cursorProgram'][array_key_last($plan180()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next180 detail' => static fn (TestRunner $t) => $t->contains('NEXT180 DESC', $plan180()['detail']),
    'planner stat4 expression partial current source next180 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next180', $plan180()['dependencies'], true)),
    'planner stat4 expression partial current source next180 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan180()['dependency_closure']),
    'planner stat4 expression partial current source next180 non overlap' => static fn (TestRunner $t) => $t->contains('descending partial expression-index', $plan180()['non_overlap']),
    'planner stat4 expression partial current source next180 fresh uses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh180()['selectedSource']),
    'planner stat4 expression partial current source next180 fresh rowids desc' => static fn (TestRunner $t) => $t->same([60, 30, 20, 10], $fresh180()['matchedRowids']),
    'planner stat4 expression partial current source next180 ascending blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $ascending180()['status']),
    'planner stat4 expression partial current source next180 ascending not ready' => static fn (TestRunner $t) => $t->same(false, $ascending180()['selectedPlan']['next180Ready']),
    'planner stat4 expression partial current source next180 missing upper boundary blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $open180()['status']),
    'planner stat4 expression partial current source next180 invalid current indexes' => static function (TestRunner $t) use ($current180, $plan180): void {
        $bad = $current180();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan180(null, $bad));
    },
    'planner stat4 expression partial current source next180 invalid index row' => static function (TestRunner $t) use ($current180, $plan180): void {
        $bad = $current180();
        $bad['indexes'][] = 'bad';
        $bad['indexes'][0]['name'] = 'missing';
        $t->throws(InvalidArgumentException::class, static fn () => $plan180(null, $bad));
    },
    'planner stat4 expression partial current source next180 invalid between' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan180(null, null, [['left' => ['expression' => 'lower(option_name)'], 'operator' => 'BETWEEN']])),
    'planner stat4 expression partial current source next180 invalid left' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan180(null, null, [['left' => ['column' => 'option_name'], 'operator' => 'BETWEEN', 'lower' => 'a', 'upper' => 'z']])),
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next180 repeated desc scan ' . $case] = static function (TestRunner $t) use ($plan180, $case): void {
        $plan = $plan180();
        $t->same(true, count($plan['matchedRowids']) >= ($case % 7));
    };
}

return $tests;
