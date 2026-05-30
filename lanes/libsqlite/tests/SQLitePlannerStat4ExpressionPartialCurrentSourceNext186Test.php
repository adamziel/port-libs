<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq186 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull186 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprGt186 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '>', 'right' => $right];
$exprIn186 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared186 = static function (): array {
    return [
        'name' => 'prepared-wp-options-stat4-expression-partial-in-limit-next186',
        'schemaCookie' => 1860,
        'stat4Generation' => 80,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-stale'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-stale'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_in_limit_partial_stat4_next186',
            'rootPage' => 18601,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>', 'right' => 'plugin_'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_admin', 1]],
                ['neq' => '1 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 2]],
                ['neq' => '1 1', 'nlt' => '6 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 3]],
                ['neq' => '1 1', 'nlt' => '9 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 4]],
            ],
        ]],
    ];
};

$current186 = static function () use ($prepared186): array {
    $source = $prepared186();
    $source['name'] = 'current-wp-options-stat4-expression-partial-in-limit-next186';
    $source['schemaCookie'] = 1867;
    $source['stat4Generation'] = 99;
    $source['indexes'][0]['rootPage'] = 18688;
    $source['rows'] = [
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'shop-current'],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-current'],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-copy'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_MAIL', 'option_value' => 'mail-uppercase'],
        ['rowid' => 40, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'network-shop'],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'lazy-mail'],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme'],
    ];

    return $source;
};

