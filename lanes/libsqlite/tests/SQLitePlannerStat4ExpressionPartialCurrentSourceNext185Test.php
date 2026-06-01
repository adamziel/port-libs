<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq185 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull185 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between185 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared185 = static fn (): array => [
    'name' => 'prepared-wp-options-sample-provenance-stat4-expression-partial-next185',
    'schemaCookie' => 1850,
    'stat4Generation' => 121,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_partial_stat4_next185',
        'rootPage' => 18501,
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

$current185 = static function () use ($prepared185): array {
    $source = $prepared185();
    $source['name'] = 'current-wp-options-sample-provenance-stat4-expression-partial-next185';
    $source['schemaCookie'] = 1858;
    $source['stat4Generation'] = 144;
    $source['indexes'][0]['rootPage'] = 18588;
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
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_seo', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return $source;
};

$terms185 = static fn (): array => [
    $between185('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq185('autoload', 'yes'),
    $notNull185('option_name'),
];
$plan185 = static fn (int $limit = 4, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePayloadWindowFence(
    $prepared ?? $prepared185(),
    $current ?? $current185(),
    $terms ?? $terms185(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unchanged185 = static function () use ($prepared185, $plan185): array {
    $source = $prepared185();
    $source['indexes'][0]['descending'] = true;

    return $plan185(2, 0, $source, $source);
};
$missingRow185 = static function () use ($current185, $plan185): array {
    $current = $current185();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => ($row['rowid'] ?? null) !== 30));

    return $plan185(2, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next185 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next185-ready', $plan185()['status']),
    'planner stat4 expression partial current source next185 selected current' => static fn (TestRunner $t) => $t->same('current', $plan185()['selectedSource']),
    'planner stat4 expression partial current source next185 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan185()['stalePreparedStatement']),
    'planner stat4 expression partial current source next185 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan185()['reprepareRequired']),
    'planner stat4 expression partial current source next185 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_desc_partial_stat4_next185', $plan185()['selectedPlan']['name']),
    'planner stat4 expression partial current source next185 root page' => static fn (TestRunner $t) => $t->same(18588, $plan185()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next185 base ready' => static fn (TestRunner $t) => $t->same(true, $plan185()['selectedPlan']['next182Ready']),
    'planner stat4 expression partial current source next185 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan185()['selectedPlan']['next185Ready']),
    'planner stat4 expression partial current source next185 sample changed' => static fn (TestRunner $t) => $t->same(true, $plan185()['sampleDeltaFence']['changed']),
    'planner stat4 expression partial current source next185 selected sample changed' => static fn (TestRunner $t) => $t->same(true, $plan185()['selectedPlan']['next185CurrentSampleChanged']),
    'planner stat4 expression partial current source next185 prepared sample count' => static fn (TestRunner $t) => $t->same(3, $plan185()['sampleDeltaFence']['preparedCount']),
    'planner stat4 expression partial current source next185 current sample count' => static fn (TestRunner $t) => $t->same(6, $plan185()['sampleDeltaFence']['currentCount']),
    'planner stat4 expression partial current source next185 prepared rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan185()['sampleDeltaFence']['preparedRowids']),
    'planner stat4 expression partial current source next185 current rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30, 40, 50, 60], $plan185()['sampleDeltaFence']['currentRowids']),
    'planner stat4 expression partial current source next185 selected sample rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30, 40, 50, 60], $plan185()['selectedPlan']['next185CurrentSampleRowids']),
    'planner stat4 expression partial current source next185 window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan185()['selectedPlan']['next185WindowRowids']),
    'planner stat4 expression partial current source next185 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan185()['matchedRowids']),
    'planner stat4 expression partial current source next185 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms'], $plan185()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next185 no missing rowids' => static fn (TestRunner $t) => $t->same([], $plan185()['missingCurrentRowids']),
    'planner stat4 expression partial current source next185 provenance count' => static fn (TestRunner $t) => $t->same(4, count($plan185()['currentSourceRowProvenance'])),
    'planner stat4 expression partial current source next185 provenance sources' => static fn (TestRunner $t) => $t->same(['current', 'current', 'current', 'current'], array_column($plan185()['currentSourceRowProvenance'], 'source')),
    'planner stat4 expression partial current source next185 provenance key column' => static fn (TestRunner $t) => $t->same(['option_name', 'option_name', 'option_name', 'option_name'], array_column($plan185()['currentSourceRowProvenance'], 'keyColumn')),
    'planner stat4 expression partial current source next185 provenance key values' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'Plugin_Mail', 'plugin_forms', 'Plugin_Forms'], array_column($plan185()['currentSourceRowProvenance'], 'keyValue')),
    'planner stat4 expression partial current source next185 provenance sample keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', null], array_column($plan185()['currentSourceRowProvenance'], 'sampleKey')),
    'planner stat4 expression partial current source next185 provenance anchors' => static fn (TestRunner $t) => $t->same([true, true, true, false], array_column($plan185()['currentSourceRowProvenance'], 'stat4Anchor')),
    'planner stat4 expression partial current source next185 projected rows retained' => static fn (TestRunner $t) => $t->same('mail', $plan185()['projectedRows'][1]['option_value']),
    'planner stat4 expression partial current source next185 duplicate projected row retained' => static fn (TestRunner $t) => $t->same('forms-copy', $plan185()['projectedRows'][3]['option_value']),
    'planner stat4 expression partial current source next185 sample signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['sampleDeltaFence']['currentSignature'])),
    'planner stat4 expression partial current source next185 prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['sampleDeltaFence']['preparedSignature'])),
    'planner stat4 expression partial current source next185 delta signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['stat4Fence']['next185SampleDeltaSignature'])),
    'planner stat4 expression partial current source next185 provenance signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['stat4Fence']['next185ProvenanceSignature'])),
    'planner stat4 expression partial current source next185 cursor opcode' => static fn (TestRunner $t) => $t->same('Stat4CurrentSampleFence', $plan185()['cursorProgram'][array_key_last($plan185()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next185 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan185()['cursorProgram'][array_key_last($plan185()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next185 cursor current sample count' => static fn (TestRunner $t) => $t->same(6, $plan185()['cursorProgram'][array_key_last($plan185()['cursorProgram'])]['currentSampleCount']),
    'planner stat4 expression partial current source next185 limit inherited' => static fn (TestRunner $t) => $t->same(4, $plan185()['limitWindow']['limit']),
    'planner stat4 expression partial current source next185 offset inherited' => static fn (TestRunner $t) => $t->same(1, $plan185()['limitWindow']['offset']),
    'planner stat4 expression partial current source next185 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next185', $plan185()['dependencies'], true)),
    'planner stat4 expression partial current source next185 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan185()['dependency_closure']),
    'planner stat4 expression partial current source next185 non overlap' => static fn (TestRunner $t) => $t->contains('stale prepared STAT4 samples', $plan185()['non_overlap']),
    'planner stat4 expression partial current source next185 detail' => static fn (TestRunner $t) => $t->contains('NEXT185 SAMPLE PROVENANCE', $plan185()['detail']),
    'planner stat4 expression partial current source next185 unchanged blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $unchanged185()['status']),
    'planner stat4 expression partial current source next185 unchanged sample not changed' => static fn (TestRunner $t) => $t->same(false, $unchanged185()['sampleDeltaFence']['changed']),
    'planner stat4 expression partial current source next185 unchanged cursor not appended' => static fn (TestRunner $t) => $t->same(false, in_array('Stat4CurrentSampleFence', array_column($unchanged185()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next185 missing row blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingRow185()['status']),
    'planner stat4 expression partial current source next185 missing rowids' => static fn (TestRunner $t) => $t->same([], $missingRow185()['missingCurrentRowids']),
    'planner stat4 expression partial current source next185 missing sample rowids' => static fn (TestRunner $t) => $t->same([30], $missingRow185()['sampleDeltaFence']['missingCurrentSampleRowids']),
    'planner stat4 expression partial current source next185 missing provenance still current' => static fn (TestRunner $t) => $t->same('current', $missingRow185()['currentSourceRowProvenance'][0]['source']),
    'planner stat4 expression partial current source next185 zero window ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next185-ready', $plan185(0, 0)['status']),
    'planner stat4 expression partial current source next185 zero window provenance' => static fn (TestRunner $t) => $t->same([], $plan185(0, 0)['currentSourceRowProvenance']),
    'planner stat4 expression partial current source next185 tail window' => static fn (TestRunner $t) => $t->same([21, 40, 10], $plan185(5, 4)['matchedRowids']),
    'planner stat4 expression partial current source next185 tail provenance key values' => static fn (TestRunner $t) => $t->same(['Plugin_Forms', 'plugin_cache', 'plugin_alpha'], array_column($plan185(5, 4)['currentSourceRowProvenance'], 'keyValue')),
    'planner stat4 expression partial current source next185 invalid current indexes' => static function (TestRunner $t) use ($current185, $plan185): void {
        $bad = $current185();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan185(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next185 invalid stat4 sample' => static function (TestRunner $t) use ($current185, $plan185): void {
        $bad = $current185();
        $bad['indexes'][0]['stat4Samples'][0] = ['sample' => ['plugin_alpha']];
        $t->throws(InvalidArgumentException::class, static fn () => $plan185(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next185 invalid rowid' => static function (TestRunner $t) use ($current185, $plan185): void {
        $bad = $current185();
        $bad['rows'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan185(1, 0, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next185 repeated provenance ' . $case] = static function (TestRunner $t) use ($plan185, $case): void {
        $plan = $plan185(1 + ($case % 4), $case % 5);
        $t->same(count($plan['matchedRowids']), count($plan['currentSourceRowProvenance']));
    };
}

return $tests;
