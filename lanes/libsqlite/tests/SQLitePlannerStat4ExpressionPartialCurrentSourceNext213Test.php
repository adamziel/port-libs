<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq213 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like213 = static fn (string $column, string $right, bool $caseSensitive = false, string $collation = 'NOCASE'): array => [
    'left' => ['column' => $column],
    'operator' => 'LIKE',
    'right' => $right,
    'caseSensitive' => $caseSensitive,
    'collation' => $collation,
];
$notNull213 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between213 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared213 = static fn (): array => [
    'name' => 'prepared-wp-options-like-case-partial-stat4-expression-next213',
    'schemaCookie' => 2130,
    'stat4Generation' => 213,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_like_case_stat4_next213',
        'rootPage' => 21301,
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
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%', 'caseSensitive' => false, 'collation' => 'NOCASE'],
            ],
            [
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'network_%', 'caseSensitive' => false, 'collation' => 'NOCASE'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current213 = static function () use ($prepared213): array {
    $source = $prepared213();
    $source['name'] = 'current-wp-options-like-case-partial-stat4-expression-next213';
    $source['schemaCookie'] = 2138;
    $source['stat4Generation'] = 269;
    $source['indexes'][0]['rootPage'] = 21388;
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

    return $source;
};

$terms213 = static fn (bool $caseSensitive = false, string $collation = 'NOCASE'): array => [
    $between213('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq213('autoload', 'yes'),
    $notNull213('option_name'),
    $eq213('blog_id', 1),
    $like213('option_name', 'plugin_%', $caseSensitive, $collation),
];
$plan213 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext213(
    $prepared ?? $prepared213(),
    $current ?? $current213(),
    $terms ?? $terms213(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$caseChanged213 = static function () use ($current213, $terms213, $plan213): array {
    $current = $current213();
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['caseSensitive'] = true;
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['collation'] = 'BINARY';

    return $plan213(5, 1, null, $current, $terms213(false, 'NOCASE'));
};
$binaryReady213 = static function () use ($current213, $terms213, $plan213): array {
    $current = $current213();
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['caseSensitive'] = true;
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['collation'] = 'BINARY';
    foreach ($current['rows'] as &$row) {
        $row['option_name'] = strtolower((string) $row['option_name']);
    }
    unset($row);

    return $plan213(5, 1, null, $current, $terms213(true, 'BINARY'));
};
$collationChanged213 = static function () use ($current213, $terms213, $plan213): array {
    $current = $current213();
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['collation'] = 'RTRIM';

    return $plan213(5, 1, null, $current, $terms213(false, 'NOCASE'));
};
$missingLike213 = static function () use ($terms213, $plan213): array {
    $terms = array_values(array_filter($terms213(), static fn (array $term): bool => strtoupper((string) ($term['operator'] ?? '')) !== 'LIKE'));

    return $plan213(5, 1, null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next213 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next213-ready', $plan213()['status']),
    'planner stat4 expression partial current source next213 selected current' => static fn (TestRunner $t) => $t->same('current', $plan213()['selectedSource']),
    'planner stat4 expression partial current source next213 inherited next212' => static fn (TestRunner $t) => $t->same(true, $plan213()['selectedPlan']['next212Ready']),
    'planner stat4 expression partial current source next213 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan213()['selectedPlan']['next213Ready']),
    'planner stat4 expression partial current source next213 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_like_case_stat4_next213', $plan213()['selectedPlan']['name']),
    'planner stat4 expression partial current source next213 root page' => static fn (TestRunner $t) => $t->same(21388, $plan213()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next213 like term count' => static fn (TestRunner $t) => $t->same(2, count($plan213()['likeCaseContractFence']['currentLikeCaseTerms'])),
    'planner stat4 expression partial current source next213 where like count' => static fn (TestRunner $t) => $t->same(1, count($plan213()['likeCaseContractFence']['whereLikeTerms'])),
    'planner stat4 expression partial current source next213 implied' => static fn (TestRunner $t) => $t->same(true, $plan213()['likeCaseContractFence']['currentLikeCaseContractImplied']),
    'planner stat4 expression partial current source next213 all rows satisfy' => static fn (TestRunner $t) => $t->same(true, $plan213()['likeCaseContractFence']['allRowsSatisfyCurrentLikeCaseContract']),
    'planner stat4 expression partial current source next213 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan213()['likeCaseContractFence']['caseContractMismatches']),
    'planner stat4 expression partial current source next213 no rejected rows' => static fn (TestRunner $t) => $t->same([], $plan213()['likeCaseContractFence']['rowidsRejectedByLikeCaseContract']),
    'planner stat4 expression partial current source next213 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan213()['selectedPlan']['next213RowsRejectedByLikeCaseContract']),
    'planner stat4 expression partial current source next213 mode nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan213()['likeCaseContractFence']['matchedLikeCaseMode']),
    'planner stat4 expression partial current source next213 selected mode nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan213()['selectedPlan']['next213LikeCaseMode']),
    'planner stat4 expression partial current source next213 first term collation' => static fn (TestRunner $t) => $t->same('NOCASE', $plan213()['likeCaseContractFence']['currentLikeCaseTerms'][0]['collation']),
    'planner stat4 expression partial current source next213 first term insensitive' => static fn (TestRunner $t) => $t->same(false, $plan213()['likeCaseContractFence']['currentLikeCaseTerms'][0]['caseSensitive']),
    'planner stat4 expression partial current source next213 first prefix' => static fn (TestRunner $t) => $t->same('plugin', $plan213()['likeCaseContractFence']['currentLikeCaseTerms'][0]['prefix']),
    'planner stat4 expression partial current source next213 proof reasons' => static fn (TestRunner $t) => $t->same(['like-case-contract-compatible', 'like-prefix-not-implied'], array_column($plan213()['likeCaseContractFence']['currentLikeCaseProofs'], 'reason')),
    'planner stat4 expression partial current source next213 proof flags' => static fn (TestRunner $t) => $t->same([true, false], array_column($plan213()['likeCaseContractFence']['currentLikeCaseProofs'], 'implied')),
    'planner stat4 expression partial current source next213 row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan213()['likeCaseContractFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next213 mixed case accepted' => static fn (TestRunner $t) => $t->same(true, $plan213()['likeCaseContractFence']['rowProofs'][1]['termResults'][0]['satisfied']),
    'planner stat4 expression partial current source next213 uppercase accepted' => static fn (TestRunner $t) => $t->same(true, $plan213()['likeCaseContractFence']['rowProofs'][4]['termResults'][0]['satisfied']),
    'planner stat4 expression partial current source next213 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan213()['matchedRowids']),
    'planner stat4 expression partial current source next213 matched keys preserved' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan213()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next213 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan213()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next213 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan213()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next213 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan213()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next213 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan213()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next213 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan213()['likeCaseContractFence']['currentLikeCaseSignature'])),
    'planner stat4 expression partial current source next213 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan213()['likeCaseContractFence']['proofSignature'])),
    'planner stat4 expression partial current source next213 selected signature' => static fn (TestRunner $t) => $t->same($plan213()['likeCaseContractFence']['currentLikeCaseSignature'], $plan213()['selectedPlan']['next213LikeCaseSignature']),
    'planner stat4 expression partial current source next213 stat4 signature' => static fn (TestRunner $t) => $t->same($plan213()['likeCaseContractFence']['currentLikeCaseSignature'], $plan213()['stat4Fence']['next213LikeCaseSignature']),
    'planner stat4 expression partial current source next213 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan213()['likeCaseContractFence']['proofSignature'], $plan213()['stat4Fence']['next213LikeCaseProofSignature']),
    'planner stat4 expression partial current source next213 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentLikeCaseContract', $plan213()['cursorProgram'][array_key_last($plan213()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next213 cursor mode' => static fn (TestRunner $t) => $t->same('next213-current-source-stat4-expression-partial-like-case-contract', $plan213()['cursorProgram'][array_key_last($plan213()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next213 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan213()['cursorProgram'][array_key_last($plan213()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next213 cursor mode value' => static fn (TestRunner $t) => $t->same('NOCASE', $plan213()['cursorProgram'][array_key_last($plan213()['cursorProgram'])]['likeCaseMode']),
    'planner stat4 expression partial current source next213 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next213', $plan213()['dependencies'], true)),
    'planner stat4 expression partial current source next213 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan213()['dependency_closure']),
    'planner stat4 expression partial current source next213 non overlap' => static fn (TestRunner $t) => $t->contains('case-sensitive LIKE', $plan213()['non_overlap']),
    'planner stat4 expression partial current source next213 detail' => static fn (TestRunner $t) => $t->contains('NEXT213 LIKE CASE CONTRACT FENCE', $plan213()['detail']),
    'planner stat4 expression partial current source next213 case changed blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-like-case-reprepare', $caseChanged213()['status']),
    'planner stat4 expression partial current source next213 case mismatch reason' => static fn (TestRunner $t) => $t->same('case-sensitive-like-mode-changed', $caseChanged213()['likeCaseContractFence']['caseContractMismatches'][0]['reason']),
    'planner stat4 expression partial current source next213 case changed rejected mixed rows' => static fn (TestRunner $t) => $t->same([50, 21, 22], $caseChanged213()['likeCaseContractFence']['rowidsRejectedByLikeCaseContract']),
    'planner stat4 expression partial current source next213 case changed no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentLikeCaseContract', array_column($caseChanged213()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next213 binary ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next213-ready', $binaryReady213()['status']),
    'planner stat4 expression partial current source next213 binary mode' => static fn (TestRunner $t) => $t->same('BINARY', $binaryReady213()['likeCaseContractFence']['matchedLikeCaseMode']),
    'planner stat4 expression partial current source next213 binary no rejected rows' => static fn (TestRunner $t) => $t->same([], $binaryReady213()['likeCaseContractFence']['rowidsRejectedByLikeCaseContract']),
    'planner stat4 expression partial current source next213 collation changed blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-like-case-reprepare', $collationChanged213()['status']),
    'planner stat4 expression partial current source next213 collation mismatch reason' => static fn (TestRunner $t) => $t->same('like-collation-changed', $collationChanged213()['likeCaseContractFence']['caseContractMismatches'][0]['reason']),
    'planner stat4 expression partial current source next213 missing like blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-like-case-reprepare', $missingLike213()['status']),
    'planner stat4 expression partial current source next213 invalid current indexes' => static function (TestRunner $t) use ($current213, $plan213): void {
        $bad = $current213();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan213(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next213 invalid like left' => static function (TestRunner $t) use ($current213, $plan213): void {
        $bad = $current213();
        unset($bad['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['left']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan213(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next213 repeated like case fence ' . $case] = static function (TestRunner $t) use ($plan213, $case): void {
        $plan = $plan213(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['likeCaseContractFence']['rowProofs']));
    };
}

return $tests;
