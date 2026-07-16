<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq183 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull183 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprGt183 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '>', 'right' => $right];
$exprIn183 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared183 = static function (): array {
    return [
        'name' => 'prepared-wp-options-stat4-expression-partial-in-next183',
        'schemaCookie' => 1830,
        'stat4Generation' => 71,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-stale'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-stale'],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'shop-stale'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_in_partial_stat4_next183',
            'rootPage' => 18301,
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
                ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 2]],
                ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 3]],
                ['neq' => '1 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 4]],
            ],
        ]],
    ];
};

$current183 = static function () use ($prepared183): array {
    $source = $prepared183();
    $source['name'] = 'current-wp-options-stat4-expression-partial-in-next183';
    $source['schemaCookie'] = 1838;
    $source['stat4Generation'] = 94;
    $source['indexes'][0]['rootPage'] = 18388;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_admin', 1]],
        ['neq' => '1 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 2]],
        ['neq' => '1 1', 'nlt' => '6 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 3]],
        ['neq' => '1 1', 'nlt' => '9 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 4]],
    ];
    $source['rows'] = [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-current'],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-copy'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'shop-current'],
        ['rowid' => 40, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'network-shop'],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'lazy-mail'],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme'],
    ];

    return $source;
};

$terms183 = static fn (array $values = null): array => [
    $exprIn183('LOWER( option_name )', $values ?? [['literal' => 'plugin_shop'], ['literal' => 'plugin_cache'], ['literal' => 'plugin_mail']]),
    $eq183('blog_id', 1),
    $eq183('autoload', 'yes'),
    $notNull183('option_name'),
    $exprGt183('lower(option_name)', 'plugin_'),
];
$needed183 = ['option_name', 'option_value', 'autoload', 'blog_id'];
$plan183 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePartialInProbeFence(
    $prepared ?? $prepared183(),
    $current ?? $current183(),
    $terms ?? $terms183(),
    $needed ?? $needed183,
);
$fresh183 = static function () use ($current183, $plan183): array {
    $source = $current183();
    $source['schemaCookie'] = 1830;
    $source['stat4Generation'] = 71;

    return $plan183($source, $source);
};
$duplicate183 = static fn (): array => $plan183(null, null, $terms183([['literal' => 'plugin_shop'], ['literal' => 'plugin_shop']]));
$unproved183 = static fn (): array => $plan183(null, null, [
    $exprIn183('lower(option_name)', [['literal' => 'plugin_shop']]),
    $eq183('blog_id', 1),
    $eq183('autoload', 'no'),
    $notNull183('option_name'),
    $exprGt183('lower(option_name)', 'plugin_'),
]);

