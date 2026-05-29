<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq231 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like231 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull231 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between231 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload231 = static fn (array $row): array => [
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

$prepared231 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-partial-page-next231',
    'schemaCookie' => 2310,
    'stat4Generation' => 231,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_partial_page_next231',
        'rootPage' => 23101,
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
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
            [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
            ],
            [
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'network%'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current231 = static function () use ($prepared231, $payload231): array {
    $source = $prepared231();
    $source['name'] = 'current-wp-options-stat4-partial-page-next231';
    $source['schemaCookie'] = 2319;
    $source['stat4Generation'] = 321;
    $source['indexes'][0]['rootPage'] = 23188;
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
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload231, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms231 = static fn (): array => [
    $between231('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq231('autoload', 'yes'),
    $notNull231('option_name'),
    $eq231('blog_id', 1),
    $like231('option_name', 'plugin_%'),
];
$plan231 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext231(
    $prepared ?? $prepared231(),
    $current ?? $current231(),
    $terms ?? $terms231(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$insertedCurrentRow231 = static function () use ($current231, $payload231, $plan231): array {
    $current = $current231();
    array_unshift($current['rows'], ['rowid' => 55, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_updates', 'option_value' => 'updates', 'updated_at' => 55]);
    $current['indexes'][0]['stat4Samples'][] = ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_updates', 55]];
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload231, array_slice($current['rows'], 0, 9));

    return $plan231(5, 1, null, $current);
};
$residualReject231 = static function () use ($current231, $payload231, $plan231): array {
    $current = $current231();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 50) {
            $row['blog_id'] = 2;
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload231, array_slice($current['rows'], 0, 8));

    return $plan231(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next231 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next231-ready', $plan231()['status']),
    'planner stat4 expression partial current source next231 selected current' => static fn (TestRunner $t) => $t->same('current', $plan231()['selectedSource']),
    'planner stat4 expression partial current source next231 inherits next228' => static fn (TestRunner $t) => $t->same(true, $plan231()['selectedPlan']['next228Ready']),
    'planner stat4 expression partial current source next231 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan231()['selectedPlan']['next231Ready']),
    'planner stat4 expression partial current source next231 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_partial_page_next231', $plan231()['selectedPlan']['name']),
    'planner stat4 expression partial current source next231 root page' => static fn (TestRunner $t) => $t->same(23188, $plan231()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next231 qualified rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22, 40, 10], $plan231()['currentSourcePageFence']['currentQualifiedRowids']),
    'planner stat4 expression partial current source next231 expected page' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan231()['currentSourcePageFence']['expectedPageRowids']),
    'planner stat4 expression partial current source next231 actual page' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan231()['currentSourcePageFence']['actualPageRowids']),
    'planner stat4 expression partial current source next231 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan231()['matchedRowids']),
    'planner stat4 expression partial current source next231 page matches' => static fn (TestRunner $t) => $t->same(true, $plan231()['currentSourcePageFence']['selectedPageMatchesCurrentSource']),
    'planner stat4 expression partial current source next231 rows resolve' => static fn (TestRunner $t) => $t->same(true, $plan231()['currentSourcePageFence']['allMatchedRowsResolveToCurrentSource']),
    'planner stat4 expression partial current source next231 rows satisfy where' => static fn (TestRunner $t) => $t->same(true, $plan231()['currentSourcePageFence']['allMatchedRowsSatisfyWhereTerms']),
    'planner stat4 expression partial current source next231 no missing' => static fn (TestRunner $t) => $t->same([], $plan231()['currentSourcePageFence']['missingMatchedCurrentRowids']),
    'planner stat4 expression partial current source next231 no rejects' => static fn (TestRunner $t) => $t->same([], $plan231()['currentSourcePageFence']['matchedRowidsRejectedByWhereTerms']),
    'planner stat4 expression partial current source next231 limit' => static fn (TestRunner $t) => $t->same(5, $plan231()['currentSourcePageFence']['limit']),
    'planner stat4 expression partial current source next231 offset' => static fn (TestRunner $t) => $t->same(1, $plan231()['currentSourcePageFence']['offset']),
    'planner stat4 expression partial current source next231 qualified count' => static fn (TestRunner $t) => $t->same(8, $plan231()['currentSourcePageFence']['qualifiedRowCount']),
    'planner stat4 expression partial current source next231 page count' => static fn (TestRunner $t) => $t->same(5, $plan231()['currentSourcePageFence']['pageRowCount']),
    'planner stat4 expression partial current source next231 first proof rowid' => static fn (TestRunner $t) => $t->same(30, $plan231()['currentSourcePageFence']['matchedRowProofs'][0]['rowid']),
    'planner stat4 expression partial current source next231 first proof key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan231()['currentSourcePageFence']['matchedRowProofs'][0]['expressionKey']),
    'planner stat4 expression partial current source next231 term keys' => static fn (TestRunner $t) => $t->same(['expression:lower(option_name)', 'column:autoload', 'column:option_name', 'column:blog_id', 'column:option_name'], array_column($plan231()['currentSourcePageFence']['matchedRowProofs'][0]['whereTermProofs'], 'leftKey')),
    'planner stat4 expression partial current source next231 term operators' => static fn (TestRunner $t) => $t->same(['BETWEEN', '=', 'IS NOT NULL', '=', 'LIKE'], array_column($plan231()['currentSourcePageFence']['matchedRowProofs'][0]['whereTermProofs'], 'operator')),
    'planner stat4 expression partial current source next231 term flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan231()['currentSourcePageFence']['matchedRowProofs'][0]['whereTermProofs'], 'satisfied')),
    'planner stat4 expression partial current source next231 selected page signature' => static fn (TestRunner $t) => $t->same($plan231()['currentSourcePageFence']['pageSignature'], $plan231()['selectedPlan']['next231PageSignature']),
    'planner stat4 expression partial current source next231 selected proof signature' => static fn (TestRunner $t) => $t->same($plan231()['currentSourcePageFence']['proofSignature'], $plan231()['selectedPlan']['next231ProofSignature']),
    'planner stat4 expression partial current source next231 stat4 page signature' => static fn (TestRunner $t) => $t->same($plan231()['currentSourcePageFence']['pageSignature'], $plan231()['stat4Fence']['next231PageSignature']),
    'planner stat4 expression partial current source next231 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan231()['currentSourcePageFence']['proofSignature'], $plan231()['stat4Fence']['next231ProofSignature']),
    'planner stat4 expression partial current source next231 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan231()['currentSourcePageFence']['pageSignature']), strlen($plan231()['currentSourcePageFence']['proofSignature'])]),
    'planner stat4 expression partial current source next231 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4ExpressionPartialPage', $plan231()['cursorProgram'][array_key_last($plan231()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next231 cursor mode' => static fn (TestRunner $t) => $t->same('next231-current-source-stat4-expression-partial-page-membership', $plan231()['cursorProgram'][array_key_last($plan231()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next231 cursor qualified' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22, 40, 10], $plan231()['cursorProgram'][array_key_last($plan231()['cursorProgram'])]['qualifiedRowids']),
    'planner stat4 expression partial current source next231 cursor expected' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan231()['cursorProgram'][array_key_last($plan231()['cursorProgram'])]['expectedPageRowids']),
    'planner stat4 expression partial current source next231 cursor actual' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan231()['cursorProgram'][array_key_last($plan231()['cursorProgram'])]['actualPageRowids']),
    'planner stat4 expression partial current source next231 cursor signature' => static fn (TestRunner $t) => $t->same($plan231()['currentSourcePageFence']['proofSignature'], $plan231()['cursorProgram'][array_key_last($plan231()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next231 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next231', $plan231()['dependencies'], true)),
    'planner stat4 expression partial current source next231 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan231()['dependency_closure']),
    'planner stat4 expression partial current source next231 non overlap' => static fn (TestRunner $t) => $t->contains('page membership validation', $plan231()['non_overlap']),
    'planner stat4 expression partial current source next231 detail' => static fn (TestRunner $t) => $t->contains('NEXT231 PAGE MEMBERSHIP FENCE', $plan231()['detail']),
    'planner stat4 expression partial current source next231 inserted row refreshes' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next231-ready', $insertedCurrentRow231()['status']),
    'planner stat4 expression partial current source next231 inserted qualified rowids' => static fn (TestRunner $t) => $t->same([60, 55, 30, 50, 20, 21, 22, 40, 10], $insertedCurrentRow231()['currentSourcePageFence']['currentQualifiedRowids']),
    'planner stat4 expression partial current source next231 inserted expected page' => static fn (TestRunner $t) => $t->same([55, 30, 50, 20, 21], $insertedCurrentRow231()['currentSourcePageFence']['expectedPageRowids']),
    'planner stat4 expression partial current source next231 inserted actual page refreshes' => static fn (TestRunner $t) => $t->same([55, 30, 50, 20, 21], $insertedCurrentRow231()['currentSourcePageFence']['actualPageRowids']),
    'planner stat4 expression partial current source next231 inserted page matches refresh' => static fn (TestRunner $t) => $t->same(true, $insertedCurrentRow231()['currentSourcePageFence']['selectedPageMatchesCurrentSource']),
    'planner stat4 expression partial current source next231 inserted cursor appended' => static fn (TestRunner $t) => $t->same(true, in_array('RecheckCurrentStat4ExpressionPartialPage', array_column($insertedCurrentRow231()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next231 residual refreshes' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next231-ready', $residualReject231()['status']),
    'planner stat4 expression partial current source next231 residual reject rowid already filtered' => static fn (TestRunner $t) => $t->same([], $residualReject231()['currentSourcePageFence']['matchedRowidsRejectedByWhereTerms']),
    'planner stat4 expression partial current source next231 residual expected page' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22, 40], $residualReject231()['currentSourcePageFence']['expectedPageRowids']),
    'planner stat4 expression partial current source next231 residual actual page' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22, 40], $residualReject231()['currentSourcePageFence']['actualPageRowids']),
    'planner stat4 expression partial current source next231 residual page matches refreshed rows' => static fn (TestRunner $t) => $t->same(true, $residualReject231()['currentSourcePageFence']['selectedPageMatchesCurrentSource']),
    'planner stat4 expression partial current source next231 invalid current rows' => static function (TestRunner $t) use ($current231, $plan231): void {
        $bad = $current231();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan231(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next231 invalid where operator' => static function (TestRunner $t) use ($terms231, $plan231): void {
        $terms = $terms231();
        $terms[0]['operator'] = 'GLOB';
        $t->throws(InvalidArgumentException::class, static fn () => $plan231(5, 1, null, null, $terms));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next231 repeated page proof ' . $case] = static function (TestRunner $t) use ($plan231, $case): void {
        $plan = $plan231(1 + ($case % 5), $case % 4);
        $t->same($plan['currentSourcePageFence']['pageSignature'], $plan['selectedPlan']['next231PageSignature']);
    };
}

return $tests;
