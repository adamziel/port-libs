<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq191 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull191 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between191 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared191 = static fn (): array => [
    'name' => 'prepared-wp-options-payload-stat4-expression-partial-next191',
    'schemaCookie' => 1910,
    'stat4Generation' => 121,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_payload_partial_stat4_next191',
        'rootPage' => 19101,
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

$current191 = static function () use ($prepared191): array {
    $source = $prepared191();
    $source['name'] = 'current-wp-options-payload-stat4-expression-partial-next191';
    $source['schemaCookie'] = 1918;
    $source['stat4Generation'] = 144;
    $source['indexes'][0]['rootPage'] = 19188;
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

$terms191 = static fn (): array => [
    $between191('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq191('autoload', 'yes'),
    $notNull191('option_name'),
];
$plan191 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext191(
    $prepared ?? $prepared191(),
    $current ?? $current191(),
    $terms ?? $terms191(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$mismatch191 = static function () use ($current191, $plan191): array {
    $current = $current191();
    $current['rows'][3]['option_name'] = 'plugin_forms_renamed';

    return $plan191(5, 1, null, $current);
};
$staleSample191 = static function () use ($current191, $plan191): array {
    $current = $current191();
    $current['indexes'][0]['stat4Samples'][2]['sample'][0] = 'plugin_forms_old';

    return $plan191(5, 1, null, $current);
};
$badExpression191 = static function () use ($current191, $plan191): array {
    $current = $current191();
    $current['indexes'][0]['expression'] = 'upper(option_name)';

    return $plan191(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next191 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next191-ready', $plan191()['status']),
    'planner stat4 expression partial current source next191 selected current' => static fn (TestRunner $t) => $t->same('current', $plan191()['selectedSource']),
    'planner stat4 expression partial current source next191 base peer ready' => static fn (TestRunner $t) => $t->same(true, $plan191()['selectedPlan']['next188Ready']),
    'planner stat4 expression partial current source next191 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan191()['selectedPlan']['next191Ready']),
    'planner stat4 expression partial current source next191 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_payload_partial_stat4_next191', $plan191()['selectedPlan']['name']),
    'planner stat4 expression partial current source next191 root page' => static fn (TestRunner $t) => $t->same(19188, $plan191()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next191 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan191()['payloadExpressionFence']['expression']),
    'planner stat4 expression partial current source next191 selected expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan191()['selectedPlan']['next191Expression']),
    'planner stat4 expression partial current source next191 expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan191()['selectedPlan']['next191PayloadExpressionColumn']),
    'planner stat4 expression partial current source next191 checked rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan191()['payloadExpressionFence']['checkedRowids']),
    'planner stat4 expression partial current source next191 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan191()['matchedRowids']),
    'planner stat4 expression partial current source next191 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan191()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next191 all keys match' => static fn (TestRunner $t) => $t->same(true, $plan191()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next191 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan191()['payloadExpressionFence']['mismatchedRowids']),
    'planner stat4 expression partial current source next191 selected no mismatches' => static fn (TestRunner $t) => $t->same([], $plan191()['selectedPlan']['next191MismatchedRowids']),
    'planner stat4 expression partial current source next191 no null keys' => static fn (TestRunner $t) => $t->same([], $plan191()['payloadExpressionFence']['nullExpressionRowids']),
    'planner stat4 expression partial current source next191 detail count' => static fn (TestRunner $t) => $t->same(5, count($plan191()['payloadExpressionFence']['details'])),
    'planner stat4 expression partial current source next191 first detail rowid' => static fn (TestRunner $t) => $t->same(30, $plan191()['payloadExpressionFence']['details'][0]['rowid']),
    'planner stat4 expression partial current source next191 first detail payload key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan191()['payloadExpressionFence']['details'][0]['payloadExpressionKey']),
    'planner stat4 expression partial current source next191 mixed case lower mail' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan191()['payloadExpressionFence']['details'][1]['payloadExpressionKey']),
    'planner stat4 expression partial current source next191 mixed case lower forms' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan191()['payloadExpressionFence']['details'][3]['payloadExpressionKey']),
    'planner stat4 expression partial current source next191 uppercase lower forms' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan191()['payloadExpressionFence']['details'][4]['payloadExpressionKey']),
    'planner stat4 expression partial current source next191 detail match flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan191()['payloadExpressionFence']['details'], 'matchesIndexedKey')),
    'planner stat4 expression partial current source next191 projection current payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan191()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next191 peer fence preserved' => static fn (TestRunner $t) => $t->same(['plugin_forms' => [20, 21, 22]], $plan191()['peerFence']['peerRowids']),
    'planner stat4 expression partial current source next191 provenance preserved' => static fn (TestRunner $t) => $t->same(['current', 'current', 'current', 'current', 'current'], array_column($plan191()['currentSourceRowProvenance'], 'source')),
    'planner stat4 expression partial current source next191 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCoveringPayloadExpressionKey', $plan191()['cursorProgram'][array_key_last($plan191()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next191 cursor mode' => static fn (TestRunner $t) => $t->same('next191-current-source-stat4-expression-partial-payload', $plan191()['cursorProgram'][array_key_last($plan191()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next191 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan191()['cursorProgram'][array_key_last($plan191()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next191 cursor expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan191()['cursorProgram'][array_key_last($plan191()['cursorProgram'])]['expression']),
    'planner stat4 expression partial current source next191 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan191()['payloadExpressionFence']['signature'])),
    'planner stat4 expression partial current source next191 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan191()['selectedPlan']['next191PayloadExpressionSignature'])),
    'planner stat4 expression partial current source next191 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan191()['stat4Fence']['next191PayloadExpressionSignature'])),
    'planner stat4 expression partial current source next191 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next191', $plan191()['dependencies'], true)),
    'planner stat4 expression partial current source next191 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan191()['dependency_closure']),
    'planner stat4 expression partial current source next191 non overlap' => static fn (TestRunner $t) => $t->contains('covering payload still recomputes', $plan191()['non_overlap']),
    'planner stat4 expression partial current source next191 detail' => static fn (TestRunner $t) => $t->contains('NEXT191 PAYLOAD EXPRESSION FENCE', $plan191()['detail']),
    'planner stat4 expression partial current source next191 mismatch blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-payload-reprepare', $mismatch191()['status']),
    'planner stat4 expression partial current source next191 mismatch rowids' => static fn (TestRunner $t) => $t->same([20], $mismatch191()['payloadExpressionFence']['mismatchedRowids']),
    'planner stat4 expression partial current source next191 mismatch actual key' => static fn (TestRunner $t) => $t->same('plugin_forms_renamed', $mismatch191()['payloadExpressionFence']['details'][2]['payloadExpressionKey']),
    'planner stat4 expression partial current source next191 mismatch sample key' => static fn (TestRunner $t) => $t->same('plugin_forms', $mismatch191()['payloadExpressionFence']['details'][2]['stat4SampleExpressionKey']),
    'planner stat4 expression partial current source next191 mismatch no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCoveringPayloadExpressionKey', array_column($mismatch191()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next191 stale sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-payload-reprepare', $staleSample191()['status']),
    'planner stat4 expression partial current source next191 stale sample rowids' => static fn (TestRunner $t) => $t->same([20], $staleSample191()['payloadExpressionFence']['mismatchedRowids']),
    'planner stat4 expression partial current source next191 stale sample detail' => static fn (TestRunner $t) => $t->same('plugin_forms_old', $staleSample191()['payloadExpressionFence']['details'][2]['stat4SampleExpressionKey']),
    'planner stat4 expression partial current source next191 invalid expression' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badExpression191),
    'planner stat4 expression partial current source next191 invalid indexes' => static function (TestRunner $t) use ($current191, $plan191): void {
        $bad = $current191();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan191(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next191 invalid sample rowid' => static function (TestRunner $t) use ($current191, $plan191): void {
        $bad = $current191();
        $bad['indexes'][0]['stat4Samples'][0]['sample'][1] = 'rowid';
        $t->throws(InvalidArgumentException::class, static fn () => $plan191(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next191 invalid sample shape' => static function (TestRunner $t) use ($current191, $plan191): void {
        $bad = $current191();
        unset($bad['indexes'][0]['stat4Samples'][0]['sample'][0]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan191(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next191 repeated payload fence ' . $case] = static function (TestRunner $t) use ($plan191, $case): void {
        $plan = $plan191(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['payloadExpressionFence']['details']));
    };
}

return $tests;