return [
    'planner stat4 expression partial current source next183 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next183-ready', $plan183()['status']),
    'planner stat4 expression partial current source next183 selected current' => static fn (TestRunner $t) => $t->same('current', $plan183()['selectedSource']),
    'planner stat4 expression partial current source next183 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan183()['stalePreparedStatement']),
    'planner stat4 expression partial current source next183 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan183()['reprepareRequired']),
    'planner stat4 expression partial current source next183 expression normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan183()['expression']),
    'planner stat4 expression partial current source next183 in values' => static fn (TestRunner $t) => $t->same(['plugin_shop', 'plugin_cache', 'plugin_mail'], $plan183()['inValues']),
    'planner stat4 expression partial current source next183 probe count' => static fn (TestRunner $t) => $t->same(3, $plan183()['inProbeCount']),
    'planner stat4 expression partial current source next183 matched rowids in order' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20], $plan183()['matchedRowids']),
    'planner stat4 expression partial current source next183 matched keys in order' => static fn (TestRunner $t) => $t->same(['plugin_shop', 'plugin_cache', 'plugin_cache', 'plugin_mail'], $plan183()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next183 first payload current' => static fn (TestRunner $t) => $t->same('shop-current', $plan183()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next183 duplicate cache rows kept' => static fn (TestRunner $t) => $t->same(['cache-current', 'cache-copy'], [$plan183()['matchedRows'][1]['payload']['option_value'], $plan183()['matchedRows'][2]['payload']['option_value']]),
    'planner stat4 expression partial current source next183 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(40, $plan183()['matchedRowids'], true)),
    'planner stat4 expression partial current source next183 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(50, $plan183()['matchedRowids'], true)),
    'planner stat4 expression partial current source next183 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan183()['matchedRowids'], true)),
    'planner stat4 expression partial current source next183 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan183()['matchedRowids'], true)),
    'planner stat4 expression partial current source next183 deduplicated rowids' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20], $plan183()['deduplicatedRowids']),
    'planner stat4 expression partial current source next183 probe values' => static fn (TestRunner $t) => $t->same(['plugin_shop', 'plugin_cache', 'plugin_mail'], array_column($plan183()['probes'], 'inValue')),
    'planner stat4 expression partial current source next183 probe ordinals' => static fn (TestRunner $t) => $t->same([0, 1, 2], array_column($plan183()['probes'], 'inOrdinal')),
    'planner stat4 expression partial current source next183 probes ready' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan183()['probes'], 'ready')),
    'planner stat4 expression partial current source next183 probe selected current' => static fn (TestRunner $t) => $t->same(['current', 'current', 'current'], array_column($plan183()['probes'], 'selectedSource')),
    'planner stat4 expression partial current source next183 unsampled keys' => static fn (TestRunner $t) => $t->same(['plugin_shop', 'plugin_cache', 'plugin_mail'], array_column($plan183()['probes'], 'unsampledEqualityKey')),
    'planner stat4 expression partial current source next183 shop bracket' => static fn (TestRunner $t) => $t->same(['plugin_security', 'plugin_zulu'], [$plan183()['probes'][0]['stat4Bracket']['left']['key'], $plan183()['probes'][0]['stat4Bracket']['right']['key']]),
    'planner stat4 expression partial current source next183 cache bracket' => static fn (TestRunner $t) => $t->same(['plugin_admin', 'plugin_forms'], [$plan183()['probes'][1]['stat4Bracket']['left']['key'], $plan183()['probes'][1]['stat4Bracket']['right']['key']]),
    'planner stat4 expression partial current source next183 mail bracket' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_security'], [$plan183()['probes'][2]['stat4Bracket']['left']['key'], $plan183()['probes'][2]['stat4Bracket']['right']['key']]),
    'planner stat4 expression partial current source next183 probe rowids' => static fn (TestRunner $t) => $t->same([[30], [10, 11], [20]], array_column($plan183()['probes'], 'matchedRowids')),
    'planner stat4 expression partial current source next183 in fence ready' => static fn (TestRunner $t) => $t->same(true, $plan183()['inOrderFence']['ready']),
    'planner stat4 expression partial current source next183 in fence rowids' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20], $plan183()['inOrderFence']['rowids']),
    'planner stat4 expression partial current source next183 in fence keys' => static fn (TestRunner $t) => $t->same(['plugin_shop', 'plugin_cache', 'plugin_cache', 'plugin_mail'], $plan183()['inOrderFence']['keys']),
    'planner stat4 expression partial current source next183 in fence signatures' => static fn (TestRunner $t) => $t->same([64, 64, 64], array_map('strlen', $plan183()['inOrderFence']['probeSignatures'])),
    'planner stat4 expression partial current source next183 stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan183()['inOrderFence']['rowStreamSignature'])),
    'planner stat4 expression partial current source next183 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan183()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next183 cursor rewind in list' => static fn (TestRunner $t) => $t->same('RewindInList', $plan183()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next183 cursor seek probe' => static fn (TestRunner $t) => $t->same('SeekProbeBracket', $plan183()['cursorProgram'][2]['opcode']),
    'planner stat4 expression partial current source next183 cursor idx eq' => static fn (TestRunner $t) => $t->same('IdxEq', $plan183()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next183 cursor dedupe' => static fn (TestRunner $t) => $t->same('DeduplicateRowid', $plan183()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next183 cursor result' => static fn (TestRunner $t) => $t->same([30, 10, 11, 20], $plan183()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next183 cursor next probe' => static fn (TestRunner $t) => $t->same('NextInProbe', $plan183()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next183 detail' => static fn (TestRunner $t) => $t->contains('NEXT183 IN MULTI-PROBE', $plan183()['detail']),
    'planner stat4 expression partial current source next183 dependencies' => static fn (TestRunner $t) => $t->same(['SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan', 'sqlite-sqlplanner-stat4-expression-partial-current-source-next183'], $plan183()['dependencies']),
    'planner stat4 expression partial current source next183 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan183()['dependency_closure']),
    'planner stat4 expression partial current source next183 non overlap' => static fn (TestRunner $t) => $t->contains('IN-list multi-probe', $plan183()['non_overlap']),
    'planner stat4 expression partial current source next183 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh183()['selectedSource']),
    'planner stat4 expression partial current source next183 fresh no stale' => static fn (TestRunner $t) => $t->same(false, $fresh183()['stalePreparedStatement']),
    'planner stat4 expression partial current source next183 duplicate in fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $duplicate183()['status']),
    'planner stat4 expression partial current source next183 duplicate cursor fallback' => static fn (TestRunner $t) => $t->same('FallbackFullScan', $duplicate183()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next183 unproved fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved183()['status']),
    'planner stat4 expression partial current source next183 invalid missing in' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan183(null, null, array_slice($terms183(), 1))),
    'planner stat4 expression partial current source next183 invalid in left' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan183(null, null, [['left' => ['column' => 'option_name'], 'operator' => 'IN', 'values' => ['plugin_shop']]])),
    'planner stat4 expression partial current source next183 invalid empty values' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan183(null, null, $terms183([]))),
    'planner stat4 expression partial current source next183 invalid non list values' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan183(null, null, [['left' => ['expression' => 'lower(option_name)'], 'operator' => 'IN', 'values' => ['a' => 'plugin_shop']]])),
    'planner stat4 expression partial current source next183 invalid probe rows' => static function (TestRunner $t) use ($current183, $plan183): void {
        $bad = $current183();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan183(null, $bad));
    },
];
