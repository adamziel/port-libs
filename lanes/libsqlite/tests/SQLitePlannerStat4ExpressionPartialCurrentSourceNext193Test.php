<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq193 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull193 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between193 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$rowidBetween193 = static fn (string $alias, int $lower, int $upper): array => ['left' => ['column' => $alias], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$rowidIn193 = static fn (string $alias, array $values): array => ['left' => ['column' => $alias], 'operator' => 'IN', 'values' => $values];
$rowidGt193 = static fn (string $alias, int $right): array => ['left' => ['column' => $alias], 'operator' => '>', 'right' => $right];

$prepared193 = static fn (): array => [
    'name' => 'prepared-wp-options-rowid-alias-partial-stat4-expression-next193',
    'schemaCookie' => 1930,
    'stat4Generation' => 151,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_rowid_partial_stat4_next193',
        'rootPage' => 19301,
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

$current193 = static function () use ($prepared193): array {
    $source = $prepared193();
    $source['name'] = 'current-wp-options-rowid-alias-partial-stat4-expression-next193';
    $source['schemaCookie'] = 1939;
    $source['stat4Generation'] = 177;
    $source['indexes'][0]['rootPage'] = 19388;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
    ];

    return $source;
};

$terms193 = static fn (?array $rowidTerm = null): array => array_values(array_filter([
    $between193('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq193('autoload', 'yes'),
    $notNull193('option_name'),
    $rowidTerm ?? $rowidBetween193('rowid', 10, 60),
]));
$plan193 = static fn (int $limit = 4, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext193(
    $prepared ?? $prepared193(),
    $current ?? $current193(),
    $terms ?? $terms193(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$narrow193 = static fn (): array => $plan193(4, 1, null, null, $terms193($rowidIn193('_rowid_', [20, 21, 30, 50])));
$sampleNarrow193 = static fn (): array => $plan193(4, 1, null, null, $terms193($rowidIn193('oid', [10, 20, 30, 50])));
$gt193 = static fn (): array => $plan193(4, 1, null, null, $terms193($rowidGt193('rowid', 19)));
$missingSample193 = static function () use ($current193, $plan193): array {
    $current = $current193();
    array_pop($current['rows']);
    foreach ($current['rows'] as $i => $row) {
        if (($row['rowid'] ?? null) === 60) {
            unset($current['rows'][$i]);
        }
    }
    $current['rows'] = array_values($current['rows']);

    return $plan193(4, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next193 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next193-ready', $plan193()['status']),
    'planner stat4 expression partial current source next193 inherited status' => static fn (TestRunner $t) => $t->same(true, $plan193()['selectedPlan']['next189Ready']),
    'planner stat4 expression partial current source next193 selected source' => static fn (TestRunner $t) => $t->same('current', $plan193()['selectedSource']),
    'planner stat4 expression partial current source next193 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_rowid_partial_stat4_next193', $plan193()['selectedPlan']['name']),
    'planner stat4 expression partial current source next193 root page' => static fn (TestRunner $t) => $t->same(19388, $plan193()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next193 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan193()['matchedRowids']),
    'planner stat4 expression partial current source next193 fence rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan193()['rowidAliasFence']['rowids']),
    'planner stat4 expression partial current source next193 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan193()['rowidAliasFence']['ready']),
    'planner stat4 expression partial current source next193 selected ready flag' => static fn (TestRunner $t) => $t->same(true, $plan193()['selectedPlan']['next193Ready']),
    'planner stat4 expression partial current source next193 constraint alias' => static fn (TestRunner $t) => $t->same('rowid', $plan193()['rowidAliasFence']['constraints'][0]['alias']),
    'planner stat4 expression partial current source next193 constraint operator' => static fn (TestRunner $t) => $t->same('BETWEEN', $plan193()['rowidAliasFence']['constraints'][0]['operator']),
    'planner stat4 expression partial current source next193 constraint values' => static fn (TestRunner $t) => $t->same([10, 60], $plan193()['rowidAliasFence']['constraints'][0]['values']),
    'planner stat4 expression partial current source next193 selected constraints' => static fn (TestRunner $t) => $t->same($plan193()['rowidAliasFence']['constraints'], $plan193()['selectedPlan']['next193RowidConstraints']),
    'planner stat4 expression partial current source next193 no rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan193()['rowidAliasFence']['rejectedRowids']),
    'planner stat4 expression partial current source next193 no sample rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan193()['rowidAliasFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next193 selected rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan193()['selectedPlan']['next193RejectedRowids']),
    'planner stat4 expression partial current source next193 check count' => static fn (TestRunner $t) => $t->same(4, count($plan193()['rowidAliasFence']['checks'])),
    'planner stat4 expression partial current source next193 sample check count' => static fn (TestRunner $t) => $t->same(6, count($plan193()['rowidAliasFence']['sampleChecks'])),
    'planner stat4 expression partial current source next193 checks current' => static fn (TestRunner $t) => $t->same(['current', 'current', 'current', 'current'], array_column($plan193()['rowidAliasFence']['checks'], 'source')),
    'planner stat4 expression partial current source next193 checks ready' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan193()['rowidAliasFence']['checks'], 'ready')),
    'planner stat4 expression partial current source next193 sample checks ready' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan193()['rowidAliasFence']['sampleChecks'], 'ready')),
    'planner stat4 expression partial current source next193 sample check rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], array_column($plan193()['rowidAliasFence']['sampleChecks'], 'rowid')),
    'planner stat4 expression partial current source next193 check reasons empty' => static fn (TestRunner $t) => $t->same([[], [], [], []], array_column($plan193()['rowidAliasFence']['checks'], 'reasons')),
    'planner stat4 expression partial current source next193 sample reasons empty' => static fn (TestRunner $t) => $t->same([[], [], [], [], [], []], array_column($plan193()['rowidAliasFence']['sampleChecks'], 'reasons')),
    'planner stat4 expression partial current source next193 payload retained' => static fn (TestRunner $t) => $t->same('forms-copy', $plan193()['projectedRows'][3]['option_value']),
    'planner stat4 expression partial current source next193 inherited payload fence ready' => static fn (TestRunner $t) => $t->same(true, $plan193()['currentPayloadPartialFence']['ready']),
    'planner stat4 expression partial current source next193 cursor opcode' => static fn (TestRunner $t) => $t->same('RowidAliasFence', $plan193()['cursorProgram'][array_key_last($plan193()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next193 cursor mode' => static fn (TestRunner $t) => $t->same('next193-current-source-stat4-expression-partial-rowid', $plan193()['cursorProgram'][array_key_last($plan193()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next193 cursor ready' => static fn (TestRunner $t) => $t->same(true, $plan193()['cursorProgram'][array_key_last($plan193()['cursorProgram'])]['ready']),
    'planner stat4 expression partial current source next193 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan193()['cursorProgram'][array_key_last($plan193()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next193 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan193()['rowidAliasFence']['signature'])),
    'planner stat4 expression partial current source next193 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan193()['stat4Fence']['next193RowidAliasSignature'])),
    'planner stat4 expression partial current source next193 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next193', $plan193()['dependencies'], true)),
    'planner stat4 expression partial current source next193 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan193()['dependency_closure']),
    'planner stat4 expression partial current source next193 non overlap' => static fn (TestRunner $t) => $t->contains('rowid alias constraints', $plan193()['non_overlap']),
    'planner stat4 expression partial current source next193 detail' => static fn (TestRunner $t) => $t->contains('NEXT193 ROWID ALIAS FENCE', $plan193()['detail']),
    'planner stat4 expression partial current source next193 narrow blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-rowid-reprepare', $narrow193()['status']),
    'planner stat4 expression partial current source next193 narrow rejected rowid' => static fn (TestRunner $t) => $t->same([10, 40, 60], $narrow193()['rowidAliasFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next193 narrow alias' => static fn (TestRunner $t) => $t->same('_rowid_', $narrow193()['rowidAliasFence']['constraints'][0]['alias']),
    'planner stat4 expression partial current source next193 narrow sample reason' => static fn (TestRunner $t) => $t->same(['sample-rowid-in'], $narrow193()['rowidAliasFence']['sampleChecks'][1]['reasons']),
    'planner stat4 expression partial current source next193 sample narrow blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-rowid-reprepare', $sampleNarrow193()['status']),
    'planner stat4 expression partial current source next193 sample narrow matched rejected' => static fn (TestRunner $t) => $t->same([21], $sampleNarrow193()['rowidAliasFence']['rejectedRowids']),
    'planner stat4 expression partial current source next193 sample narrow sample rejected' => static fn (TestRunner $t) => $t->same([40, 60], $sampleNarrow193()['rowidAliasFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next193 oid alias' => static fn (TestRunner $t) => $t->same('oid', $sampleNarrow193()['rowidAliasFence']['constraints'][0]['alias']),
    'planner stat4 expression partial current source next193 gt blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-rowid-reprepare', $gt193()['status']),
    'planner stat4 expression partial current source next193 gt sample rejected' => static fn (TestRunner $t) => $t->same([10], $gt193()['rowidAliasFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next193 missing sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-rowid-reprepare', $missingSample193()['status']),
    'planner stat4 expression partial current source next193 missing sample reason' => static fn (TestRunner $t) => $t->same(['missing-current-sample-row'], $missingSample193()['rowidAliasFence']['sampleChecks'][5]['reasons']),
    'planner stat4 expression partial current source next193 invalid rowid operator' => static function (TestRunner $t) use ($terms193, $plan193): void {
        $terms = $terms193(['left' => ['column' => 'rowid'], 'operator' => 'LIKE', 'right' => '2%']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan193(1, 0, null, null, $terms));
    },
    'planner stat4 expression partial current source next193 invalid rowid literal' => static function (TestRunner $t) use ($terms193, $plan193): void {
        $terms = $terms193(['left' => ['column' => 'rowid'], 'operator' => '=', 'right' => 'bad']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan193(1, 0, null, null, $terms));
    },
    'planner stat4 expression partial current source next193 invalid in list' => static function (TestRunner $t) use ($terms193, $plan193): void {
        $terms = $terms193(['left' => ['column' => '_rowid_'], 'operator' => 'IN', 'values' => []]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan193(1, 0, null, null, $terms));
    },
    'planner stat4 expression partial current source next193 invalid current indexes' => static function (TestRunner $t) use ($current193, $plan193): void {
        $current = $current193();
        $current['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan193(1, 0, null, $current));
    },
    'planner stat4 expression partial current source next193 invalid sample rowid' => static function (TestRunner $t) use ($current193, $plan193): void {
        $current = $current193();
        $current['indexes'][0]['stat4Samples'][0]['sample'][1] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan193(1, 0, null, $current));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next193 repeated rowid fence ' . $case] = static function (TestRunner $t) use ($plan193, $case): void {
        $plan = $plan193(1 + ($case % 4), $case % 3);
        $t->same(count($plan['rowidAliasFence']['rowids']), count($plan['rowidAliasFence']['checks']));
    };
}

return $tests;
