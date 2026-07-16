<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq241 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like241 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull241 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between241 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload241 = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['option_name']),
    'coveredValues' => [
        'option_name' => $row['option_name'],
        'option_value' => $row['option_value'],
        'updated_at' => $row['updated_at'],
        'blog_id' => $row['blog_id'],
        'autoload' => $row['autoload'],
    ],
];

$prepared241 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-residual-next241',
    'schemaCookie' => 2410,
    'stat4Generation' => 241,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_residual_partial_next241',
        'rootPage' => 24101,
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
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 1, 40]],
            ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '4 1', 'nlt' => '2 5', 'ndlt' => '2 3', 'sample' => ['plugin_forms', 2, 80]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 4', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 6', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current241 = static function () use ($prepared241, $payload241): array {
    $source = $prepared241();
    $source['name'] = 'current-wp-options-stat4-residual-next241';
    $source['schemaCookie'] = 2419;
    $source['stat4Generation'] = 641;
    $source['indexes'][0]['rootPage'] = 24188;
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload241,
        array_values(array_filter($source['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $source;
};

$terms241 = static fn (): array => [
    $between241('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq241('autoload', 'yes'),
    $notNull241('option_name'),
    $eq241('blog_id', 1),
    $like241('option_name', 'plugin_%'),
];
$valueTerms241 = static fn (): array => [
    $between241('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq241('autoload', 'yes'),
    $notNull241('option_name'),
    $eq241('blog_id', 1),
    $like241('option_value', '%copy%'),
];
$plan241 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceResidualWhereValidation(
    $prepared ?? $prepared241(),
    $current ?? $current241(),
    $terms ?? $terms241(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleBlog241 = static function () use ($current241, $payload241, $plan241): array {
    $current = $current241();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 20) {
            $row['blog_id'] = 2;
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload241,
        array_values(array_filter($current['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $plan241(5, 1, null, $current);
};
$staleValue241 = static function () use ($current241, $payload241, $plan241, $valueTerms241): array {
    $current = $current241();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 21) {
            $row['option_value'] = 'forms-stale';
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload241,
        array_values(array_filter($current['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $plan241(5, 1, null, $current, $valueTerms241());
};
$missingRow241 = static function () use ($current241, $plan241): array {
    $current = $current241();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => $row['rowid'] !== 30));

    return $plan241(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next241 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next241-ready', $plan241()['status']),
    'planner stat4 expression partial current source next241 inherits next238 payload fence' => static fn (TestRunner $t) => $t->same(true, $plan241()['stat4CoveringPayloadFence']['allPayloadsMatchCurrentRows']),
    'planner stat4 expression partial current source next241 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan241()['selectedPlan']['next241Ready']),
    'planner stat4 expression partial current source next241 selected current' => static fn (TestRunner $t) => $t->same('current', $plan241()['selectedSource']),
    'planner stat4 expression partial current source next241 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_residual_partial_next241', $plan241()['selectedPlan']['name']),
    'planner stat4 expression partial current source next241 root page' => static fn (TestRunner $t) => $t->same(24188, $plan241()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next241 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan241()['matchedRowids']),
    'planner stat4 expression partial current source next241 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan241()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next241 residual matched count' => static fn (TestRunner $t) => $t->same(5, $plan241()['stat4ResidualWhereFence']['matchedRowCount']),
    'planner stat4 expression partial current source next241 residual term count' => static fn (TestRunner $t) => $t->same(5, $plan241()['stat4ResidualWhereFence']['residualTermCount']),
    'planner stat4 expression partial current source next241 accepted rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan241()['stat4ResidualWhereFence']['residualAcceptedRowids']),
    'planner stat4 expression partial current source next241 rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan241()['stat4ResidualWhereFence']['residualRejectedRowids']),
    'planner stat4 expression partial current source next241 all residuals match' => static fn (TestRunner $t) => $t->same(true, $plan241()['stat4ResidualWhereFence']['allMatchedRowsSatisfyResidualWhere']),
    'planner stat4 expression partial current source next241 first proof rowid' => static fn (TestRunner $t) => $t->same(30, $plan241()['stat4ResidualWhereFence']['rowProofs'][0]['rowid']),
    'planner stat4 expression partial current source next241 first proof expression key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan241()['stat4ResidualWhereFence']['rowProofs'][0]['expressionKey']),
    'planner stat4 expression partial current source next241 first proof flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan241()['stat4ResidualWhereFence']['rowProofs'][0]['termProofs'], 'matches')),
    'planner stat4 expression partial current source next241 first proof labels' => static fn (TestRunner $t) => $t->same(['lower(option_name)', 'autoload', 'option_name', 'blog_id', 'option_name'], array_column($plan241()['stat4ResidualWhereFence']['rowProofs'][0]['termProofs'], 'left')),
    'planner stat4 expression partial current source next241 selected accepted rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan241()['selectedPlan']['next241ResidualAcceptedRowids']),
    'planner stat4 expression partial current source next241 selected rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan241()['selectedPlan']['next241ResidualRejectedRowids']),
    'planner stat4 expression partial current source next241 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan241()['stat4ResidualWhereFence']['residualWhereSignature']), strlen($plan241()['stat4ResidualWhereFence']['proofSignature'])]),
    'planner stat4 expression partial current source next241 selected where signature' => static fn (TestRunner $t) => $t->same($plan241()['stat4ResidualWhereFence']['residualWhereSignature'], $plan241()['selectedPlan']['next241ResidualWhereSignature']),
    'planner stat4 expression partial current source next241 selected proof signature' => static fn (TestRunner $t) => $t->same($plan241()['stat4ResidualWhereFence']['proofSignature'], $plan241()['selectedPlan']['next241ProofSignature']),
    'planner stat4 expression partial current source next241 stat4 where signature' => static fn (TestRunner $t) => $t->same($plan241()['stat4ResidualWhereFence']['residualWhereSignature'], $plan241()['stat4Fence']['next241ResidualWhereSignature']),
    'planner stat4 expression partial current source next241 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan241()['stat4ResidualWhereFence']['proofSignature'], $plan241()['stat4Fence']['next241ProofSignature']),
    'planner stat4 expression partial current source next241 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4ResidualWhere', $plan241()['cursorProgram'][array_key_last($plan241()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next241 cursor mode' => static fn (TestRunner $t) => $t->same('next241-current-source-stat4-expression-partial-residual-where', $plan241()['cursorProgram'][array_key_last($plan241()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next241 cursor counts' => static fn (TestRunner $t) => $t->same([5, 5], [$plan241()['cursorProgram'][array_key_last($plan241()['cursorProgram'])]['matchedRowCount'], $plan241()['cursorProgram'][array_key_last($plan241()['cursorProgram'])]['residualTermCount']]),
    'planner stat4 expression partial current source next241 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan241()['cursorProgram'][array_key_last($plan241()['cursorProgram'])]['acceptedRowids']),
    'planner stat4 expression partial current source next241 cursor signature' => static fn (TestRunner $t) => $t->same($plan241()['stat4ResidualWhereFence']['proofSignature'], $plan241()['cursorProgram'][array_key_last($plan241()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next241 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next241', $plan241()['dependencies'], true)),
    'planner stat4 expression partial current source next241 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan241()['dependency_closure']),
    'planner stat4 expression partial current source next241 non overlap' => static fn (TestRunner $t) => $t->contains('residual WHERE validation', $plan241()['non_overlap']),
    'planner stat4 expression partial current source next241 detail' => static fn (TestRunner $t) => $t->contains('NEXT241 RESIDUAL WHERE FENCE', $plan241()['detail']),
    'planner stat4 expression partial current source next241 stale blog replans before residual' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next241-ready', $staleBlog241()['status']),
    'planner stat4 expression partial current source next241 stale blog rowid removed before residual' => static fn (TestRunner $t) => $t->same([], $staleBlog241()['stat4ResidualWhereFence']['residualRejectedRowids']),
    'planner stat4 expression partial current source next241 stale blog matched rows shrink' => static fn (TestRunner $t) => $t->same([30, 50, 21, 22, 40], $staleBlog241()['matchedRowids']),
    'planner stat4 expression partial current source next241 stale blog cursor still verifies residual' => static fn (TestRunner $t) => $t->same(true, in_array('VerifyCurrentStat4ResidualWhere', array_column($staleBlog241()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next241 stale value blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-residual-reprepare', $staleValue241()['status']),
    'planner stat4 expression partial current source next241 stale value rowid' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $staleValue241()['stat4ResidualWhereFence']['residualRejectedRowids']),
    'planner stat4 expression partial current source next241 stale value proof' => static fn (TestRunner $t) => $t->same(['forms-stale', '%copy%', false], [$staleValue241()['stat4ResidualWhereFence']['rowProofs'][3]['termProofs'][4]['value'], $staleValue241()['stat4ResidualWhereFence']['rowProofs'][3]['termProofs'][4]['right'], $staleValue241()['stat4ResidualWhereFence']['rowProofs'][3]['termProofs'][4]['matches']]),
    'planner stat4 expression partial current source next241 missing matched row excluded before residual' => static fn (TestRunner $t) => $t->same([50, 20, 21, 22, 40], $missingRow241()['matchedRowids']),
    'planner stat4 expression partial current source next241 unsupported residual skipped by lower planner' => static function (TestRunner $t) use ($plan241, $terms241): void {
        $terms = $terms241();
        $terms[] = ['left' => ['column' => 'option_value'], 'operator' => 'GLOB', 'right' => 'forms*'];
        $t->same([], $plan241(5, 1, null, null, $terms)['stat4ResidualWhereFence']['residualRejectedRowids']);
    },
    'planner stat4 expression partial current source next241 malformed row list rejected' => static function (TestRunner $t) use ($current241, $plan241): void {
        $bad = $current241();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan241(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next241 duplicate current rowid rejected' => static function (TestRunner $t) use ($current241, $plan241): void {
        $bad = $current241();
        $bad['rows'][] = $bad['rows'][0];
        $t->throws(InvalidArgumentException::class, static fn () => $plan241(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next241 wildcard like admitted' => static fn (TestRunner $t) => $t->same(true, $plan241()['stat4ResidualWhereFence']['rowProofs'][0]['termProofs'][4]['matches']),
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next241 repeated residual proof ' . $case] = static function (TestRunner $t) use ($plan241, $case): void {
        $plan = $plan241(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4ResidualWhereFence']['proofSignature'], $plan['selectedPlan']['next241ProofSignature']);
    };
}

return $tests;
