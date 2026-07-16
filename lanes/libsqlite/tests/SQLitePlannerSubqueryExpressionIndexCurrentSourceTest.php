<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan;

$expr123 = static fn (string $function, string $column, string $collation = 'NOCASE', string $affinity = 'TEXT'): array => [
    'function' => $function,
    'column' => $column,
    'collation' => $collation,
    'affinity' => $affinity,
];

$prepared123 = static fn (): array => [
    'name' => 'prepared-subquery-expression-index-canonical',
    'schemaCookie' => 122,
    'stat4Generation' => 77,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_subquery_expr_canonical',
        'rootPage' => 12301,
        'estimatedRows' => 80,
        'expressions' => [$expr123('lower', 'option_name')],
        'coveringColumns' => ['option_id', 'option_name', 'autoload'],
        'partialPredicate' => "lower(option_name) >= 'plugin_'",
        'sql' => "CREATE INDEX idx_wp_options_lower_subquery_expr_canonical ON wp_options(lower(option_name) COLLATE NOCASE, autoload) WHERE lower(option_name) >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_length_subquery_expr_canonical',
        'rootPage' => 12302,
        'estimatedRows' => 9,
        'expressions' => [$expr123('length', 'option_name', 'BINARY', 'INTEGER')],
        'coveringColumns' => ['option_id', 'option_name'],
        'sql' => 'CREATE INDEX idx_wp_options_length_subquery_expr_canonical ON wp_options(length(option_name))',
    ]],
];

$current123 = static function () use ($prepared123): array {
    $source = $prepared123();
    $source['name'] = 'current-subquery-expression-index-canonical';
    $source['schemaCookie'] = 123;
    $source['stat4Generation'] = 78;
    $source['indexes'][0]['rootPage'] = 12310;
    $source['indexes'][0]['estimatedRows'] = 5;

    return $source;
};

$predicate123 = static fn (?array $rows = null, string $collation = 'NOCASE', string $affinity = 'TEXT'): array => [
    'operator' => 'IN_SUBQUERY',
    'left' => $expr123('lower', 'option_name', $collation, $affinity),
    'subquery' => [
        'sourceName' => 'wp_active_option_name_keys',
        'keyColumn' => 'expr_key',
        'collation' => $collation,
        'affinity' => $affinity,
        'rows' => $rows ?? [
            ['expr_key' => 'Plugin_Cache', 'blog_id' => 1],
            ['expr_key' => 'plugin_forms', 'blog_id' => 1],
            ['expr_key' => 'PLUGIN_CACHE', 'blog_id' => 1],
            ['expr_key' => 'plugin_security', 'blog_id' => 2],
        ],
        'correlatedOuterColumns' => ['blog_id', 'autoload'],
    ],
];

$plan123 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    array $needed = ['option_id', 'option_name', 'autoload'],
): array => SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan::materializeSubqueryExpressionIndex(
    $prepared ?? $prepared123(),
    $current ?? $current123(),
    $predicate ?? $predicate123(),
    $needed,
);

$fresh123 = static fn (): array => $plan123($prepared123(), $prepared123());
$deferred123 = static fn (): array => $plan123(null, null, null, ['option_id', 'option_value']);
$null123 = static fn (): array => $plan123(null, null, $predicate123([
    ['expr_key' => 'plugin_cache'],
    ['expr_key' => null],
    ['expr_key' => 'plugin_forms'],
]));
$outsidePartial123 = static fn (): array => $plan123(null, null, $predicate123([
    ['expr_key' => 'admin_email'],
    ['expr_key' => 'plugin_cache'],
]));
$lengthPredicate123 = static fn (): array => [
    'operator' => 'IN_SUBQUERY',
    'left' => $expr123('length', 'option_name', 'BINARY', 'INTEGER'),
    'subquery' => [
        'sourceName' => 'wp_option_name_lengths',
        'keyColumn' => 'expr_key',
        'collation' => 'BINARY',
        'affinity' => 'INTEGER',
        'rows' => [['expr_key' => '12'], ['expr_key' => 13], ['expr_key' => '12']],
        'correlatedOuterColumns' => ['blog_id'],
    ],
];
$lengthPlan123 = static fn (): array => $plan123(null, null, $lengthPredicate123(), ['option_id', 'option_name']);

