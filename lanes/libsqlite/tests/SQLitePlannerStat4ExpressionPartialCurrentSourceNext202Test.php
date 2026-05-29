<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq202 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull202 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between202 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared202 = static fn (): array => [
    'name' => 'prepared-wp-options-partial-predicate-stat4-expression-next202',
    'schemaCookie' => 2020,
    'stat4Generation' => 201,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_predicate_stat4_next202',
        'rootPage' => 20201,
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

$current202 = static function () use ($prepared202): array {
    $source = $prepared202();
    $source['name'] = 'current-wp-options-partial-predicate-stat4-expression-next202';
    $source['schemaCookie'] = 2029;
    $source['stat4Generation'] = 232;
    $source['indexes'][0]['rootPage'] = 20288;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
    ];

    return $source;
};

$terms202 = static fn (): array => [
    $between202('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq202('autoload', 'yes'),
    $notNull202('option_name'),
];
$plan202 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext202(
    $prepared ?? $prepared202(),
    $current ?? $current202(),
    $terms ?? $terms202(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$autoloadDrift202 = static function () use ($current202, $plan202): array {
    $current = $current202();
    $current['indexes'][0]['partialPredicateTerms'][2]['right'] = 'on';

    return $plan202(5, 1, null, $current);
};
$rangeDrift202 = static function () use ($current202, $plan202): array {
    $current = $current202();
    $current['indexes'][0]['partialPredicateTerms'][0]['right'] = 'plugin_beta';

    return $plan202(5, 1, null, $current);
};
$nullName202 = static function () use ($current202, $plan202): array {
    $current = $current202();
    $current['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'];

    return $plan202(5, 1, null, $current);
};
$reordered202 = static function () use ($current202, $plan202): array {
    $current = $current202();
    $term = $current['indexes'][0]['partialPredicateTerms'][0];
    $current['indexes'][0]['partialPredicateTerms'][0] = $current['indexes'][0]['partialPredicateTerms'][1];
    $current['indexes'][0]['partialPredicateTerms'][1] = $term;

    return $plan202(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next202 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next202-ready', $plan202()['status']),
    'planner stat4 expression partial current source next202 selected current' => static fn (TestRunner $t) => $t->same('current', $plan202()['selectedSource']),
    'planner stat4 expression partial current source next202 inherited next196 ready' => static fn (TestRunner $t) => $t->same(true, $plan202()['selectedPlan']['next196Ready']),
    'planner stat4 expression partial current source next202 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_predicate_stat4_next202', $plan202()['selectedPlan']['name']),
    'planner stat4 expression partial current source next202 root page' => static fn (TestRunner $t) => $t->same(20288, $plan202()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next202 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan202()['selectedPlan']['next202Ready']),
    'planner stat4 expression partial current source next202 term count' => static fn (TestRunner $t) => $t->same(4, $plan202()['partialPredicateDefinitionFence']['termCount']),
    'planner stat4 expression partial current source next202 prepared term count' => static fn (TestRunner $t) => $t->same(4, $plan202()['partialPredicateDefinitionFence']['preparedTermCount']),
    'planner stat4 expression partial current source next202 current term count' => static fn (TestRunner $t) => $t->same(4, $plan202()['partialPredicateDefinitionFence']['currentTermCount']),
    'planner stat4 expression partial current source next202 selected term count' => static fn (TestRunner $t) => $t->same(4, $plan202()['selectedPlan']['next202PartialPredicateTerms']),
    'planner stat4 expression partial current source next202 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan202()['matchedRowids']),
    'planner stat4 expression partial current source next202 definition matches' => static fn (TestRunner $t) => $t->same(true, $plan202()['partialPredicateDefinitionFence']['partialPredicateDefinitionMatches']),
    'planner stat4 expression partial current source next202 changed terms empty' => static fn (TestRunner $t) => $t->same([], $plan202()['partialPredicateDefinitionFence']['changedTerms']),
    'planner stat4 expression partial current source next202 selected changed terms empty' => static fn (TestRunner $t) => $t->same([], $plan202()['selectedPlan']['next202ChangedTerms']),
    'planner stat4 expression partial current source next202 term check count' => static fn (TestRunner $t) => $t->same(4, count($plan202()['partialPredicateDefinitionFence']['termChecks'])),
    'planner stat4 expression partial current source next202 first term matches' => static fn (TestRunner $t) => $t->same(true, $plan202()['partialPredicateDefinitionFence']['termChecks'][0]['matches']),
    'planner stat4 expression partial current source next202 first term left kind' => static fn (TestRunner $t) => $t->same('expression', $plan202()['partialPredicateDefinitionFence']['preparedTerms'][0]['leftKind']),
    'planner stat4 expression partial current source next202 first term left normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan202()['partialPredicateDefinitionFence']['preparedTerms'][0]['left']),
    'planner stat4 expression partial current source next202 autoload term right' => static fn (TestRunner $t) => $t->same('yes', $plan202()['partialPredicateDefinitionFence']['currentTerms'][2]['right']),
    'planner stat4 expression partial current source next202 not null term operator' => static fn (TestRunner $t) => $t->same('IS NOT NULL', $plan202()['partialPredicateDefinitionFence']['currentTerms'][3]['operator']),
    'planner stat4 expression partial current source next202 term flags pass' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan202()['partialPredicateDefinitionFence']['termChecks'], 'matches')),
    'planner stat4 expression partial current source next202 covering still elided' => static fn (TestRunner $t) => $t->same(true, $plan202()['tableLookupElided']),
    'planner stat4 expression partial current source next202 peer order still stable' => static fn (TestRunner $t) => $t->same(true, $plan202()['peerOrderFence']['peerOrderStable']),
    'planner stat4 expression partial current source next202 cursor opcode' => static fn (TestRunner $t) => $t->same('Stat4PartialPredicateDefinitionFence', $plan202()['cursorProgram'][array_key_last($plan202()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next202 cursor mode' => static fn (TestRunner $t) => $t->same('next202-current-source-stat4-expression-partial-definition', $plan202()['cursorProgram'][array_key_last($plan202()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next202 cursor term count' => static fn (TestRunner $t) => $t->same(4, $plan202()['cursorProgram'][array_key_last($plan202()['cursorProgram'])]['termCount']),
    'planner stat4 expression partial current source next202 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan202()['partialPredicateDefinitionFence']['signature'])),
    'planner stat4 expression partial current source next202 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan202()['selectedPlan']['next202PartialPredicateSignature'])),
    'planner stat4 expression partial current source next202 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan202()['stat4Fence']['next202PartialPredicateSignature'])),
    'planner stat4 expression partial current source next202 detail' => static fn (TestRunner $t) => $t->contains('NEXT202 PARTIAL PREDICATE DEFINITION FENCE', $plan202()['detail']),
    'planner stat4 expression partial current source next202 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next202', $plan202()['dependencies'], true)),
    'planner stat4 expression partial current source next202 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan202()['dependency_closure']),
    'planner stat4 expression partial current source next202 non overlap' => static fn (TestRunner $t) => $t->contains('partial-index predicate definition', $plan202()['non_overlap']),
    'planner stat4 expression partial current source next202 autoload definition drift blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-definition-reprepare', $autoloadDrift202()['status']),
    'planner stat4 expression partial current source next202 autoload definition changed term' => static fn (TestRunner $t) => $t->same([2], $autoloadDrift202()['partialPredicateDefinitionFence']['changedTerms']),
    'planner stat4 expression partial current source next202 autoload definition current right' => static fn (TestRunner $t) => $t->same('on', $autoloadDrift202()['partialPredicateDefinitionFence']['termChecks'][2]['currentTerm']['right']),
    'planner stat4 expression partial current source next202 autoload drift no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('Stat4PartialPredicateDefinitionFence', array_column($autoloadDrift202()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next202 range definition drift blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-definition-reprepare', $rangeDrift202()['status']),
    'planner stat4 expression partial current source next202 range definition changed term' => static fn (TestRunner $t) => $t->same([0], $rangeDrift202()['partialPredicateDefinitionFence']['changedTerms']),
    'planner stat4 expression partial current source next202 extra term drift blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-definition-reprepare', $nullName202()['status']),
    'planner stat4 expression partial current source next202 extra term changed term' => static fn (TestRunner $t) => $t->same([4], $nullName202()['partialPredicateDefinitionFence']['changedTerms']),
    'planner stat4 expression partial current source next202 reordered definition blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-definition-reprepare', $reordered202()['status']),
    'planner stat4 expression partial current source next202 reordered changed terms' => static fn (TestRunner $t) => $t->same([0, 1], $reordered202()['partialPredicateDefinitionFence']['changedTerms']),
    'planner stat4 expression partial current source next202 invalid indexes' => static function (TestRunner $t) use ($current202, $plan202): void {
        $bad = $current202();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan202(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next202 missing terms' => static function (TestRunner $t) use ($current202, $plan202): void {
        $bad = $current202();
        $bad['indexes'][0]['partialPredicateTerms'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan202(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next202 invalid left operand' => static function (TestRunner $t) use ($current202, $plan202): void {
        $bad = $current202();
        $bad['indexes'][0]['partialPredicateTerms'][0]['left'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan202(5, 1, null, $bad));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next202 repeated predicate fence ' . $case] = static function (TestRunner $t) use ($plan202, $case): void {
        $plan = $plan202(1 + ($case % 5), $case % 4);
        $t->same($plan['partialPredicateDefinitionFence']['preparedTerms'], $plan['partialPredicateDefinitionFence']['currentTerms']);
    };
}

return $tests;
