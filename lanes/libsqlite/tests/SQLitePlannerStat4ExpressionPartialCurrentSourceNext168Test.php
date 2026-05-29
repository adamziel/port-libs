<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq168 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull168 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprLike168 = static fn (string $expression, string $pattern): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern];

$prepared168 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-next168',
        'schemaCookie' => 1680,
        'stat4Generation' => 81,
        'rows' => [
            ['rowid' => 101, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-cache_alpha', 'option_value' => 'old-cache', 'updated_at' => 10],
            ['rowid' => 102, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-forms', 'option_value' => 'old-forms', 'updated_at' => 20],
            ['rowid' => 103, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-seo', 'option_value' => 'old-seo', 'updated_at' => 30],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_like_partial_stat4_next168',
            'rootPage' => 16801,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'blog_id', 'autoload'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin-cache_alpha', 101]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin-forms', 102]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin-seo', 103]],
            ],
        ]],
    ], $overrides);
};

$current168 = static function (array $overrides = []) use ($prepared168): array {
    $source = $prepared168([
        'name' => 'current-wp-options-stat4-expression-partial-next168',
        'schemaCookie' => 1689,
        'stat4Generation' => 92,
    ]);
    $source['indexes'][0]['rootPage'] = 16888;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin-cache_alpha', 201]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin-cache_beta', 202]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin-forms', 203]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin-mailer', 204]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['pluginz_after_prefix', 205]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 206]],
    ];
    $source['rows'] = [
        ['rowid' => 204, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin-Mailer', 'option_value' => 'mail', 'updated_at' => 40],
        ['rowid' => 201, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-cache_alpha', 'option_value' => 'cache-alpha', 'updated_at' => 15],
        ['rowid' => 202, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-cache_beta', 'option_value' => 'cache-beta', 'updated_at' => 16],
        ['rowid' => 203, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 205, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'pluginz_after_prefix', 'option_value' => 'outside', 'updated_at' => 50],
        ['rowid' => 206, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 60],
        ['rowid' => 207, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin-lazy', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 208, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin-network', 'option_value' => 'network', 'updated_at' => 80],
        ['rowid' => 209, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms168 = static fn (): array => [
    $exprLike168('LOWER( option_name )', 'plugin-%'),
    $eq168('blog_id', 1),
    $eq168('autoload', 'yes'),
    $notNull168('option_name'),
];
$needed168 = ['option_name', 'option_value', 'updated_at'];
$plan168 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext168(
    $prepared ?? $prepared168(),
    $current ?? $current168(),
    $terms ?? $terms168(),
    $needed ?? $needed168,
);
$fresh168 = static function () use ($prepared168, $plan168): array {
    $source = $prepared168();

    return $plan168($source, $source);
};
$wildcard168 = static function () use ($terms168, $plan168): array {
    $terms = $terms168();
    $terms[0]['right'] = 'plugin-%_cache%';

    return $plan168(null, null, $terms);
};
$missingPartial168 = static function () use ($terms168, $plan168): array {
    $terms = $terms168();
    unset($terms[1]);

    return $plan168(null, null, array_values($terms));
};
$noStat4168 = static function () use ($current168, $plan168): array {
    $current = $current168();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan168(null, $current);
};
$nonCovering168 = static function () use ($current168, $plan168): array {
    $current = $current168();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan168(null, $current);
};

return [
    'planner stat4 expression partial current source next168 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next168-ready', $plan168()['status']),
    'planner stat4 expression partial current source next168 selects current' => static fn (TestRunner $t) => $t->same('current', $plan168()['selectedSource']),
    'planner stat4 expression partial current source next168 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan168()['stalePreparedStatement']),
    'planner stat4 expression partial current source next168 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan168()['reprepareRequired']),
    'planner stat4 expression partial current source next168 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan168()['schemaCookieChanged']),
    'planner stat4 expression partial current source next168 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan168()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next168 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan168()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next168 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_like_partial_stat4_next168', $plan168()['selectedPlan']['name']),
    'planner stat4 expression partial current source next168 root page' => static fn (TestRunner $t) => $t->same(16888, $plan168()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next168 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan168()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next168 covering' => static fn (TestRunner $t) => $t->same(true, $plan168()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next168 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan168()['tableLookupRequired']),
    'planner stat4 expression partial current source next168 partial by like' => static fn (TestRunner $t) => $t->same(true, $plan168()['selectedPlan']['likePrefixImpliedByPartial']),
    'planner stat4 expression partial current source next168 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan168()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next168 pattern' => static fn (TestRunner $t) => $t->same('plugin-%', $plan168()['selectedPlan']['likePattern']),
    'planner stat4 expression partial current source next168 prefix' => static fn (TestRunner $t) => $t->same('plugin-', $plan168()['selectedPlan']['likePrefix']),
    'planner stat4 expression partial current source next168 lower fence' => static fn (TestRunner $t) => $t->same('plugin-', $plan168()['prefixFence']['lower']),
    'planner stat4 expression partial current source next168 upper fence' => static fn (TestRunner $t) => $t->same('plugin.', $plan168()['prefixFence']['upper']),
    'planner stat4 expression partial current source next168 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan168()['prefixFence']['lowerInclusive']),
    'planner stat4 expression partial current source next168 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan168()['prefixFence']['upperInclusive']),
    'planner stat4 expression partial current source next168 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin-cache_alpha', 'plugin-cache_beta', 'plugin-forms', 'plugin-mailer'], $plan168()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next168 stat4 rowids' => static fn (TestRunner $t) => $t->same([201, 202, 203, 204], $plan168()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial current source next168 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan168()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next168 estimated cost' => static fn (TestRunner $t) => $t->same(5, $plan168()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next168 row count' => static fn (TestRunner $t) => $t->same(4, $plan168()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source next168 rowids' => static fn (TestRunner $t) => $t->same([201, 202, 203, 204], $plan168()['matchedRowids']),
    'planner stat4 expression partial current source next168 keys' => static fn (TestRunner $t) => $t->same(['plugin-cache_alpha', 'plugin-cache_beta', 'plugin-forms', 'plugin-mailer'], $plan168()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next168 current payload wins' => static fn (TestRunner $t) => $t->same('cache-alpha', $plan168()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next168 mixed case key normalized' => static fn (TestRunner $t) => $t->same('plugin-mailer', $plan168()['matchedRows'][3]['expressionKey']),
    'planner stat4 expression partial current source next168 excludes next prefix' => static fn (TestRunner $t) => $t->same(false, in_array(205, $plan168()['matchedRowids'], true)),
    'planner stat4 expression partial current source next168 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(206, $plan168()['matchedRowids'], true)),
    'planner stat4 expression partial current source next168 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(207, $plan168()['matchedRowids'], true)),
    'planner stat4 expression partial current source next168 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(208, $plan168()['matchedRowids'], true)),
    'planner stat4 expression partial current source next168 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(209, $plan168()['matchedRowids'], true)),
    'planner stat4 expression partial current source next168 prepared stale row absent' => static fn (TestRunner $t) => $t->same(false, in_array('old-cache', array_column(array_column($plan168()['matchedRows'], 'payload'), 'option_value'), true)),
    'planner stat4 expression partial current source next168 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan168()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next168 cursor seek lower' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'key' => 'plugin-'], $plan168()['cursorProgram'][1]),
    'planner stat4 expression partial current source next168 cursor upper fence' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxLT', 'key' => 'plugin.'], $plan168()['cursorProgram'][2]),
    'planner stat4 expression partial current source next168 cursor covering column' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan168()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next168 cursor residual like' => static fn (TestRunner $t) => $t->same(['opcode' => 'ResidualLikeCheck', 'pattern' => 'plugin-%'], $plan168()['cursorProgram'][4]),
    'planner stat4 expression partial current source next168 cursor result rowids' => static fn (TestRunner $t) => $t->same([201, 202, 203, 204], $plan168()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next168 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan168()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next168 fence cookie' => static fn (TestRunner $t) => $t->same(1689, $plan168()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next168 fence generation' => static fn (TestRunner $t) => $t->same(92, $plan168()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next168 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan168()['stat4Fence']['sourceSignature'], $plan168()['stat4Fence']['prefixSignature'], $plan168()['stat4Fence']['stat4Signature'], $plan168()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next168 prepared summary rowids' => static fn (TestRunner $t) => $t->same([101, 102, 103], $plan168()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next168 current summary rowids' => static fn (TestRunner $t) => $t->same([201, 202, 203, 204], $plan168()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next168 detail' => static fn (TestRunner $t) => $t->contains('LIKE PREFIX', $plan168()['detail']),
    'planner stat4 expression partial current source next168 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next168'], $plan168()['dependencies']),
    'planner stat4 expression partial current source next168 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan168()['dependency_closure']),
    'planner stat4 expression partial current source next168 non overlap' => static fn (TestRunner $t) => $t->contains('LIKE-prefix partial expression admission', $plan168()['non_overlap']),
    'planner stat4 expression partial current source next168 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh168()['selectedSource']),
    'planner stat4 expression partial current source next168 fresh rowids' => static fn (TestRunner $t) => $t->same([101, 102, 103], $fresh168()['matchedRowids']),
    'planner stat4 expression partial current source next168 wildcard pattern falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $wildcard168()['status']),
    'planner stat4 expression partial current source next168 missing partial term falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartial168()['status']),
    'planner stat4 expression partial current source next168 no stat4 falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4168()['status']),
    'planner stat4 expression partial current source next168 noncovering keeps ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next168-ready', $nonCovering168()['status']),
    'planner stat4 expression partial current source next168 noncovering table lookup' => static fn (TestRunner $t) => $t->same(true, $nonCovering168()['tableLookupRequired']),
    'planner stat4 expression partial current source next168 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering168()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next168 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan168(null, null, [])),
    'planner stat4 expression partial current source next168 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan168(null, null, null, [])),
    'planner stat4 expression partial current source next168 invalid stat4 sample' => static function (TestRunner $t) use ($current168, $plan168): void {
        $bad = $current168();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan168(null, $bad));
    },
    'planner stat4 expression partial current source next168 invalid rowid' => static function (TestRunner $t) use ($current168, $plan168): void {
        $bad = $current168();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan168(null, $bad));
    },
];