$tests = [
    'planner subquery expression index canonical status ready' => static fn (TestRunner $t) => $t->same('subquery-expression-index-current-source-ready', $plan123()['status']),
    'planner subquery expression index canonical selects current' => static fn (TestRunner $t) => $t->same('current', $plan123()['selectedSource']),
    'planner subquery expression index canonical stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan123()['stalePreparedStatement']),
    'planner subquery expression index canonical requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan123()['reprepareRequired']),
    'planner subquery expression index canonical schema changed' => static fn (TestRunner $t) => $t->same(true, $plan123()['schemaCookieChanged']),
    'planner subquery expression index canonical stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan123()['stat4GenerationChanged']),
    'planner subquery expression index canonical signature changed' => static fn (TestRunner $t) => $t->same(true, $plan123()['indexSignatureChanged']),
    'planner subquery expression index canonical current index chosen' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_subquery_expr_canonical', $plan123()['selectedPlan']['name']),
    'planner subquery expression index canonical current root chosen' => static fn (TestRunner $t) => $t->same(12310, $plan123()['selectedPlan']['rootPage']),
    'planner subquery expression index canonical prepared root preserved' => static fn (TestRunner $t) => $t->same(12301, $plan123()['preparedSource']['rootPage']),
    'planner subquery expression index canonical expression matched' => static fn (TestRunner $t) => $t->same(true, $plan123()['selectedPlan']['expressionMatched']),
    'planner subquery expression index canonical collation matched' => static fn (TestRunner $t) => $t->same(true, $plan123()['selectedPlan']['collationMatched']),
    'planner subquery expression index canonical affinity matched' => static fn (TestRunner $t) => $t->same(true, $plan123()['selectedPlan']['affinityMatched']),
    'planner subquery expression index canonical partial implied' => static fn (TestRunner $t) => $t->same(true, $plan123()['selectedPlan']['partialPredicateImplied']),
    'planner subquery expression index canonical covering' => static fn (TestRunner $t) => $t->same(true, $plan123()['selectedPlan']['covering']),
    'planner subquery expression index canonical next source covering index' => static fn (TestRunner $t) => $t->same('expression-index-covering', $plan123()['selectedPlan']['nextSource']),
    'planner subquery expression index canonical no deferred lookup' => static fn (TestRunner $t) => $t->same(false, $plan123()['selectedPlan']['deferredTableLookup']),
    'planner subquery expression index canonical values normalized nocase' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $plan123()['subquery']['values']),
    'planner subquery expression index canonical duplicate removed' => static fn (TestRunner $t) => $t->same(1, $plan123()['subquery']['duplicatesRemoved']),
    'planner subquery expression index canonical null absent' => static fn (TestRunner $t) => $t->same(false, $plan123()['subquery']['nullSeen']),
    'planner subquery expression index canonical row count' => static fn (TestRunner $t) => $t->same(4, $plan123()['subquery']['rowCount']),
    'planner subquery expression index canonical source name' => static fn (TestRunner $t) => $t->same('wp_active_option_name_keys', $plan123()['subquery']['source']),
    'planner subquery expression index canonical key column' => static fn (TestRunner $t) => $t->same('expr_key', $plan123()['subquery']['keyColumn']),
    'planner subquery expression index canonical correlated columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload'], $plan123()['subquery']['correlatedOuterColumns']),
    'planner subquery expression index canonical subquery collation' => static fn (TestRunner $t) => $t->same('NOCASE', $plan123()['subquery']['collation']),
    'planner subquery expression index canonical subquery affinity' => static fn (TestRunner $t) => $t->same('TEXT', $plan123()['subquery']['affinity']),
    'planner subquery expression index canonical typed value type' => static fn (TestRunner $t) => $t->same('text', $plan123()['subquery']['typedValues'][0]['type']),
    'planner subquery expression index canonical range first' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan123()['selectedPlan']['rangeFence']['first']),
    'planner subquery expression index canonical range last' => static fn (TestRunner $t) => $t->same('plugin_security', $plan123()['selectedPlan']['rangeFence']['last']),
    'planner subquery expression index canonical cursor source' => static fn (TestRunner $t) => $t->same('current', $plan123()['cursorTape']['source']),
    'planner subquery expression index canonical cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_subquery_expr_canonical', $plan123()['cursorTape']['indexName']),
    'planner subquery expression index canonical cursor root' => static fn (TestRunner $t) => $t->same(12310, $plan123()['cursorTape']['rootPage']),
    'planner subquery expression index canonical cursor seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plan123()['cursorTape']['seekOpcode']),
    'planner subquery expression index canonical cursor stop' => static fn (TestRunner $t) => $t->same('IdxGT', $plan123()['cursorTape']['stopOpcode']),
    'planner subquery expression index canonical cursor range fence' => static fn (TestRunner $t) => $t->same($plan123()['selectedPlan']['rangeFence'], $plan123()['cursorTape']['rangeFence']),
    'planner subquery expression index canonical cursor table elided' => static fn (TestRunner $t) => $t->same(true, $plan123()['cursorTape']['tableLookupElided']),
    'planner subquery expression index canonical cursor deferred null' => static fn (TestRunner $t) => $t->same(null, $plan123()['cursorTape']['deferredSeekOpcode']),
    'planner subquery expression index canonical cursor program opens expression index' => static fn (TestRunner $t) => $t->same('expression-index', $plan123()['cursorTape']['program'][0]['source']),
    'planner subquery expression index canonical cursor program opens keys' => static fn (TestRunner $t) => $t->same('subquery-expression-keys', $plan123()['cursorTape']['program'][1]['source']),
    'planner subquery expression index canonical cursor program reads covering column' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'expression-index', 'column' => 'autoload'], $plan123()['cursorTape']['program'][7]),
    'planner subquery expression index canonical source fence cookie' => static fn (TestRunner $t) => $t->same(123, $plan123()['currentSourceFence']['schemaCookie']),
    'planner subquery expression index canonical source fence stat4' => static fn (TestRunner $t) => $t->same(78, $plan123()['currentSourceFence']['stat4Generation']),
    'planner subquery expression index canonical signature hashes' => static fn (TestRunner $t) => $t->same(64, strlen($plan123()['currentSourceFence']['indexSignature'])),
    'planner subquery expression index canonical subquery signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan123()['currentSourceFence']['subquerySignature'])),
    'planner subquery expression index canonical detail names expression keys' => static fn (TestRunner $t) => $t->contains('CURRENT IN-SUBQUERY EXPRESSION KEYS', $plan123()['detail']),
    'planner subquery expression index canonical dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan123()['dependency_closure']),
    'planner subquery expression index canonical non overlap' => static fn (TestRunner $t) => $t->contains('avoids accepted subquery-covering', $plan123()['non_overlap']),
    'planner subquery expression index canonical fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh123()['selectedSource']),
    'planner subquery expression index canonical fresh root' => static fn (TestRunner $t) => $t->same(12301, $fresh123()['selectedPlan']['rootPage']),
    'planner subquery expression index canonical deferred status stays ready' => static fn (TestRunner $t) => $t->same('subquery-expression-index-current-source-ready', $deferred123()['status']),
    'planner subquery expression index canonical deferred lookup opcode' => static fn (TestRunner $t) => $t->same('DeferredSeek', $deferred123()['cursorTape']['deferredSeekOpcode']),
    'planner subquery expression index canonical missing covering column' => static fn (TestRunner $t) => $t->same(['option_value'], $deferred123()['selectedPlan']['missingCoveringColumns']),
    'planner subquery expression index canonical null blocks partial' => static fn (TestRunner $t) => $t->same('requires-next-stage', $null123()['status']),
    'planner subquery expression index canonical null recorded' => static fn (TestRunner $t) => $t->same(true, $null123()['subquery']['nullSeen']),
    'planner subquery expression index canonical outside partial falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $outsidePartial123()['status']),
    'planner subquery expression index canonical length index selected' => static fn (TestRunner $t) => $t->same('idx_wp_options_length_subquery_expr_canonical', $lengthPlan123()['selectedPlan']['name']),
    'planner subquery expression index canonical length values cast' => static fn (TestRunner $t) => $t->same([12, 13], $lengthPlan123()['subquery']['values']),
    'planner subquery expression index canonical length typed integer' => static fn (TestRunner $t) => $t->same('integer', $lengthPlan123()['subquery']['typedValues'][0]['type']),
    'planner subquery expression index canonical length duplicate removed' => static fn (TestRunner $t) => $t->same(1, $lengthPlan123()['subquery']['duplicatesRemoved']),
    'planner subquery expression index canonical validates operator' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan123(null, null, ['operator' => 'IN'])),
    'planner subquery expression index canonical validates left expression' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan123(null, null, ['operator' => 'IN_SUBQUERY', 'subquery' => ['rows' => [['key' => 'x']]]])),
    'planner subquery expression index canonical validates key column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan123(null, null, $predicate123([['wrong' => 'plugin_cache']]))),
    'planner subquery expression index canonical validates non empty keys' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan123(null, null, $predicate123([['expr_key' => null]]))),
];

return $tests;