$terms186 = static fn (array $values = null): array => [
    $exprIn186('LOWER( option_name )', $values ?? [['literal' => 'plugin_shop'], ['literal' => 'plugin_cache'], ['literal' => 'plugin_mail']]),
    $eq186('blog_id', 1),
    $eq186('autoload', 'yes'),
    $notNull186('option_name'),
    $exprGt186('lower(option_name)', 'plugin_'),
];
$needed186 = ['option_name', 'option_value', 'autoload', 'blog_id'];
$plan186 = static fn (int $limit = 3, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceInLimitProjectionFence(
    $prepared ?? $prepared186(),
    $current ?? $current186(),
    $terms ?? $terms186(),
    $needed ?? $needed186,
    $limit,
    $offset,
);
$zero186 = static fn (): array => $plan186(0, 0);
$exhausted186 = static fn (): array => $plan186(5, 9);
$fresh186 = static function () use ($current186, $plan186): array {
    $source = $current186();
    $source['schemaCookie'] = 1860;
    $source['stat4Generation'] = 80;

    return $plan186(2, 0, $source, $source);
};
$duplicate186 = static fn (): array => $plan186(2, 0, null, null, $terms186([['literal' => 'plugin_shop'], ['literal' => 'plugin_shop']]));

return [
    'planner stat4 expression partial current source next186 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next186-ready', $plan186()['status']),
    'planner stat4 expression partial current source next186 selected current' => static fn (TestRunner $t) => $t->same('current', $plan186()['selectedSource']),
    'planner stat4 expression partial current source next186 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan186()['stalePreparedStatement']),
    'planner stat4 expression partial current source next186 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan186()['reprepareRequired']),
    'planner stat4 expression partial current source next186 expression normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan186()['expression']),
    'planner stat4 expression partial current source next186 in values' => static fn (TestRunner $t) => $t->same(['plugin_shop', 'plugin_cache', 'plugin_mail'], $plan186()['inValues']),
    'planner stat4 expression partial current source next186 probe count' => static fn (TestRunner $t) => $t->same(3, $plan186()['inProbeCount']),
    'planner stat4 expression partial current source next186 base probe rowids' => static fn (TestRunner $t) => $t->same([[30], [10, 11], [20, 22]], array_column($plan186()['probes'], 'matchedRowids')),
    'planner stat4 expression partial current source next186 window input rowids' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20, 22], $plan186()['limitWindow']['inputRowids']),
    'planner stat4 expression partial current source next186 window rowids' => static fn (TestRunner $t) => $t->same([10, 11, 20], $plan186()['limitWindow']['rowids']),
    'planner stat4 expression partial current source next186 window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_mail'], $plan186()['limitWindow']['keys']),
    'planner stat4 expression partial current source next186 window ordinals' => static fn (TestRunner $t) => $t->same([1, 1, 2], $plan186()['limitWindow']['inOrdinals']),
    'planner stat4 expression partial current source next186 window values' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_mail'], $plan186()['limitWindow']['inValues']),
    'planner stat4 expression partial current source next186 window count' => static fn (TestRunner $t) => $t->same(3, $plan186()['limitWindow']['count']),
    'planner stat4 expression partial current source next186 window not exhausted' => static fn (TestRunner $t) => $t->same(false, $plan186()['limitWindow']['exhausted']),
    'planner stat4 expression partial current source next186 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan186()['limitWindow']['signature'])),
    'planner stat4 expression partial current source next186 projected count' => static fn (TestRunner $t) => $t->same(3, count($plan186()['projectedRows'])),
    'planner stat4 expression partial current source next186 projected first rowid' => static fn (TestRunner $t) => $t->same(10, $plan186()['projectedRows'][0]['rowid']),
    'planner stat4 expression partial current source next186 projected first key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan186()['projectedRows'][0]['expressionKey']),
    'planner stat4 expression partial current source next186 projected first value' => static fn (TestRunner $t) => $t->same('cache-current', $plan186()['projectedRows'][0]['option_value']),
    'planner stat4 expression partial current source next186 projected duplicate value' => static fn (TestRunner $t) => $t->same('cache-copy', $plan186()['projectedRows'][1]['option_value']),
    'planner stat4 expression partial current source next186 projected mail value' => static fn (TestRunner $t) => $t->same('mail-current', $plan186()['projectedRows'][2]['option_value']),
    'planner stat4 expression partial current source next186 projected columns' => static fn (TestRunner $t) => $t->same($needed186, $plan186()['projectedColumns']),
    'planner stat4 expression partial current source next186 matched rowids windowed' => static fn (TestRunner $t) => $t->same([10, 11, 20], $plan186()['matchedRowids']),
    'planner stat4 expression partial current source next186 matched keys windowed' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_mail'], $plan186()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next186 excludes first offset row' => static fn (TestRunner $t) => $t->same(false, in_array(30, $plan186()['matchedRowids'], true)),
    'planner stat4 expression partial current source next186 excludes after limit row' => static fn (TestRunner $t) => $t->same(false, in_array(22, $plan186()['matchedRowids'], true)),
    'planner stat4 expression partial current source next186 keeps dedupe input' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20, 22], $plan186()['deduplicatedRowids']),
    'planner stat4 expression partial current source next186 cursor inherits open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan186()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next186 cursor inherits in rewind' => static fn (TestRunner $t) => $t->same('RewindInList', $plan186()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next186 cursor result before window' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20, 22], $plan186()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next186 cursor limit opcode' => static fn (TestRunner $t) => $t->same('LimitOffsetWindow', $plan186()['cursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source next186 cursor limit rowids' => static fn (TestRunner $t) => $t->same([10, 11, 20], $plan186()['cursorProgram'][7]['rowids']),
    'planner stat4 expression partial current source next186 cursor payload opcode' => static fn (TestRunner $t) => $t->same('ColumnFromCoveringIndexPayload', $plan186()['cursorProgram'][8]['opcode']),
    'planner stat4 expression partial current source next186 in fence limit signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan186()['inOrderFence']['next186LimitSignature'])),
    'planner stat4 expression partial current source next186 in fence projection signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan186()['inOrderFence']['next186ProjectionSignature'])),
    'planner stat4 expression partial current source next186 zero limit ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next186-ready', $zero186()['status']),
    'planner stat4 expression partial current source next186 zero limit empty' => static fn (TestRunner $t) => $t->same([], $zero186()['matchedRowids']),
    'planner stat4 expression partial current source next186 exhausted ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next186-ready', $exhausted186()['status']),
    'planner stat4 expression partial current source next186 exhausted flag' => static fn (TestRunner $t) => $t->same(true, $exhausted186()['limitWindow']['exhausted']),
    'planner stat4 expression partial current source next186 exhausted rows empty' => static fn (TestRunner $t) => $t->same([], $exhausted186()['matchedRowids']),
    'planner stat4 expression partial current source next186 fresh source prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh186()['selectedSource']),
    'planner stat4 expression partial current source next186 fresh rowids' => static fn (TestRunner $t) => $t->same([30, 10], $fresh186()['matchedRowids']),
    'planner stat4 expression partial current source next186 duplicate fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $duplicate186()['status']),
    'planner stat4 expression partial current source next186 duplicate rows empty' => static fn (TestRunner $t) => $t->same([], $duplicate186()['matchedRowids']),
    'planner stat4 expression partial current source next186 detail' => static fn (TestRunner $t) => $t->contains('NEXT186 IN LIMIT WINDOW', $plan186()['detail']),
    'planner stat4 expression partial current source next186 dependencies' => static fn (TestRunner $t) => $t->same(['SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan', 'sqlite-sqlplanner-stat4-expression-partial-current-source-next186'], $plan186()['dependencies']),
    'planner stat4 expression partial current source next186 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan186()['dependency_closure']),
    'planner stat4 expression partial current source next186 non overlap' => static fn (TestRunner $t) => $t->contains('unwindowed IN-list multi-probe', $plan186()['non_overlap']),
    'planner stat4 expression partial current source next186 rejects negative limit' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan186(-1, 0)),
    'planner stat4 expression partial current source next186 rejects negative offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan186(1, -1)),
    'planner stat4 expression partial current source next186 rejects missing payload column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan186(1, 0, null, null, null, ['missing_column'])),
    'planner stat4 expression partial current source next186 rejects bad rowid' => static function (TestRunner $t) use ($current186, $plan186): void {
        $bad = $current186();
        $bad['rows'][0]['rowid'] = -5;
        $t->throws(InvalidArgumentException::class, static fn () => $plan186(1, 0, null, $bad));
    },
];
