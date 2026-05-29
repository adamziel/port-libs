<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq253 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like253 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull253 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between253 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload253 = static fn (array $row): array => [
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

$prepared253 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-payload-next253',
    'schemaCookie' => 2530,
    'stat4Generation' => 253,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_payload_next253',
        'rootPage' => 25301,
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
            ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current253 = static function (string $variant = 'ready') use ($prepared253, $payload253): array {
    $source = $prepared253();
    $source['name'] = 'current-wp-options-stat4-payload-next253';
    $source['schemaCookie'] = 2539;
    $source['stat4Generation'] = 953;
    $source['indexes'][0]['rootPage'] = 25388;
    $source['indexes'][0]['partialPredicateTerms'] = [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
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
    ];
    $payloads = array_map(
        $payload253,
        array_values(array_filter($source['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );
    if ($variant === 'stale-value') {
        $payloads[4]['coveredValues']['option_value'] = 'forms-copy-stale';
    }
    if ($variant === 'stale-key') {
        $payloads[4]['expressionKey'] = 'plugin_forms_stale';
    }
    if ($variant === 'missing-payload') {
        $payloads = array_values(array_filter($payloads, static fn (array $payload): bool => $payload['rowid'] !== 21));
    }
    if ($variant === 'missing-current-row') {
        $source['rows'] = array_values(array_filter($source['rows'], static fn (array $row): bool => $row['rowid'] !== 21));
    }
    if ($variant === 'duplicate-payload') {
        $payloads[] = $payloads[0];
    }
    if ($variant === 'missing-covered-values') {
        unset($payloads[0]['coveredValues']);
    }
    $source['indexes'][0]['stat4ExpressionPayloads'] = $payloads;

    return $source;
};

$terms253 = static fn (): array => [
    $between253('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq253('autoload', 'yes'),
    $notNull253('option_name'),
    $eq253('blog_id', 1),
    $like253('option_name', 'plugin_%'),
];

$plan253 = static fn (string $variant = 'ready', int $limit = 5, int $offset = 1, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentStat4PayloadFence(
    $prepared253(),
    $current253($variant),
    $terms253(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$tests = [
    'planner stat4 expression partial current source next253 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next253-ready', $plan253()['status']),
    'planner stat4 expression partial current source next253 inherits next250' => static fn (TestRunner $t) => $t->same(true, $plan253()['selectedPlan']['next250Ready']),
    'planner stat4 expression partial current source next253 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_payload_next253', $plan253()['selectedPlan']['name']),
    'planner stat4 expression partial current source next253 root page' => static fn (TestRunner $t) => $t->same(25388, $plan253()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next253 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan253()['matchedRowids']),
    'planner stat4 expression partial current source next253 payload rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan253()['stat4CurrentPayloadFence']['payloadMatchedRowids']),
    'planner stat4 expression partial current source next253 no payload mismatches' => static fn (TestRunner $t) => $t->same([], $plan253()['stat4CurrentPayloadFence']['payloadMismatchRowids']),
    'planner stat4 expression partial current source next253 no missing payloads' => static fn (TestRunner $t) => $t->same([], $plan253()['stat4CurrentPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next253 no missing current rows' => static fn (TestRunner $t) => $t->same([], $plan253()['stat4CurrentPayloadFence']['missingCurrentRowids']),
    'planner stat4 expression partial current source next253 all payloads current' => static fn (TestRunner $t) => $t->same(true, $plan253()['stat4CurrentPayloadFence']['allYieldedRowsHaveCurrentPayloads']),
    'planner stat4 expression partial current source next253 selected ready' => static fn (TestRunner $t) => $t->same(true, $plan253()['selectedPlan']['next253Ready']),
    'planner stat4 expression partial current source next253 selected signature' => static fn (TestRunner $t) => $t->same($plan253()['stat4CurrentPayloadFence']['payloadSignature'], $plan253()['selectedPlan']['next253PayloadSignature']),
    'planner stat4 expression partial current source next253 stat fence ready' => static fn (TestRunner $t) => $t->same(true, $plan253()['stat4Fence']['next253CurrentPayloadReady']),
    'planner stat4 expression partial current source next253 stat fence signature' => static fn (TestRunner $t) => $t->same($plan253()['stat4CurrentPayloadFence']['payloadSignature'], $plan253()['stat4Fence']['next253CurrentPayloadSignature']),
    'planner stat4 expression partial current source next253 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4ExpressionPayload', $plan253()['cursorProgram'][array_key_last($plan253()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next253 cursor mode' => static fn (TestRunner $t) => $t->same('next253-current-source-stat4-expression-payload', $plan253()['cursorProgram'][array_key_last($plan253()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next253 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan253()['cursorProgram'][array_key_last($plan253()['cursorProgram'])]['payloadMatchedRowids']),
    'planner stat4 expression partial current source next253 first proof rowid' => static fn (TestRunner $t) => $t->same(30, $plan253()['stat4CurrentPayloadFence']['rowProofs'][0]['rowid']),
    'planner stat4 expression partial current source next253 first proof expression' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan253()['stat4CurrentPayloadFence']['rowProofs'][0]['currentExpressionKey']),
    'planner stat4 expression partial current source next253 first proof payload expression' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan253()['stat4CurrentPayloadFence']['rowProofs'][0]['payloadExpressionKey']),
    'planner stat4 expression partial current source next253 first proof current value' => static fn (TestRunner $t) => $t->same('seo', $plan253()['stat4CurrentPayloadFence']['rowProofs'][0]['currentCoveredValues']['option_value']),
    'planner stat4 expression partial current source next253 first proof payload value' => static fn (TestRunner $t) => $t->same('seo', $plan253()['stat4CurrentPayloadFence']['rowProofs'][0]['payloadCoveredValues']['option_value']),
    'planner stat4 expression partial current source next253 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan253()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next253 partial predicate remains ready' => static fn (TestRunner $t) => $t->same(true, $plan253()['stat4CurrentPartialPredicateFence']['allYieldedRowsSatisfyCurrentPartialPredicate']),
    'planner stat4 expression partial current source next253 detail' => static fn (TestRunner $t) => $t->contains('NEXT253 PAYLOAD FENCE', $plan253()['detail']),
    'planner stat4 expression partial current source next253 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next253', $plan253()['dependencies'], true)),
    'planner stat4 expression partial current source next253 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan253()['dependency_closure']),
    'planner stat4 expression partial current source next253 non overlap' => static fn (TestRunner $t) => $t->contains('payload row-image fencing', $plan253()['non_overlap']),
    'planner stat4 expression partial current source next253 stale value blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-payload-reprepare', $plan253('stale-value')['status']),
    'planner stat4 expression partial current source next253 stale value mismatch rowid' => static fn (TestRunner $t) => $t->same([21], $plan253('stale-value')['stat4CurrentPayloadFence']['payloadMismatchRowids']),
    'planner stat4 expression partial current source next253 stale value proof' => static fn (TestRunner $t) => $t->same(false, $plan253('stale-value')['stat4CurrentPayloadFence']['rowProofs'][3]['coveredValuesMatch']),
    'planner stat4 expression partial current source next253 stale key blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-payload-reprepare', $plan253('stale-key')['status']),
    'planner stat4 expression partial current source next253 stale key proof' => static fn (TestRunner $t) => $t->same(false, $plan253('stale-key')['stat4CurrentPayloadFence']['rowProofs'][3]['expressionMatches']),
    'planner stat4 expression partial current source next253 missing payload blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-payload-reprepare', $plan253('missing-payload')['status']),
    'planner stat4 expression partial current source next253 missing payload rowid' => static fn (TestRunner $t) => $t->same([21], $plan253('missing-payload')['stat4CurrentPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next253 missing current row inherited ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next253-ready', $plan253('missing-current-row')['status']),
    'planner stat4 expression partial current source next253 needed column subset' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_seo'], $plan253('ready', 5, 1, ['option_name'])['stat4CurrentPayloadFence']['rowProofs'][0]['currentCoveredValues']),
    'planner stat4 expression partial current source next253 duplicate payload throws' => static function (TestRunner $t) use ($plan253): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan253('duplicate-payload'));
    },
    'planner stat4 expression partial current source next253 missing covered values throws' => static function (TestRunner $t) use ($plan253): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan253('missing-covered-values'));
    },
    'planner stat4 expression partial current source next253 invalid needed columns' => static function (TestRunner $t) use ($plan253): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan253('ready', 5, 1, []));
    },
    'planner stat4 expression partial current source next253 invalid limit' => static function (TestRunner $t) use ($plan253): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan253('ready', -1, 0));
    },
    'planner stat4 expression partial current source next253 invalid offset' => static function (TestRunner $t) use ($plan253): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan253('ready', 1, -1));
    },
];

foreach (range(1, 24) as $case) {
    $tests['planner stat4 expression partial current source next253 repeated payload proof ' . $case] = static function (TestRunner $t) use ($plan253, $case): void {
        $plan = $plan253('ready', 1 + ($case % 5), $case % 3);
        $t->same($plan['stat4CurrentPayloadFence']['payloadSignature'], $plan['selectedPlan']['next253PayloadSignature']);
    };
}

return $tests;
